<?php

namespace App\Http\Controllers\Sale;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DeliveryNote;
use App\Models\Log;
use App\Models\SaleReceipt;
use App\Models\SaleReceiptItem;
use App\Models\SaleOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;


class SaleReceiptController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = SaleReceipt::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('delivery_notes', 'sale_receipts.dn_id', '=', 'delivery_notes.id')
            ->join('customers', 'sale_receipts.customer_id', '=', 'customers.id') // Join with vendors
            ->select('sale_receipts.*', 'customers.name as customer_name','delivery_notes.dn_code')
            ->where('sale_receipts.business_id',$businessId)
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
                if (!$user->hasBusinessPermission($businessId, 'create sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'dn_id'=>'required|exists:delivery_notes,id',
                    'receipt_date'=> 'required',
                ],[
                'dn_id.required' => 'The dnn_id is required.',
                'dn_id.exists' => 'The dn_id is invalid.',

                'receipt_date.required' => 'Receipt Date is required.'
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            do {
                $receipt_no = 'SR-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleReceipt::where('receipt_no', $receipt_no)->exists());

            $DN = DeliveryNote::with('items')->where('status',1)->find($request->dn_id);
            if (!$DN) throw new Exception('Delivery Note is not approved yet.', 400);

            $SOID = $DN->sale_order_id;
            $SO = SaleOrder::find($SOID);
            if (!$SO) throw new Exception('Sale Order not found.', 404);

            DB::beginTransaction();

            $saleReceipt = SaleReceipt::create([
                'customer_id' => $SO->customer_id,
                'so_no' => $SO->order_code,
                'dn_id' => $request->dn_id,
                'business_id' => $businessId,
                'receipt_no' => $receipt_no,
                'receipt_date' => $request->receipt_date,
                'delivery_cost'=> $DN->delivery_cost,
                'total_discount'=> $DN->total_discount,
                'total_tax'=> $DN->total_tax,
                'total'=> $DN->total,
            ]);
            // Map DN items to PI items
            foreach ($DN->items as $item) {
                SaleReceiptItem::create([
                    'sale_receipt_id' => $saleReceipt->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'discount_in_percentage' => $item->discount_in_percentage,
                    'total' => $item->total_price,
                    'tax' => $item->tax,
                ]);
            }

            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Sale Receipt. Code:'. $receipt_no,
            ]);
            DB::commit();
            return response()->json($saleReceipt, 200);
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
                if (!$user->hasBusinessPermission($businessId, 'view sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleReceipt::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('businesses', 'sale_receipts.business_id', '=', 'businesses.id')
            ->join('customers', 'sale_receipts.customer_id', '=', 'customers.id') // Join with vendors
            ->join('cities', 'customers.city_id', '=', 'cities.id')
            ->select('sale_receipts.*',
            'customers.name as customer_name',
            'customers.address as customer_address',
            'customers.telephone as customer_telephone',
            'businesses.name as business_name',
            'cities.name as city_name'
            ) // Select fields including vendor name
            ->where('sale_receipts.id', $id)->first();
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
                if (!$user->hasBusinessPermission($businessId, 'approve sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleReceipt::find($id);
            // 0 = Pending, 1 = Approved, 2 = Rejected
            if (empty($data)) throw new Exception('Sale Receipt not found', 400);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'update sale receipt Status. Code: '. $data->receipt_no,   
            ]);
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
                if (!$user->hasBusinessPermission($businessId, 'view sale receipt')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $data = SaleReceipt::with(['items.product' => function ($query) {
                $query->select('id', 'title');
            }])
            ->join('businesses', 'sale_receipts.business_id', '=', 'businesses.id')
            ->join('customers', 'sale_receipts.customer_id', '=', 'customers.id') // Join with vendors
            ->join('cities as customer_city', 'customers.city_id', '=', 'customer_city.id')
            ->join('cities as business_city', 'businesses.city_id', '=', 'business_city.id')
            ->select('sale_receipts.*',
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
            ->where('sale_receipts.id', $id)->first();
            
            if (!$data) throw new Exception('Sale Receipt not found', 404);

            return view('invoice.sale-receipt', compact('data'));
        } catch (QueryException $e) {
            return response()->json( 'DB error: ' ,400);
        } catch (Exception $e) {
            return response()->json('error' ,400);
        }
    }


    public function createSR($id, $businessId)
    {
        try{

            do {
                $receipt_no = 'SR-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleReceipt::where('receipt_no', $receipt_no)->exists());

            $DN = DeliveryNote::with('items')->where('status',1)->find($id);
            if (!$DN) throw new Exception('Delivery Note is not approved yet.', 400);

            $SOID = $DN->sale_order_id;
            $SO = SaleOrder::find($SOID);
            if (!$SO) throw new Exception('Sale Order not found.', 404);

            DB::beginTransaction();

            $saleReceipt = SaleReceipt::create([
                'customer_id' => $SO->customer_id,
                'so_no' => $SO->order_code,
                'dn_id' => $id,
                'business_id' => $businessId,
                'receipt_no' => $receipt_no,
                'delivery_cost'=> $DN->delivery_cost,
                'total_discount'=> $DN->total_discount,
                'total_tax'=> $DN->total_tax,
                'total'=> $DN->total,
                'receipt_date' => date('Y-m-d'),
            ]);

            // Map DN items to PI items
            foreach ($DN->items as $item) {
                SaleReceiptItem::create([
                    'sale_receipt_id' => $saleReceipt->id,
                    'product_id' => $item->product_id,
                    'measurement_unit' => $item->measurement_unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_in_percentage' => $item->discount_in_percentage,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                    'total' => $item->total_price,
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
