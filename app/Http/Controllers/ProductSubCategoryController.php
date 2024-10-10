<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
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
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request)
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
            
            $subcategory = ProductSubCategory::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
            ]);


            return response()->json($subcategory,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function update(Request $request, $id)
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'update product sub category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'category_id'=>'required|string|exists:product_categories,id',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $subcategory = ProductSubCategory::find($id);
            if (!$subcategory) throw new Exception('Product Sub Category not found', 404);

            $subcategory->update([
                'name' => $request->name,
                'category_id' => $request->category_id,
            ]);
            return response()->json($subcategory, 200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    

    /**
     * Show the form for List a new resource.
     */
    public function list($id): JsonResponse
    {
        try{
            $data = ProductSubCategory::find($id);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
