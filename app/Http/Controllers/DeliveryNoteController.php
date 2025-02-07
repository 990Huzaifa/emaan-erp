<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
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
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.delivered' => 'required|numeric',
                    'items.*.charged' => 'required|numeric',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.lot_id' => 'required|exists:lots,id',
                    'items.*.tax' => 'required|numeric',
                ],[
                    'sale_order_id.required' => 'Sale Order is required.',
                    'sale_order_id.exists' => 'Sale Order does not exist.',

                    'dn_date.required' => 'Delivery Note Date is required.',
                    'dn_date.date' => 'Delivery Note Date must be a valid date.',

                    'remarks.string' => 'Remarks must be a string.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',
                    
                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.delivered.required' => 'Delivered is required.',
                    'items.*.delivered.numeric' => 'Delivered must be a number.',

                    'items.*.charged.required' => 'Charged is required.',
                    'items.*.charged.numeric' => 'Charged must be a number.',

                    'items.*.unit_price.required' => 'Unit Price is required.',
                    'items.*.unit_price.numeric' => 'Unit Price must be a number.',

                    'items.*.total_price.required' => 'Total Price is required.',
                    'items.*.total_price.numeric' => 'Total Price must be a number.',

                    'items.*.lot_id.required' => 'Lot is required.',
                    'items.*.lot_id.exists' => 'Lot does not exist.',

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
            ]);

            foreach($request->items as $item){
                DeliveryNoteItem::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'product_id' => $item['product_id'],
                    'lot_id'=> $item['lot_id'],
                    'quantity' => $item['quantity'],
                    'delivered' => $item['delivered'],
                    'charged' => $item['charged'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'tax' => $item['tax'],
                ]);
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Delivery Note',
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
        try{
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
            $deliveryNote = DeliveryNote::with(['items' => function ($query) {
                $query->with(['product:id,title', 'lot:id,lot_code']); // Include product and lot details
            }])
            ->where('id', $id) // Filter by the specific purchase order ID
            ->first();
            $response = $deliveryNote->toArray();
            foreach ($response['items'] as &$item) {
                if (isset($item['product'])) {
                    $item['product']['lot_id'] = $item['lot']['id'] ?? null;
                    $item['product']['lot_code'] = $item['lot']['lot_code'] ?? null;
                    unset($item['lot']);
                }
            }
    
            return response()->json($response, 200);

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
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.delivered' => 'required|numeric',
                    'items.*.charged' => 'required|numeric',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.lot_id' => 'required|exists:lots,id',
                    'items.*.tax' => 'required|numeric',
                ],[
                    'sale_order_id.required' => 'Sale Order is required.',
                    'sale_order_id.exists' => 'Sale Order does not exist.',

                    'dn_date.required' => 'Delivery Note Date is required.',
                    'dn_date.date' => 'Delivery Note Date must be a valid date.',

                    'remarks.string' => 'Remarks must be a string.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',

                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.delivered.required' => 'Delivered is required.',
                    'items.*.delivered.numeric' => 'Delivered must be a number.',

                    'items.*.charged.required' => 'Charged is required.',
                    'items.*.charged.numeric' => 'Charged must be a number.',

                    'items.*.unit_price.required' => 'Unit Price is required.',
                    'items.*.unit_price.numeric' => 'Unit Price must be a number.',

                    'items.*.total_price.required' => 'Total Price is required.',
                    'items.*.total_price.numeric' => 'Total Price must be a number.',

                    'items.*.lot_id.required' => 'Lot is required.',
                    'items.*.lot_id.exists' => 'Lot does not exist.',

                    'items.*.tax.required' => 'Tax is required.',
                    'items.*.tax.numeric' => 'Tax must be a number.',
                ]
            );
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();

            $deliveryNote = DeliveryNote::find($id);
            $deliveryNote->update([
                'sale_order_id' => $request->sale_order_id,
                'business_id' => $businessId,
                'dn_date' => $request->dn_date,
                'received_by' => $user->id, //need to know
                'remarks' => $request->remarks,
            ]);

            foreach($request->items as $item){
                if (isset($item['id'])) {
                    $deliveryNoteItem = DeliveryNoteItem::find($item['id']);
                    $deliveryNoteItem->update([
                        'product_id' => $item['product_id'],
                        'lot_id' => $item['lot_id'],
                        'quantity' => $item['quantity'],
                        'delivered' => $item['delivered'],
                        'charged' => $item['charged'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);

                }else{
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'product_id' => $item['product_id'],
                        'lot_id' => $item['lot_id'],
                        'quantity' => $item['quantity'],
                        'delivered' => $item['delivered'],
                        'charged' => $item['charged'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
                
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Delivery Note',
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
                foreach ($data->items as $item) {
                    // hitting inventory minus in quantity
                    $inventory_details = InventoryDetail::where('lot_id', $item->lot_id)->first();
                    $inventory_details->update([
                        'stock' => $inventory_details->stock - $item->quantity,
                    ]);


                    $product = Product::find($item->product_id);
                    $total_amount_dn += $item->charged;
                    $total_charged = $item->charged;
                    $p_cb = calculateBalance($product->acc_id,$total_charged,false);
                    

                    // Debit amount from Product's account
                    Transaction::create([
                        'business_id' => $businessId,
                        'acc_id' => $product->acc_id,
                        'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                        'description' => 'debit amount from product account by DN',
                        'debit' => $total_amount_dn, // FIXED
                        'credit' => 0.00,
                        'current_balance' => $p_cb
                    ]);
                }
            }

            $c_cb = calculateBalance($customer->acc_id,$total_amount_dn,true);
            // Credit amount to Vendor's account
            Transaction::create([
                'business_id' => $businessId,
                'acc_id' => $customer->acc_id,
                'transaction_type' => 1, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'credit amount to vendor account by GRN',
                'debit' => 0.00, // No money debited from business account
                'credit' => $total_amount_dn, // Money credited to business account
                'current_balance' => $c_cb
            ]);

            Log::create([
                'user_id' => $user->id,
                'description' => 'update Delivery Note Status',   
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

}
