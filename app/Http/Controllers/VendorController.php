<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'email'=>'nullable|email|string',
                    'city_id'=>'required|exists:cities,id',
                    'telephone'=>'nullable|string',
                    'phone'=>'required|string',
                    'website'=>'nullable|string',
                    'avatar'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'address'=>'required|string',
                    'opening_credit_balance'=>'nullable|numeric',
                    'opening_debit_balance'=>'nullable|numeric',

            ],[
                'name.required'     => 'Name is required.',
                'name.string'       => 'Name must be a string.',
                'name.max'          => 'Name cannot exceed 255 characters.',

                'email.email'       => 'Please provide a valid email address.',
                'email.max'         => 'Email cannot exceed 255 characters.',

                'city.required'     => 'City is required.',
                'city.exists'       => 'City does not exist.',

                'telephone.string'  => 'Telephone must be a string.',
                'telephone.max'     => 'Telephone cannot exceed 20 characters.',

                'phone.required'    => 'Phone is required.',
                'phone.string'      => 'Phone must be a string.',
                'phone.max'         => 'Phone cannot exceed 20 characters.',

                'website.url'       => 'Please provide a valid URL for the website.',
                'website.max'       => 'Website URL cannot exceed 255 characters.',

                'avatar.image'      => 'Avatar must be an image.',
                'avatar.mimes'      => 'Avatar must be a file of type: jpeg, png, jpg, gif.',
                'avatar.max'        => 'Avatar image size cannot exceed 2MB.',

                'address.required'  => 'Address is required.',
                'address.string'    => 'Address must be a string.',
                'address.max'       => 'Address cannot exceed 500 characters.',

                'opening_credit_balance.numeric' => 'Opening credit balance must be a number.',
                'opening_debit_balance.numeric' => 'Opening debit balance must be a number.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar=null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('vendor-avatar'), $image_name);
                $avatar = 'vendor-avatar/' . $image_name;
            }
            $user = Vendor::create([
                'name'=>$request->name,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'telephone' => $request->telephone ?? null,
                'phone' => $request->phone,
                'website' => $request->website ?? null,
                'avatar' => $avatar,
                'address' => $request->address,
                'opening_credit_balance' => $request->opening_credit_balance ?? 0,
                'opening_debit_balance' => $request->opening_debit_balance ?? 0,

            ]);
            $user->syncPermissions($request->permissions);
        
            return response()->json($user);
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
}
