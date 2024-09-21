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
                    'city'=>'required|string',
                    'business_name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'city.required'=>'City is Required',
                'city.string'=>'City is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $business = Business::create([
                'name'=> $request->business_name,
                'city'=> $request->city,
                'email' => $request->email,
            ]);
            
            $setupCode = $this->generateSetupCode();            
            $user = User::create([
                'name'=>$request->name,
                'city'=>$request->city,
                'email' => $request->email,
                'setup_code' => $setupCode,
            ]);
            $setupUrl = route('setup-account', ['code' => $setupCode, 'id' => $user->id]);
            $uhb = UserHasBusiness::create([
                'business_id'=>$business->id,
                'user_id'=>$user->id,
            ]);
            $allPermissions = Permission::all();
            $uhb->syncPermissions($allPermissions);
            Mail::to('princehuzaifa990@gmail.com')->send(new UserMail([
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

    private function generateSetupCode($length = 12)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
