<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiveNote;
use App\Models\GoodsReceiveNoteItem;
use App\Models\InventoryDetail;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class GRNController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = GoodsReceiveNote::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->where('business_id',$businessId)
            ->join('purchase_orders','purchase_orders.id','=','goods_receive_notes.purchase_order_id')
            ->join('users', 'users.id', '=', 'goods_receive_notes.received_by') // Corrected join
            ->select('goods_receive_notes.*', 'users.name as received_by', 'purchase_orders.order_code as po_code')
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
                if (!$user->hasBusinessPermission($businessId, 'create goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'grn_date'=>'required',
                    'purchase_order_id' => 'required|numeric|exists:purchase_orders,id',
                    'remarks' => 'nullable|string',
                    'items' => 'required|array',

            ],[
                'grn_date.required' => 'GRN date is required.',

                'purchase_order_id.required' => 'Purchase order is required.',
                'purchase_order_id.exists' => 'Purchase order does not exist.',

                'remarks.string' => 'Remarks must be a string.',

                'items.required' => 'Items are required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            do {
                $grn_code = 'GRN-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (GoodsReceiveNote::where('grn_code', $grn_code)->exists());
            $GRN = GoodsReceiveNote::create([
                'purchase_order_id' => $request->purchase_order_id,
                'business_id' => $businessId,
                'grn_code' => $grn_code,
                'grn_date' => $request->grn_date,
                'received_by' => $user->id,
                'remarks' => $request->remarks
            ]);

            foreach ($request->items as $item) {
                GoodsReceiveNoteItem::create([
                    'goods_receive_note_id' => $GRN->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'receive' => $item['receive'],
                    'billed' => $item['billed'],
                    'purchase_unit_price' => $item['purchase_unit_price'],
                    'sale_unit_price' => $item['sale_unit_price'],
                    'total_price' => $item['total_price'],
                    'tax' => $item['tax'],
                ]);
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'user created GRN',
            ]);
            DB::commit();
            return response()->json($GRN,200);
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
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = GoodsReceiveNote::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->where('id', $id) // Filter by the specific purchase order ID
            ->firstOrFail();
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
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'grn_date'=>'required',
                    'purchase_order_id' => 'required|numeric|exists:purchase_orders,id',
                    'remarks' => 'nullable|string',
                    'items' => 'required|array',

            ],[
                'grn_date.required' => 'GRN date is required.',

                'purchase_order_id.required' => 'Purchase order is required.',
                'purchase_order_id.exists' => 'Purchase order does not exist.',

                'remarks.string' => 'Remarks must be a string.',

                'items.required' => 'Items are required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            $GRN = GoodsReceiveNote::find($id);
            $GRN->update([
                'purchase_order_id' => $request->purchase_order_id,
                'grn_date' => $request->grn_date,
                'remarks' => $request->remarks
            ]);

            foreach ($request->items as $item) {
                if (isset($item['id'])) {
                    $GRNItem = GoodsReceiveNoteItem::find($item['id']);
                    $GRNItem->update([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'receive' => $item['receive'],
                        'billed' => $item['billed'],
                        'purchase_unit_price' => $item['purchase_unit_price'],
                        'sale_unit_price' => $item['sale_unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                } else {
                    GoodsReceiveNoteItem::create([
                        'goods_receive_note_id' => $GRN->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'receive' => $item['receive'],
                        'billed' => $item['billed'],
                        'purchase_unit_price' => $item['purchase_unit_price'],
                        'sale_unit_price' => $item['sale_unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update GRN',   
            ]);
            DB::commit();
            return response()->json($GRN,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    
    public function updateStatus(Request $request, string $id)
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = GoodsReceiveNote::find($id);
            DB::beginTransaction();
            if (empty($data)) throw new Exception('GRN not found', 400);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);
            // transaction start
            $vendor = Vendor::find($data->purchase_order->vendor_id);
            $vendor_acc = $vendor->acc_id;
            // for products
            $total_billed = 0;
            foreach ($data->items as $item) {
                $total_billed += $item->billed;
                $product = Product::find($item->product_id);
                $product_acc = $product->acc_id;
                Transaction::create([
                    'business_id' => $data->business_id,
                    'acc_id'=>$product_acc,
                    'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => 'Item is purchased by this vendor: '.$vendor->name,
                    'credit' => 0.00,
                    'debit' => $item->billed
                ]);
            }
            // for vendor
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id'=>$vendor_acc,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'purchase item from this vendor: '.$vendor->name,
                'credit' => $total_billed,
                'debit' => 0.00
            ]);
            // lot entry
            if($request->status == 1){
                foreach ($data->items as $item) {
                    $product = Product::find($item->product_id);
                    do {
                        $lot_code = 'LOT-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (Lot::where('lot_code', $lot_code)->exists());
                    $lot = Lot::create([
                        'purchase_order_id' => $data->purchase_order_id,
                        'grn_id' => $id,
                        'product_id' => $item->product_id,
                        'lot_code' => $lot_code,
                        'vendor_id' => $data->purchase_order->vendor_id,
                        'purchase_unit_price' => $item->purchase_unit_price,
                        'sale_unit_price' => $item->sale_unit_price,
                        'quantity' => $item->quantity,
                        'status' => 1,
                        'total_price' => $item->purchase_unit_price * $item->quantity,
                    ]);
                    InventoryDetail::create([
                        'lot_id' => $lot->id,
                        'product_id' => $item->product_id,
                        'stock' => $item->quantity,
                        'unit_price' => $item->sale_unit_price,
                        'in_stock' => 1,
                    ]);
                }
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'update GRN Status',   
            ]);

            DB::commit();
            return response()->json($data);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    
    public function list(): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = GoodsReceiveNote::select('id','grn_code')->where('status',1)->where('business_id',$businessId)->get();
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }    
    }

    public function approveGRNShow($id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = GoodsReceiveNote::with(['items.product' => function ($query) use ($id) {
                $query->select('products.id', 'products.title')
                    ->join('lots', function ($join) use ($id) {
                        $join->on('products.id', '=', 'lots.product_id')
                            ->where('lots.grn_id', '=', $id);
                    })
                    ->addSelect('lots.id as lot_id', 'lots.lot_code'); // Include lot information
            }])
            
            ->where('id', $id) // Filter by the specific purchase order ID
            ->firstOrFail();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
