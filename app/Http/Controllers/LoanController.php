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
    public function index()
    {
        //
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

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

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
}
