<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use Exception;
use App\Models\Log;
use App\Models\DeliveryNote;
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
            if ($user->role == 'user') {
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
            if ($user->role == 'user') {
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
            DB::beginTransaction();
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
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
                    'lot_id' => $item['lot_id'],
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
    public function show(string $id)
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view delivery notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $deliveryNote = DeliveryNote::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->where('id', $id) // Filter by the specific purchase order ID
            ->firstOrFail();
            return response()->json($deliveryNote,200);

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
        //
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
            $data = DeliveryNote::find($id);
            DB::beginTransaction();
            if (empty($data)) throw new Exception('DN not found', 400);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);
            // transaction start
            $customer = Customer::find($data->sale_orde->customer_id);
            $customer_acc = $customer->acc_id;
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
                    'description' => 'Item is purchased by this customer: '.$customer->name,
                    'credit' => 0.00,
                    'debit' => $item->billed
                ]);
            }
            // for vendor
            Transaction::create([
                'business_id' => $data->business_id,
                'acc_id'=>$customer_acc,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'purchase item from this vendor: '.$customer->name,
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
}
