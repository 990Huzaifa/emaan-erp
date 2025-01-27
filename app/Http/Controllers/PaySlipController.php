<?php

namespace App\Http\Controllers;

use App\Models\PaySlip;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class PaySlipController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list pay slip')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');

            $paySlips = PaySlip::select(
                'pay_slips.*',
                'employees.name as employee_name'
            )
            ->join('employees', 'pay_slips.employee_id', '=', 'employees.id')
            ->where('pay_slips.business_id', $user->login_business)
            ->where(function ($query) use ($searchQuery) {
                if ($searchQuery) {
                    $query->where('employees.name', 'like', '%' . $searchQuery . '%')
                    ->orWhere('pay_slips.slip_no', 'like', '%' . $searchQuery . '%');
                }
            })
            ->orderBy('id', 'desc');

            // Execute the query with pagination
            $data = $paySlips->paginate($perPage);
            return response()->json(['data' => $data], 200);
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
                if (!$user->hasBusinessPermission($businessId, 'create pay slip')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'employee_id' => 'required|exists:employees,id',
                    'pay_period_start' =>'required',
                    'pay_period_end' => 'required',
                    'issue_date' => 'required',
                    'basic_pay' => 'required',
                    'loan_deduction' => 'required',
                    'tax_deduction' => 'required',
                    'allowance' => 'required',
                    'bonus' => 'required',
                    'net_pay' => 'required',
                ],[
                    'employee_id.required' => 'Employee is required',
                    'employee_id.exists' => 'Employee does not exist',
                    'pay_period_start.required' => 'Pay period start is required',
                    'pay_period_end.required' => 'Pay period end is required',
                    'issue_date.required' => 'Issue date is required',
                    'basic_pay.required' => 'Basic salary is required',
                    'loan_deduction.required' => 'Loan deduction is required',
                    'tax_deduction.required' => 'Tax deduction is required',
                    'allowance.required' => 'Allowance is required',
                    'bonus.required' => 'Bonus is required',
                    'net_pay.required' => 'Net pay is required',
                    
                ]);

                if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
                do {
                    $slip_no = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (PaySlip::where('slip_no', $slip_no)->exists());

                $data = PaySlip::create([
                    "slip_date" => $request->slip_date,
                    "slip_no" => $slip_no,
                    "business_id" => $user->login_business,
                    "employee_id" => $request->employee_id,
                    "pay_period_start" => $request->pay_period_start,
                    "pay_period_end" => $request->pay_period_end,
                    "issue_date" => $request->issue_date,
                    "basic_pay" => $request->basic_pay,
                    "loan_deduction" => $request->loan_deduction,
                    "tax_deduction" => $request->tax_deduction,
                    "allowance" => $request->allowance,
                    "bonus" => $request->bonus,
                    "net_pay" => $request->net_pay,
                    "status" => $request->status

                ]);

                return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
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
