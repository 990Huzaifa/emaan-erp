<?php

namespace App\Http\Controllers;

use App\Models\ExpenseVoucher;
use DB;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = ExpenseVoucher::with(['acc', 'expense_acc'])->where('business_id', $businessId)->get();
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error'=>$e->getMessage()], 400);            
        } catch (Exception $e) {
            return response()->json(['error'=>$e->getMessage()], 400);
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
                if (!$user->hasBusinessPermission($businessId, 'create expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'voucher_date' => 'required|date',
                    'expense_acc' => 'required|exists:chart_of_accounts,id',
                    'cheque_no' => 'required_if:payment_method,BANK|string',
                    'cheque_date' => 'required_if:payment_method,BANK|date',
                    'voucher_amount' => 'required|numeric',
                ],
                [
                    'acc_id.required' => 'Account is required',
                    'acc_id.exists' => 'Account does not exist',

                    'voucher_date.required' => 'Voucher date is required',
                    'voucher_date.date' => 'Voucher date is not valid',

                    'expense_acc.required' => 'Expense account is required',
                    'expense_acc.exists' => 'Expense account does not exist',

                    'cheque_no.required_if' => 'Cheque number is required',
                    'cheque_date.required_if' => 'Cheque date is required',

                    'voucher_amount.required' => 'Voucher amount is required',
                    'voucher_amount.numeric' => 'Voucher amount is not valid',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $data = ExpenseVoucher::create([
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'voucher_date' => $request->voucher_date,
                'expense_acc' => $request->expense_acc,
            ]);
            DB::commit();
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error'=>$e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view expense voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = ExpenseVoucher::with(['acc', 'expense_acc'])->find($id);   
            if (empty($data)) throw new Exception('Expense voucher not found', 404);         
            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error'=>$e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error'=>$e->getMessage()], 400);
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
