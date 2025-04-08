<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiveNote;
use App\Models\GoodsReceiveNoteItem;
use App\Models\InventoryDetail;
use App\Models\Lot;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Transaction;
use App\Models\Vendor;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
            if ($user->role != 'admin') {
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
                $query->select('id', 'title','measurement_unit_id')
                ->with('measurementUnit:id,name'); // Select product name and id
            }])
            ->where('goods_receive_notes.business_id',$businessId)
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
            if ($user->role != 'admin') {
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
            $n_url ='view-goods-received-note/'.$GRN->id;
            notifyUser($user->id, $businessId,'approve goods received notes', 'New Goods received note created',$n_url);
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
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = GoodsReceiveNote::with(['items.product' => function ($query) use ($id) {
            $query->select('products.id', 'products.title')
                ->when(
                    GoodsReceiveNote::where('id', $id)->value('status') == 1,
                    function ($query) use ($id) {
                        $query->join('lots', function ($join) use ($id) {
                            $join->on('products.id', '=', 'lots.product_id')
                                ->where('lots.grn_id', '=', $id);
                        })
                        ->addSelect('lots.id as lot_id', 'lots.lot_code'); // Include lot information
                    }
                );
        }])
        ->where('id', $id) // Filter by the specific GRN ID
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
                'grn_date' => $request->grn_date,
                'remarks' => $request->remarks,
                'status' => 0,
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
    
    
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
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

            // lot entry
            $vendor = Vendor::find($data->purchase_order->vendor_id);
            $total_amount_grn = 0;
            if($request->status == 1){
                foreach ($data->items as $item) {
                    $product = Product::find($item->product_id);
                    
                    // hit transaction
                    $total_amount_grn += $item->billed;
                    $total_billed = $item->billed;

                    // hit inventory
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
                    $check = InventoryDetail::where('product_id', $item->product_id)->first();
                    if ($check) {
                        $check->update([
                            'stock' => $check->stock + $item->quantity
                        ]);
                    }else{
                        InventoryDetail::create([
                            'product_id' => $item->product_id,
                            'stock' => $item->quantity,
                            'in_stock' => 1,
                        ]);
                    }
                    
                }
                // entry is credit but amount will be debited
                $v_cb = calculateBalance($vendor->acc_id,$total_amount_grn,true);
                // Credit amount to Vendor's account
                $link = $data->purhcase_order_id;
                Transaction::create([
                    'business_id' => $businessId,
                    'acc_id' => $vendor->acc_id,
                    'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                    'description' => 'credit amount to vendor account by GRN with the PO is '. $data->purchase_order->order_code,
                    'link' => $link,
                    'credit' => $total_amount_grn, // Money credited to business account
                    'debit' => 0.00, // No money debited from business account
                    'current_balance' => $v_cb
                ]);


                // create sale receipt 
                $invoiceObj = new PurchaseInvoiceController();
                $invoice = $invoiceObj->createInvoice($id, $businessId);
                if($invoice != true) throw new Exception('Error in creating purchase invoice', 400);


                Log::create([
                    'user_id' => $user->id,
                    'description' => 'update GRN Status to approved',   
                ]);

                $n_url ='view-goods-received-note/'.$id;
                notifyUser($user->id, $businessId,'view goods received notes', 'Goods received note Approved successfully',$n_url);
            }else{
                Log::create([
                    'user_id' => $user->id,
                    'description' => 'update GRN Status to rejected',   
                ]);

                $n_url ='view-goods-received-note/'.$id;
                notifyUser($user->id, $businessId,'view goods received notes', 'Goods received note Rejected',$n_url);
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
    
    public function reverse($id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'reverse goods received notes')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = GoodsReceiveNote::find($id);
            DB::beginTransaction();
            if (empty($data)) throw new Exception('Goods Receive Note not found', 400);
            if($data->status != 1) throw new Exception('status can not be reversed', 400);
            

            // reversal start
            $vendor = Vendor::find($data->purchase_order->vendor_id);
            $total_amount_grn = 0;
            foreach ($data->items as $item) {
                $lot = Lot::where('product_id', $item->product_id)
                ->where('grn_id', $id)->first();

                $lot->update([
                    'quantity' => $lot->quantity + $item->quantity,
                    'total_price' => $lot->quantity  * $lot->purchase_unit_price,
                ]);
                $check = InventoryDetail::where('product_id', $item->product_id)->first();
                
                $check->update([
                    'stock' => $check->stock + $item->quantity
                ]);
                $total_amount_grn += $item->billed;
            }

            // reveresd transaction
            // entry is debit but amount will be credit
            $v_cb = calculateBalance($vendor->acc_id,$total_amount_grn,false);
            // Debit amount to Vendor's account
            $link = $data->purhcase_order_id;
            Transaction::create([
                'business_id' => $businessId,
                'acc_id' => $vendor->acc_id,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'debit amount to vendor account because of GRN reversal with the PO is '. $data->purchase_order->order_code,
                'link' => $link,
                'credit' => 0.00, // No money debited from business account
                'debit' => $total_amount_grn, // Money credited to business account
                'current_balance' => $v_cb
            ]);
            // status change
            $data->update([
                'status' => 3
            ]);
            // purchase invoice updates
            PurchaseInvoice::where('grn_id', $id)->update([
                'status' => 3
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'update GRN Status to reversed',   
            ]);
            $n_url ='view-goods-received-note/'.$id;
            notifyUser($user->id, $businessId,'view goods received notes', 'Goods received note reversed successfully',$n_url);
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

}
