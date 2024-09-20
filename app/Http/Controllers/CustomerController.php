<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $user;
    
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
                    'avatar' => 'nullable|image',
                    'website' => 'nullable|url',
                    'address' => 'nullable|string|max:255',
                    'telephone' => 'nullable|string|max:20',

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

                'avatar.image' => 'Avatar must be an image file',

                'website.url' => 'Website must be valid url',

                'address.string' => 'Address must be a string.',
                'address.max' => 'Address cannot exceed 255 characters.',

                'telephone.string' => 'Telephone number must be a string.',
                'telephone.max' => 'Telephone number cannot exceed 20 characters.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('customer-avatar'), $image_name);
                $avatar = 'customer-avatar/' . $image_name;
            }
            do {
                $c_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Customer::where('c_code', $c_code)->exists());
            $customer = Customer::create([
                'name'=>$request->name,
                'c_code'=>$c_code,
                'business_id'=>$user->business_id,
                'city'=>$request->city,
                'cnic'=>$request->cnic,
                'email' => $request->email ?? null,
                'telephone' => $request->telephone ?? null,
                'mobile' => $request->mobile ?? null,
                'website' => $request->website ?? null,
                'address' => $request->address ?? null,
                'avatar' => $avatar,
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
    public function show(string $id)
    {
        try{
            $user = Auth::user();
        
            if (!$user->can('view customers')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            $customer = Customer::find($id);
        
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
    public function update(Request $request, string $id)
    {
        try {
            $user = Auth::user();
        
            if (!$user->can('update customers')){
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
                    'avatar' => 'nullable|image',
                    'website' => 'nullable|url',
                    'address' => 'nullable|string|max:255',
                    'telephone' => 'nullable|string|max:20',


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

                'avatar.image' => 'Avatar must be an image file',

                'website.url' => 'Website must be valid url',

                'address.string' => 'Address must be a string.',
                'address.max' => 'Address cannot exceed 255 characters.',

                'telephone.string' => 'Telephone number must be a string.',
                'telephone.max' => 'Telephone number cannot exceed 20 characters.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = $customer->avatar;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('customer-avatar'), $image_name);
                $avatar = 'customer-avatar/' . $image_name;
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
                'avatar' => $avatar,
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
    public function destroy(string $id)
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
