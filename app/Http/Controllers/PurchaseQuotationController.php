<?php

namespace App\Http\Controllers;

use App\Models\PurchaseQuotationItem;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\PurchaseQuotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class PurchaseQuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list purchase quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseQuotation::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->join('vendors', 'purchase_quotations.vendor_id', '=', 'vendors.id') // Join with vendors
            ->select('purchase_quotations.*', 'vendors.name as vendor_name') // Select fields including vendor name
            ->orderBy('purchase_quotations.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('quotation_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
            $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validate = Validator::make(
                $request->all(),[
                    'order_date'=>'required|date',
                    'due_date'=> 'required|date',
                    'vendor_id'=>'required|exists:vendors,id',
                    'products' => 'required|array',

            ],[
                'order_date.required'=>'Quotation date is required',
                'due_date.required'=> 'Due date is required'
            ]
            );
            if ($validate->fails()) throw new Exception($validate->errors()->first(), 400);
            $orderCode = 'PQ-'.uniqid();
            $quotation = PurchaseQuotation::create([
                'order_date'=>$request->order_date,
                'due_date'=>$request->due_date,
                'quotation_code'=>$orderCode,
                'vendor_id'=>$request->vendor_id,
                'business_id'=>$businessId
            ]);
            foreach ($request->products as $product) {
                purchaseQuotationItem::create([
                    'purchase_quotation_id' => $quotation->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                ]);
            }
            $quotation->refresh();
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create purchase quotation',
            ]);
            return response()->json($quotation);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
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
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'view purchase quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseQuotation::with(['items.product' => function ($query) {
                $query->select('id', 'title','image','purchase_price','sale_price','sales_tax_rate'); // Select product name and id
            }])
            ->join('vendors', 'purchase_quotations.vendor_id', '=', 'vendors.id') // Join with the vendors table
            ->select('purchase_quotations.*', 'vendors.name as vendor_name') // Select fields including vendor name
            ->where('purchase_quotations.id', $id) // Filter by the specific purchase order ID
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
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'edit purchase quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'terms_of_payment' => 'nullable|string',
                    'remarks' => 'nullable|string',
                    'products' => 'required|array',    

            ],[

                'order_date.required' => 'Order date is required.',
                'due_date.required' => 'Due date is required.',
                'products.required' => 'Items are required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $data = PurchaseQuotation::find($id);
            if (empty($data)) throw new Exception('No PO found', 404);
            $data->update([
                'quotation_date'=>$request->quotation_date,
                'due_date'=>$request->due_date,
                'vendor_id'=>$request->vendor_id,
            ]);
            $existingItems = PurchaseQuotationItem::where('purchase_quotation_id', $id)->get()->keyBy('id');
            $requestItemIds = [];
            foreach ($request->products as $item) {
                if (isset($item['id']) && isset($existingItems[$item['id']])) {
                    // Update existing item
                    $existingItems[$item['id']]->update([
                        'quantity' => $item['quantity']
                    ]);
                    $requestItemIds[] = $item['id'];  // Keep track of updated items
                } else {
                    // Create new item
                    PurchaseQuotationItem::create([
                        'purchase_quotation_id' => $id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity']
                    ]);
                }
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            PurchaseQuotationItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Quotation',   
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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase quotation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseQuotation::find($id);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Quotation Status',   
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
}
