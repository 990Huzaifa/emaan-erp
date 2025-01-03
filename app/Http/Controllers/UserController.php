<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Log;
use Illuminate\Validation\Rule;
use App\Mail\UserMail;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Exceptions\UnauthorizedException;
use App\Notifications\GeneralNotification;

class UserController extends Controller
{

    protected $user;

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

            $query = User::orderBy('id', 'desc')
            ->where('role','user')
            ->where('is_verify',1)
            ->join('cities', 'users.city_id', '=', 'cities.id')
            ->select('users.*', 'cities.name as city'); 
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
                    $userIds = User::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('email', 'like', '%' . $searchQuery . '%')
                        ->orWhere('u_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found user IDs
                    $query = $query->whereIn('users.id', $userIds);
                }
            }
            $data = $query->paginate($perPage);

            // Attach the related business names to each user
            $data->getCollection()->transform(function ($user) {
                // Fetch business ids and names from businesses table via user_has_businesses
                $businesses = DB::table('user_has_businesses')
                    ->join('businesses', 'user_has_businesses.business_id', '=', 'businesses.id')
                    ->where('user_has_businesses.user_id', $user->id)
                    ->select('businesses.id', 'businesses.name')
                    ->get();
            
                // Append the business array (with id and name) to the user object
                $user->business_names = $businesses->map(function($business) {
                    return [
                        'id' => $business->id,
                        'name' => $business->name
                    ];
                })->toArray();
            
                return $user;
            });
            Log::create([
                'user_id' => $user->id,
                'description' => 'User list users',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request):JsonResponse
    {
        try{
            $Auser = Auth::user();
            
            // Check if the user has the required permission
            if ($Auser->role == 'user') {
                $businessId = $Auser->login_business;
                if (!$Auser->hasBusinessPermission($businessId, 'create users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'nullable|string',
                    'city'=>'nullable|exists:cities,id',
                    'email'=>'required|email|string|unique:users,email',
                    'permissions'=>'required'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'permissions.required' => 'permissions is required.',
                
                'city.exists'=>'City is not valid',
            ]);
            $str_permissions = $request->input('permissions');

            // Check if permissions is a JSON string and decode it
            if (is_string($str_permissions)) {
                $str_permissions = json_decode($str_permissions, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Permissions format is invalid', 400);
                }
            }
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $u_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (User::where('u_code', $u_code)->exists());
            $setupCode = generateSetupCode(); 
            $user = User::create([
                'name'=>$request->name,
                'city_id'=>1,
                'u_code'=>$u_code,
                'email' => $request->email,
                'setup_code' => $setupCode,
            ]);
            $user->notify(new GeneralNotification("Welcome to the platform! Your account has been successfully created."));
            $setupUrl = config('app.frontend_url').'/setup-system-user/'.$setupCode;
            // sync permissions to user according to business
            foreach ($str_permissions as $businessId => $permissions) {
                $uhb = UserHasBusiness::create([
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                ]);
                // Add "edit profile" permission
                if (!in_array('edit profile', $permissions)) {
                    $permissions[] = 'edit profile';
                }
    
                $uhb->syncPermissions($permissions);
            }
            // sending mail to user
            Mail::to($request->email)->send(new UserMail([
                'message'=> 'Please setup your account by clicking on the below link',
                'url' => $setupUrl,
                'is_url'=>true,
            ])); 
        
            Log::create([
                'user_id' => $Auser->id,
                'description' => 'User create user',
            ]);    
            
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id):JsonResponse
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $userData = User::findOrFail($id);

            // Fetch the user's associated businesses and permissions
            $userHasBusinesses = UserHasBusiness::where('user_id', $id)->get();
            $businessPermissions = [];
    
            // Loop through each business and fetch permissions
            foreach ($userHasBusinesses as $userHasBusiness) {
                $businessPermissions[] = [
                    $userHasBusiness->business_id => $userHasBusiness->getAllPermissions()->pluck('name'),  // Assuming getPermissions() returns the permissions
                ];
            }
    
            // Prepare response data
            $data = [
                'user' => $userData,
                'business_permissions' => $businessPermissions,
            ];
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch user details',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $user = User::findOrFail($id);
            if (empty($user)) throw new Exception('No User found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'nullable|string',
                    'city'=>'nullable|exists:cities,id',
                    'email' => [
                    'required',
                    'email',
                    'string',
                    Rule::unique('users')->ignore($user->id), // Exclude current user
                ],
                    'permissions'=>'required'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'The email has already been taken.',
                
                'permissions.required' => 'permissions is required.',
                
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = null;
            $cnic_front = null;
            $cnic_back = null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('user-avatar'), $image_name);
                $avatar = 'user-avatar/' . $image_name;
            }
            if ($request->hasFile('cnic_front')) {
                $front_image = $request->file('cnic_front');
                $front_image_name = 'cnic_' . $user->id . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('user-cnic'), $front_image_name);
                $cnic_front = 'user-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . $user->id . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('user-cnic'), $back_image_name);
                $cnic_back = 'user-cnic/' . $back_image_name;
            }
            $cnic_images = [$cnic_front, $cnic_back];
            $user->update([
                    'email' =>$request->email,
                    'name'=> $request->name,
                    'city_id'=>$request->city_id ?? $user->city_id,
                    'phone'=>$request->phone ?? null,
                    'address' => $request->address ?? null,
                    'cnic'=>$request->cnic ?? null,
                    'cnic_images'=> $cnic_images,
                    'avatar' => $avatar,
            ]);
            if($request->email != $user->email){
                // sending mail to user
                Mail::to($request->email)->send(new UserMail([
                    'message'=> 'your Email has beed updated your new email is '.$request->email,
                    'url' => config('frontend.url'),
                    'is_url'=>true,
                ]));
            }
            
            $str_permissions = $request->input('permissions');

            // Check if permissions is a JSON string and decode it
            if (is_string($str_permissions)) {
                $str_permissions = json_decode($str_permissions, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Permissions format is invalid', 400);
                }
            }
            $uhb_ids = UserHasBusiness::where('user_id', $id)->pluck('id')->toArray();

            // Delete the permissions associated with these UserHasBusiness records
            DB::table('model_has_permissions')->whereIn('model_id', $uhb_ids)
                ->where('model_type', (new UserHasBusiness())->getMorphClass())
                ->delete();

            // Now delete the UserHasBusiness records
            UserHasBusiness::where('user_id', $id)->delete();
            
            foreach ($str_permissions as $business_id => $permissions) {
                
                    $uhb = UserHasBusiness::create([
                        'user_id' => $id,
                        'business_id' => $business_id,
                    ]);
                
            
                // Add "edit profile" permission if not already in the list
                if (!in_array('edit profile', $permissions)) {
                    $permissions[] = 'edit profile';
                }
            
                // Sync the permissions
                $uhb->syncPermissions($permissions);
            }

            
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update user details with permissions',
            ]);
            return response()->json(['success'=>'user updated successfully.'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function updateStatus($id):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $user = User::findOrFail($id);
            if (empty($user)) throw new Exception('No User found', 404);
            if($user->status == 1){
                $user->update([
                    'status'=>0,
                ]);
            }else{
                $user->update([
                    'status'=>1,
                ]);
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update user status',
            ]);
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function verify(Request $request, $id):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = User::findOrFail($id);
            if (empty($data)) throw new Exception('No User found', 404);

            $data->update([
                'is_verify'=>1
            ]);          
            Log::create([
                'user_id' => $user->id,
                'description' => 'User verify user',
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function inviteList(Request $request):JsonResponse
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
            $searchQuery = $request->query('search');

            $query = User::orderBy('id', 'desc')
            ->where('role', 'user')
            ->where(function($q) {
                $q->where('setup_code', '<>', '')
                  ->orWhere('setup_code', '=', '0');
            })
            ->join('cities', 'users.city_id', '=', 'cities.id')
            ->select('users.*', 'cities.name as city');
            
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = User::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('email', 'like', '%' . $searchQuery . '%')
                        ->orWhere('u_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
                        $query = $query->whereIn('users.id', $userIds);
                }
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            $data->getCollection()->transform(function ($user) {
                // Fetch business ids and names from businesses table via user_has_businesses
                $businesses = DB::table('user_has_businesses')
                    ->join('businesses', 'user_has_businesses.business_id', '=', 'businesses.id')
                    ->where('user_has_businesses.user_id', $user->id)
                    ->select('businesses.id', 'businesses.name')
                    ->get();
            
                // Append the business array (with id and name) to the user object
                $user->business_names = $businesses->map(function($business) {
                    return [
                        'id' => $business->id,
                        'name' => $business->name
                    ];
                })->toArray();
            
                return $user;
            });
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch invite list of users',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function waitList(Request $request):JsonResponse
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
            $searchQuery = $request->query('search');

            $query = User::orderBy('id', 'desc')
            ->where('role', 'user')
            ->where('is_verify', 0)
            ->join('cities', 'users.city_id', '=', 'cities.id')
            ->select('users.*', 'cities.name as city');
            
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = User::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('email', 'like', '%' . $searchQuery . '%')
                        ->orWhere('u_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
                        $query = $query->whereIn('users.id', $userIds);
                }
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            $data->getCollection()->transform(function ($user) {
                // Fetch business ids and names from businesses table via user_has_businesses
                $businesses = DB::table('user_has_businesses')
                    ->join('businesses', 'user_has_businesses.business_id', '=', 'businesses.id')
                    ->where('user_has_businesses.user_id', $user->id)
                    ->select('businesses.id', 'businesses.name')
                    ->get();
            
                // Append the business array (with id and name) to the user object
                $user->business_names = $businesses->map(function($business) {
                    return [
                        'id' => $business->id,
                        'name' => $business->name
                    ];
                })->toArray();
            
                return $user;
            });
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch invite list of users',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function sendSetupMail($id): JsonResponse
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $user = User::find($id);
            if (empty($user)) throw new Exception('Account not found', 404);
            $setupCode = generateSetupCode();   
            $user->update([
                'setup_code' => $setupCode,

            ]);
            $setupUrl = config('app.frontend_url').'/setup-system-user/'.$setupCode;
            Mail::to($user->email)->send(new UserMail([
                'message'=>'Please setup your account by clicking on the below link',
                'url' => $setupUrl,
                'is_url'=>true,
            ])); 
            Log::create([
                'user_id' => $user->id,
                'description' => 'User send setup mail to user',
            ]);
            return response()->json(['success'=>'Setup mail sent successfully.'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function destroy($id): JsonResponse
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'delete users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $user = User::find($id);
            if (empty($user)) throw new Exception('Account not found', 404);
            $uhb = UserHasBusiness::where('user_id',$id)->delete();
            $user->delete(); 
            Log::create([
                'user_id' => $user->id,
                'description' => 'Delete user',
            ]);
            return response()->json(['success'=>'user has been deleted successfully'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function editProfile(Request $request, $id):JsonResponse
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit profile')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $user = User::findOrFail($id);
            if (empty($user)) throw new Exception('No User found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'phone'=>'nullable|string',
                    'cnic'=>'nullable|string',
                    'address'=>'nullable|string',
                    'cnic_back'=> 'nullable|image',
                    'cnic_front'=> 'nullable|image',
                    'avatar'=> 'nullable|image',
            ],[
                'phone.string'=>'Phone is must be a string',
    
                'cnic.string' => 'CNIC must be type String.',

                'address.string' => 'Address must be type String.',

                'cnic_back.image' => 'CNIC Back must be type image.',

                'cnic_front.image' => 'CNIC Front must be type image.',

                'avatar.image' => 'Avatar must be type image.',

                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',
                'password.min' => 'Password must be at least 8 characters.',
                
                'confirm_password.required' => 'Password is required.',
                'confirm_password.string' => 'Password must be a string.',
                'confirm_password.min' => 'Password must be at least 8 characters.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = null;
            $cnic_front = null;
            $cnic_back = null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('user-avatar'), $image_name);
                $avatar = 'user-avatar/' . $image_name;
            }
            if ($request->hasFile('cnic_front')) {
                $front_image = $request->file('cnic_front');
                $front_image_name = 'cnic_' . $user->id . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('user-cnic'), $front_image_name);
                $cnic_front = 'user-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . $user->id . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('user-cnic'), $back_image_name);
                $cnic_back = 'user-cnic/' . $back_image_name;
            }
            $cnic_images = [$cnic_front, $cnic_back];
            $user->update([
                'name'=>$request->name,
                'phone'=>$request->phone,
                'address'=>$request->address,
                'city_id'=>$request->city,
                'email'=>$request->email,
                'cnic'=>$request->cnic,
                'avatar'=>$avatar,
                'cnic_images'=>$cnic_images,
            ]);
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'User edit his profile',
            ]);
            return response()->json($user);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function updateInvite(Request $request, $id):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $user = User::findOrFail($id);
            
            $validator = Validator::make(
                $request->all(),[
                    'email'=>'required|email|string|unique:users,email',
                    'permissions'=>'required|array'

            ],[

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'permissions.required' => 'permissions is required.',
                'permissions.array' => 'permissions must be type array.',
                
                'city.exists'=>'City is not valid',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            if (empty($user)) throw new Exception('No User found', 404);
            
            $setupCode = generateSetupCode();   
            $user->update([
                'email' =>$request->email,
                'setup_code' => $setupCode,

            ]);
            $setupUrl = config('app.frontend_url').'/setup-system-user/'.$setupCode;
            Mail::to($request->email)->send(new UserMail([
                'message'=>'Please setup your account by clicking on the below link',
                'url' => $setupUrl,
                'is_url'=>true,
            ]));
            
            // sync permissions to user according to business
            foreach ($request->permissions as $businessId => $permissions) {
                $uhb = UserHasBusiness::create([
                    'business_id' => $businessId,
                    'user_id' => $id,
                ]);
                $uhb->syncPermissions($permissions);
            }
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update invite user with permissions',
            ]);
            return response()->json($user,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function createPartner(Request $request):JsonResponse
    {
        try{
            $Auser = Auth::user();
            
            // Check if the user has the required permission
            if ($Auser->role == 'user') {
                $businessId = $Auser->login_business;
                if (!$Auser->hasBusinessPermission($businessId, 'create users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'nullable|string',
                    'city'=>'nullable|exists:cities,id',
                    'email'=>'required|email|string|unique:users,email',
                    'permissions'=>'required'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'permissions.required' => 'permissions is required.',
                
                'city.exists'=>'City is not valid',
            ]);
            $str_permissions = $request->input('permissions');
            
            // Check if permissions is a JSON string and decode it
            if (is_string($str_permissions)) {
                $str_permissions = json_decode($str_permissions, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Permissions format is invalid', 400);
                }
            }
            
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $u_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (User::where('u_code', $u_code)->exists());
            $setupCode = generateSetupCode(); 
            $user = User::create([
                'name'=>$request->name,
                'city_id'=>1,
                'u_code'=>$u_code,
                'email' => $request->email,
                'setup_code' => $setupCode,
            ]);
            $user->notify(new GeneralNotification("Welcome to the platform! Your account has been successfully created."));
            $setupUrl = config('app.frontend_url').'/setup-system-user/'.$setupCode;
            // sync permissions to user according to business
            foreach ($str_permissions as $businessId => $permissions) {
                $uhb = UserHasBusiness::create([
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                ]);
                // Add "edit profile" permission
                if (!in_array('edit profile', $permissions)) {
                    $permissions[] = 'edit profile';
                }
    
                $uhb->syncPermissions($permissions);
            }
            // sending mail to user
            Mail::to($request->email)->send(new UserMail([
                'message'=> 'Please setup your account by clicking on the below link',
                'url' => $setupUrl,
                'is_url'=>true,
            ])); 
        
            Log::create([
                'user_id' => $Auser->id,
                'description' => 'User create user',
            ]);    
            
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function waitPartnerList(Request $request):JsonResponse
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
            $searchQuery = $request->query('search');
            
            $userBusinesses = UserHasBusiness::where('user_id', $user->id)->pluck('business_id')->toArray();

            $userIdsQuery = User::where('users.role', 'user')
            ->where('users.is_verify', 0)
            ->where('users.id', '<>', $user->id)
            ->join('user_has_businesses', 'users.id', '=', 'user_has_businesses.user_id')
            ->whereIn('user_has_businesses.business_id', $userBusinesses)
            ->distinct()
            ->pluck('users.id'); // Get distinct user IDs only

        // Step 2: Use the list of IDs to fetch the actual user data, including additional columns
        $query = User::whereIn('users.id', $userIdsQuery)
            ->orderBy('users.id', 'desc')
            ->join('cities', 'users.city_id', '=', 'cities.id')
            ->select('users.*', 'cities.name as city');
            
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = User::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('email', 'like', '%' . $searchQuery . '%')
                        ->orWhere('u_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
                        $query = $query->whereIn('users.id', $userIds);
                }
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            $data->getCollection()->transform(function ($user) {
                // Fetch business ids and names from businesses table via user_has_businesses
                $businesses = DB::table('user_has_businesses')
                    ->join('businesses', 'user_has_businesses.business_id', '=', 'businesses.id')
                    ->where('user_has_businesses.user_id', $user->id)
                    ->select('businesses.id', 'businesses.name')
                    ->get();
            
                // Append the business array (with id and name) to the user object
                $user->business_names = $businesses->map(function($business) {
                    return [
                        'id' => $business->id,
                        'name' => $business->name
                    ];
                })->toArray();
            
                return $user;
            });
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch invite list of users',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
