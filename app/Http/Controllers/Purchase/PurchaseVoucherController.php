<?php

namespace App\Http\Controllers\Purchase;

use App\Models\PurchaseVoucher;
use App\Models\Transaction;
use App\Models\Log;
use App\Models\Vendor;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class PurchaseVoucherController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $start_date = Carbon::parse($request->query('start_date'))->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($request->query('end_date'))->endOfDay()->addDays(1)->toDateTimeString();

            $query = PurchaseVoucher::select('purchase_vouchers.*','vendors.name as vendor_name','chart_of_accounts.name as acc_name')
            ->join('vendors','purchase_vouchers.vendor_id', '=', 'vendors.id')
            ->join('chart_of_accounts','purchase_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('purchase_vouchers.business_id',$businessId)
            ->orderBy('voucher_date', 'desc');


            if (!empty($request->query('start_date')) && !empty($request->query('end_date'))) {
                $query = $query->whereBetween('voucher_date', [$start_date, $end_date]);
            }

            if (!empty($searchQuery)) {
                $query->where('purchase_vouchers.voucher_code', 'like', '%' . $searchQuery . '%');
                
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
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'data' => 'required|array',
                    'data.*.vendor_id' => 'required|exists:vendors,id',
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
                    'data.array' => 'The data field must be an array.',
                    
                    'data.*.vendor_id.required' => 'The Vendor field is required.',
                    'data.*.vendor_id.exists' => 'The selected Vendor is invalid.',

                    'data.*.voucher_amount.required' => 'The voucher amount field is required.',
                    'data.*.voucher_amount.numeric' => 'The voucher amount must be a number.',

                    'data.*.payment_method.required' => 'The payment method field is required.',
                    'data.*.payment_method.in' => 'The selected payment method is invalid.',

                    'data.*.acc_id.required' => 'The Account field is required.',
                    'data.*.acc_id.exists' => 'The selected account is invalid.',

                    'data.*.bank_transaction_type.required_if' => 'The bank transaction type field is required when payment method is BANK.',
                    'data.*.bank_transaction_type.in' => 'The selected bank transaction type is invalid.',

                    'data.*.cheque_no.required_if' => 'The cheque number field is required when bank transaction type is CHEQUE.',
                    'data.*.cheque_no.string' => 'The cheque number must be a string.',
                    
                    'data.*.cheque_date.required_if' => 'The cheque date field is required when bank transaction type is CHEQUE.',
                    'data.*.cheque_date.date' => 'The cheque date must be a valid date.',
                    
                    'data.*.voucher_date.required' => 'The voucher date field is required.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            $insertData = [];
            foreach($request->data as $item){
                do {
                    $voucher_code = 'PV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (PurchaseVoucher::where('voucher_code', $voucher_code)->exists());
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
                    'acc_id' => $item['acc_id'],
                    'business_id' => $businessId,
                    'payment_method' => $item['payment_method'],
                    'bank_transaction_type' => $item['bank_transaction_type'] ?? null,
                    'description' => $description,
                    'cheque_no' => $item['cheque_no'] ?? null,
                    'cheque_date' => $item['cheque_date'] ?? null,
                    'voucher_code' => $voucher_code, 
                    'vendor_id' => $item['vendor_id'],
                    'voucher_amount' => $item['voucher_amount'],
                    'status' => 0, // 0 un paid, 1 paid
                    'voucher_date' => Carbon::parse($item['voucher_date'])->format('Y-m-d') . ' ' . Carbon::now()->format('H:i:s'),
                    'created_by' => $user->id,
                ];
            }
            PurchaseVoucher::insert($insertData);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Purchase Vouchers created successfully',
            ]);
            DB::commit();
            return response()->json($insertData, 200);
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
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseVoucher::select(
                'purchase_vouchers.*',
                'vendors.name as vendor_name',
                'chart_of_accounts.name as acc_name'
                )
                ->join('vendors','purchase_vouchers.vendor_id','=','vendors.id')
                ->join('chart_of_accounts','purchase_vouchers.acc_id','=','chart_of_accounts.id')
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
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user(); 
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'vendor_id' => 'required|exists:vendors,id',
                    "payment_method" => 'required|string|in:CASH,BANK,OTHER',
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'bank_transaction_type' => 'required_if:payment_method,BANK|string|in:CHEQUE,ONLINE',
                    'cheque_no' => 'required_if:bank_transaction_type,CHEQUE|string',
                    'cheque_date' => 'required_if:bank_transaction_type,CHEQUE|date',
                    'voucher_date' => 'required',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'vendor_id.required' => 'The Vendor field is required.',
                    'vendor_id.exists' => 'The selected Vendor is invalid.',
                    
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
            $data = PurchaseVoucher::find($id);
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
                'vendor_id' => $request->vendor_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'bank_transaction_type' => $request->bank_transaction_type ?? null,
                'description' => $description,
                'payment_method' => $request->payment_method,
                'cheque_no' => $request->cheque_no ?? null,
                'cheque_date' => $request->cheque_date ?? null,
                'voucher_date' => $request->voucher_date,
                'voucher_amount' => $request->voucher_amount,
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

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 400);
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            DB::beginTransaction();
            $data->update([
                'approved_by' => $user->id,
                'approve_date' => Carbon::now(),
                'status'=> 1
                ]);
            
            // transaction
            $vendor = Vendor::find($data->vendor_id);
            $vendor_acc = $vendor->acc_id;
            // for products
            $total_billed = $data->voucher_amount;

            // Calculate Cash/Bank Account Current Balance (Post-Credit)
            $b_cb = calculateCreditBalance($data->acc_id, $total_billed); // Cash account is credited (reduced)

            // Calculate Vendor Account Current Balance (Post-Debit)
            $v_cb = calculateDebitBalance($vendor_acc, $total_billed); // Vendor account is debited
            
            // Debit amount to vendor's account (minus)
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $vendor_acc,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => $data->description,
                'debit' => $total_billed, // Money debited from vendor's account
                'credit' => 0.00, // No money credited to vendor's account
                'current_balance' => $v_cb, // Updated balance for vendor account
                'created_at' => $data->voucher_date, // Use voucher date for transaction record
            ]);

            // Credit amount from business's account (minus)
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $data->acc_id,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => $data->description,
                'debit' => 0.00, // No money debited to business account
                'credit' => $total_billed, // Money credited from business account
                'current_balance' => $b_cb,
                'created_at' => $data->voucher_date, // Use voucher date for transaction record
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Voucher status change to PAID and trnsaction done successfully. code: '.$data->code,   
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

    public function previousData(Request $request, string $grn_id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = PurchaseVoucher::where('grn_id',$grn_id)->get();

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
