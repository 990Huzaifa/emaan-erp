<?php

namespace App\Http\Controllers;

use App\Models\BusinessHasAccount;
use Exception;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list product category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            $data = ProductCategory::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
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
    public function store(Request $request)
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create product category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'description'=>'nullable|string',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
                'description.string' => 'Description is must be a string',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $category = ProductCategory::create([
                'name' => $request->name,
                'description' => $request->description ?? null,
            ]);

            return response()->json($category,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id){
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'edit product category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'description'=>'nullable|string',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
                'description.string' => 'Description is must be a string',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $category = ProductCategory::find($id);
            if(empty($category)) throw new Exception('Product Category not found', 404);
            $category->update([
                'name' => $request->name,
                'description' => $request->description ?? null,
            ]);
            
            return response()->json($category,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Show the form for List a new resource.
     */
    public function list(): JsonResponse
    {
        try{
            $data = ProductCategory::all();

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
