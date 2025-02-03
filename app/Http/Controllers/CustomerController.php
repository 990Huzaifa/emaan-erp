<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use App\Models\City;
use App\Models\Customer;
use App\Models\OpeningBalance;
use App\Models\Balance;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessHasAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;

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
            $searchQuery = $request->query('search');
            $query = Customer::orderBy('id', 'desc')->join('cities', 'customers.city_id', '=', 'cities.id')
            ->select('customers.*', 'cities.name as city');
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
                $query = $query->where('business_id',$businessId);
            }
            $perPage = $request->query('per_page', 10);
            
            if (!empty($searchQuery)) {
                $customerIds = Customer::where('name', 'like', '%' . $searchQuery . '%')
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
            if ($user->role != 'admin') {
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
                    'city_id'=>'required',
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
                
                'city_id.required'=>'City is Required',
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
                'cnic' => $request->cnic ?? null,
                'email' => $request->email ?? null,
                'telephone' => $request->telephone ?? null,
                'mobile' => $request->mobile ?? null,
                'website' => $request->website ?? null,
                'address' => $request->address ?? null,
                'logo' => $logo,
            ]);
            
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            
            Balance::create([
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $customer = DB::table('customers')
            ->join('cities', 'customers.city_id', '=', 'cities.id')
            ->where('customers.id', $id)
            ->select('customers.*', 'cities.name as city_name') // Select customer fields and city name only
            ->first();
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $customer = Customer::find($id);
            if (empty($customer)) throw new Exception('No Customer found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'city_id'=>'required|numeric',
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
                'cnic.string'=>'CNIC is must be a string',

                'email.email' => 'Please provide a valid email address.',

                'city_id.required'=>'City is Required',
                'city_id.numeric'=>'City is must be a numeric',

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
            $acc = ChartOfAccount::find($customer->acc_id);
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);
            $acc->update([
                'name' => $request->name,
            ]);
            $logo = $customer->logo;
            $oldImagePath = public_path($customer->logo);
            if ($request->hasFile('logo')) {
                $image = $request->file('logo');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('customer-logo'), $image_name);
                $logo = 'customer-logo/' . $image_name;
                
                // Remove the old image if it exists and is not the default one
                if (file_exists($oldImagePath) && !empty($image_name)) {
                    unlink($oldImagePath);
                }
            }
            
            $customer->update([
                'name'=>$request->name,
                'city_id'=>$request->city_id,
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
        
            if (!$user->can('delete customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
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
    
    public function list():JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $customer = Customer::select('id','name')->where('business_id',$businessId)->get();

            return response()->json($customer,200);
            
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function csvCustomer()
    {

        $filePath = public_path('assets/files/customer-sample.csv');

        // Check if the file exists
        if (!file_exists($filePath)) {
            return abort(404, 'File not found.');
        }
        // Define headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customer-sample.csv"',
        ];

        // Return the file as a response
        return Response::download($filePath, 'customer-sample.csv', $headers);
    }
    
    public function importCustomer(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();

            // Check user permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'csv' => 'required|file|mimes:csv,txt|max:8192', // Max 8MB
            ], [
                'csv.required' => 'CSV file is required.',
                'csv.mimes' => 'Only CSV or TXT files are allowed.',
                'csv.max' => 'CSV file size must be less than 8MB.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            $file = $request->file('csv');
            
            // Read CSV file
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            $headers = array_shift($csvData); // Remove header row

            $customers = [];
            $errors = [];
            DB::beginTransaction();
            try{
            foreach ($csvData as $row) {
                $data = array_combine($headers, $row);
                
                try {    
                    // Verify City
                    $city = City::where('name', $data['city'])->first();
                    if (!$city) {
                        throw new Exception("City '{$data['city']}' not found");
                    }
    
                    
                    do {
                        $c_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (Customer::where('c_code', $c_code)->exists());
    
                    // validate coa
                    $acc = ChartOfAccount::Where('name','CUSTOMERS')->first();
                    if(empty($acc)) throw new Exception('Customer COA not found', 404);
                    $name = strtoupper($data['name']);
                    $COA = createCOA($name,$acc->code);
    
                    // Create product
                    $customer = Customer::create([
                        'name' => $name,
                        'c_code' => $c_code,
                        'city_id' => $city->id,
                        'acc_id' => $COA->id,
                        'added_by' => $user->id,
                        'business_id' => $businessId,
                        'cnic' => $data['cnic'] ?? null,
                        'email' =>$data['email'] ?? null,
                        'telephone' => $data['telephone'] ?? null,
                        'mobile' => $data['mobile'] ?? null,
                        'website' => $data['website'] ?? null,
                        'address' => $data['address'] ?? null,
                    ]);
    
                    // Create opening balance
                    OpeningBalance::create([
                        'acc_id' => $COA->id,
                        'amount' => 0,
                    ]);
                    Balance::create([
                        'acc_id' => $COA->id,
                        'amount' => 0,
                    ]);
    
                    // Update COA reference
                    $COA->update([
                        'ref_id' => $customer->id,
                    ]);
    
                    $customers[] = $customer;

                    DB::commit();
    
                } catch (Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row error for title '{$data['title']}': " . $e->getMessage();
                }
            }
        

                // Create log entry only if some customers were imported successfully
                if (count($customers) > 0) {
                    Log::create([
                        'user_id' => $user->id,
                        'description' => 'User imported customers via CSV',
                    ]);
                }

                // Commit main transaction if we have any successful imports
                if (count($customers) > 0 || count($errors) == count($csvData)) {
                    DB::commit();
                } else {
                    DB::rollBack();
                    throw new Exception('No customers were imported successfully');
                }

                return response()->json([
                    'success' => count($customers) . ' customers imported successfully',
                    'customers' => $customers,
                    'errors' => $errors,
                ]);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        }catch(QueryException $e){
            return response()->json([' DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
