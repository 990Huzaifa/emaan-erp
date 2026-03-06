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
                    'data' => 'required|array',
                    'data.*.expense_acc' => 'required|exists:chart_of_accounts,id',
                    'data.*.voucher_amount' => 'required|numeric',
                    'data.*.description' => 'nullable|string',
                    'data.*.voucher_date' => 'required|date',
                    'data.*.payment_method' => 'required|string|in:CASH,BANK,OTHER',
                    'data.*.bank_transaction_type' => 'required_if:data.*.payment_method,BANK|string|in:CHEQUE,ONLINE',
                    'data.*.cheque_no' => 'required_if:data.*.bank_transaction_type,CHEQUE|string',
                    'data.*.cheque_date' => 'required_if:data.*.bank_transaction_type,CHEQUE|date',
                    'data.*.asset_acc' => 'required|exists:chart_of_accounts,id',
                ],
                [
                    'data.required' => 'Data array is required',
                    'data.array' => 'Data must be an array',
                    'data.*.expense_acc.required' => 'Expense account is required for each item',
                    'data.*.expense_acc.exists' => 'Expense account does not exist for each item',
                    'data.*.voucher_amount.required' => 'Voucher amount is required for each item',
                    'data.*.voucher_amount.numeric' => 'Voucher amount must be numeric for each item',
                    'data.*.voucher_date.required' => 'Voucher date is required for each item',
                    'data.*.voucher_date.date' => 'Voucher date must be a valid date for each item',
                    'data.*.payment_method.required' => 'Payment method is required for each item',
                    'data.*.payment_method.string' => 'Payment method must be a string for each item',
                    'data.*.payment_method.in' => 'Payment method must be one of CASH, BANK, or OTHER for each item',
                    'data.*.bank_transaction_type.required_if' => 'Bank transaction type is required when payment method is BANK for each item',
                    'data.*.bank_transaction_type.string' => 'Bank transaction type must be a string when payment method is BANK for each item',
                    'data.*.bank_transaction_type.in' => 'Bank transaction type must be one of CHEQUE or ONLINE when payment method is BANK for each item',
                    'data.*.cheque_no.required_if' => 'Cheque number is required when bank transaction type is CHEQUE for each item',
                    'data.*.cheque_no.string' => 'Cheque number must be a string when bank transaction type is CHEQUE for each item',
                    'data.*.cheque_date.required_if' => 'Cheque date is required when bank transaction type is CHEQUE for each item',
                    'data.*.cheque_date.date' => 'Cheque date must be a valid date when bank transaction type is CHEQUE for each item',
                    'data.*.asset_acc.required' => 'Asset account is required for each item',
                    'data.*.asset_acc.exists' => 'Asset account does not exist for each item',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $insertData = [];
            foreach ($request->data as $item) {
                do {
                    $voucher_code = 'EV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (ExpenseVoucher::where('voucher_code', $voucher_code)->exists());
                // Default Description
                $description = match ($item['payment_method']) {
                    'CASH' => 'Cash Transfer',
                    'BANK' => ($item['bank_transaction_type'] ?? '') === 'CHEQUE'
                                ? 'Cheque Payment'
                                : 'Online Bank Transfer',
                    default => 'Other Payment'
                };

                // Append custom description
                if (!empty($item['description'])) {
                    if ($item['payment_method'] === 'BANK') {
                        $description = $item['description'] . ' | ' . $description;
                    } else {
                        $description = $item['description'];
                    }
                }
                $insertData[] = [
                    'asset_acc_id' => $item['asset_acc'],
                    'expense_acc_id' => $item['expense_acc'],
                    'business_id' => $businessId,
                    'voucher_code' => $voucher_code,
                    'payment_method' => $item['payment_method'],
                    'bank_transaction_type' => $item['bank_transaction_type'] ?? null,
                    'cheque_no' => $item['cheque_no'] ?? null,
                    'cheque_date' => $item['cheque_date'] ?? null,
                    'description' => $description,
                    'voucher_amount' => $item['voucher_amount'],
                    'voucher_date' => Carbon::parse($item['voucher_date'])->format('Y-m-d') . ' ' . Carbon::now()->format('H:i:s'),
                    'status' => 0,
                    'created_by' => $user->id
                ];
            }
            ExpenseVoucher::insert($insertData);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Expense Vouchers created successfully',
            ]);
            DB::commit();
            return response()->json($insertData, 200);
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
                        'bank_transaction_type' => 'required_if:payment_method,BANK|string|in:CHEQUE,ONLINE',
                        'cheque_no' => 'required_if:bank_transaction_type,CHEQUE|string',
                        'cheque_date' => 'required_if:bank_transaction_type,CHEQUE|date',
                        'voucher_date' => 'required',                    
                        'voucher_amount' => 'required|numeric',
                    ],
                    [
                        'voucher_date.required' => 'Voucher date is required',
    
                        'expense_acc.required' => 'Expense account is required',
                        'expense_acc.exists' => 'Expense account does not exist',
    
                        'asset_acc.required' => 'Asset account is required',
                        'asset_acc.exists' => 'Asset account does not exist',
    
                        'bank_transaction_type.required_if' => 'The bank transaction type field is required when payment method is BANK.',
                        'bank_transaction_type.in' => 'The selected bank transaction type is invalid.',

                        'cheque_no.required_if' => 'The cheque number field is required when bank transaction type is CHEQUE.',
                        'cheque_no.string' => 'The cheque number must be a string.',

                        'cheque_date.required_if' => 'The cheque date field is required when bank transaction type is CHEQUE.',
                        'cheque_date.date' => 'The cheque date must be a valid date.',
    
                        'voucher_amount.required' => 'Voucher amount is required',
                        'voucher_amount.numeric' => 'Voucher amount is not valid',
                    ]
                );
                if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
                DB::beginTransaction();
                $data = ExpenseVoucher::find($id);
                if (empty($data)) throw new Exception('No data found', 404);
                if ($data->status == 1) throw new Exception('voucher already paid', 404);
                $description = $request->payment_method == 'CASH' 
                    ? 'Cash Transfer' 
                    : ($request->bank_transaction_type == 'CHEQUE' 
                        ? 'Cheque Payment' 
                        : 'Online Bank Transfer');

                if(isset($request->description) && !empty($request->description)){
                    if($request->payment_method == 'BANK'){
                        $description = $request->description . ' | ' . $description;
                    }else{
                        $description = $request->description;
                    }
                }
                $data->update([
                    'asset_acc_id' => $request->asset_acc,
                    'expense_acc_id' => $request->expense_acc,
                    'business_id' => $businessId,
                    'bank_transaction_type' => $request->bank_transaction_type,
                    'description' => $description,
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
                    'approved_date' => Carbon::now(),
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
                    'description' => $data->description,
                    'debit' => 0.00, // No money added to the asset account
                    'credit' => $total_billed, // Money leaving the asset account
                    'current_balance' => $a_cb, // Updated balance for the asset account
                    'created_at' => $data->voucher_date, // Use voucher date for transaction record
                ]);

                // Debit the expense account (money recorded as an expense)
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id' => $expense_acc,
                    'transaction_type' => 2, // 2 -> Expense
                    'description' => $data->description,
                    'debit' => $total_billed, // Money recorded as an expense
                    'credit' => 0.00, // No money leaving the expense account
                    'current_balance' => $e_cb, // Updated balance for the expense account
                    'created_at' => $data->voucher_date, // Use voucher date for transaction record
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
