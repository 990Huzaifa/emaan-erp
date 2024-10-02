<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
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

class UserController extends Controller
{

    protected $user;

    public function index(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            $data = User::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request):JsonResponse{
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            dd('test-done');
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',
                    'business_ids'=>'required|array'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'business_ids.required' => 'Business_ids is required.',
                'business_ids.array' => 'Business_ids must be type array.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            do {
                $u_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (User::where('u_code', $u_code)->exists());
            $setupCode = generateSetupCode(); 
            $user = User::create([
                'name'=>$request->name,
                'u_code'=>$u_code,
                'email' => $request->email,
                'setup_code' => $setupCode,
                'setup_code_expiry' => Carbon::now()->addHours(24), // 24 hours
            ]);
            $setupUrl = route('setup-account', ['code' => $setupCode, 'id' => $user->id]);
            // sync permissions to user according to business
            foreach ($request->permissions as $businessId => $permissions) {
                $uhb = UserHasBusiness::create([
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                ]);
    
                $uhb->syncPermissions($permissions);
            }
            // sending mail to business admin
            Mail::to($request->email)->send(new UserMail([
                'url' => $setupUrl
            ])); 
        
            
        
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id):JsonResponse{
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'view users')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $user = User::findOrFail($id);

            if (empty($user)) throw new Exception('No User found', 404);

            
            return response()->json(['data'=>$user],200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id):JsonResponse{
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
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
                    'name'=>'required|string'
    
            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
    
                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',
    
                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',
                'password.min' => 'Password must be at least 8 characters long.',
    
                'permissions.required' => 'Roles is required.',
                'permissions.array' => 'Roles must be type array.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = null;
            $old_avatar = $user->avatar;
            if ($request->hasFile('avatar')) {
                if (!empty($old_avatar)) {
                    if (file_exists(public_path($old_avatar))) {
                        unlink(public_path($old_avatar));
                    }
                }
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('user-avatar'), $image_name);
                $avatar = 'user-avatar/' . $image_name;
            }else{
                $avatar = $old_avatar;
            }
            $user->update([
                'name'=>$request->name,
                'avatar' => $avatar,
                'phone'=>$request->phone,
                'address' => $request->address,
            ]);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request, $id):JsonResponse{
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
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
                    'name'=>'required|string'
    
            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
    
                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',
    
                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',
                'password.min' => 'Password must be at least 8 characters long.',
    
                'permissions.required' => 'Roles is required.',
                'permissions.array' => 'Roles must be type array.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $user->update([
                'name'=>$request->name
            ]);
            $user->syncPermissions($request->permissions);

            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function verify(Request $request, $id):JsonResponse{
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Check if the user has the required permission
            if ($user->role == 'user') {
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

            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
