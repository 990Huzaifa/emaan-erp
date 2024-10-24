<?php

namespace App\Http\Controllers;

use App\Models\purchaseQuotationItem;
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
                if (!$user->hasBusinessPermission($businessId, 'list quotation')) {
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
    public function store(Request $request)
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create quotation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validate = Validator::make(
                $request->all(),[
                    'quotation_date'=>'required|date',
                    'vendor_id'=>'required|exists:vendors,id',
                    'products' => 'required|array',

            ],[
                'quotation_date.required'=>'Quotation date is required',
            ]
            );
            if ($validate->fails()) throw new Exception($validate->errors()->first(), 400);
            $orderCode = 'PQ-'.uniqid();
            $quotation = PurchaseQuotation::create([
                'quotation_date'=>$request->quotation_date,
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
                if (!$user->hasBusinessPermission($businessId, 'view quotation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $quotation = PurchaseQuotation::where('business_id', $businessId)->where('id', $id)->first();
            if (!$quotation) {
                return response()->json([
                    'error' => 'Quotation not found'
                ], 404);
            }
            return response()->json($quotation);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
