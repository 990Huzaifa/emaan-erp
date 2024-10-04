<?php

namespace App\Http\Controllers;

use App\Models\BusinessHasAccount;
use Exception;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class COAController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list chart of account')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            $data = ChartOfAccount::paginate($perPage);

            return response()->json($data,200);

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
                if (!$user->hasBusinessPermission($businessId, 'create chart of account')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            // dd('test-done');

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
            $check = ChartOfAccount::where('code',$request->parent_code)->first();
            if(empty($check)) throw new Exception('Invalid chart of account', 400);
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

            $businessHasAccount = BusinessHasAccount::create([
                'business_id' => $businessId,
                'chart_of_account_id' => $coa->id,
            ]);
        
            return response()->json($coa);
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
        //
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

    public function list()
    {
        try {
            $user = Auth::user();

            // Step 1: Get all ChartOfAccount entries that do not have any relation with BusinessHasAccount
            $chartOfAccountsWithoutRelation = ChartOfAccount::whereDoesntHave('business_has_accounts')->get();

            // Step 2: Initialize the final data variable
            $data = $chartOfAccountsWithoutRelation;

            // Step 3: Check if the user is a regular user ('role' is 'user')
            if ($user->role == 'user') {
                $businessId = $user->login_business;

                // Check if the user has permission to list the chart of accounts for the business
                if (!$user->hasBusinessPermission($businessId, 'list chart of account')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }

                // Step 4: Get ChartOfAccount records related to the specific business
                $businessHasAccounts = BusinessHasAccount::where('business_id', $businessId)->pluck('chart_of_account_id');
                $chartOfAccountsForBusiness = ChartOfAccount::whereIn('id', $businessHasAccounts)->get();

                // Step 5: Append the related accounts to the list of accounts without relation
                $data = $data->merge($chartOfAccountsForBusiness);
            } else {
                // If the user is not a regular user, get all ChartOfAccount records
                $data = ChartOfAccount::get();
            }

            // Step 6: Return the final data as a JSON response
            return response()->json($data, 200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
