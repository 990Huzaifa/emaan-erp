<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessHasAccount;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class ProductSubCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = ProductSubCategory::orderBy('id', 'desc');
            $query->select(
                'product_sub_categories.*',
                'product_categories.name as product_category'
            )
            ->join('product_categories', 'product_sub_categories.category_id', '=', 'product_categories.id')
            ->paginate($perPage);
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('product_sub_categories.id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = ProductSubCategory::where('name', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found user IDs
                    $query = $query->whereIn('product_sub_categories.id', $userIds);
                }
            }
            $data = $query->paginate($perPage);
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product sub Category listed successfully',
            ]);
            
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'category_id'=>'required|string|exists:product_categories,id',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'category_id.required'=>'Category id is Required',
                'category_id.string'=>'Category id is must be a string',
                'category_id.exists'=>'Category id is not exists',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            // get category acc
            $category = ProductCategory::find($request->category_id);
            $acc = ChartOfAccount::find($category->acc_id);
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);
            // create sub category acc
            DB::beginTransaction();
            $name = strtoupper($request->name);
            $COA = createCOA($name,$acc->code);

            do {
                $psc_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (ProductSubCategory::where('psc_code', $psc_code)->exists());

            $subcategory = ProductSubCategory::create([
                'name' => $name,
                'psc_code' => $psc_code,
                'acc_id' => $COA->id,
                'category_id' => $request->category_id,
            ]);
            
            $COA->update([
                'ref_id' => $category->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product Sub-Category created successfully',
            ]);
            DB::commit();
            return response()->json($subcategory,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $subcategory = ProductSubCategory::find($id);
            if (!$subcategory) throw new Exception('Product Sub Category not found', 404);
            
            
            $acc = ChartOfAccount::find($subcategory->acc_id);
            if(empty($acc)) throw new Exception('Chart of Account not found', 404);
            DB::beginTransaction();
            $name = strtoupper($request->name);
            $acc->update([
                'name' => $name,
            ]);
            
            
            $subcategory->update([
                'name' => $name,
            ]);
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product Sub-Category updated successfully',
            ]);
            DB::commit();
            return response()->json($subcategory, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function show($id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = ProductSubCategory::find($id);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product Sub-Category Fetch successfully',
            ]);
            
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function list(Request $request, $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = ProductSubCategory::select(
                'product_sub_categories.id',
                'product_sub_categories.name',
                'product_categories.name as product_category'
            )
            ->where('category_id',$id)
            ->join('product_categories', 'product_sub_categories.category_id', '=', 'product_categories.id')
            ->get();

            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function filterIndex($id):JsonResponse
    {
        try{
            $data = ProductSubCategory::where('category_id',$id)->get();
            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
