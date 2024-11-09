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

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            // Split the parent code into levels
            $acc = ChartOfAccount::where('name',"BANK")->first();
            if(empty($acc)) throw new Exception('BANK COA not found', 404);
            $COA = createCOA($request->name,$acc->code);



            $businessHasAccount = BusinessHasAccount::create([
                'business_id' => $businessId,
                'chart_of_account_id' => $COA->id,
            ]);
        
            return response()->json($COA);
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
