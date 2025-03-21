<?php

namespace App\Http\Controllers\Sale;

use Exception;
use App\Models\Log;
use App\Models\SaleOrder;
use Illuminate\Http\Request;
use App\Models\SaleOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;


class SaleOrderController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list sale orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = SaleOrder::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('customers', 'sale_orders.customer_id', '=', 'customers.id') // Join with customers
            ->select('sale_orders.*', 'customers.name as customer_name') // Select fields including customer name
            ->orderBy('sale_orders.id', 'desc');
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
                    'customer_id'=>'required|exists:customers,id',
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'items' => 'required|array',

            ],[

                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Customer does not exist.',

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
                $order_code = 'SO-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleOrder::where('order_code', $order_code)->exists());
            DB::beginTransaction();
            $data = SaleOrder::create([
                'order_code' => $order_code,
                'customer_id' => $request->customer_id,
                'business_id' => $user->login_business,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'total' => $request->total,
                'total_tax' => $request->total_tax,
                'terms_of_payment' => $request->terms_of_payment ?? null,
                'remarks' => $request->remarks ?? null,
                'special' => $request->special ?? 0,
                'status' => $request->status ?? 0
            ]);
            foreach($request->items as $item){
                SaleOrderItem::create([
                    'sale_order_id' => $data->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'tax' => $item['tax'],
                ]); 
            }
            $n_url = config('app.frontend_url').'/view-sale-order/'.$data->id;
            if($request->status == 1){
                notifyUser($user->id, $businessId,'create delivery notes', 'New sale order created and approved',$n_url);
            }else{
                notifyUser($user->id, $businessId,'approve sale orders', 'New sale order created',$n_url);
            }         
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Sale Order',   
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
                if (!$user->hasBusinessPermission($businessId, 'view sale orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleOrder::with(['items' => function ($query) {
                $query->with('product:id,title');
            }])
            ->join('customers', 'sale_orders.customer_id', '=', 'customers.id') // Join with the customer table
            ->select('sale_orders.*', 'customers.name as customer_name') // Select fields including customer name
            ->find($id);
            if (empty($data)) throw new Exception('No SO found', 404);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit sale orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'customer_id' => 'required|exists:customers,id',
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'terms_of_payment' => 'nullable|string',
                    'remarks' => 'nullable|string',
                    'items' => 'required|array',

            ],[
                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Customer does not exist.',

                'order_date.required' => 'Order date is required.',

                'due_date.required' => 'Due date is required.',

                'total.required' => 'Total is required.',
                'total.numeric' => 'Total must be a number.',
                
                'total_tax.required' => 'Total Tax is required.',
                'total_tax.numeric' => 'Total Tax must be a number.',
                
                'items.required' => 'Items are required.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $data = saleOrder::find($id);
            if (empty($data)) throw new Exception('No SO found', 404);
            $data->update([
                'customer_id'=>$request->customer_id,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'total' => $request->total,
                'total_tax' => $request->total_tax,
                'terms_of_payment' => $request->terms_of_payment ?? $data->terms_of_payment,
                'remarks' => $request->remarks ?? $data->remarks,
                'status' => 0,
            ]);
            $existingItems = SaleOrderItem::where('sale_order_id', $id)->get()->keyBy('id');
            $requestItemIds = [];
            foreach ($request->items as $item) {
                if (empty($item['id']) && isset($item['id']) && isset($existingItems[$item['id']])) {
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
                    SaleOrderItem::create([
                        'sale_order_id' => $id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            SaleOrderItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Sale Order',   
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
                if (!$user->hasBusinessPermission($businessId, 'approve sale orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $status = $request->status;
            if($status == 0) throw new Exception('Invalid status',400);
            $data = SaleOrder::find($id);
            if($data->status != 0) throw new Exception("Status can't change",400);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Sale Order Status',   
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function list(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list sale orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = SaleOrder::with(['items' => function ($query) {
                $query->with('product:id,title');
            }])// Select fields including vendor name
            ->orderBy('sale_orders.id', 'desc')->where('sale_orders.business_id',$businessId)
            ->where('sale_orders.status','=',1);
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
