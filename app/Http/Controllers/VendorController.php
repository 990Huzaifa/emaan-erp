<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create vendors')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

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
                    'opening_balance'=>'nullable|numeric',

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

                'opening_balance.numeric' => 'Opening credit balance must be a number.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar=null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('vendor-avatar'), $image_name);
                $avatar = 'vendor-avatar/' . $image_name;
            }
            do {
                $v_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Vendor::where('v_code', $v_code)->exists());
            $vendor = Vendor::create([
                'name'=>$request->name,
                'v_code'=>$request->v_code,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'telephone' => $request->telephone ?? null,
                'phone' => $request->phone,
                'website' => $request->website ?? null,
                'avatar' => $avatar,
                'address' => $request->address,
                'opening_balance' => $request->opening_balance ?? 0,

            ]);
        
            return response()->json($vendor);
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
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'view vendors')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $vendor = Vendor::findOrFail($id);

            if (empty($vendor)) throw new Exception('No vendor found', 404);

            
            return response()->json(['data'=>$vendor],200);

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
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'view vendors')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $vendor = Vendor::findOrFail($id);
            if (empty($vendor)) throw new Exception('No vendor found', 404);

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
                    'opening_balance'=>'nullable|numeric',

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

                'opening_balance.numeric' => 'Opening balance must be a number.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $vendor->update([
                'name'=>$request->name,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'telephone' => $request->telephone ?? null,
                'phone' => $request->phone,
                'website' => $request->website ?? null,
                'address' => $request->address,
                'opening_balance' => $request->opening_balance ?? 0,

            ]);

            if (empty($vendor)) throw new Exception('No vendor found', 404);

            
            return response()->json(['data'=>$vendor],200);

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
        //
    }
}
