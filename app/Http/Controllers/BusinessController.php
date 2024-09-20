<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            
            if (!$user->can('list product')){
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

            
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'cnic'=>'required|string|max:14|unique:users,cnic',
                    'city'=>'required|string',
                    'business_name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',
                    'password'=>'required|string',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'city.required'=>'City is Required',
                'city.string'=>'City is must be a string',

                'cnic.required' => 'CNIC is required.',
                'cnic.max' => 'CNIC cannot exceed 18 characters.',
                'cnic.unique' => 'This CNIC is already in use.',
                'cnic.string'=>'CNIC is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',
                'password.min' => 'Password must be at least 8 characters long.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $business = Business::create([
                'name'=> $request->business_name,
                'city'=> $request->city,
                'email' => $request->email,
            ]);
            
            
            $user = User::create([
                'name'=>$request->name,
                'city'=>$request->city,
                'cnic'=>$request->cnic,
                'business_id'=>$business->id,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $allPermissions = Permission::all();
            $user->syncPermissions($allPermissions);
            DB::commit();
        
            return response()->json($user);
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
