<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\InventoryDetail;
use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;



class MergeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Validate input data
            $validator = Validator::make(
                $request->all(), [
                    'product_data' => 'required|array',
                    'product_data.*.id' => 'required|string|exists:products,id',
                    'product_data.*.quantity' => 'required|numeric|min:1',
                    'product_data.*.purchase_unit_price' => 'required|numeric|min:0',
                    'resulting_product_data' => 'required|array',
                    'resulting_product_data.id' => 'required|string|exists:products,id',
                    'resulting_product_data.quantity' => 'required|numeric|min:1',
                    'resulting_product_data.purchase_unit_price' => 'required|numeric|min:0',
                    'resulting_product_data.sale_unit_price' => 'required|numeric|min:0',
                ],[
                    'product_data.*.id.required' => 'Product ID is required.',
                    'product_data.*.quantity.required' => 'Quantity is required.',
                    'product_data.*.quantity.min' => 'Quantity must be at least 1.',
                    'product_data.*.purchase_unit_price.required' => 'Purchase Unit Price is required.',
                    'product_data.*.purchase_unit_price.min' => 'Purchase Unit Price must be at least 0.',
                    'resulting_product_data.id.required' => 'Product ID is required.',
                    'resulting_product_data.quantity.required' => 'Quantity is required.',
                    'resulting_product_data.quantity.min' => 'Quantity must be at least 1.',
                    'resulting_product_data.purchase_unit_price.required' => 'Purchase Unit Price is required.',
                    'resulting_product_data.purchase_unit_price.min' => 'Purchase Unit Price must be at least 0.',
                    'resulting_product_data.sale_unit_price.required' => 'Sale Unit Price is required.',
                    'resulting_product_data.sale_unit_price.min' => 'Sale Unit Price must be at least 0.',
                ]
            );

            // Return validation errors if any
            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction(); // Start transaction to ensure atomic operations

            // Step 1: Process all old products (Product A + Product B + ... Product N)
            $oldProducts = [];
            $totalPurchasePrice = 0;
            $totalQuantity = 0;

            foreach ($request->product_data as $productData) {
                $product = Product::find($productData['id']);
                
                // Calculate total purchase price for merged product
                $totalPurchasePrice += $product->purchase_unit_price * $productData['quantity'];
                $totalQuantity += $productData['quantity'];

                // Update inventory by subtracting the quantity of old products
                $inventory = InventoryDetail::where('product_id', $product->id)->first();
                if ($inventory) {
                    // Deduct quantity
                    $inventory->stock -= $productData['quantity'];
                    $inventory->save();
                } else {
                    return response()->json(['error' => 'Product not found in inventory.'], 404);
                }
                
                $oldProducts[] = $product;
            }

            // Step 2: Calculate the new merged product's purchase unit price
            $mergedPurchaseUnitPrice = $totalPurchasePrice / $totalQuantity;

            // Step 3: Add the resultant product (Product C) to the inventory
            $resultingProduct = Product::find($request->resulting_product_data['id']);

            // Step 4: Add Lot entry for the resultant product
            Lot::create([
                'product_id' => $resultingProduct->id,
                'vendor_id' => null, // Assuming no specific vendor for merged product
                'purchase_order_id' => null, // Assuming no specific purchase order for merged product
                'grn_id' => null, // Assuming no specific GRN for merged product
                'lot_code' => 'MERGED-' . strtoupper(uniqid()), // Generate unique lot code
                'quantity' => $request->resulting_product_data['quantity'],
                'purchase_unit_price' => $mergedPurchaseUnitPrice,
                'sale_unit_price' => $request->resulting_product_data['sale_unit_price'],
                'total_purchase_price' => $mergedPurchaseUnitPrice * $request->resulting_product_data['quantity'],
                'source' => 'by_merge',
            ]);
            
            // Calculate the new inventory record for the resultant product
            $inventory = InventoryDetail::where('product_id', $resultingProduct->id)->first();
            if (!$inventory) {
                // Create a new inventory record if not already present
                $inventory = new InventoryDetail();
                $inventory->product_id = $resultingProduct->id;
                $inventory->stock = 0;
            }
            
            // Add the merged quantity of the resultant product
            $inventory->update([
                'stock' => $inventory->stock + $request->resulting_product_data['quantity'],
                'in_stock' => 1
            ]);

            // Commit transaction
            DB::commit();

            // Step 4: Return success response
            return response()->json([
                'message' => 'Products successfully merged and added to inventory.',
                'old_products' => $oldProducts,
                'resulting_product' => $resultingProduct
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
