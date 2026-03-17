<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lot;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleReceipt;
use App\Models\Transaction;
use Exception;
use App\Models\Log;
use App\Models\DeliveryNote;
use App\Models\InventoryDetail;
use App\Models\DeliveryNoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log as SysLog;

class DeliveryNoteController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = DeliveryNote::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->where('delivery_notes.business_id',$businessId)
            ->join('sale_orders','sale_orders.id','=','delivery_notes.sale_order_id')
            ->join('users', 'users.id', '=', 'delivery_notes.received_by') // Corrected join
            ->select('delivery_notes.*', 'users.name as received_by', 'sale_orders.order_code as so_code')
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
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'sale_order_id' => 'required|exists:sale_orders,id',
                    'dn_date'=>'required|date',
                    'remarks' => 'nullable|string',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'delivery_cost' => 'required|numeric',
                    'total_discount' => 'required|numeric',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.delivered' => 'required|numeric',
                    'items.*.charged' => 'required|numeric',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.discount' => 'required|numeric',
                    'items.*.measurement_unit' => 'required|string',
                    'items.*.discount_in_percentage' => 'required|in:0,1',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.tax' => 'required|numeric',
                ],[
                    'sale_order_id.required' => 'Sale Order is required.',
                    'sale_order_id.exists' => 'Sale Order does not exist.',

                    'dn_date.required' => 'Delivery Note Date is required.',
                    'dn_date.date' => 'Delivery Note Date must be a valid date.',

                    'total.required' => 'Total is required.',
                    'total.numeric' => 'Total must be a number.',

                    'total_tax.required' => 'Total Tax is required.',
                    'total_tax.numeric' => 'Total Tax must be a number.',

                    'delivery_cost.required' => 'Delivery Cost is required.',
                    'delivery_cost.numeric' => 'Delivery Cost must be a number.',

                    'remarks.string' => 'Remarks must be a string.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',

                    'items.*.measurement_unit.required' => 'Measurement Unit is required.',
                    'items.*.measurement_unit.string' => 'Measurement Unit must be a string.',
                    
                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.delivered.required' => 'Delivered is required.',
                    'items.*.delivered.numeric' => 'Delivered must be a number.',

                    'items.*.charged.required' => 'Charged is required.',
                    'items.*.charged.numeric' => 'Charged must be a number.',

                    'items.*.unit_price.required' => 'Unit Price is required.',
                    'items.*.unit_price.numeric' => 'Unit Price must be a number.',

                    'items.*.discount.required' => 'Discount is required.',
                    'items.*.discount.numeric' => 'Discount must be a number.',

                    'items.*.discount_in_percentage.required' => 'Discount in Percentage is required.',
                    'items.*.discount_in_percentage.in' => 'Discount in Percentage must be 0 or 1.',

                    'items.*.total_price.required' => 'Total Price is required.',
                    'items.*.total_price.numeric' => 'Total Price must be a number.',

                    'items.*.tax.required' => 'Tax is required.',
                    'items.*.tax.numeric' => 'Tax must be a number.',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $dn_code = 'DN-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (DeliveryNote::where('dn_code', $dn_code)->exists());
            $deliveryNote = DeliveryNote::create([
                'sale_order_id' => $request->sale_order_id,
                'business_id' => $businessId,
                'dn_code' => $dn_code,
                'dn_date' => $request->dn_date,
                'received_by' => $user->id, //need to know
                'remarks' => $request->remarks,
                'total_tax' => $request->total_tax,
                'delivery_cost' => $request->delivery_cost,
                'total_discount' => $request->total_discount,
                'total' => $request->total,
            ]);

            foreach($request->items as $item){
                DeliveryNoteItem::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'delivered' => $item['delivered'],
                    'charged' => $item['charged'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'tax' => $item['tax'],
                    'total_price' => $item['total_price'],
                ]);
            }
            $n_url ='view-delivery-notes/'.$deliveryNote->id;
            notifyUser($user->id, $businessId,'approve delivery notes', 'New Delivery note created',$n_url);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Delivery Note. Code: '.$dn_code,
            ]);
            DB::commit();
            return response()->json($deliveryNote,200);

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
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = DeliveryNote::with(['items' => function ($query) {
                $query->with('product:id,title')->leftJoin('inventory_details', 'delivery_note_items.product_id', '=', 'inventory_details.product_id')
                ->addSelect('delivery_note_items.*', 'inventory_details.stock as max_quantity');
            }])
            ->join('sale_orders', 'delivery_notes.sale_order_id', '=', 'sale_orders.id') // Join with the customer table
            ->join('customers', 'sale_orders.customer_id', '=', 'customers.id') // Join with the customer table
            ->select('delivery_notes.*', 'customers.name as customer_name') // Select fields including customer name
            ->find($id);
            if (empty($data)) throw new Exception('No DN found', 404);
            return response()->json($data,200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
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
                if (!$user->hasBusinessPermission($businessId, 'create delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'sale_order_id' => 'required|exists:sale_orders,id',
                    'dn_date'=>'required|date',
                    'remarks' => 'nullable|string',
                    'delivery_cost' => 'required|numeric',
                    'total_discount' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'total' => 'required|numeric',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.delivered' => 'required|numeric',
                    'items.*.charged' => 'required|numeric',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.discount' => 'required|numeric',
                    'items.*.measurement_unit' => 'required|string',
                    'items.*.discount_in_percentage' => 'required|numeric|in:0,1',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.tax' => 'required|numeric',
                ],[
                    'sale_order_id.required' => 'Sale Order is required.',
                    'sale_order_id.exists' => 'Sale Order does not exist.',

                    'dn_date.required' => 'Delivery Note Date is required.',
                    'dn_date.date' => 'Delivery Note Date must be a valid date.',

                    'delivery_cost.required' => 'Delivery Cost is required.',
                    'delivery_cost.numeric' => 'Delivery Cost must be a number.',

                    'total_discount.required' => 'Total Discount is required.',
                    'total_discount.numeric' => 'Total Discount must be a number.',

                    'total_tax.required' => 'Total Tax is required.',
                    'total_tax.numeric' => 'Total Tax must be a number.',

                    'total.required' => 'Total is required.',
                    'total.numeric' => 'Total must be a number.',

                    'remarks.string' => 'Remarks must be a string.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',

                    'items.*.measurement_unit.required' => 'Measurement Unit is required.',
                    'items.*.measurement_unit.string' => 'Measurement Unit must be a string.',

                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.delivered.required' => 'Delivered is required.',
                    'items.*.delivered.numeric' => 'Delivered must be a number.',

                    'items.*.charged.required' => 'Charged is required.',
                    'items.*.charged.numeric' => 'Charged must be a number.',

                    'items.*.unit_price.required' => 'Unit Price is required.',
                    'items.*.unit_price.numeric' => 'Unit Price must be a number.',

                    'items.*.discount.required' => 'Discount is required.',
                    'items.*.discount.numeric' => 'Discount must be a number.',

                    'items.*.discount_in_percentage.required' => 'Discount in Percentage is required.',
                    'items.*.discount_in_percentage.numeric' => 'Discount in Percentage must be a number.',
                    'items.*.discount_in_percentage.in' => 'Discount in Percentage must be 0 or 1.',

                    'items.*.total_price.required' => 'Total Price is required.',
                    'items.*.total_price.numeric' => 'Total Price must be a number.',

                    'items.*.tax.required' => 'Tax is required.',
                    'items.*.tax.numeric' => 'Tax must be a number.',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();

            $deliveryNote = DeliveryNote::find($id);
            $deliveryNote->update([
                'business_id' => $businessId,
                'dn_date' => $request->dn_date,
                'received_by' => $user->id, //need to know
                'remarks' => $request->remarks,
                'delivery_cost' => $request->delivery_cost,
                'total_discount' => $request->total_discount,
                'total_tax' => $request->total_tax,
                'total' => $request->total,
                'status' => 0,
            ]);

            foreach($request->items as $item){
                if (isset($item['id'])) {
                    $deliveryNoteItem = DeliveryNoteItem::find($item['id']);
                    $deliveryNoteItem->update([
                        'product_id' => $item['product_id'],
                        'measurement_unit' => $item['measurement_unit'],
                        'quantity' => $item['quantity'],
                        'delivered' => $item['delivered'],
                        'charged' => $item['charged'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'discount' => $item['discount'],
                        'discount_in_percentage' => $item['discount_in_percentage'],
                        'tax' => $item['tax'],
                    ]);

                }else{
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'product_id' => $item['product_id'],
                        'measurement_unit' => $item['measurement_unit'],
                        'quantity' => $item['quantity'],
                        'delivered' => $item['delivered'],
                        'charged' => $item['charged'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item['discount'],
                        'discount_in_percentage' => $item['discount_in_percentage'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
                
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'Updated Delivery Note. Code'. $deliveryNote->dn_code,
            ]);
            DB::commit();
            return response()->json($deliveryNote,200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
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
                if (!$user->hasBusinessPermission($businessId, 'approve delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = DeliveryNote::find($id);
            DB::beginTransaction();
            if (empty($data)) throw new Exception('Delivery Note not found', 400);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);

            // lot Updation
            $customer = Customer::find($data->sale_order->customer_id);
            $total_amount_dn = 0;
            if($request->status == 1){
                // Loop through each item in the delivery note
                foreach ($data->items as $item) {
                    // Fetch available lots in FIFO order
                    // $lots = Lot::where('product_id', $item->product_id)
                    // ->where('quantity', '>', 0)
                    // ->orderBy('created_at', 'asc')
                    // ->get();
    
                    // $remainingQty = $item->quantity; // Total quantity to deduct from lots
                    
                    // // Deduct item quantity from lots one by one (FIFO)
                    // foreach ($lots as $lot) {
                    //     if ($remainingQty <= 0) break; // Stop if we've deducted the required quantity

                    //     $deductQty = min($lot->quantity, $remainingQty); // Take only what we need from this lot
                    //     // Update lot: reduce quantity and total_price based on unit price
                    //     $lot->update([
                    //         'quantity' => $lot->quantity - $deductQty,
                    //         'total_price' => $lot->total_price - ($deductQty * $item->unit_price),
                    //     ]);
                    //     $remainingQty -= $deductQty;
                    // }

                    // Update overall inventory stock for the product
                    $inventory_details = InventoryDetail::where('product_id', $item->product_id)->first();
                    if ($inventory_details) {
                        $inventory_details->update([
                            'stock' => $inventory_details->stock - $item->quantity,
                        ]);
                    }

                    $total_amount_dn += $item->charged;
                }
                
                $c_cb = calculateBalance(
                    $customer->acc_id,
                    $total_amount_dn, // debit
                    0,
                    $data->dn_date
                );
                $link =$data->sale_order_id;
                // Debit amount to customer's account
                Transaction::create([
                    'business_id' => $businessId,
                    'acc_id' => $customer->acc_id,
                    'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => 'SO no: '.$data->sale_order->order_code,
                    'link' => $link,
                    'debit' => $total_amount_dn, // FIXED
                    'credit' => 0.00, // FIXED
                    'current_balance' => $c_cb,
                    'created_at' => $data->dn_date
                ]);

                // create sale receipt 
                $srObj = new SaleReceiptController();
                $sr = $srObj->createSR($id, $businessId);
                if($sr != true) throw new Exception('Error in creating sale receipt', 400);

                Log::create([
                    'user_id' => $user->id,
                    'description' => 'update Delivery Note Status to approved. Code: '. $data->dn_code,   
                ]);
                $n_url ='view-delivery-notes/'.$id;
                notifyUser($user->id, $businessId,'view delivery notes', 'Delivery note Approved successfully',$n_url);
            }else{
                Log::create([
                    'user_id' => $user->id,
                    'description' => 'update Delivery Note Status to rejected. Code: '. $data->dn_code,   
                ]);
                $n_url ='view-delivery-notes/'.$id;
                notifyUser($user->id, $businessId,'view delivery notes', 'Delivery note Rejected',$n_url);
            }
            
            
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

    public function reverse(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'reverse delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = DeliveryNote::find($id);
            DB::beginTransaction();
            if (empty($data)) throw new Exception('Delivery Note not found', 400);
            if($data->status != 1) throw new Exception('status can not be reversed', 400);
            
            // reversal start
            $customer = Customer::find($data->sale_order->customer_id);
            $total_amount_dn = 0;
            foreach ($data->items as $item) {
                // Fetch available lots in FIFO order
                $lot = Lot::where('product_id', $item->product_id)
                ->orderBy('quantity', 'asc')
                ->first();

                $lot->update([
                    'quantity' => $lot->quantity + $item->quantity,
                    'total_price' => $lot->total_price + ($lot->sale_unit_price * $item->quantity)
                ]);
                

                // Update Inventory stock
                $inventory_details = InventoryDetail::where('product_id', $item->product_id)->first();
                if ($inventory_details) {
                    $inventory_details->update([
                        'stock' => $inventory_details->stock + $item->quantity,
                    ]);
                }

                $total_amount_dn += $item->charged;
            }
            $c_cb = calculateBalance($customer->acc_id,$total_amount_dn,false);
            $link =$data->sale_order_id;
            // Debit amount to customer's account
            Transaction::create([
                'business_id' => $businessId,
                'acc_id' => $customer->acc_id,
                'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'credit amount to customer account becasue this transaction reversed by DN with the SO is '.$data->sale_order->order_code,
                'link' => $link,
                'debit' => 0.00, // FIXED
                'credit' => $total_amount_dn, // FIXED
                'current_balance' => $c_cb
            ]);
            // status changes
            $data->update([
                'status' => 3
            ]);

            // sales receipt update
            SaleReceipt::where('dn_id', $id)->update([
                'status' => 3
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'update Delivery Note Status to reversed. Code: '. $data->dn_code,  
            ]);
            $n_url ='view-delivery-notes/'.$id;
            notifyUser($user->id, $businessId,'view delivery notes', 'Delivery note reversed successfully',$n_url);
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = DeliveryNote::select('id','dn_code')->where('status',1)->where('business_id',$businessId)->get();
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }    
    }

    public function readyDn($soId, $userId)
    {
        try{
            $so = SaleOrder::find($soId);
            if(empty($so)) throw new Exception('Sale order not found', 400);
            DB::beginTransaction();
            do {
                $dn_code = 'DN-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (DeliveryNote::where('dn_code', $dn_code)->exists());
            $deliveryNote = DeliveryNote::create([
                'sale_order_id' => $soId,
                'business_id' => $so->business_id,
                'dn_code' => $dn_code,
                'dn_date' => $so->order_date,
                'received_by' => $userId, //need to know
                'remarks' => $so->remarks,
                'terms_of_payment' => $so->terms_of_payment,
                'total_tax' => $so->total_tax,
                'delivery_cost' => $so->delivery_cost,
                'total_discount' => $so->total_discount,
                'total' => $so->total,
                'status' => 1,
            ]);
            $soItems = SaleOrderItem::where('sale_order_id', $soId)->get();
            foreach($soItems as $item){
                DeliveryNoteItem::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'product_id' => $item->product_id,
                    'measurement_unit' => $item->measurement_unit,
                    'quantity' => $item->quantity,
                    'delivered' => $item->quantity,
                    'charged' => $item->unit_price * $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'discount_in_percentage' => $item->discount_in_percentage,
                    'tax' => $item->tax,
                    'total_price' => $item->total_price,
                ]);
            }

            $customer = Customer::find($so->customer_id);
            $total_amount_dn = 0;

            foreach ($deliveryNote->items as $item) {
                    // Fetch available lots in FIFO order
                    // $lots = Lot::where('product_id', $item->product_id)
                    // ->where('quantity', '>', 0)
                    // ->orderBy('created_at', 'asc')
                    // ->get();
    
                    // $remainingQty = $item->quantity; // Total quantity to deduct from lots
                    
                    // // Deduct item quantity from lots one by one (FIFO)
                    // foreach ($lots as $lot) {
                    //     if ($remainingQty <= 0) break; // Stop if we've deducted the required quantity

                    //     $deductQty = min($lot->quantity, $remainingQty); // Take only what we need from this lot
                    //     // Update lot: reduce quantity and total_price based on unit price
                    //     $lot->update([
                    //         'quantity' => $lot->quantity - $deductQty,
                    //         'total_price' => $lot->total_price - ($deductQty * $item->unit_price),
                    //     ]);
                    //     $remainingQty -= $deductQty;
                    // }

                    // Update overall inventory stock for the product
                    $inventory_details = InventoryDetail::where('product_id', $item->product_id)->first();
                    if ($inventory_details) {
                        $inventory_details->update([
                            'stock' => $inventory_details->stock - $item->quantity,
                        ]);
                    }

                    $total_amount_dn += $item->charged;
                }
                
                $c_cb = calculateBalance($customer->acc_id,$total_amount_dn,true);
                $link =$soId;
                // Debit amount to customer's account
                Transaction::create([
                    'business_id' => $so->business_id,
                    'acc_id' => $customer->acc_id,
                    'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => 'SO no: '.$so->order_code,
                    'link' => $link,
                    'debit' => $total_amount_dn, // FIXED
                    'credit' => 0.00, // FIXED
                    'current_balance' => $c_cb
                ]);

                // create sale receipt 
                $srObj = new SaleReceiptController();
                $sr = $srObj->createSR($deliveryNote->id, $so->business_id);
                if($sr != true) throw new Exception('Error in creating sale invoice', 400);

                Log::create([
                    'user_id' => $so->user_id,
                    'description' => 'update Delivery Note Status to approved. Code: '. $deliveryNote->dn_code,   
                ]);
                $n_url ='view-delivery-notes/'.$deliveryNote->id;
                notifyUser($so->user_id, $so->business_id,'view delivery notes', 'Delivery note Approved successfully',$n_url);
            
            
            DB::commit();
            return true;
        }catch(QueryException $e){
            DB::rollBack();
            SysLog::error('Error creating DN: ' . $e->getMessage());          
        }catch(Exception $e){
            DB::rollBack();
            SysLog::error('Error creating DN: ' . $e->getMessage());
        }
    }

}
