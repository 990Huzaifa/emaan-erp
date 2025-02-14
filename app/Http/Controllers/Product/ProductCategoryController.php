<?php

namespace App\Http\Controllers\Product;

use Exception;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessHasAccount;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;


class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'list product category')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            $data = ProductCategory::paginate($perPage);

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

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
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

            $acc = ChartOfAccount::where('name',"INVENTORY")->first();
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);
            DB::beginTransaction();
            $name = strtoupper($request->name);
            $COA = createCOA($name,$acc->code);

            do {
                $pc_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (ProductCategory::where('pc_code', $pc_code)->exists());
            
            $category = ProductCategory::create([
                'name' => $name,
                'pc_code' => $pc_code,
                'acc_id' => $COA->id,
                'description' => $request->description ?? null,
            ]);
            $COA->update([
                'ref_id' => $category->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product Category created successfully',
            ]);
            DB::commit();
            return response()->json($category,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id){
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
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
            $acc = ChartOfAccount::find($category->acc_id);
            if(empty($acc)) throw new Exception('Chart of Account not found', 404);
            DB::beginTransaction();
            $name = strtoupper($request->name);
            $acc->update([
                'name' => $name,
            ]);
            $category->update([
                'name' => $name,
                'description' => $request->description ?? null,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Product Category updated successfully',
            ]);
            DB::commit();
            return response()->json($category,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Show the form for List a new resource.
     */
    public function list(): JsonResponse
    {
        try{
            $data = ProductCategory::select('id','name')->get();

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
