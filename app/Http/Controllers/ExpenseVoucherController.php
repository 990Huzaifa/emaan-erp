<?php

namespace App\Http\Controllers;

use App\Models\ExpenseVoucher;
use App\Models\Log;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
                if ($user->role != 'admin') {
                    $businessId = $user->login_business;
                    if (!$user->hasBusinessPermission($businessId, 'list expense voucher')) {
                        return response()->json([
                            'error' => 'User does not have the required permission.'
                        ], 403);
                    }
                }
    
            // Set pagination and search parameters
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search', '');
            $start_date = Carbon::parse($request->query('start_date'))->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($request->query('end_date'))->endOfDay()->addDays(1)->toDateTimeString();
    
            // Build the query
            $query = ExpenseVoucher::join('chart_of_accounts as asset_account', 'asset_account.id', '=', 'expense_vouchers.asset_acc_id')
                ->join('chart_of_accounts as expense_account', 'expense_account.id', '=', 'expense_vouchers.expense_acc_id')
                ->select(
                    'expense_vouchers.*',
                    'asset_account.name as asset_account_name',
                    'expense_account.name as expense_account_name'
                )
                ->where('expense_vouchers.business_id', $businessId);
    
            // Apply date range filter if provided
            if (!empty($request->query('start_date')) && !empty($request->query('end_date'))) {
                $query = $query->whereBetween('voucher_date', [$start_date, $end_date]);
            }
    
            // Apply search filter if provided
            if (!empty($searchQuery)) {
                $query->where(function ($subQuery) use ($searchQuery) {
                    $subQuery->where('voucher_code', 'like', '%' . $searchQuery . '%')
                        ->orWhere('expense_vouchers.description', 'like', '%' . $searchQuery . '%');
                });
            }
    
            // Paginate the results
            $data = $query->paginate($perPage);
    
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'create expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'asset_acc' => 'required|exists:chart_of_accounts,id',
                    "payment_method" => 'required|string|in:CASH,BANK,OTHER',
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required',
                    'data' => 'required|array',
                    'data.*.expense_acc' => 'required|exists:chart_of_accounts,id',
                    'data.*.voucher_amount' => 'required|numeric',
                ],
                [
                    'voucher_date.required' => 'Voucher date is required',

                    'expense_acc.required' => 'Expense account is required',
                    'expense_acc.exists' => 'Expense account does not exist',

                    'asset_acc.required' => 'Asset account is required',
                    'asset_acc.exists' => 'Asset account does not exist',

                    'payment_method.required' => 'Payment method is required',
                    'payment_method.in' => 'Payment method is invalid',

                    'cheque_no.required_if' => 'Cheque number is required',

                    'cheque_date.required_if' => 'Cheque date is required',
                    
                    'data.required' => 'Data is required',
                    'data.*.expense_acc.required' => 'Expense account is required',
                    'data.*.expense_acc.exists' => 'Expense account does not exist',
                    'data.*.voucher_amount.required' => 'Voucher amount is required',
                    'data.*.voucher_amount.numeric' => 'Voucher amount must be a number',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $data = [];
            foreach ($request->data as $item) {
                do {
                    $voucher_code = 'EV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (ExpenseVoucher::where('voucher_code', $voucher_code)->exists());
                $data[] = [
                    'asset_acc_id' => $request->asset_acc,
                    'expense_acc_id' => $item['expense_acc'],
                    'business_id' => $businessId,
                    'voucher_code' => $voucher_code,
                    'payment_method' => $request->payment_method,
                    'cheque_no' => $request->cheque_no ?? null,
                    'cheque_date' => $request->cheque_date ?? null,
                    'voucher_amount' => $item['voucher_amount'],
                    'voucher_date' => $request->voucher_date,
                    'status' => 0,
                    'created_by' => $user->id
                ];
            }
            ExpenseVoucher::insert($data);
            DB::commit();
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error'=>$e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = ExpenseVoucher::join('chart_of_accounts as asset_account', 'asset_account.id', '=', 'expense_vouchers.asset_acc_id')
                ->join('chart_of_accounts as expense_account', 'expense_account.id', '=', 'expense_vouchers.expense_acc_id')
                ->select(
                    'expense_vouchers.*',
                    'asset_account.name as asset_account_name',
                    'expense_account.name as expense_account_name'
                )->find($id);   
            if (empty($data)) throw new Exception('Expense voucher not found', 404);         
            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error'=>$e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error'=>$e->getMessage()], 400);
        }

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        {
            try{
                $user = Auth::user();
                $businessId = $user->login_business;
                if ($user->role != 'admin') {
                    if (!$user->hasBusinessPermission($businessId, 'create expense voucher')) {
                        return response()->json([
                            'error' => 'User does not have the required permission.'
                        ], 403);
                    }
                }
                $validator = Validator::make(
                    $request->all(),[
                        'expense_acc' => 'required|exists:chart_of_accounts,id',
                        'asset_acc' => 'required|exists:chart_of_accounts,id',
                        "payment_method" => 'required|string|in:CASH,BANK,OTHER',
                        'cheque_no' => 'required_if:payment_method,BANK|string',
                        'cheque_date' => 'required_if:payment_method,BANK|date',
                        'voucher_date' => 'required',                    
                        'voucher_amount' => 'required|numeric',
                    ],
                    [
                        'voucher_date.required' => 'Voucher date is required',
    
                        'expense_acc.required' => 'Expense account is required',
                        'expense_acc.exists' => 'Expense account does not exist',
    
                        'asset_acc.required' => 'Asset account is required',
                        'asset_acc.exists' => 'Asset account does not exist',
    
                        'cheque_no.required_if' => 'Cheque number is required',
                        'cheque_date.required_if' => 'Cheque date is required',
    
                        'voucher_amount.required' => 'Voucher amount is required',
                        'voucher_amount.numeric' => 'Voucher amount is not valid',
                    ]
                );
                if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
                DB::beginTransaction();
                $data = ExpenseVoucher::find($id);
                if (empty($data)) throw new Exception('No data found', 404);
                if ($data->status == 1) throw new Exception('voucher already paid', 404);
                $data->update([
                    'asset_acc_id' => $request->asset_acc,
                    'expense_acc_id' => $request->expense_acc,
                    'business_id' => $businessId,
                    'payment_method' => $request->payment_method,
                    'cheque_no' => $request->cheque_no ?? null,
                    'cheque_date' => $request->cheque_date ?? null,
                    'voucher_amount' => $request->voucher_amount,
                    'voucher_date' => $request->voucher_date,
                ]);
                DB::commit();
                return response()->json($data, 200);
            }catch(QueryException $e){
                DB::rollBack();
                return response()->json(['DB error'=>$e->getMessage()], 400);
            }catch(Exception $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()], 400);
            }
        }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = ExpenseVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 400);
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            DB::beginTransaction();
                $data->update([
                    'status'=>$request->status,
                    'approved_by' => $user->id,
                    'approved_date' => now(),
                    ]);

            if($data->status == 1){
                // transaction
                $asset_acc = $data->asset_acc_id;
                $expense_acc = $data->expense_acc_id;
                $total_billed = $data->voucher_amount;
                $a_cb = calculateBalance($asset_acc, $total_billed, true);
                $e_cb = calculateBalance($expense_acc, $total_billed, false);
                

                // Credit the asset account (money is leaving)
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id' => $asset_acc,
                    'transaction_type' => 2, // 2 -> Expense
                    'description' => 'Payment for expense voucher.',
                    'debit' => 0.00, // No money added to the asset account
                    'credit' => $total_billed, // Money leaving the asset account
                    'current_balance' => $a_cb // Updated balance for the asset account
                ]);

                // Debit the expense account (money recorded as an expense)
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id' => $expense_acc,
                    'transaction_type' => 2, // 2 -> Expense
                    'description' => 'Recording expense payment.',
                    'debit' => $total_billed, // Money recorded as an expense
                    'credit' => 0.00, // No money leaving the expense account
                    'current_balance' => $e_cb // Updated balance for the expense account
                ]);
            }
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'Voucher status change to PAID and trnsaction done successfully.',   
            ]);

            DB::commit();
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
