<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

            // return response()->json($user);
            
            if (!$user->can('list customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $perPage = $request->query('per_page', 10);

            $data = Customer::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data);

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
        
            if (!$user->can('create customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'city'=>'required|string',
                    'email' => 'nullable|email',
                    'cnic'=>'required|string|max:14|unique:customers,cnic',
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

                'city.required'=>'City is Required',
                'city.string'=>'City is must be a string',

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
            $customer = Customer::create([
                'name'=>$request->name,
                'c_code'=>$c_code,
                'business_id'=>$user->business_id,
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
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
        
            if (!$user->can('view customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
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
        
            if (!$user->can('edit customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $customer = Customer::find($id);
            if (empty($customer)) throw new Exception('No User found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'city'=>'required|string',
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

                'city.required'=>'City is Required',
                'city.string'=>'City is must be a string',

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
                'city'=>$request->city,
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
}
