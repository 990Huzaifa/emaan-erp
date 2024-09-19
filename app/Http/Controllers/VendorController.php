<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'email'=>'nullable|email|string',
                    'city'=>'required|string',
                    'telephone'=>'nullable|string',
                    'phone'=>'required|string',
                    'website'=>'nullable|string',
                    'avatar'=>'nullable|string',
                    'address'=>'required|string',

            ],[
                'name.required'     => 'Name is required.',
                'name.string'       => 'Name must be a string.',
                'name.max'          => 'Name cannot exceed 255 characters.',

                'email.email'       => 'Please provide a valid email address.',
                'email.max'         => 'Email cannot exceed 255 characters.',

                'city.required'     => 'City is required.',
                'city.string'       => 'City must be a string.',
                'city.max'          => 'City cannot exceed 255 characters.',

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
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $user = Vendor::create([
                'name'=>$request->name,
                'email' => $request->email,
            ]);
            $user->syncPermissions($request->permissions);
        
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
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
}
