<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Exception;
use App\Models\Lot;
use Illuminate\Http\Request;
use App\Models\InventoryDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list inventory detail')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = InventoryDetail::join('lots', 'inventory_details.lot_id', '=', 'lots.id')
            ->join('products', 'inventory_details.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.title',
                DB::raw('SUM(inventory_details.stock) as total_quantity'),
                'lots.status'
            )
            ->groupBy('inventory_details.product_id', 'products.title', 'products.id', 'lots.status')
            ->orderBy('inventory_details.id', 'desc');

            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('lots.lot_code', 'like', '%' . $searchQuery . '%')
                      ->orWhere('products.title', 'like', '%' . $searchQuery . '%');
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
     * Display the specified resource.
     */

    // public function show(string $id): JsonResponse
    // {
    //     try{
    //         $user = Auth::user();
            
    //         // Check if the user has the required permission
    //         if ($user->role != 'admin') {
    //             $businessId = $user->login_business;
    //             if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
    //                 return response()->json([
    //                     'error' => 'User does not have the required permission.'
    //                 ], 403);
    //             }
    //         }
    //         $data = Lot::select('lots.*','products.title','products.image','vendors.name as vendor_name','purchase_orders.order_code as purchase_order_code','goods_receive_notes.grn_code as grn_code','inventory_details.stock as quantity')
    //         ->where('lots.product_id', $id)
    //         ->join('inventory_details','inventory_details.lot_id','=','lots.id')
    //         ->join('products', 'lots.product_id', '=', 'products.id')
    //         ->join('vendors', 'lots.vendor_id', '=', 'vendors.id')
    //         ->join('purchase_orders', 'lots.purchase_order_id', '=', 'purchase_orders.id')
    //         ->join('goods_receive_notes', 'lots.grn_id', '=', 'goods_receive_notes.id')

    //         ->get();
    //         return response()->json($data,200);
    //     }catch(QueryException $e){
    //         return response()->json(['DB error' => $e->getMessage()], 400);
    //     }catch(Exception $e){
    //         return response()->json(['error' => $e->getMessage()], 400);
    //     }
    // }


    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Fetch product details (common for all lots)
            $productDetail = Product::select('id', 'title', 'image')
                ->where('id', $id)
                ->first();

            if (!$productDetail) {
                return response()->json(['error' => 'Product not found'], 404);
            }
            $searchQuery = $request->query('search');
            // Fetch paginated lot list with search filter on lot_code
            $query = Lot::select(
                    'lots.*',
                    'vendors.name as vendor_name',
                    'purchase_orders.order_code as purchase_order_code',
                    'goods_receive_notes.grn_code as grn_code',
                    'inventory_details.stock as quantity'
                )
                ->where('lots.product_id', $id)
                ->join('inventory_details', 'inventory_details.lot_id', '=', 'lots.id')
                ->join('vendors', 'lots.vendor_id', '=', 'vendors.id')
                ->join('purchase_orders', 'lots.purchase_order_id', '=', 'purchase_orders.id')
                ->join('goods_receive_notes', 'lots.grn_id', '=', 'goods_receive_notes.id');

            // Apply search filter if 'lot_code' parameter exists
            if (!empty($searchQuery)) {
                $query->where('lots.lot_code', 'LIKE', '%' . $searchQuery . '%');
            }

            // Paginate the result
            $lots = $query->paginate(10);

            return response()->json([
                'product_detail' => $productDetail,
                'lots' => $lots
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
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
            if ($user->role != 'admin') {
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view inventory detail')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Lot::select('lots.*','inventory_details.stock as quantity')
            ->join('inventory_details','inventory_details.lot_id','=','lots.id')
            ->where('inventory_details.product_id',$product_id)
            ->get();
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
     
}
