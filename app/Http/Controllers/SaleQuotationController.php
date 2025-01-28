<?php

namespace App\Http\Controllers;

use App\Models\SaleQuotationItem;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\SaleQuotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class SaleQuotationController extends Controller
{

    /**
     * Display a listing of the resource.
     */

    public function index(Request $request):JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list sale quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $perPage = request()->query('per_page', 10);
            $searchQuery = request()->query('search');
            $query = SaleQuotation::with(['items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }])
                ->join('customers', 'sale_quotations.customer_id', '=', 'customers.id') // Join with customers
                ->select('sale_quotations.*', 'customers.name as customer_name') // Select fields including customer name
                ->where('sale_quotations.business_id', $businessId)
                ->orderBy('sale_quotations.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('quotation_code', 'like', '%' . $searchQuery . '%');
            }

            // Execute the query with pagination
            $data = $query->paginate($perPage);

            return response()->json($data);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
        //
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
                if (!$user->hasBusinessPermission($businessId, 'create sale quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                'customer_id' => 'required|exists:customers,id',
                'order_date'=>'required|date',
                'due_date'=> 'required|date',
                'products.*.product_id' => 'required',
                'products.*.quantity' => 'required',
            ],[
                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Customer does not exist.',
                'order_date.required' => 'Order date is required.',
                'due_date.required' => 'Due date is required.',
                'products.*.product_id.required' => 'Product is required.',
                'products.*.quantity.required' => 'Quantity is required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $quotation_code = 'SQ-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (SaleQuotation::where('quotation_code', $quotation_code)->exists());

            $saleQuotation = SaleQuotation::create([
                'customer_id' => $request->customer_id,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'quotation_code' => $quotation_code,
                'business_id' => $businessId,
            ]);

            foreach ($request->products as $product) {
                SaleQuotationItem::create([
                    'sale_quotation_id' => $saleQuotation->id,
                    'product_id' => $product['product_id'],
                    'lot_id' => $product['lot_id'],
                    'quantity' => $product['quantity'],
                ]);
            }

            Log::create([
                'user_id' => $user->id,
                'description' => 'User saved sale quotation',
            ]);

            DB::commit();
            return response()->json($saleQuotation, 200);

        }catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check user role and permissions
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view sale quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            // Fetch the sale quotation with items and lot codes for each product
            $saleQuotation = SaleQuotation::with(['items' => function ($query) {
                $query->with(['product:id,title', 'lot:id,lot_code']);
            }])
            ->find($id);
    
            if (!$saleQuotation) {
                return response()->json(['error' => 'Sale quotation not found'], 404);
            }
    
            return response()->json($saleQuotation);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
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
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'edit sale quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'customer_id' => 'required|exists:customers,id',
                    'order_date'=>'required',
                    'due_date' => 'required',
                    'terms_of_payment' => 'nullable|string',
                    'remarks' => 'nullable|string',
                    'products.*.product_id' => 'required',
                    'products.*.quantity' => 'required',    

            ],[
                'customer_id.required' => 'Customer is required.',
                'customer_id.exists' => 'Customer does not exist.',

                'order_date.required' => 'Order date is required.',
                'due_date.required' => 'Due date is required.',

                'products.*.product_id.required' => 'Product is required.',
                'products.*.quantity.required' => 'Quantity is required.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $data = SaleQuotation::find($id);
            if (empty($data)) throw new Exception('No SQ found', 404);
            $data->update([
                'quotation_date'=>$request->quotation_date,
                'due_date'=>$request->due_date,
                'vendor_id'=>$request->vendor_id,
            ]);
            $existingItems = SaleQuotationItem::where('sale_quotation_id', $id)->get()->keyBy('id');
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
                    SaleQuotationItem::create([
                        'sale_quotation_id' => $id,
                        'product_id' => $item['product_id'],
                        'lot_id' => $item['lot_id'],
                        'quantity' => $item['quantity']
                    ]);
                }
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            SaleQuotationItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Sale Quotation',   
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


    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve sale quotations')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = SaleQuotation::find($id);
            if($data->status != 0) throw new Exception('status can not be changed', 400);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Sale Quotation Status',   
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
