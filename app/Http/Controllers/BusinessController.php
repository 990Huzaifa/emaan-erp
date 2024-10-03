<?php

namespace App\Http\Controllers;

use App\Mail\UserMail;
use Exception;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list businesses')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $data = Business::paginate($perPage);
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
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create businesses')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'city'=>'required|numeric',
                    'name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',
                    'password'=>'required|string|min:8',
                    'confirm_password'=>'required|string|min:8',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'city.required'=>'City is Required',
                'city.numeric'=>'City is must be a numeric',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters.',

                'confirm_password.required' => 'Confirm Password is required.',
                'confirm_password.min' => 'Confirm Password must be at least 8 characters.',

            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            if($request->password != $request->confirm_password) throw new Exception('Password Mismatch', 400);
            DB::beginTransaction();
            // creating business
            $business = Business::create([
                'name'=> $request->name,
                'city_id'=> $request->city,
                'email' => $request->email,
            ]);
            $setupCode = generateSetupCode();   
            // creating user of business
            do {
                $u_code = bin2hex(random_bytes(32)); 
            } while (User::where('u_code', $u_code)->exists());  
            $user = User::create([
                'name'=>$request->name,
                'u_code'=>$u_code,
                'city_id'=>$request->city,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            // $setupUrl = route('setup-account', ['code' => $setupCode])
            // $setupUrl = config('app.frontend_url').'/setup-user/'.$setupCode;
            // sync permissions to user according to business
            $uhb = UserHasBusiness::create([
                'business_id'=>$business->id,
                'user_id'=>$user->id,
            ]);
            $allPermissions = Permission::all();
            $uhb->syncPermissions($allPermissions);
            // sending mail to business admin
            Mail::to($request->email)->send(new UserMail([
                'message'=>'You are Admin of the business now you have all the access of this business.',
            ])); 
            DB::commit();
            return response()->json(['message'=>'Mail has been sent to business Admin']);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()],400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()],400);
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
