<?php

namespace App\Http\Controllers;

use App\Models\SalaryVoucher;
use Exception;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SalaryVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list salary voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = SalaryVoucher::select('salary_vouchers.*','employee.name as employee_name','chart_of_accounts.name as acc_name')
            ->join('employees','salary_vouchers.employee_id', '=', 'employees.id')
            ->join('chart_of_accounts','salary_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('salary_vouchers.business_id',$user->login_business)
            ->orderBy('id', 'desc');
            if (!empty($searchQuery)) {
                $query->where('salary_vouchers.voucher_code', 'like', '%' . $searchQuery . '%');
                
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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create salary voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'employee_id' => 'required|exists:employees,id',
                    "payment_method" => 'required|string|in:CASH,BANK,OTHER',
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_date' => 'required|date',
                    'voucher_amount' => 'required|numeric',
                ], [
                    'employee_id.required' => 'The Employee field is required.',
                    'employee_id.exists' => 'The selected Employee is invalid.',
                    
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
                $voucher_code = 'SV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SalaryVoucher::where('voucher_code', $voucher_code)->exists());
            $data = SalaryVoucher::create([
                'employee_id' => $request->employee_id,
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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view salary voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = SalaryVoucher::select(
                'salary_vouchers.*',
                'employees.name as employee_name',
                'chart_of_accounts.name as acc_name'
                )
                ->join('employees','salary_vouchers.employee_id','=','employees.id')
                ->join('chart_of_accounts','salary_vouchers.acc_id','=','chart_of_accounts.id')
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
