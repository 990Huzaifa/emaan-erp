<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
            if($request->type == 'user'){
                $user = User::where('email',$request->email)->first();
                if (empty($user)) throw new Exception('Account not found.', 404);
                if (!Hash::check($request->password, $user->password)) throw new Exception('Invalid login credentials.', 404);
                $this->accessToken = $user->createToken('authToken')->plainTextToken;
                // $permissionNames  = $user->getAllPermissions();
                // $permissionNames = $user->getAllPermissions()->pluck('name');
                // $permission=['user'=>$permissionNames];
                $businesses = UserHasBusiness::where('user_id',$user->id)->get();
                return response()->json([$this->accessToken,$user,$businesses ]);
            }elseif ($request->type == 'admin') {
                $admin = Admin::where('email',$request->email)->first();
                if (empty($admin) || !Hash::check($request->password, $admin->password)) throw new Exception('Invalid login credentials.', 404);
                $accessToken = $admin->createToken('authToken')->plainTextToken;
                return response()->json([$admin,$accessToken]);
            }else{
                return response()->json(['Type error' => 'Invalid login type'], 400);
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

    public function setup($code, $id)
    {

        dd('helo');
        // Verify the setup code and user ID
        $user = User::findOrFail($id);
        if ($user->setup_code !== $code) {
            return redirect()->route('home')->with('error', 'Invalid setup code.');
        }

        // Proceed with account setup
        // ...

        return view('auth.setup', compact('user'));
    }
}
