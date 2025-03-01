<?php

namespace App\Http\Controllers\Purchase;

use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
            ->where('business_id',$businessId)
            ->select('purchase_orders.*', 'vendors.name as vendor_name') // Select fields including vendor name
            ->orderBy('purchase_orders.id', 'desc');
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
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'vendor_id'=>'required|exists:vendors,id',
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'items' => 'required|array',

            ],[

                'vendor_id.required' => 'Vendor is required.',
                'vendor_id.exists' => 'Vendor does not exist.',

                'order_date.required' => 'Order date is required.',

                'due_date.required' => 'Due date is required.',

                'total.required' => 'Total is required.',
                'total.numeric' => 'Total must be a number.',
                
                'total_tax.required' => 'Total Tax is required.',
                'total_tax.numeric' => 'Total Tax must be a number.',
                
                'items.required' => 'Items are required.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $order_code = 'PO-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseOrder::where('order_code', $order_code)->exists());
            $data = PurchaseOrder::create([
                'order_code' => $order_code,
                'vendor_id' => $request->vendor_id,
                'business_id' => $user->login_business,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'total' => $request->total,
                'total_tax' => $request->total_tax,
                'terms_of_payment' => $request->terms_conditions ?? null,
                'remarks' => $request->remarks ?? null,
                'status' => $request->status ?? 0
            ]);
            foreach($request->items as $item){
                PurchaseOrderItem::create([
                    'purchase_order_id' => $data->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'tax' => $item['tax'],
                ]); 
            }         
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Purchase Order',   
            ]);
            return response()->json($data);
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
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseOrder::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id') // Join with the vendors table
            ->select('purchase_orders.*', 'vendors.name as vendor_name') // Select fields including vendor name
            ->where('purchase_orders.id', $id) // Filter by the specific purchase order ID
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'vendor_id'=>'required|exists:vendors,id',
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'terms_of_payment' => 'nullable|string',
                    'remarks' => 'nullable|string',
                    'items' => 'required|array',

            ],[
                'vendor_id.required' => 'Vendor is required.',
                'vendor_id.exists' => 'Vendor does not exist.',

                'order_date.required' => 'Order date is required.',

                'due_date.required' => 'Due date is required.',

                'total.required' => 'Total is required.',
                'total.numeric' => 'Total must be a number.',
                
                'total_tax.required' => 'Total Tax is required.',
                'total_tax.numeric' => 'Total Tax must be a number.',
                
                'items.required' => 'Items are required.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $data = PurchaseOrder::find($id);
            if (empty($data)) throw new Exception('No PO found', 404);
            $data->update([
                'vendor_id'=>$request->vendor_id,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'total' => $request->total,
                'total_tax' => $request->total_tax,
                'terms_of_payment' => $request->terms_conditions ?? $data->terms_of_payment,
                'remarks' => $request->remarks ?? $data->remarks,
                'status' => 0,
            ]);
            $existingItems = PurchaseOrderItem::where('purchase_order_id', $id)->get()->keyBy('id');
            $requestItemIds = [];
            foreach ($request->items as $item) {
                if (isset($item['id']) && isset($existingItems[$item['id']])) {
                    // Update existing item
                    $existingItems[$item['id']]->update([
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                    $requestItemIds[] = $item['id'];  // Keep track of updated items
                } else {
                    // Create new item
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            PurchaseOrderItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Order',   
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            if($request->status == 0) throw new Exception('Invalid status',400);
            $data = PurchaseOrder::find($id);
            if($data->status != 0) throw new Exception("Status can't change",400);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Order Status',   
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function list(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::select('id','order_code')->where('status',1)->where('business_id',$businessId)
            ->orderBy('purchase_orders.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->get();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function list2(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::select('id','order_code')->where('status',1)->where('vendor_id',$request->vendor_id)->where('business_id',$businessId)
            ->orderBy('purchase_orders.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->get();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
