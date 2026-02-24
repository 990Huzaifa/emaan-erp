<?php

namespace App\Http\Controllers\Sale;

use App\Models\SaleReceipt;
use App\Models\SaleReceiptItem;
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
use Illuminate\Support\Facades\Log as SysLog;

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
                    'delivery_cost' => 'required|numeric',
                    'total_discount' => 'required|numeric',
                    'items' => 'required|array',
                    'is_dn_approved' => 'nullable|boolean',

            ],[

                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Customer does not exist.',

                'order_date.required' => 'Order date is required.',

                'due_date.required' => 'Due date is required.',

                'total.required' => 'Total is required.',
                'total.numeric' => 'Total must be a number.',
                
                'total_tax.required' => 'Total Tax is required.',
                'total_tax.numeric' => 'Total Tax must be a number.',

                'delivery_cost.required' => 'Delivery cost is required.',
                'delivery_cost.numeric' => 'Delivery cost must be a number.',

                'total_discount.required' => 'Total Discount is required.',
                'total_discount.numeric' => 'Total Discount must be a number.',
                
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
                'delivery_cost' => $request->delivery_cost,
                'total_discount' => $request->total_discount ?? 0,
                'terms_of_payment' => $request->terms_of_payment ?? null,
                'remarks' => $request->remarks ?? null,
                'special' => $request->special ?? 0,
                'status' => $request->status ?? 0
            ]);

            $total_discount = 0;

            foreach($request->items as $item){
                $discount = 0;

                // Calculate discount amount
                if (!empty($item['discount_in_percentage']) && $item['discount_in_percentage']) {
                    $discount = round(($item['unit_price'] * $item['quantity']) * ($item['discount'] / 100));
                } else {
                    $discount = round($item['discount']); // Flat value
                }

                $subtotal = round(($item['unit_price'] * $item['quantity']) - $discount);
                $total_discount += $discount;

                // logic end

                SaleOrderItem::create([
                    'sale_order_id' => $data->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'discount_in_percentage' => $item['discount_in_percentage'] ?? 0,
                    'total_price' => $item['total_price'],
                    'tax' => $item['tax'],
                ]); 
            }
            if (round($request->total_discount) != $total_discount) {
                throw new Exception('Total discount does not match calculated item discounts.'.$total_discount, 400);
            }
            $n_url ='view-sale-order/'.$data->id;
            $data->update([
                'total_discount' => $request->total_discount
            ]);
            if($request->status == 1 && $request->is_dn_approved == 0){
                notifyUser($user->id, $businessId,'create delivery notes', 'New sale order created and approved',$n_url);
            }else if($request->status == 1 && $request->is_dn_approved == 1){
                $DNController = new DeliveryNoteController();
                SysLog::info('Ready DN for SO: ' . $data->order_code);
                $DNController->readyDn($data->id, $user->id);
            }else{
                notifyUser($user->id, $businessId,'approve sale orders', 'New sale order created',$n_url);
            }         
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Sale Order. Code:'. $order_code,
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
                $query->with('product:id,title')->leftJoin('inventory_details', 'sale_order_items.product_id', '=', 'inventory_details.product_id')
                ->addSelect('sale_order_items.*', 'inventory_details.stock as max_quantity');
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
                    'delivery_cost' => 'required|numeric',
                    'total_discount' => 'required|numeric',
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

                'delivery_cost.required' => 'Delivery cost is required.',
                'delivery_cost.numeric' => 'Delivery cost must be a number.',

                'total_discount.required' => 'Total discount is required.',
                'total_discount.numeric' => 'Total discount must be a number.',
                
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
                'total_discount' => $request->total_discount,
                'delivery_cost' => $request->delivery_cost,
                'terms_of_payment' => $request->terms_of_payment ?? $data->terms_of_payment,
                'remarks' => $request->remarks ?? $data->remarks,
                'status' => 0,
            ]);
            $existingItems = SaleOrderItem::where('sale_order_id', $id)->get()->keyBy('id');
            $requestItemIds = [];
            $total_discount = 0;
            foreach ($request->items as $item) {
                $discount = 0;

                // Calculate discount amount
                if (!empty($item['discount_in_percentage']) && $item['discount_in_percentage']) {
                    $discount = round(($item['unit_price'] * $item['quantity']) * ($item['discount'] / 100));
                } else {
                    $discount = round($item['discount']); // Flat value
                }

                $subtotal = round(($item['unit_price'] * $item['quantity']) - $discount);
                $total_discount += $discount;


                if (empty($item['id']) && isset($item['id']) && isset($existingItems[$item['id']])) {
                    // Update existing item
                    $existingItems[$item['id']]->update([
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'discount' => $item['discount'],
                        'discount_in_percentage' => $item['discount_in_percentage'],
                        'tax' => $item['tax'],
                    ]);
                    $requestItemIds[] = $item['id'];  // Keep track of updated items
                } else {
                    // Create new item
                    SaleOrderItem::create([
                        'sale_order_id' => $id,
                        'product_id' => $item['product_id'],
                        'measurement_unit' => $item['measurement_unit'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'discount' => $item['discount'],
                        'discount_in_percentage' => $item['discount_in_percentage'],
                        'tax' => $item['tax'],
                    ]);
                }
            }
            if (round($request->total_discount) != $total_discount) {
                throw new Exception('Total discount does not match calculated item discounts.'.$total_discount, 400);
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            SaleOrderItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Sale Order. Code:'. $data->order_code,
            ]);
            $n_url ='view-sale-order/'.$id;
            notifyUser($user->id, $businessId,'view sale orders', 'sale order has been updated',$n_url);
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
                'description' => 'Update Sale Order Status. Code:'. $data->order_code,   
            ]);
            $n_url ='view-sale-order/'.$id;
            if($request->status == 1){
                notifyUser($user->id, $businessId,'create delivery notes', 'sale order approved successfully',$n_url);
            }elseif($request->status == 2){
                notifyUser($user->id, $businessId,'view sale orders', 'sale order Rejected',$n_url);
            }
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

    public function getLastReceiptItem($customerId): JsonResponse
    {
        try{
            $SR = SaleReceipt::where('customer_id', $customerId)->orderBy('created_at', 'desc')->first();
            if (empty($SR)) throw new Exception("No sale receipt found",400);
            
            $SRI = SaleReceiptItem::where('sale_receipt_id', $SR->id)->get();
            
            return response()->json($SRI,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function print(string $id)
    {
        try {
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = SaleOrder::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('businesses', 'sale_orders.business_id', '=', 'businesses.id')
            ->join('customers', 'sale_orders.customer_id', '=', 'customers.id') // Join with vendors
            ->join('cities as customer_city', 'customers.city_id', '=', 'customer_city.id')
            ->join('cities as business_city', 'businesses.city_id', '=', 'business_city.id')
            ->select('sale_orders.*',
            'customers.name as customer_name',
            'customers.address as customer_address',
            'customers.telephone as customer_telephone',
            'businesses.name as business_name',
            'businesses.logo as business_logo',
            'businesses.address as business_address',
            'businesses.phone as business_telephone',
            'customer_city.name as customer_city_name',
            'business_city.name as business_city_name'
            ) // Select fields including vendor name
            ->where('sale_orders.id', $id)->first();

            $acc_id = Customer::where('id',$data->customer_id)->value('acc_id');
            
            $current_balance = Transaction::where('acc_id', $acc_id)
            ->orderBy('id', 'desc')->value('current_balance');

            if (!$data) throw new Exception('Sale Receipt not found', 404);
            return view('invoice.sale-receipt', compact('data','current_balance'));
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
