<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\GoodsReceiveNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturnVoucher;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\Lot;
use App\Models\Log;
use App\Models\InventoryDetail;
use App\Models\Vendor;
use App\Models\OpeningBalance;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnVoucherController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase return voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseReturnVoucher::select('purchase_return_vouchers.*','vendors.name as vendor_name','chart_of_accounts.name as acc_name')
            ->join('vendors','purchase_return_vouchers.vendor_id', '=', 'vendors.id')
            ->join('chart_of_accounts','purchase_return_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('purchase_return_vouchers.business_id',$businessId)
            ->orderBy('id', 'desc');
            if (!empty($searchQuery)) {
                $query->where('purchase_return_vouchers.voucher_code', 'like', '%' . $searchQuery . '%');
                
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
                if (!$user->hasBusinessPermission($businessId, 'create purchase return voucher')) {
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
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required|date',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'vendor_id.required' => 'The Vendor field is required.',
                    'vendor_id.exists' => 'The selected Vendor is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'payment_method.required' => 'The payment method field is required.',
                    'payment_method.in' => 'The selected payment method is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required.',
                    'cheque_no.string' => 'The cheque number must be a string.',

                    'cheque_date.required_if' => 'The cheque date field is required.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',

                    'voucher_date.required' => 'The voucher date field is required.',
                    'voucher_date.date' => 'The voucher date must be a valid date.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            do {
                $voucher_code = 'PV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseReturnVoucher::where('voucher_code', $voucher_code)->exists());
            $data = PurchaseReturnVoucher::create([
                'vendor_id' => $request->vendor_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'payment_method' => $request->payment_method,
                'cheque_no' => $request->cheque_no ?? null,
                'cheque_date' => $request->cheque_date ?? null,
                'voucher_code' => $voucher_code, 
                'voucher_date' => $request->voucher_date,
                'voucher_amount' => $request->voucher_amount,
                'status' => 0, // 0 un paid, 1 paid
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
                if (!$user->hasBusinessPermission($businessId, 'view purchase return voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseReturnVoucher::select(
                'purchase_return_voucher.*',
                'vendors.name as vendor_name',
                'chart_of_accounts.name as acc_name'
                )
                ->join('vendors','purchase_return_voucher.vendor_id','=','vendors.id')
                ->join('chart_of_accounts','purchase_return_voucher.acc_id','=','chart_of_accounts.id')
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
                if (!$user->hasBusinessPermission($businessId, 'edit purchase return voucher')) {
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
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required|date',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'vendor_id.required' => 'The Vendor field is required.',
                    'vendor_id.exists' => 'The selected Vendor is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'payment_method.required' => 'The payment method field is required.',
                    'payment_method.in' => 'The selected payment method is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required.',
                    'cheque_no.string' => 'The cheque number must be a string.',

                    'cheque_date.required_if' => 'The cheque date field is required.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',

                    'voucher_date.required' => 'The voucher date field is required.',
                    'voucher_date.date' => 'The voucher date must be a valid date.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            $data = PurchaseReturnVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            if ($data->status == 1) throw new Exception('voucher already paid', 404);
            $data->update([
                'vendor_id' => $request->vendor_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
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
                if (!$user->hasBusinessPermission($businessId, 'approve purchase return voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseReturnVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 400);
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            DB::beginTransaction();
            $data->update([
                'status'=>1
                ]);
            // transaction
            $vendor = Vendor::find($data->vendor_id);
            $vendor_acc = $vendor->acc_id;
            // for products
            $total_billed = $data->voucher_amount;

            // Calculate Cash/Bank Account Current Balance (Post-Credit)
            $b_cb = calculateBalance($data->acc_id, $total_billed, false); // Cash account is credited (reduced)

            // Calculate Vendor Account Current Balance (Post-Debit)
            $v_cb = calculateBalance($vendor_acc, $total_billed, true); // Vendor account is debited
            
            // Credit amount to vendor's account
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $vendor_acc,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Payment received by vendor: ' . $vendor->name,
                'debit' => 0.00, // No money credited to vendor's account
                'credit' => $total_billed, // Money debited from vendor's account
                'current_balance' => $v_cb // Updated balance for vendor account
            ]);

            // Debit amount from business's account
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $data->acc_id,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Payment send to vendor: ' . $vendor->name,
                'credit' => 0.00, // No money debited to business account
                'debit' => $total_billed, // Money credited from business account
                'current_balance' => $b_cb
            ]);
            
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

    public function previousData(Request $request, string $grn_id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase return voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = PurchaseReturnVoucher::where('grn_id',$grn_id)->get();

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    
}
