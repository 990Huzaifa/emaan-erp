<?php

namespace App\Http\Controllers;

use App\Models\JournalVoucher;
use Exception;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JournalVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = JournalVoucher::select('journal_vouchers.*','chart_of_accounts.name as asset_name','uses.name as partner_name')
            ->join('chart_of_accounts', 'journal_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->join('users', 'journal_vouchers.partner_id', '=', 'users.id')
            ->orderBy('id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create journal voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'Voucher_date'=>'required|date',
                    'acc_id'=>'required|exists:chart_of_accounts,id',
                    'partner_id'=>'required|exists:users,id',
                    'voucher_amount'=>'required|numeric',
                    'payment_method'=>'required|string|in:CASH,BANK,OTHER',
                    'cheque_no'=>'required_if:payment_method,BANK|string',
                    'cheque_date'=>'required_if:payment_method,BANK|date',
                ],[

                    'voucher_date.required'=>'Voucher Date is Required',
                    'voucher_date.date'=>'Voucher Date must be a date',

                    'acc_id.required'=>'Account is Required',
                    'acc_id.exists'=>'Account is Invalid',

                    'partner_id.required'=>'Partner is Required',
                    'partner_id.exists'=>'Partner is Invalid',

                    'voucher_amount.numeric'=>'Amount must be a number',
                    'voucher_amount.required'=>'Amount is Required',

                    'payment_method.required'=>'Payment Method is Required',
                    'payment_method.in'=>'Payment Method is Invalid',

                    'cheque_no.required_if'=>'Cheque No is Required',
                    'cheque_no.string'=>'Cheque No must be a string',

                    'cheque_date.required_if'=>'Cheque Date is Required',
                    'cheque_date.date'=>'Cheque Date must be a date',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(),400);

            DB::beginTransaction();
            $journalVoucher = JournalVoucher::create([
                'Voucher_date'=>$request->Voucher_date,
                'acc_id'=>$request->acc_id,
                'partner_id'=>$request->partner_id,
                'voucher_amount'=>$request->voucher_amount,
                'payment_method'=>$request->payment_method,
                'cheque_no'=>$request->cheque_no,
                'cheque_date'=>$request->cheque_date,
            ]);
            DB::commit();
            return response()->json([$journalVoucher,200]);
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
