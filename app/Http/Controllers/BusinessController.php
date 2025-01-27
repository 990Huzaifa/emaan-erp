<?php

namespace App\Http\Controllers;

use App\Mail\UserMail;
use Exception;
use App\Models\User;
use App\Models\Log;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use App\Models\UserHasBusiness;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessHasAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list businesses')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $data = Business::orderBy('id','desc')
            ->join('cities','cities.id','=','businesses.city_id')
            ->select('businesses.*','cities.name as city')
            ->paginate($perPage);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User list business',
            ]);
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
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create businesses')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'city'=>'required|numeric',
                    'name'=>'required|string',
                    'logo'=>'required|image|mimes:jpeg,png,jpg,gif,svg',
                    'email'=>'required|email|string|unique:users,email',
                    'password'=>'required|string|min:8',
                    'confirm_password'=>'required|string|min:8',
                    'opening_balance'=>'nullable|numeric',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'city.required'=>'City is Required',
                'city.numeric'=>'City is must be a numeric',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters.',

                'confirm_password.required' => 'Confirm Password is required.',
                'confirm_password.min' => 'Confirm Password must be at least 8 characters.',

                'logo.required' => 'Logo is required.',
                'logo.image' => 'Logo must be an image.',
                'logo.mimes' => 'Logo must be in jpeg, png, jpg, gif, or svg format.',
                
                'opening_balance.numeric'=>'Opening Balance is must be a numeric',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            if($request->password != $request->confirm_password) throw new Exception('Password Mismatch', 400);
            DB::beginTransaction();
            // creating business
            $logo = null;
            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('business-logo'), $image_name);
                $logo = 'business-logo/' . $image_name;
            }
            $business = Business::create([
                'name'=> $request->name,
                'city_id'=> $request->city,
                'email' => $request->email,
                'cash' => $request->opening_balance ?? 0.00,
                'logo' => $logo,
            ]); 
            // validate coa
            $acc = ChartOfAccount::Where('name','CASH')->first();
            if(empty($acc)) throw new Exception('Cash COA not found', 404);

            $COA = createCOA('CASH IN HAND',$acc->code);

            BusinessHasAccount::create([
                'business_id' => $business->id,
                'chart_of_account_id' => $COA->id,
            ]);
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            // creating user of business
            do {
                $u_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (User::where('u_code', $u_code)->exists());  
            $user = User::create([
                'name'=>$request->name.' Admin',
                'u_code'=>$u_code,
                'city_id'=>$request->city,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_verify'=>1,
            ]);
            // sync permissions to user according to business
            $uhb = UserHasBusiness::create([
                'business_id'=>$business->id,
                'user_id'=>$user->id,
            ]);
            $allPermissions = Permission::all();
            $uhb->syncPermissions($allPermissions);
            // sending mail to business admin
            Mail::to($request->email)->send(new UserMail([
                'message'=>'You are Admin of the business now you have all the access of this business.',
                'url'=>config('app.frontend_url'),
                'is_url'=>true,
            ]));
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create business',
            ]);
            DB::commit();
            return response()->json(['message'=>'Mail has been sent to business Admin']);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()],400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()],400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
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
    public function update(Request $request, string $id): JsonResponse
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

    public function list(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list businesses')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Business::select(['id','name'])->get();
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch list of businesses',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function businessAccounts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            // if ($user->role == 'user') {
            //     if (!$user->hasBusinessPermission($businessId, 'list businesses')) {
            //         return response()->json([
            //             'error' => 'User does not have the required permission.'
            //         ], 403);
            //     }
            // }
            $type = $request->type;
            if(!$type) throw new Exception('Type not define',400);
            // Retrieve account codes for BANK and CASH
            $acc_code = ChartOfAccount::where('name', $type)->value('code');
            // Define the query with specific columns to fetch
            $query = BusinessHasAccount::where('business_has_accounts.business_id', $businessId)
                ->join('opening_balances', 'business_has_accounts.chart_of_account_id', '=', 'opening_balances.acc_id')
                ->join('chart_of_accounts', 'business_has_accounts.chart_of_account_id', '=', 'chart_of_accounts.id')
                ->where('chart_of_accounts.parent_code', $acc_code)
                ->select([
                    'business_has_accounts.chart_of_account_id as acc_id', // Account ID from business has accounts
                    'opening_balances.amount as balance', // Amount from opening balances
                    'chart_of_accounts.name as account_name', // Account name from chart of accounts
                    'chart_of_accounts.code as account_code'
                ]);
    
            // Fetch the data
            $data = $query->get();
    
            
    
            // Log the action
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetched list of business accounts',
            ]);
    
            return response()->json($data, 200);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
    
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function expenseAccounts(): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            // if ($user->role == 'user') {
            //     if (!$user->hasBusinessPermission($businessId, 'list businesses')) {
            //         return response()->json([
            //             'error' => 'User does not have the required permission.'
            //         ], 403);
            //     }
            // }
            // Retrieve account codes for BANK and CASH
            $Expense_acc_code = ChartOfAccount::where('name', 'EXPENSE')->value('code');
            // Define the query with specific columns to fetch
            $query = BusinessHasAccount::where('business_has_accounts.business_id', $businessId)
                ->join('opening_balances', 'business_has_accounts.chart_of_account_id', '=', 'opening_balances.acc_id')
                ->join('chart_of_accounts', 'business_has_accounts.chart_of_account_id', '=', 'chart_of_accounts.id')
                ->where('chart_of_accounts.parent_code', $Expense_acc_code)
                ->select([
                    'business_has_accounts.chart_of_account_id as acc_id', // Account ID from business has accounts
                    'opening_balances.amount as balance', // Amount from opening balances
                    'chart_of_accounts.name as account_name', // Account name from chart of accounts
                    'chart_of_accounts.code as account_code'
                ]);
    
            // Fetch the data
            $data = $query->get();
    
            
    
            // Log the action
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetched list of business accounts',
            ]);
    
            return response()->json($data, 200);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
    
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



}
