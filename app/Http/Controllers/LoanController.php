<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'view loan')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->input('per_page', 10);
            $data = Loan::where('business_id', $businessId)->paginate($perPage);
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
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create loan')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'loan_amount' => 'required',
                'loan_date' => 'required',
            ],[
                'employee_id.required' => 'Employee ID is required',
                'employee_id.exists' => 'Employee ID does not exist',
                'loan_amount.required' => 'Loan amount is required',
                'loan_date.required' => 'Loan date is required',
            ]);
            // check employee loan 
            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);
            $checkLoan = Loan::where('employee_id', $request->employee_id)->first();
            if ($checkLoan->remaining_amount > 0 && $checkLoan->status == 0) throw new Exception('Employee has a pending loan',400);

            $data = Loan::create([
                'employee_id' => $request->employee_id,
                'business_id' => $businessId,
                'loan_amount' => $request->loan_amount,
                'loan_date' => $request->loan_date,
                'installments' => 0,    
                'installment_amount' => 0,
                'remaining_amount' => $request->loan_amount
            ]);

            return response()->json($data, 200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
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
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'show loan')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = Loan::find($id);
            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
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

    public function filterLoan(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list loan')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = Loan::where('business_id', $businessId)->where('employee_id', $id)->get();
            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
