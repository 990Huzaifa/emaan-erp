<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DeliveryNote;
use App\Models\Log;
use App\Models\SaleReceipt;
use App\Models\SaleReceiptItem;
use App\Models\SaleOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SaleReceiptController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = SaleReceipt::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('delivery_notes', 'sale_receipts.dn_id', '=', 'delivery_notes.id')
            ->join('customers', 'sale_receipts.customer_id', '=', 'customers.id') // Join with vendors
            ->select('sale_receipts.*', 'customers.name as customer_name','delivery_notes.dn_code')
            ->where('sale_receipts.business_id',$businessId)
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
    public function store(Request $request)
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'dn_id'=>'required|exists:delivery_notes,id',
                    'receipt_date'=> 'required',
                ],[
                'dn_id.required' => 'The dnn_id is required.',
                'dn_id.exists' => 'The dn_id is invalid.',

                'receipt_date.required' => 'Receipt Date is required.'
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            do {
                $receipt_no = 'SR-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleReceipt::where('receipt_no', $receipt_no)->exists());

            $DN = DeliveryNote::with('items')->where('status',1)->find($request->grn_id);
            if (!$DN) throw new Exception('Delivery Note is not approved yet.', 400);

            $SOID = $DN->sale_order_id;
            $SO = SaleOrder::find($SOID);
            if (!$SO) throw new Exception('Sale Order not found.', 404);

            DB::beginTransaction();

            $saleReceipt = SaleReceipt::create([
                'dn_id' => $request->dn_id,
                'receipt_no' => $receipt_no,
                'receipt_date' => $request->receipt_date,
                'customer_id' => $SO->customer_id,
                'business_id' => $businessId,
            ]);

            // Map DN items to PI items
            foreach ($DN->items as $item) {
                SaleReceiptItem::create([
                    'sale_receipt_id' => $saleReceipt->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->purchase_unit_price,
                    'total' => $item->total_price,
                    'tax' => $item->tax,
                ]);
            }

            DB::commit();
            return response()->json($saleReceipt, 200);
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
