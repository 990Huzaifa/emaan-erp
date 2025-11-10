<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\Vendor;
use DB;
use Exception;
use App\Models\Log;
use App\Models\GoodsReceiveNote;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class PurchaseInvoiceController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseInvoice::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('goods_receive_notes', 'purchase_invoices.grn_id', '=', 'goods_receive_notes.id')
            ->join('vendors', 'purchase_invoices.vendor_id', '=', 'vendors.id') // Join with vendors
            ->select('purchase_invoices.*', 'vendors.name as vendor_name','goods_receive_notes.grn_code')
            ->where('purchase_invoices.business_id',$businessId)
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
                if (!$user->hasBusinessPermission($businessId, 'create purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'grn_id'=>'required|exists:goods_receive_notes,id',
                ],[
                'grn_id.required' => 'The grn_id is required.',
                'grn_id.exists' => 'The grn_id is invalid.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $invoice_no = 'PI-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseInvoice::where('invoice_no', $invoice_no)->exists());

            $GRN = GoodsReceiveNote::with('items')->where('status',1)->find($request->grn_id);
            if (!$GRN) throw new Exception('Goods Receive Note is not approved yet.', 400);

            $POID = $GRN->purchase_order_id;
            $PO = PurchaseOrder::find($POID);
            if (!$PO) throw new Exception('Purchase Order not found.', 404);
            DB::beginTransaction();
            $purchaseInvoice = PurchaseInvoice::create([
                'invoice_no' => $invoice_no,
                'invoice_date' => $request->invoice_date,
                'business_id' => $user->login_business,
                'grn_id' => $request->grn_id,
                'vendor_id' => $PO->vendor_id,
                'po_no' => $PO->order_code,
                'terms_of_payment' => $GRN->terms_of_payment,
                'remarks' => $GRN->remarks,
                'total' => $GRN->total,
                'total_discount' => $GRN->total_discount,
                'total_tax' => $GRN->total_tax,
                'delivery_cost' => $GRN->delivery_cost,
                'status' => $request->status ?? 0
            ]);

            $invoiceTotal = 0;
            // Map GRN items to PI items
            foreach ($GRN->items as $item) {
                $invoiceTotal += $item->total_price;
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $purchaseInvoice->id,
                    'product_id' => $item->product_id,
                    'measurement_unit' => $item->measurement_unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->purchase_unit_price,
                    'total' => $item->total_price,
                    'discount_in_percentage' => $item->discount_in_percentage,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                ]);
            }
            $purchaseInvoice->update([
                'total'=> $invoiceTotal
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Purchase Invoice created successfully code: '. $purchaseInvoice->invoice_no,
            ]);
            DB::commit();
            return response()->json($purchaseInvoice, 200);
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
                if (!$user->hasBusinessPermission($businessId, 'view purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseInvoice::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('businesses', 'purchase_invoices.business_id', '=', 'businesses.id')
            ->join('vendors', 'purchase_invoices.vendor_id', '=', 'vendors.id') // Join with vendors
            ->join('cities', 'vendors.city_id', '=', 'cities.id')
            ->select('purchase_invoices.*',
            'vendors.name as vendor_name',
            'vendors.address as vendor_address',
            'vendors.phone as vendor_phone',
            'businesses.name as business_name',
            'cities.name as city_name'
            ) // Select fields including vendor name
            ->where('purchase_invoices.id', $id)->first();
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
    public function update(Request $request, string $id)
    {
        //
    }
    
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseInvoice::find($id);
            // 0 = Pending, 1 = Approved, 2 = Rejected
            if (empty($data)) throw new Exception('Purchase Invoice not found', 400);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);

            // hit transaction to the vendor account now. in sum and Temporary Liability in minus
            $total_amount_invoce  = $data->total;
            $grir_acc_id = ChartOfAccount::where('code','2-2')->value('id');
            $grir_cb = calculateDebitBalance($grir_acc_id, $total_amount_invoce);
            // Debit amount to TL's account
            $link = $data->id;
            Transaction::create([
                'business_id' => $businessId,
                'acc_id' => $grir_acc_id,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Debit GR/IR Clearing for temporary liability on Invoice: ' . $data->id,
                'link' => $link,
                'credit' => 0.00, // Money credited to business account
                'debit' => $total_amount_invoce, // No money debited from business account
                'current_balance' => $grir_cb
            ]);

            // Credit amount to vendor account

            $v_cb = calculateCreditBalance($data->vendor_id, $total_amount_invoce);

            Transaction::create([
                'business_id' => $businessId,
                'acc_id' => $data->vendor_id,
                'transaction_type' => 0, // 0->purchase, 1->sale, 2->expense, 3->income
                'description' => 'Credit Vendor for book liability on Invoice: ' . $data->id,
                'link' => $link,
                'credit' => $total_amount_invoce, // Money credited to business account
                'debit' => 0.00, // No money debited from business account
                'current_balance' => $v_cb
            ]);


            Log::create([
                'user_id' => $user->id,
                'description' => 'Update purchase invoice Status code: '. $data->invoice_no,   
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
    public function destroy(string $id)
    {
        //
    }
    
    public function list(): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = PurchaseInvoice::select('id','invoice_no')->where('status',1)->where('business_id',$businessId)->get();
            return response()->json($data);
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
                if (!$user->hasBusinessPermission($businessId, 'view purchase invoice')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
    
            $data = PurchaseInvoice::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('businesses', 'purchase_invoices.business_id', '=', 'businesses.id')
            ->join('vendors', 'purchase_invoices.vendor_id', '=', 'vendors.id')
            ->join('cities', 'vendors.city_id', '=', 'cities.id')
            ->select(
                'purchase_invoices.*',
                'vendors.name as vendor_name',
                'vendors.address as vendor_address',
                'vendors.phone as vendor_phone',
                'businesses.name as business_name',
                'businesses.logo as business_logo',
                'cities.name as vendor_city'
            )
            ->where('purchase_invoices.id', $id)->first();
    
            $acc_id = Vendor::where('id',$data->vendor_id)->value('acc_id');
            
            $current_balance = Transaction::where('acc_id', $acc_id)
            ->orderBy('id', 'desc')->value('current_balance');

            if (!$data) throw new Exception('Sale Receipt not found', 404);
            return view('invoice.purchase-invoice', compact('data','current_balance'));

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function createInvoice($id, $businessId)
    {
        try{
            do {
                $invoice_no = 'PI-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseInvoice::where('invoice_no', $invoice_no)->exists());

            $GRN = GoodsReceiveNote::with('items')->where('status',1)->find($id);
            if (!$GRN) throw new Exception('Goods Receive Note is not approved yet.', 400);

            $POID = $GRN->purchase_order_id;
            $PO = PurchaseOrder::find($POID);
            if (!$PO) throw new Exception('Purchase Order not found.', 404);
            DB::beginTransaction();
            $purchaseInvoice = PurchaseInvoice::create([
                'vendor_id' => $PO->vendor_id,
                'po_no' => $PO->order_code,
                'delivery_cost' => $GRN->delivery_cost,
                'invoice_no' => $invoice_no,
                'invoice_date' => date('Y-m-d'),
                'business_id' => $businessId,
                'grn_id' => $id,
                'terms_of_payment' => $GRN->terms_of_payment,
                'remarks' => $GRN->remarks,
                'total' => $GRN->total,
                'total_discount' => $GRN->total_discount,
                'total_tax' => $GRN->total_tax

            ]);
            $invoiceTotal = 0;
            // Map GRN items to PI items
            foreach ($GRN->items as $item) {
                $invoiceTotal += $item->total_price;
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $purchaseInvoice->id,
                    'product_id' => $item->product_id,
                    'measurement_unit' => $item->measurement_unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->purchase_unit_price,
                    'total' => $item->total_price,
                    'discount_in_percentage' => $item->discount_in_percentage,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                ]);
            }

            DB::commit();
            return true;
        }catch(QueryException $e){
            DB::rollBack();
            return false;
        }catch(Exception $e){
            DB::rollBack();
            return false;
        }
    }
}
