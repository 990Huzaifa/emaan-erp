<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalVoucher;
use App\Models\Partner;
use Carbon\Carbon;
use Exception;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JournalVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $start_date = Carbon::parse($request->query('start_date'))->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($request->query('end_date'))->endOfDay()->addDays(1)->toDateTimeString();


            $query = JournalVoucher::select('journal_vouchers.*','chart_of_accounts.name as asset_name')
            ->join('chart_of_accounts', 'journal_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('journal_vouchers.business_id','=',$businessId)
            ->orderBy('id', 'desc');


            if (!empty($request->query('start_date')) && !empty($request->query('end_date'))) {
                $query = $query->whereBetween('voucher_date', [$start_date, $end_date]);
            }

            if (!empty($searchQuery)) {
                $query = $query->where('voucher_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'data' => 'required|array',
                    'data.*.voucher_amount' => 'required|numeric',
                    'data.*.description' => 'nullable|string',
                    'data.*.voucher_date' => 'required|date',
                    
                    'data.*.payment_method' => 'required|string|in:CASH,BANK,OTHER',
                    'data.*.acc_id' => 'required|exists:chart_of_accounts,id',
                    'data.*.type'=>'required|string|in:WITHDRAW,DEPOSIT',

                ],[
                    'data.required' => 'The data field is required.',
                    'data.array' => 'The data field must be an array.',
                    
                    'data.*.vendor_id.required' => 'The Vendor field is required.',
                    'data.*.vendor_id.exists' => 'The selected Vendor is invalid.',

                    'data.*.voucher_amount.required' => 'The voucher amount field is required.',
                    'data.*.voucher_amount.numeric' => 'The voucher amount must be a number.',

                    'data.*.payment_method.required' => 'The payment method field is required.',
                    'data.*.payment_method.in' => 'The selected payment method is invalid.',

                    'data.*.acc_id.required' => 'The Account field is required.',
                    'data.*.acc_id.exists' => 'The selected account is invalid.',
                    
                    'data.*.voucher_date.required' => 'The voucher date field is required.',
                    
                    'data.*.type.required'=>'Type is Required',
                    'data.*.type.in'=>'Type is Invalid',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            DB::beginTransaction();
            $data = [];
            foreach ($request->data as $item) {
                do {
                    $voucher_code = 'JV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (JournalVoucher::where('voucher_code', $voucher_code)->exists());
                $description = $request->payment_method == 'CASH' 
                    ? 'Cash Transfer' 
                    : 'Bank Transfer';

                if(isset($item['description']) && !empty($item['description'])){
                    $description = $item['description'];
                }
                $data[]=[
                    'acc_id'=>$item['acc_id'],
                    'voucher_code'=>$voucher_code,
                    'business_id'=>$user->login_business,
                    'voucher_amount'=>$item['voucher_amount'],
                    'description'=>$description,
                    'type'=>$item['type'],
                    'voucher_date'=>Carbon::parse($request->voucher_date)->format('Y-m-d') . ' ' . Carbon::now()->format('H:i:s'),
                    'status'=>0,
                    'created_by'=>$user->id
                ];
            }
            JournalVoucher::insert($data);
            DB::commit();
            return response()->json($data,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
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
                if (!$user->hasBusinessPermission($businessId, 'view journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = JournalVoucher::select('journal_vouchers.*','chart_of_accounts.name as account_name','partners.name as partner_name')
            ->join('chart_of_accounts', 'journal_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->join('partners', 'journal_vouchers.partner_id', '=', 'partners.id')
            ->find($id);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'voucher_date'=>'required',
                    'acc_id'=>'required|exists:chart_of_accounts,id',
                    'partner_id'=>'required|exists:partners,id',
                    'voucher_amount'=>'required|numeric',
                    'payment_method'=>'required|string|in:CASH,BANK,OTHER',
                    'type'=>'required|string|in:WITHDRAW,DEPOSIT',
                    'bank_transaction_type' => 'required_if:payment_method,BANK|string|in:CHEQUE,ONLINE',
                    'cheque_no' => 'required_if:bank_transaction_type,CHEQUE|string',
                    'cheque_date' => 'required_if:bank_transaction_type,CHEQUE|date',
                ],[
                    
                    'acc_id.required'=>'Account is Required',
                    'acc_id.exists'=>'Account is Invalid',
                    
                    'partner_id.required'=>'Partner is Required',
                    'partner_id.exists'=>'Partner is Invalid',
                    
                    'voucher_amount.numeric'=>'Amount must be a number',
                    'voucher_amount.required'=>'Amount is Required',
                    
                    'payment_method.required'=>'Payment Method is Required',
                    'payment_method.in'=>'Payment Method is Invalid',
                    
                    'bank_transaction_type.required_if' => 'The bank transaction type field is required when payment method is BANK.',
                    'bank_transaction_type.in' => 'The selected bank transaction type is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required when bank transaction type is CHEQUE.',
                    'cheque_no.string' => 'The cheque number must be a string.',

                    'cheque_date.required_if' => 'The cheque date field is required when bank transaction type is CHEQUE.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',
                    
                    'voucher_date.required'=>'Voucher Date is Required',

                    'type.required'=>'Type is Required',
                    'type.in'=>'Type is Invalid',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            DB::beginTransaction();
            $data = JournalVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            if ($data->status == 1) throw new Exception('voucher already paid', 404);
            $description = $request->payment_method == 'CASH' 
                    ? 'Cash Transfer' 
                    : ($request->bank_transaction_type == 'CHEQUE' 
                        ? 'Cheque Payment' 
                        : 'Online Bank Transfer');

                if(isset($request->description) && !empty($request->description)){
                    $description = $request->description;
                }
            $data->update([
                'acc_id'=>$request->acc_id,
                'partner_id'=>$request->partner_id,
                'voucher_amount'=>$request->voucher_amount,
                'payment_method'=>$request->payment_method,
                'type'=>$request->type,
                'bank_transaction_type'=>$request->bank_transaction_type,
                'description'=>$description,
                'cheque_no'=>$request->cheque_no  ?? null,
                'cheque_date'=>$request->cheque_date ?? null,
                'voucher_date'=>$request->voucher_date,
            ]);
            DB::commit();
            return response()->json($data,200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            if($request->status == 0) throw new Exception('Status is invalid', 400);
            $data = JournalVoucher::findOrFail($id);
            if (empty($data)) throw new Exception('No Journal Voucher found', 404);
            if($request->status == $data->status) throw new Exception('Status is already updated', 400);

            $data->update([
                'approved_by' => $user->id,
                'approved_date' => Carbon::now(),
                'status'=>$request->status,
            ]);

            if($data->status == 1){
                $acc_id = $data->acc_id;
                $total_amount = $data->voucher_amount;
                if ($data->type === 'WITHDRAW') {
                    // Withdrawal: Debit Partner Account, Credit Business Account (money leaves business, reduces equity)
                    $a_cb = calculateBalance(
                        $acc_id,
                        $total_amount,
                        0,
                        $data->voucher_date
                    );
        
                    // Credit the asset account (money is leaving the business)
                    Transaction::create([
                        'business_id' => $data->business_id,
                        'acc_id' => $acc_id,
                        'transaction_type' => 2, // Withdrawal
                        'description' => $data->description,
                        'debit' => $total_amount,
                        'credit' => 0.00,
                        'current_balance' => $a_cb,
                        'created_at' => $data->voucher_date
                    ]);
                } elseif ($data->type === 'DEPOSIT') {
                    // Contribution: Debit Business Account, Credit Partner Account (money enters business, increases equity)
                    $a_cb = calculateBalance(
                        $acc_id,
                        0,
                        $total_amount,
                        $data->voucher_date
                    );
        
                    // Debit the asset account (money is added to the business)
                    Transaction::create([
                        'business_id' => $data->business_id,
                        'acc_id' => $acc_id,
                        'transaction_type' => 1, // Contribution
                        'description' => $data->description,
                        'debit' => 0.00,
                        'credit' => $total_amount,
                        'current_balance' => $a_cb
                    ]);

                    recalculateAccountTransactions($acc_id);
                } else {
                    throw new Exception('Invalid voucher type.');
                }
            }
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
