<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use App\Models\City;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessHasAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $user;
    
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            $query = Customer::orderBy('id', 'desc');
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
                $query = $query->where('business_id',$businessId);
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');

            $query = $query->join('chart_of_accounts', 'customers.chart_of_accounts_id', '=', 'chart_of_accounts.id')
            ->select('customers.*', 'chart_of_accounts.name as chart_of_account');
            if (!empty($searchQuery)) {
                $customerIds = Customer::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('email', 'like', '%' . $searchQuery . '%')
                        ->orWhere('c_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found Customers IDs
                    $query = $query->whereIn('id', $customerIds);
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);

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
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'email' => 'nullable|email',
                    'cnic'=>'nullable|string|max:14|unique:customers,cnic',
                    'logo' => 'nullable|image',
                    'website' => 'nullable|url',
                    'address' => 'nullable|string|max:255',
                    'telephone' => 'nullable|string|max:20',
                    'mobile' => 'required|string|max:12',
                    'opening_balance'=>'nullable|numeric',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'cnic.required' => 'CNIC is required.',
                'cnic.max' => 'CNIC cannot exceed 14 characters.',
                'cnic.unique' => 'This CNIC is already in use.',
                'cnic.string'=>'CNIC is must be a string',

                'email.email' => 'Please provide a valid email address.',

                'logo.image' => 'logo must be an image file',

                'website.url' => 'Website must be valid url',

                'address.string' => 'Address must be a string.',
                'address.max' => 'Address cannot exceed 255 characters.',

                'telephone.string' => 'Telephone number must be a string.',
                'telephone.max' => 'Telephone number cannot exceed 20 characters.',

                'mobile.string' => 'Mobile number must be a string.',
                'mobile.max' => 'Mobile number cannot exceed 12 characters.',
                'mobile.required' => 'Mobile number is required.',

                'opening_balance.numeric'=>'Opening Balance is must be a numeric',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $logo = null;
            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('customer-logo'), $image_name);
                $logo = 'customer-logo/' . $image_name;
            }
            do {
                $c_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Customer::where('c_code', $c_code)->exists());

            // validate coa
            $acc = ChartOfAccount::Where('name','CUSTOMERS')->first();
            if(empty($acc)) throw new Exception('Customer COA not found', 404);

            // validate city
            $city = City::find($request->city_id);
            if(empty($city)) throw new Exception('City not found', 404);
            $COA = createCOA($request->name,$acc->code);
            $customer = Customer::create([
                'name' => $request->name,
                'c_code' => $c_code,
                'acc_id' => $COA->id,
                'business_id' => $user->login_business,
                'city_id' => $request->city_id,
                'cnic' => $request->cnic,
                'email' => $request->email ?? null,
                'telephone' => $request->telephone ?? null,
                'mobile' => $request->mobile ?? null,
                'website' => $request->website ?? null,
                'address' => $request->address ?? null,
                'logo' => $logo,
            ]);
            BusinessHasAccount::create([
                'business_id' => $user->login_business,
                'chart_of_account_id' => $COA->id,
            ]);
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            $COA->update([
                'ref_id' => $customer->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create customer',
            ]);

            
            
            return response()->json($customer);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $customer = Customer::find($id);
            if (empty($customer)) throw new Exception('No Customer found', 404);
            return response()->json($customer);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $customer = Customer::find($id);
            if (empty($customer)) throw new Exception('No User found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'coa_id'=>'required|numeric',
                    'email' => 'nullable|email',
                    'cnic'=>'required|string|max:14',
                    'logo' => 'nullable|image',
                    'website' => 'nullable|url',
                    'address' => 'nullable|string|max:255',
                    'telephone' => 'nullable|string|max:20',
                    'mobile' => 'nullable|string|max:12',


            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'cnic.required' => 'CNIC is required.',
                'cnic.max' => 'CNIC cannot exceed 14 characters.',
                'cnic.unique' => 'This CNIC is already in use.',
                'cnic.string'=>'CNIC is must be a string',

                'email.email' => 'Please provide a valid email address.',

                'coa_id.required'=>'COA id is Required',
                'coa_id.string'=>'COA id is must be a numeric',

                'logo.image' => 'logo must be an image file',

                'website.url' => 'Website must be valid url',

                'address.string' => 'Address must be a string.',
                'address.max' => 'Address cannot exceed 255 characters.',

                'telephone.string' => 'Telephone number must be a string.',
                'telephone.max' => 'Telephone number cannot exceed 20 characters.',

                'mobile.string' => 'Mobile number must be a string.',
                'mobile.max' => 'Mobile number cannot exceed 12 characters.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $logo = $customer->logo;
            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('customer-logo'), $image_name);
                $logo = 'customer-logo/' . $image_name;
            }
            $customer->update([
                'name'=>$request->name,
                'chart_of_account_id'=>$request->coa_id,
                'cnic'=>$request->cnic,
                'email' => $request->email ?? null,
                'telephone' => $request->telephone ?? null,
                'mobile' => $request->mobile ?? null,
                'website' => $request->website ?? null,
                'address' => $request->address ?? null,
                'logo' => $logo,
            ]);
            return response()->json($customer);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'delete customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $customer = Customer::find($id);
            if (empty($customer)) throw new Exception('No User found', 404);
            $customer->delete();        
            return response()->json(['message' => 'Customer deleted successfully.']);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
