<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Constraint\IsEmpty;

class UserController extends Controller
{
    public function index(Request $request):JsonResponse
    {
        try{
            $perPage = $request->query('per_page', 10);

            $data = User::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function store(Request $request):JsonResponse{
        try{
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'email'=>'required|email|string|unique:users,email',
                    'password'=>'required|string',
                    'permissions'=>'required|array'

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
            $user = User::create([
                'name'=>$request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $user->syncPermissions($request->permissions);
        
            return response()->json($user);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function show($id):JsonResponse{
        try{
            $user = User::findOrFail($id);

            if (empty($user)) throw new Exception('No User found', 404);

            $permissionNames  = $user->getAllPermissions()->pluck('name');
            $permission=['user'=>$permissionNames];
            return response()->json(['data'=>$user, 'permissions' =>$permission],200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
