<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected $accessToken;

    public function login(Request $request):JsonResponse{
        try{
            $validator = Validator::make(
                $request->all(),[
                    'email'=>'required|string|email',
                    'password'=>'required|string',
                    'type'=> 'required|string|in:admin,user',
    
            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
    
                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
    
                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',

                'type.required' => 'Login type is required.',
                'type.string' => 'Login type must be a string.',
                'type.in' => 'Login type Invalid.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $user = User::where('email',$request->email)->first();
            if (empty($user)) throw new Exception('Account not found.', 404);
            if (!Hash::check($request->password, $user->password)) throw new Exception('Invalid login credentials.', 404);
            $this->accessToken = $user->createToken('authToken')->plainTextToken;

            if($user->role == 'user'){
                $businesses = UserHasBusiness::where('user_id',$user->id)->get();
                return response()->json(["access_token"=>$this->accessToken,"data"=>$user,'businesses'=>$businesses ]);
            }elseif ($user->role == 'admin') {
                return response()->json(["access_token"=>$this->accessToken,"data"=>$user]);
            }else{
                return response()->json(['error' => 'Invalid login'], 400);
            }
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()],400);
        }
    }

    public function loginPermissions($id):JsonResponse
    {
        try{
            $user = Auth::user();
            $business = UserHasBusiness::where('user_id',$user->id)->where('business_id',$id)->first();
            if (empty($business)) throw new Exception('No Register Business found', 404);
            $permissionNames = $business->getAllPermissions()->pluck('name');
            return response()->json(['permissions'=>$permissionNames],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function setup(Request $request,$code, $id): JsonResponse
    {
        try{
            $user = User::findOrFail($id);
            if ($user->setup_code !== $code) throw new Exception('Invalid setup code', 400);
            if (Carbon::now()->greaterThan($user->setup_code_expiry))  throw new Exception('Setup code has expired', 400);
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
                $front_image_name = 'cnic_' . $id . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('user-cnic'), $front_image_name);
                $cnic_front = 'user-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . $id . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('user-cnic'), $back_image_name);
                $cnic_back = 'user-cnic/' . $back_image_name;
            }
            $user->update([
                'city'=>$request->city,
                'phone'=>$request->phone,
                'address' => $request->address,
                'joining_date' => Date::now(),
                'cnic'=>$request->cnic,
                'cnic-images'=>[$cnic_front, $cnic_back],
                'avatar' => $avatar,
                'password' => Hash::make($request->password),
            ]);
            return response()->json(['success'=>'setup code verified'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
        
    }
}
