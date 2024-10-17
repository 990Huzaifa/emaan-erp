<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $isActive = $request->query('is_active');
            $searchQuery = $request->query('search');

            $query = Vendor::orderBy('id', 'desc')
            ->join('cities', 'vendors.city_id', '=', 'cities.id')
            ->select('vendors.*', 'cities.name as city'); 
            if ($isActive === 'active') {
                $query = $query->where('is_active', 1);
            } elseif ($isActive === 'inactive') {
                $query = $query->where('is_active', 0);
            }
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = Vendor::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('v_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found user IDs
                    $query = $query->whereIn('users.id', $userIds);
                }
            }
            $data = $query->paginate($perPage);


            Log::create([
                'user_id' => $user->id,
                'description' => 'User list vendors',
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
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
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
                    'telephone' => 'nullable|string|required_without:phone',
                    'phone' => 'nullable|string|required_without:telephone',
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
                'telephone.required_without' => 'Either telephone or phone is required.',

                'phone.string'      => 'Phone must be a string.',
                'phone.max'         => 'Phone cannot exceed 20 characters.',
                'phone.required_without' => 'Either phone or telephone is required.',

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

            $acc = ChartOfAccount::where('name',"VENDORS")->first();
            if(empty($acc)) throw new Exception('Vendors COA not found', 404);
            $COA = createCOA($request->name,$acc->code);
            
            $vendor = Vendor::create([
                'name'=>$request->name,
                'v_code'=>$v_code,
                'acc_id'=>$COA->id,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'telephone' => $request->telephone ?? null,
                'phone' => $request->phone,
                'website' => $request->website ?? null,
                'avatar' => $avatar,
                'address' => $request->address,
            ]);
            $COA->update([
                'ref_id' => $vendor->id,
            ]);
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create vendor',
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

            Log::create([
                'user_id' => $user->id,
                'description' => 'User view vendor',
            ]);
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
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit vendors')) {
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
                    'telephone' => 'nullable|string|required_without:phone',
                    'phone' => 'nullable|string|required_without:telephone',
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
                'telephone.required_without' => 'Either telephone or phone is required.',

                'phone.string'      => 'Phone must be a string.',
                'phone.max'         => 'Phone cannot exceed 20 characters.',
                'phone.required_without' => 'Either phone or telephone is required.',

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
            $acc = ChartOfAccount::find($vendor->acc_id);
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);
           $acc->update([
                'name' => $request->name,
            ]);
            $avatar = $vendor->avatar; // Keep current image by default
            $oldImagePath = public_path($vendor->avatar); // Path to the old image

            if ($request->hasFile('avatar')) {
                // Upload the new image
                $uploadedImage = $request->file('avatar');
                $imageName = 'avatar_' . time() . '.' . $uploadedImage->getClientOriginalExtension();
                $uploadedImage->move(public_path('vendor-avatar'), $imageName);
                $avatar = 'vendor-avatar/' . $imageName;

                // Remove the old image if it exists and is not the default one
                if (file_exists($oldImagePath) && !empty($product->image)) {
                    unlink($oldImagePath);
                }
            }
            $vendor->update([
                'name'=>$request->name,
                'v_code'=>$vendor->v_code,
                'acc_id'=>$acc->id,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'telephone' => $request->telephone ?? null,
                'phone' => $request->phone,
                'website' => $request->website ?? null,
                'address' => $request->address,
                'opening_balance' => $request->opening_balance ?? 0,
                'avatar' => $avatar,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update vendor',
            ]);

            
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

    public function list():JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list vendors')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $vendors = Vendor::select('id','name')->get();

            return response()->json($vendors,200);
            
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
