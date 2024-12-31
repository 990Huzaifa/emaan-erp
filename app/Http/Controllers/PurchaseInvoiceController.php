<?php

namespace App\Http\Controllers;


use App\Models\GoodsReceiveNote;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
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
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'grn_id'=>'required|exists:goods_received_notes,id',
                ],[
                'grn_id.required' => 'The goods receive note field is required.',
                'grn_id.exists' => 'The selected goods receive note is invalid.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $invoice_no = 'PI-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseInvoice::where('invoice_no', $invoice_no)->exists());
            $GRN = GoodsReceiveNote::find($request->grn_id);
            $POID = $GRN->purchase_order_id;
            $PO = PurchaseOrder::find($POID);
            PurchaseInvoice::create([
                'invoice_no' => $invoice_no,
                'invoice_date' => $request->invoice_date,
                'business_id' => $user->login_business,
                'grn_id' => $request->grn_id,
            ]);
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
