<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Lot;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\InventoryDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

class InventoryDetailController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = InventoryDetail::with(['product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])->join('lots', 'inventory_details.lot_id', '=', 'lots.id') // Join with vendors
            ->select('inventory_details.*', 'lots.lot_code')->orderBy('inventory_details.id', 'desc');
            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('lots.lot_code', 'like', '%' . $searchQuery . '%')
                      ->orWhereHas('product', function ($q) use ($searchQuery) {
                          $q->where('title', 'like', '%' . $searchQuery . '%');
                      });
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
                if (!$user->hasBusinessPermission($businessId, 'view inventory details')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = InventoryDetail::with(['product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
            ->where('id', $id) // Filter by the specific purchase order ID
            ->firstOrFail();
            return response()->json($data,200);
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


    public function inventoryProduct(): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list inventory detail')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = InventoryDetail::join('lots', 'inventory_details.lot_id', '=', 'lots.id')
            ->join('products', 'inventory_details.product_id', '=', 'products.id')
            ->select(
                'products.id as product_id',
                'products.title',
                DB::raw('SUM(lots.quantity) as quantity'),
                'lots.status'
            )
            ->groupBy('inventory_details.product_id', 'products.title', 'products.id', 'lots.status')
            ->orderBy('inventory_details.id', 'desc')->get();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get the resource from storage by specified Lot id.
     */
    public function lotIndex(string $product_id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view inventory details')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Lot::where('product_id',$product_id)->get();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
