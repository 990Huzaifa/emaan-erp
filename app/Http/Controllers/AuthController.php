<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request):JsonResponse{
        try{
            $validator = Validator::make(
                $request->all(),[
                    'email'=>'required|string|email',
                    'password'=>'required|string',
    
            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
    
                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
    
                'password.required' => 'Password is required.',
                'password.string' => 'Password must be a string.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
                $user = User::where('email',$request->email)->first();
                if (empty($user) || !Hash::check($request->password, $user->password)) throw new Exception('Invalid login credentials.', 404);
                $accessToken = $user->createToken('authToken')->plainTextToken;
                // $permissionNames  = $user->getAllPermissions();
                $permissionNames = $user->getAllPermissions()->pluck('name');
                $permission=['user'=>$permissionNames];
                return response()->json([$accessToken, $user, $permission]);
            
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
