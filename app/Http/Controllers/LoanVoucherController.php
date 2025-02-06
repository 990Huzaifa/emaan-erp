<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LoanVoucher;
use Illuminate\Http\Request;
use Exception;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoanVoucherController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list loan voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = LoanVoucher::select('loan_vouchers.*','chart_of_accounts.name as asset_name','employees.name as employee_name')
            ->join('chart_of_accounts', 'loan_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->join('employees', 'loan_vouchers.employee_id', '=', 'employees.id')
            ->orderBy('id', 'desc');
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
                if (!$user->hasBusinessPermission($businessId, 'create loan voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'voucher_date'=>'required|date',
                    'acc_id'=>'required|exists:chart_of_accounts,id',
                    'employee_id'=>'required|exists:employees,id',
                    'voucher_amount'=>'required|numeric',
                    'payment_method'=>'required|string|in:CASH,BANK,OTHER',
                    'cheque_no'=>'required_if:payment_method,BANK|string',
                    'cheque_date'=>'required_if:payment_method,BANK|date',
                ],[
                    
                    'acc_id.required'=>'Account is Required',
                    'acc_id.exists'=>'Account is Invalid',
                    
                    'employee_id.required'=>'Employee is Required',
                    'employee_id.exists'=>'Employee is Invalid',
                    
                    'voucher_amount.numeric'=>'Amount must be a number',
                    'voucher_amount.required'=>'Amount is Required',
                    
                    'payment_method.required'=>'Payment Method is Required',
                    'payment_method.in'=>'Payment Method is Invalid',
                    
                    'cheque_no.required_if'=>'Cheque No is Required',
                    'cheque_no.string'=>'Cheque No must be a string',
                    
                    'cheque_date.required_if'=>'Cheque Date is Required',
                    'cheque_date.date'=>'Cheque Date must be a date',
                    
                    'voucher_date.required'=>'Voucher Date is Required',
                    'voucher_date.date'=>'Voucher Date must be a date',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            DB::beginTransaction();
            do {
                $voucher_code = 'LV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (LoanVoucher::where('voucher_code', $voucher_code)->exists());
            $data = LoanVoucher::create([
                'acc_id'=>$request->acc_id,
                'voucher_code'=>$voucher_code,
                'business_id'=>$user->login_business,
                'employee_id'=>$request->employee_id,
                'voucher_amount'=>$request->voucher_amount,
                'payment_method'=>$request->payment_method,
                'cheque_no'=>$request->cheque_no,
                'cheque_date'=>$request->cheque_date,
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
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view loan voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = LoanVoucher::select('journal_vouchers.*','chart_of_accounts.name as account_name','employees.name as employee_name')
            ->join('chart_of_accounts', 'journal_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->join('employees', 'journal_vouchers.employee_id', '=', 'employees.id')
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
                if (!$user->hasBusinessPermission($businessId, 'edit loan voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'voucher_date'=>'required|date',
                    'acc_id'=>'required|exists:chart_of_accounts,id',
                    'employee_id'=>'required|exists:employees,id',
                    'voucher_amount'=>'required|numeric',
                    'payment_method'=>'required|string|in:CASH,BANK,OTHER',
                    'cheque_no'=>'required_if:payment_method,BANK|string',
                    'cheque_date'=>'required_if:payment_method,BANK|date',
                ],[
                    
                    'acc_id.required'=>'Account is Required',
                    'acc_id.exists'=>'Account is Invalid',
                    
                    'employee_id.required'=>'Employee is Required',
                    'employee_id.exists'=>'Employee is Invalid',
                    
                    'voucher_amount.numeric'=>'Amount must be a number',
                    'voucher_amount.required'=>'Amount is Required',
                    
                    'payment_method.required'=>'Payment Method is Required',
                    'payment_method.in'=>'Payment Method is Invalid',
                    
                    'cheque_no.required_if'=>'Cheque No is Required',
                    'cheque_no.string'=>'Cheque No must be a string',
                    
                    'cheque_date.required_if'=>'Cheque Date is Required',
                    'cheque_date.date'=>'Cheque Date must be a date',
                    
                    'voucher_date.required'=>'Voucher Date is Required',
                    'voucher_date.date'=>'Voucher Date must be a date',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            DB::beginTransaction();
            $loanVoucher = LoanVoucher::findOrFail($id);
            $loanVoucher->update([
                'acc_id'=>$request->acc_id,
                'employee_id'=>$request->employee_id,
                'voucher_amount'=>$request->voucher_amount,
                'payment_method'=>$request->payment_method,
                'cheque_no'=>$request->cheque_no  ?? null,
                'cheque_date'=>$request->cheque_date ?? null,
                'voucher_date'=>$request->voucher_date,
            ]);
            DB::commit();
            return response()->json($loanVoucher,200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource status in storage.
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
            
            if($request->status == 0) throw new Exception('Status is Invalid', 400);
            $data = LoanVoucher::findOrFail($id);
            if (empty($data)) throw new Exception('No Journal Voucher found', 404);
            if($request->status == $data->status) throw new Exception('Status is already updated', 400);
            DB::beginTransaction();
            $acc_id = $data->acc_id;
            $employee_id = $data->employee_id;
            $employee_acc_id = Employee::where('id', $employee_id)->first()->value('acc_id');
            $total_amount = $data->voucher_amount;
                // Withdrawal: Debit Partner Account, Credit Business Account (money leaves business, reduces equity)
            $a_cb = calculateBalance($acc_id, $total_amount, false); // Business asset account
            $p_cb = calculateBalance($employee_acc_id, $total_amount, true); // Partner equity account

            // Credit the asset account (money is leaving the business)
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $acc_id,
                'transaction_type' => 2, // Withdrawal
                'description' => 'Partner withdrawal.',
                'debit' => $total_amount,
                'credit' => 0.00,
                
                'current_balance' => $a_cb
            ]);

            // Debit the partner's equity account
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id' => $employee_acc_id,
                'transaction_type' => 2, // Withdrawal
                'description' => 'Reduction in partner equity due to withdrawal.',
                'debit' => 0.00,
                'credit' => $total_amount,
                'current_balance' => $p_cb
            ]);



            $data->update([
                'status'=>$request->status,
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
}
