<?php

namespace App\Http\Controllers\Purchase;

use App\Models\GoodsReceiveNote;
use App\Models\InventoryDetail;
use App\Models\Lot;
use App\Models\PurchaseOrder;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\PurchaseReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class PurchaseReturnController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseReturn::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('vendors', 'purchase_returns.vendor_id', '=', 'vendors.id') // Join with vendors
            ->select('purchase_returns.*', 'vendors.name as vendor_name') // Select fields including vendor name
            ->where('business_id',$businessId)
            ->orderBy('purchase_returns.id', 'desc');
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
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                'grn_id' => 'required|exists:goods_receive_notes,id',
                'return_date' => 'required|date',
                'reason' => 'required|string',
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric',
            ],[
                'grn_id.required' => 'GRN is required.',
                'grn_id.exists' => 'GRN does not exist.',
                'return_date.required' => 'Return date is required.',
                'reason.required' => 'Reason is required.',
                'items.required' => 'Items are required.',
                'items.*.product_id.required' => 'Product is required.',
                'items.*.product_id.exists' => 'Product does not exist.',
                'items.*.quantity.required' => 'Quantity is required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            DB::beginTransaction();
            do {
                $pr_code = 'PR-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseReturn::where('pr_code', $pr_code)->exists());

            $po_id = GoodsReceiveNote::where('id', $request->grn_id)->value('purchase_order_id');
            $vendor_id = PurchaseOrder::where('id', $po_id)->value('vendor_id');
            $data = PurchaseReturn::create([
                'grn_id' => $request->grn_id,
                'business_id' => $businessId,
                'vendor_id' => $vendor_id,
                'pr_code' => $pr_code,
                'purchase_order_id' => $po_id,
                'return_date' => $request->return_date,
                'reason' => $request->reason,
                'return_by' => $user->id
            ]);
            
            foreach ($request->items as $item) {
                $data->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_id' => $item['lot_id'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['total'],
                ]);
            }
            DB::commit();
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseReturn::with(['items' => function ($query) {
                $query->with(['product:id,title', 'lot:id,lot_code']);
            }])
            ->join('users','purchase_returns.return_by','=','users.id')
            ->join('goods_receive_notes', 'purchase_returns.grn_id', '=', 'goods_receive_notes.id')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('vendors', 'purchase_returns.vendor_id', '=', 'vendors.id') // Join with vendors
            ->select(
                'purchase_returns.*', 
                'vendors.name as vendor_name',
                'users.name as return_by'
            )
            ->where('purchase_returns.id', $id)
            ->first();
            if (!$data) throw new Exception('Purchase Return not found', 404);
            return response()->json($data);

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
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    
    public function updateStatus(Request $request,string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase return')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseReturn::find($id);
            
            DB::beginTransaction();
            $data->update([
                'status' => $request->status
            ]);
            if($data->status == 1){
                foreach ($data->items as $item) {
                    $inventory_detail = InventoryDetail::where('product_id',$item->product_id)->first();
                    $lot = Lot::find($item->lot_id);
                    $inventory_detail->update([
                        'stock' => $inventory_detail->stock - $item->quantity,
                    ]);
                    $lot->update([
                        'quantity' => $lot->quantity - $item->quantity,
                        'total_price' => $lot->purchase_unit_price * ($lot->quantity - $item->quantity),
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
