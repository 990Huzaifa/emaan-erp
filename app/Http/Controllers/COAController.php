<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessHasAccount;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class COAController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
    
            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list chart of account')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
    
            $perPage = $request->query('per_page', 10);
    
            // Fetch chart of accounts connected with the business_has_accounts table
            $data = ChartOfAccount::join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
                ->leftJoin('opening_balances', 'chart_of_accounts.id', '=', 'opening_balances.acc_id')
                ->where('business_has_accounts.business_id', $businessId)
                ->select(
                    'chart_of_accounts.id',
                    'chart_of_accounts.name',
                    'chart_of_accounts.code'
                )
                ->paginate($perPage);
    
            if ($data->isEmpty()) {
                throw new Exception('No data found', 404);
            }
    
            return response()->json($data);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
    
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
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
                    'name'=>'required|string|unique:chart_of_accounts,name',
                    'type'=>'required|string|in:BANK,CASH,EXPENSES',
                    'opening_balance'=>'required|numeric',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
                'name.unique'=>'Name is Already in use',

                'type.required'=>'Type is Required',
                'type.string'=>'Type must be a string',
                'type.in'=>'Type Invalid',

                'opening_balance.required'=>'Balance is Required',
                'opening_balance.numeric'=>'Balance must be a number',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            // Split the parent code into levels
            $type = $request->type;
            if($type == 'EXPENSES'){
                $type = 'BUSINESS EXPENSE';
            }
            $acc = ChartOfAccount::where('name',$type)->first();
            if(empty($acc)) throw new Exception(`$type COA not found`, 404);
            DB::beginTransaction();
            
            $COA = createCOA($request->name,$acc->code);



            BusinessHasAccount::create([
                'business_id' => $businessId,
                'chart_of_account_id' => $COA->id,
            ]);

            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);

            DB::commit();
            return response()->json($COA);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
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

    public function list(): JsonResponse
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
