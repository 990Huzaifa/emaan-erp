<?php

namespace App\Http\Controllers\Sale;

use App\Models\DeliveryNote;
use App\Models\InventoryDetail;
use App\Models\Log;
use App\Models\Lot;
use App\Models\SaleOrder;
use App\Models\SaleReturn;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;


class SaleReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'list sale return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = SaleReturn::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('customers', 'sale_returns.customer_id', '=', 'customers.id') // Join with customer
            ->join('delivery_notes', 'sale_returns.dn_id', '=', 'delivery_notes.id')
            ->join('sale_orders', 'sale_returns.sale_order_id', '=', 'sale_orders.id')
            ->select('sale_returns.*', 'customers.name as customer_name', 'delivery_notes.dn_code as dn_code', 'sale_orders.order_code as sale_order_code') // Select fields including vendor name
            ->where('sale_returns.business_id',$businessId)
            ->orderBy('sale_returns.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('return_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create sale return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                'dn_id' => 'required|exists:delivery_notes,id',
                'return_date' => 'required|date',
                'reason' => 'required|string',
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric',
                'items.*.unit_price' => 'required|numeric',
                'items.*.total_price' => 'required|numeric',
                'items.*.lot_id' => 'nullable|exists:lots,id',
            ],[
                'dn_id.required' => 'Delivery note ID is required.',
                'dn_id.exists' => 'Delivery note does not exist.',

                'reason.required' => 'Reason is required.',
                'return_date.required' => 'Return date is required.',

                'items.required' => 'Items are required.',
                'items.*.product_id.required' => 'Product is required.',
                'items.*.product_id.exists' => 'Product does not exist.',
                'items.*.quantity.required' => 'Quantity is required.',
                'items.*.quantity.numeric' => 'Quantity must be a number.',
                'items.*.unit_price.required' => 'Unit price is required.',
                'items.*.unit_price.numeric' => 'Unit price must be a number.',
                'items.*.total_price.required' => 'Total price is required.',
                'items.*.total_price.numeric' => 'Total price must be a number.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            DB::beginTransaction();
            do {
                $sr_code = 'PR-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleReturn::where('sr_code', $sr_code)->exists());

            $so_id = DeliveryNote::where('id', $request->dn_id)->value('sale_order_id');
            $customer_id = SaleOrder::where('id', $so_id)->value('customer_id');

            $data = SaleReturn::create([
                'sr_code' => $sr_code,
                'dn_id' => $request->dn_id,
                'business_id' => $businessId,
                'sale_order_id' => $so_id,
                'customer_id' => $customer_id,
                'received_date' => $request->return_date,
                'reason' => $request->reason,
                'received_by' => $user->id
            ]);
            foreach($request->items as $item){
                $data->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'lot_id' => $item['lot_id'] ?? null,
                ]);
            }
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view sale return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleReturn::with(['items' => function ($query) {
                $query->with(['product:id,title', 'lot:id,lot_code']);
            }])
            ->join('users','sale_returns.received_by','=','users.id')
            ->join('delivery_notes', 'sale_returns.dn_id', '=', 'delivery_notes.id')
            ->join('sale_orders', 'sale_returns.sale_order_id', '=', 'sale_orders.id')
            ->join('customers', 'sale_returns.customer_id', '=', 'customers.id') // Join with customers
            ->select(
            'sale_returns.*', 
                    'customers.name as customer_name',
                    'users.name as received_by',
                    'delivery_notes.dn_code as dn_code',
                    'sale_orders.order_code as sale_order_code'
                )
            ->where('sale_returns.id', $id)
            ->first();
            if (!$data) throw new Exception('Sale Return not found', 404);
            return response()->json($data);

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
    public function updateStatus(Request $request,string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve sale return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleReturn::find($id);
            
            DB::beginTransaction();
            $data->update([
                'status' => $request->status
            ]);
            if($request->status == 1){
                foreach ($data->items as $item) {
                    $inventory_detail = InventoryDetail::where('product_id',$item->product_id)->first();
                    $lot = Lot::find($item->lot_id);
                    $inventory_detail->update([
                        'stock' => $inventory_detail->stock + $item->quantity,
                    ]);
                    $lot->update([
                        'quantity' => $lot->quantity + $item->quantity,
                    ]);
                }
            }
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Return Status',   
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