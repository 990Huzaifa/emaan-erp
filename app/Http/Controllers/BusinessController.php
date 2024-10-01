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
            if (!$user->can('list businesses')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
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
            if($user->role == 'user'){
                if (!$user->can('create businesses')){
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                } 
            }

            $validator = Validator::make(
                $request->all(),[
                    'city_id'=>'required|numeric',
                    'name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'city_id.required'=>'City is Required',
                'city_id.numeric'=>'City is must be a numeric',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            // creating business
            $business = Business::create([
                'name'=> $request->name,
                'city_id'=> $request->city_id,
                'email' => $request->email,
            ]);
            $setupCode = generateSetupCode();   
            // creating user of business
            do {
                $u_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (User::where('u_code', $u_code)->exists());  
            $user = User::create([
                'name'=>$request->name,
                'u_code'=>$u_code,
                'city_id'=>$request->city_id,
                'email' => $request->email,
                'setup_code' => $setupCode,
                'setup_code_expiry' => Carbon::now()->addHours(24), // 24 hours
            ]);
            $setupUrl = route('setup-account', ['code' => $setupCode, 'id' => $user->id]);
            // sync permissions to user according to business
            $uhb = UserHasBusiness::create([
                'business_id'=>$business->id,
                'user_id'=>$user->id,
            ]);
            $allPermissions = Permission::all();
            $uhb->syncPermissions($allPermissions);
            // sending mail to business admin
            Mail::to('princehuzaifa990@gmail.com')->send(new UserMail([
                'message'=>'Please setup your account by clicking on the below link',
                'url' => $setupUrl
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
