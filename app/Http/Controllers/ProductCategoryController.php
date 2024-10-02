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
            return response()->json($data);

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
                    'parent_code'=>'required|string|regex:/^(\d+(-\d+){0,3})$/',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'parent_code.required'=>'Parent Code is Required',
                'parent_code.string'=>'Parent Code is must be a string',
                'parent_code.regex' => 'Parent code must follow the correct format (e.g., 1, 1-2, 1-1-2).',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            // Split the parent code into levels
            $parentCodeParts = explode('-', $request->parent_code);
            $numLevels = count($parentCodeParts);
            // generate code
            $newCode = null;
            $baseCode = $request->parent_code;
            for ($i = 1; $i <= 9; $i++) {
                $generatedCode = $baseCode . '-' . $i;
                
                // Check if the generated code already exists in the database
                $codeExists = ChartOfAccount::where('code', $generatedCode)->exists();
                
                if (!$codeExists) {
                    $newCode = $generatedCode; // Assign the generated code to newCode if it doesn't exist
                    break; // Exit the loop once a unique code is found
                }
            }

            // If no unique code was found, throw an exception
            if ($newCode === null) {
                throw new Exception('Unable to generate a unique code.', 400);
            }
            
            $level1 = isset($parentCodeParts[0]) ? $parentCodeParts[0] : '0';
            $level2 = isset($parentCodeParts[1]) ? $parentCodeParts[1] : '0';
            $level3 = isset($parentCodeParts[2]) ? $parentCodeParts[2] : '0';
            $level4 = isset($parentCodeParts[3]) ? $parentCodeParts[3] : '0';
            $level5 = isset($parentCodeParts[4]) ? $parentCodeParts[4] : '0';

            if ($numLevels == 1) {
                $level2 = $i;  // New level 2 value
            } elseif ($numLevels == 2) {
                $level3 = $i;  // New level 3 value
            } elseif ($numLevels == 3) {
                $level4 = $i;  // New level 4 value
            } elseif ($numLevels == 4) {
                $level5 = $i;  // New level 5 value
            }

            $coa = ChartOfAccount::create([
                'code'=>$newCode,
                'name'=>$request->name,
                'parent_code'=>$baseCode,
                'level1' => $level1,
                'level2' => $level2,
                'level3' => $level3,
                'level4' => $level4,
                'level5' => $level5,
            ]);

            $category = ProductCategory::create([
                'name' => $request->name,
            ]);

            $relation = BusinessHasAccount::create([
                'business_id' => $businessId,
                'chart_of_account_id' => $coa->id,
            ]);

            return response()->json($category);
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
            return response()->json($data);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
