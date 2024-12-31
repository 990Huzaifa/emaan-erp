<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiveNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVoucher;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\Lot;
use App\Models\Log;
use App\Models\InventoryDetail;
use App\Models\Vendor;
use App\Models\OpeningBalance;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseVoucherController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseVoucher::select('purchase_vouchers.*','goods_receive_notes.grn_code','chart_of_accounts.name as acc_name')
            ->join('goods_receive_notes','purchase_vouchers.grn_id', '=', 'goods_receive_notes.id')
            ->join('chart_of_accounts','purchase_vouchers.acc_id', '=', 'chart_of_accounts.id')
            ->where('purchase_vouchers.business_id',$businessId)
            ->orderBy('id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where(function ($query) use ($searchQuery) {
                    $query->where('purchase_vouchers.voucher_code', 'like', '%' . $searchQuery . '%')
                          ->orWhere('goods_receive_notes.grn_code', 'like', '%' . $searchQuery . '%');
                });
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
                if (!$user->hasBusinessPermission($businessId, 'create purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'grn_id' => 'required|exists:goods_receive_notes,id',
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'voucher_date' => 'required|date',
                    'voucher_amount' => 'required|numeric',
                ],[
                    'grn_id.required' => 'The goods receive note field is required.',
                    'grn_id.exists' => 'The selected goods receive note is invalid.',
                    
                    'acc_id.required' => 'The Account field is required.',
                    'acc_id.exists' => 'The selected account is invalid.',

                    'voucher_date.required' => 'The voucher date field is required.',
                    'voucher_date.date' => 'The voucher date must be a valid date.',

                    'voucher_amount.required' => 'The voucher amount field is required.',
                    'voucher_amount.numeric' => 'The voucher amount must be a number.',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first());
            DB::beginTransaction();
            // $PO_ID = GoodsReceiveNote::find($request->grn_id)->value('purchase_order_id');
            // if(empty($PO_ID)) throw new Exception('Purchase order not found', 400);
            // $V_ID = PurchaseOrder::find($PO_ID)->value('voucher_id');
            // if(empty($V_ID)) throw new Exception('Vendor not found', 400);
            do {
                $voucher_code = 'PV-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseVoucher::where('voucher_code', $voucher_code)->exists());
            $data = PurchaseVoucher::create([
                'grn_id' => $request->grn_id,
                'acc_id' => $request->acc_id,
                'business_id' => $businessId,
                'voucher_code' => $voucher_code, 
                'voucher_date' => $request->voucher_date,
                'voucher_amount' => $request->voucher_amount,
                'status' => 0, // 0 un paid, 1 paid
            ]);
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
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            // $grn_id = $data->grn_id;

            // $previous_data = PurchaseVoucher::where('grn_id', $grn_id)->where('id','<>',$id)->get();

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = PurchaseVoucher::find($id);
            if (empty($data)) throw new Exception('No data found', 400);
            if ($data->status == 1) throw new Exception('Already Paid', 400);
            DB::beginTransaction();
            $data->update([
                'status'=>1
                ]);
            // transaction
            $grn = GoodsReceiveNote::find($data->grn_id);
            $vendor = Vendor::find($grn->purchase_order->vendor_id);
            $vendor_acc = $vendor->acc_id;
            // for products
            $total_billed = $data->voucher_amount;
            $check_pv = PurchaseVoucher::where('grn_id',$data->grn_id)->where('id','<>',$id)->exists();

            if(!$check_pv){
                //for products trasaction
                foreach ($grn->items as $item) {
                    $product = Product::find($item->product_id);
                    $product_acc = $product->acc_id;
                    
                    $product_t = Transaction::where('acc_id', $product_acc)->orderBy('id', 'desc')->first();
                    $p_cb = $item->billed;
                    if(empty($product_t)){
                        $p_ob = OpeningBalance::where('acc_id', $product_acc)->value('amount');
                        $p_cb += $p_ob;
                    }else{
                        $p_cb +=$product_t->current_balance;
                    }
                    Transaction::create([
                        'business_id' => $grn->business_id,
                        'acc_id'=>$product_acc,
                        'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                        'description' => 'Item is purchased by this vendor: '.$vendor->name,
                        'debit' => $item->billed,
                        'credit' => 0.00,
                        'current_balance' => $p_cb
                    ]);
                    
                    //lot entry
                    do {
                        $lot_code = 'LOT-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (Lot::where('lot_code', $lot_code)->exists());
                    $lot = Lot::create([
                        'product_id' => $item->product_id,
                        'lot_code' => $lot_code,
                        'vendor_id' => $grn->purchase_order->vendor_id,
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
            // for vendor trasaction
            $vendor_t = Transaction::where('acc_id', $vendor_acc)->orderBy('id', 'desc')->first();
            $v_cb = $total_billed;
            
            // Check if any transaction exists for this vendor account
            if (empty($vendor_t)) {
                // No prior transactions, get opening balance
                $v_ob = OpeningBalance::where('acc_id', $vendor_acc)->value('amount');
                $v_cb += $v_ob; // Add opening balance to the total billed
            } else {
                // Prior transaction exists, add total billed to the last current balance
                $v_cb += $vendor_t->current_balance;
            }
            Transaction::create([
                'business_id' => $grn->business_id,
                'acc_id'=>$vendor_acc,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'purchase item from this vendor: '.$vendor->name,
                'debit' => 0.00,
                'credit' => $total_billed,
                'current_balance' => $v_cb
            ]);
            
            $account = OpeningBalance::where('acc_id',$data->acc_id)->first();
            $account->update([
                'amount' => $account->amount - $data->voucher_amount,
                ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Voucher status change to PAID and trnsaction done successfully.',   
            ]);
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

    public function previousData(Request $request, string $grn_id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = PurchaseVoucher::where('grn_id',$grn_id)->get();

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
