<?php

namespace App\Http\Controllers\Sale;

use App\Models\Customer;
use App\Models\Log;
use App\Models\SaleVoucher;
use App\Models\Transaction;
use App\Services\VoucherUpdateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;


class SaleVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $start_date = Carbon::parse($request->query('start_date'))->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($request->query('end_date'))->endOfDay()->addDays(1)->toDateTimeString();


            $query = SaleVoucher::select('sale_vouchers.*','customers.name as customer_name','chart_of_accounts.name as acc_name')
            ->join('customers','sale_vouchers.customer_id', '=', 'customers.id')
            ->join('chart_of_accounts','sale_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('sale_vouchers.business_id',$user->login_business)
            ->orderBy('voucher_date', 'desc');


            if (!empty($request->query('start_date')) && !empty($request->query('end_date'))) {
                $query = $query->whereBetween('voucher_date', [$start_date, $end_date]);
            }

            if (!empty($searchQuery)) {
                $query->where('sale_vouchers.voucher_code', 'like', '%' . $searchQuery . '%');
                
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Permission Check
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'data' => 'required|array',

                'data.*.customer_id' => 'required|exists:customers,id',
                'data.*.voucher_amount' => 'required|numeric',
                'data.*.description' => 'nullable|string',
                'data.*.voucher_date' => 'required|date',

                'data.*.payment_method' => 'required|string|in:CASH,BANK,OTHER',
                'data.*.acc_id' => 'required|exists:chart_of_accounts,id',

                'data.*.bank_transaction_type' => 'required_if:data.*.payment_method,BANK|nullable|in:CHEQUE,ONLINE',

                'data.*.cheque_no' => 'required_if:data.*.bank_transaction_type,CHEQUE|nullable|string',
                'data.*.cheque_date' => 'required_if:data.*.bank_transaction_type,CHEQUE|nullable|date',

            ], [
                'data.required' => 'The data field is required.',
                'data.*.customer_id.required' => 'Customer is required.',
                'data.*.voucher_amount.required' => 'Voucher amount is required.',
                'data.*.payment_method.required' => 'Payment method is required.',
                'data.*.acc_id.required' => 'Account is required.',
            ]);

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first());
            }

            DB::beginTransaction();

            $insertData = [];

            foreach ($request->data as $item) {

                // Generate Unique Voucher Code
                do {
                    $voucher_code = 'SV-' . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (SaleVoucher::where('voucher_code', $voucher_code)->exists());

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
                        $description = $item['description'];
                    } else {
                        $description = $item['description'];
                    }
                }

                $insertData[] = [
                    'voucher_code' => $voucher_code,
                    'acc_id' => $item['acc_id'],
                    'payment_method' => $item['payment_method'],
                    'bank_transaction_type' => $item['bank_transaction_type'] ?? null,
                    'cheque_no' => $item['cheque_no'] ?? null,
                    'cheque_date' => $item['cheque_date'] ?? null,
                    'customer_id' => $item['customer_id'],
                    'description' => $description,
                    'voucher_amount' => $item['voucher_amount'],
                    'voucher_date' => Carbon::parse($item['voucher_date'])->format('Y-m-d') . ' ' . Carbon::now()->format('H:i:s'),
                    'business_id' => $user->login_business,
                    'created_by' => $user->id,
                ];
            }

            SaleVoucher::insert($insertData);

            Log::create([
                'user_id' => $user->id,
                'description' => 'Sale Vouchers created successfully',
            ]);

            DB::commit();

            return response()->json($insertData, 200);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        } catch (Exception $e) {
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
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = SaleVoucher::select(
                'sale_vouchers.*',
                'customers.name as customer_name',
                'chart_of_accounts.name as acc_name'
                )
                ->join('customers','sale_vouchers.customer_id','=','customers.id')
                ->join('chart_of_accounts','sale_vouchers.acc_id','=','chart_of_accounts.id')
                ->find($id);
            if (empty($data)) throw new Exception('No data found', 404);

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = SaleVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 400);
            DB::beginTransaction();
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            
            $voucherDateTime = Carbon::parse($data->voucher_date);  // Parse the date from your model
            $currentDateTime = Carbon::now();  // Get the current date and time
            $daysDifference = Carbon::parse($data->voucher_date)->diffInDays($currentDateTime);

            $data->update([
                'days' => $daysDifference,
                'approved_by' => $user->id,
                'approve_date' => $currentDateTime,
                'status'=> $request->status
                ]);

            if ($request->status == 1) {
                // transaction
                $this->updateClass($data->customer_id);
                $customer = Customer::find($data->customer_id);
                $customer_acc = $customer->acc_id;
                // for products
                $total_billed = $data->voucher_amount;

                $c_cb = calculateBalance(
                    $customer_acc,
                    0,
                    $total_billed,
                    $data->voucher_date
                );
                $b_cb = calculateBalance(
                    $data->acc_id,
                    $total_billed,
                    0,
                    $data->voucher_date
                );
                
                // Credit amount to customer's account
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id' => $customer_acc,
                    'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => $data->description,
                    'link' => $id,
                    'debit' => 0.00, // No money deducted from customer's side
                    'credit' => $total_billed, // Money credited to customer
                    'current_balance' => $c_cb, // Updated balance for customer account
                    'created_at' => $data->voucher_date, // Use voucher date for transaction record
                ]);

                // Debit amount from business's account
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id' => $data->acc_id,
                    'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => $data->description,
                    'link' => $id,
                    'credit' => 0.00, // No money credited to business account
                    'debit' => $total_billed, // Money debited from business account
                    'current_balance' => $b_cb,
                    'created_at' => $data->voucher_date, // Use voucher date for transaction record
                ]);
                Log::create([
                    'user_id' => $user->id,
                    'description' => 'Voucher status change to PAID and trnsaction done successfully. Code: ' . $data->voucher_code,   
                ]);

                recalculateAccountTransactions($data->acc_id);
                recalculateAccountTransactions($customer_acc);
            }

            

            

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

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Permission check
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'voucher_ids' => 'required|array',
                'voucher_ids.*' => 'exists:sale_vouchers,id',
                'status' => 'required|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            DB::beginTransaction();

            // Preloading customers
            $vouchers = SaleVoucher::with('customer')->whereIn('id', $request->voucher_ids)->get();
            $customerAccounts = $vouchers->mapWithKeys(function($voucher) {
                return [$voucher->customer_id => $voucher->customer->acc_id];
            });

            // Preload customer credit and debit balances
            $transactions = [];
            $logs = [];
            $voucherUpdates = [];
            $currentDateTime = Carbon::now();

            foreach ($vouchers as $data) {
                if ($data->status == 1) {
                    continue; // already paid skip
                }

                $daysDifference = Carbon::parse($data->voucher_date)->diffInDays($currentDateTime);
                $voucherUpdates[] = [
                    'id' => $data->id,
                    'days' => $daysDifference,
                    'approved_by' => $user->id,
                    'approve_date' => $currentDateTime,
                    'status' => $request->status
                ];

                if ($request->status == 1) {
                    $this->updateClass($data->customer_id);

                    $total_billed = $data->voucher_amount;

                    // Customer credit
                    $c_cb = calculateCreditBalance($customerAccounts[$data->customer_id], $total_billed,$data->voucher_date);
                    // Business debit
                    $b_cb = calculateDebitBalance($data->acc_id, $total_billed,$data->voucher_date);

                    // Add customer transaction
                    $transactions[] = [
                        'business_id' => $data->business_id,
                        'acc_id' => $customerAccounts[$data->customer_id],
                        'transaction_type' => 1,
                        'description' => $data->description,
                        'debit' => 0.00,
                        'credit' => $total_billed,
                        'current_balance' => $c_cb,
                        'created_at' => $data->voucher_date,
                    ];

                    // Add business transaction
                    $transactions[] = [
                        'business_id' => $data->business_id,
                        'acc_id' => $data->acc_id,
                        'transaction_type' => 1,
                        'description' => $data->description,
                        'credit' => 0.00,
                        'debit' => $total_billed,
                        'current_balance' => $b_cb,
                        'created_at' => $data->voucher_date,
                    ];

                    // Add logs
                    $logs[] = [
                        'user_id' => $user->id,
                        'description' => 'Voucher status change to PAID and transaction done successfully. Code: ' . $data->voucher_code,
                    ];
                }
            }

            // Update vouchers in bulk
            SaleVoucher::whereIn('id', array_column($voucherUpdates, 'id'))
                ->update($voucherUpdates);

            // Insert transactions in bulk
            if (count($transactions) > 0) {
                Transaction::insert($transactions);
            }

            // Insert logs in bulk
            if (count($logs) > 0) {
                Log::insert($logs);
            }

            recalculateAccountTransactions($data->acc_id);

            recalculateAccountTransactions($customerAccounts[$data->customer_id]);

            DB::commit();

            return response()->json([
                'message' => 'Bulk voucher status updated successfully',
                'total_processed' => count($vouchers)
            ], 200);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            DB::rollBack();
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
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create sale voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'customer_id' => 'required|exists:customers,id',
                    "payment_method" => 'required|string|in:CASH,BANK,OTHER',
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'bank_transaction_type' => 'required_if:payment_method,BANK|string|in:CHEQUE,ONLINE',
                    'cheque_no' => 'required_if:bank_transaction_type,CHEQUE|string',
                    'cheque_date' => 'required_if:bank_transaction_type,CHEQUE|date',
                    'voucher_date' => 'required',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'customer_id.required' => 'The Customer field is required.',
                    'customer_id.exists' => 'The selected Customer is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'payment_method.required' => 'The payment method field is required.',
                    'payment_method.in' => 'The selected payment method is invalid.',

                    'bank_transaction_type.required_if' => 'The bank transaction type field is required when payment method is BANK.',
                    'bank_transaction_type.in' => 'The selected bank transaction type is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required when bank transaction type is CHEQUE.',
                    'cheque_no.string' => 'The cheque number must be a string.',
                    
                    'cheque_date.required_if' => 'The cheque date field is required when bank transaction type is CHEQUE.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',
                    'voucher_date.required' => 'The voucher date field is required.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            $data = SaleVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            if ($data->status == 1){
                $updateFlow = new VoucherUpdateService();
                $res = $updateFlow->updateVoucherFlow($id,1, $request->all());
                if(isset($res['success']) && $res['success'] === false){
                    return response()->json(['error' => $res['message']], 400);
                }
            }else{
                $description = $request->payment_method == 'CASH' 
                    ? 'Cash Transfer' 
                    : ($request->bank_transaction_type == 'CHEQUE' 
                        ? 'Cheque Payment' 
                        : 'Online Bank Transfer');

                if(isset($request->description) && !empty($request->description)){
                    if($request->payment_method == 'BANK'){
                        $description = $request->description;
                    }else{
                        $description = $request->description;
                    }
                }
                $data->update([
                    'customer_id' => $request->customer_id,
                    'acc_id' => $request->acc_id,
                    'business_id' => $businessId,
                    'bank_transaction_type' => $request->bank_transaction_type ?? null,
                    'description' => $description,
                    'payment_method' => $request->payment_method,
                    'cheque_no' => $request->cheque_no ?? null,
                    'cheque_date' => $request->cheque_date ?? null,
                    'voucher_date' => $request->voucher_date,
                    'voucher_amount' => $request->voucher_amount,
                    'status' => 0, // 0 un paid, 1 paid
                ]);

                Log::create([
                    'user_id' => $user->id,
                    'description' => 'Voucher updated successfully. Code: ' . $data->voucher_code,   
                ]);
            };
            
            DB::commit();
            return response()->json("Sale voucher updated successfully.", 200);
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
    public function updateClass(string $customerId)
    {
        $class = "A";
        $last_voucher = SaleVoucher::where('customer_id',$customerId)->where('status',1)->orderBy('id','desc')->first();
        if(!empty($last_voucher)){
            if($last_voucher->days <= 20){
                $class = "A";
            }elseif ($last_voucher->days <= 45) {
                $class = "B";
            }else{
                $class = "C";
            }
        }
        $customer  = Customer::find($customerId);
        $customer->update([
            "class" => $class,
        ]);
        return true;
    }
}
