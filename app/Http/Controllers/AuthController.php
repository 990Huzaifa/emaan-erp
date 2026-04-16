<?php

namespace App\Http\Controllers;

use App\Services\MailingService;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Log;
use App\Models\Admin;
use App\Models\Business;
use App\Mail\UserMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use App\Models\PasswordResetToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected $accessToken;

        protected $externalMailService;

    public function __construct(MailingService $externalMailService)
    {
        $this->externalMailService = $externalMailService;
    }

    public function login(Request $request):JsonResponse
    {
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

                'type.required' => 'Login type is required.',
                'type.string' => 'Login type must be a string.',
                'type.in' => 'Login type Invalid.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $user = User::where('email',$request->email)->first();
            if (empty($user)) throw new Exception('Account not found.', 400);
            if($user->is_verify == 0) throw new Exception('Account not verified.', 400);
            if($user->status == 0) throw new Exception('Account not active.', 400);
            if (!Hash::check($request->password, $user->password)) throw new Exception('Invalid login credentials.', 404);
            // $user->tokens()->delete();
            $token = $user->createToken('authToken');
            $this->accessToken = $token->plainTextToken;
            Log::create([
                'user_id' => $user->id,
                'description' => 'User logged in',
            ]);
            $user->update([
                'last_login' => now(),
                'ip' => $request->ip(),
            ]);
            if($user->role == 'user'){
                $userBusinesses = UserHasBusiness::where('user_id', $user->id)->pluck('business_id');
            
                // Now fetch the business records based on the business IDs
                $businesses = Business::whereIn('id', $userBusinesses)->get();
                return response()->json(["access_token"=>$this->accessToken,"userInfo"=>$user,'role'=>'user','businesses'=>$businesses ]);
            }elseif ($user->role == 'admin') {
                return response()->json(["access_token"=>$this->accessToken,"userInfo"=>$user,'role'=>'admin']);
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
            $user->update([
                'login_business'=>$id,
            ]);
            return response()->json(['permissions'=>$permissionNames],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function setup(Request $request,$code): JsonResponse
    {
        try{
            $user = User::where('setup_code',$code)->first();
            if (empty($user)) throw new Exception('Invalid setup code', 400);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'phone'=>'nullable|string',
                    'cnic'=>'nullable|string',
                    'address'=>'nullable|string',
                    'cnic_back'=> 'nullable|image',
                    'cnic_front'=> 'nullable|image',
                    'avatar'=> 'nullable|image',
                    'password'=> 'required|string|min:8',
                    'confirm_password'=> 'required|string|min:8',
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
            if($request->password != $request->confirm_password) throw new Exception('Password Mismatch', 400);
            $user->update([
                'name'=>$request->name ?? null,
                'city_id'=>$request->city_id ?? $user->city_id,
                'phone'=>$request->phone ?? null,
                'address' => $request->address ?? null,
                'cnic'=>$request->cnic ?? null,
                'cnic_images'=> $cnic_images,
                'avatar' => $avatar,
                'password' => Hash::make($request->password),
                'setup_code' => null,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User setup completed',
            ]);
            return response()->json(['success'=>'Profile setup successfully.'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
        
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = validator(
                $request->all(),
                [
                    'email' => 'required|email|exists:users',
                ],
                [
                    'email.required' => 'Email Address required',
                    'email.email' => 'Invalid Email',
                    'email.exists' => 'Invalid Email Address',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $tokenExist = PasswordResetToken::where('email', $request->email)->exists();
            if ($tokenExist) PasswordResetToken::where('email', $request->email)->delete();

            $token = Str::random(60);
            PasswordResetToken::insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now()
            ]);

            $user = User::where('email', $request->email)->first();


            $this->externalMailService->sendView(
                    to: $request->email,
                    subject: 'Reset Your Password',
                    view: 'mails.user-mail',
                    data: [
                        'message'=> 'Please reset your password by clicking on the below link',
                        'url' => config('app.frontend_url').'/reset-password/'.$token,
                        'is_url'=>true,
                    ],
                    fromEmail: 'noreply@salesvince.com'
                );
            Log::create([
                'user_id' => $user->id,
                'description' => 'User forgot password',
            ]);
            return response()->json([
                'message' => 'Reset link sent successfully',
            ], 200);
        }catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()],400);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try{
            $validator = validator(
                $request->all(),
                [
                    'token' => 'required|string',

                    'password' => 'required|string|min:8',
                    'confirm_password' => 'required|string|min:8|same:password',
                ],
                [
                    'token.required' => 'Token required',

                    'password.required' => 'Password required',
                    'password.string' => 'Password must be a string',
                    'password.min' => 'Password must be at least 8 characters',

                    'confirm_password.required' => 'Confirm Password required',
                    'confirm_password.string' => 'Confirm Password must be a string',
                    'confirm_password.min' => 'Confirm Password must be at least 8 characters',
                    'confirm_password.same' => 'Confirm Password must be same as Password',
                ]
            );

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            $data  = PasswordResetToken::where('token', $request->token)->first();
            $user = User::where('email', $data->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            PasswordResetToken::where('token', $request->token)->delete();
            Log::create([
                'user_id' => $user->id,
                'description' => 'User reset password',
            ]);
            return response()->json([
                'message' => 'Password reset successfully',
            ], 200);
        }catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()],400);
        }
    }
    
    public function profile(): JsonResponse
    {
        try{
            $user = Auth::user();
            Log::create([
                'user_id' => $user->id,
                'description' => 'User profile',
            ]);
            return response()->json(['user' => $user], 200);
        }catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()],400);
        }
    }
}
