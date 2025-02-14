<?php

namespace App\Http\Controllers\Sale;

use App\Models\Customer;
use App\Models\Log;
use App\Models\OpeningBalance;
use App\Models\SaleVoucher;
use App\Models\Transaction;
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
            ->orderBy('id', 'desc');


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
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'customer_id.required' => 'The Customer field is required.',
                    'customer_id.exists' => 'The selected Customer is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'payment_method.required' => 'The payment method field is required.',
                    'payment_method.in' => 'The selected payment method is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required.',
                    'cheque_no.string' => 'The cheque number must be a string.',

                    'cheque_date.required_if' => 'The cheque date field is required.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',

                    'voucher_date.required' => 'The voucher date field is required.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            do {
                $voucher_code = 'SV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleVoucher::where('voucher_code', $voucher_code)->exists());
            $data = SaleVoucher::create([
                'customer_id' => $request->customer_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'payment_method' => $request->payment_method,
                'cheque_no' => $request->cheque_no ?? null,
                'cheque_date' => $request->cheque_date ?? null,
                'voucher_code' => $voucher_code, 
                'voucher_date' => $request->voucher_date,
                'voucher_amount' => $request->voucher_amount,
                'status' => 0, // 0 un paid, 1 paid
                'created_by' => $user->id,
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
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            DB::beginTransaction();
            $data->update([
                'approved_by' => $user->id,
                'approved_date' => now(),
                'status'=>1
                ]);
            // transaction
            $customer = Customer::find($data->customer_id);
            $customer_acc = $customer->acc_id;
            // for products
            $total_billed = $data->voucher_amount;

            $c_cb = calculateBalance($customer_acc, $total_billed, true); // Debit customer's account
            $b_cb = calculateBalance($data->acc_id, $total_billed, false);  // Credit business's account
            
            // Debit amount to customer's account
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $customer_acc,
                'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Payment made by customer: ' . $customer->name,
                'debit' => $total_billed, // No money deducted from customer's side
                'credit' => 0.00, // Money credited to customer
                'current_balance' => $c_cb // Updated balance for customer account
            ]);

            // Credit amount from business's account
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $data->acc_id,
                'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Payment received from customer: ' . $customer->name,
                'debit' => 0.00, // Money debited from business account
                'credit' => $total_billed, // No money credited to business account
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
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'customer_id.required' => 'The Customer field is required.',
                    'customer_id.exists' => 'The selected Customer is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'payment_method.required' => 'The payment method field is required.',
                    'payment_method.in' => 'The selected payment method is invalid.',

                    'cheque_no.required_if' => 'The cheque number field is required.',
                    'cheque_no.string' => 'The cheque number must be a string.',

                    'cheque_date.required_if' => 'The cheque date field is required.',
                    'cheque_date.date' => 'The cheque date must be a valid date.',

                    'voucher_date.required' => 'The voucher date field is required.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            $data = SaleVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            if ($data->status == 1) throw new Exception('voucher already paid', 404);
            $data->update([
                'customer_id' => $request->customer_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'payment_method' => $request->payment_method,
                'cheque_no' => $request->cheque_no ?? null,
                'cheque_date' => $request->cheque_date ?? null,
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
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
