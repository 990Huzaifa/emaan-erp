<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index(Request $request):JsonResponse
    {
        try{
            $perPage = $request->query('per_page', 10);

            $data = Role::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()],  400);
        }
    }


    public function store(Request $request):JsonResponse
    {
        try{
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string|max:115|unique:roles'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
                'name.max'=>'Name cannot exceed 115 characters.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $permission = Role::create([
                'name'=>$request->name
            ]);

            return response()->json($permission);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()],  400);
        }
    }

    public function addPermissionToRole($roleID):JsonResponse
    {
        try{

            $role = Role::findOrFail($roleID);
            if ($role == '' && $role == null) throw new Exception('No data found', 404);
            $permission = Permission::get();
            $role_has_permissions = DB::table('role_has_permissions')
            ->where('role_has_permissions.role_id',$roleID)
            ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')->all();
            return response()->json([
                'role_id' => $roleID,
                'permission' => $permission,
                'role_has_permissions' => $role_has_permissions
            ]);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()],  400);
        }

    }
    public function syncPermission(Request $request, $roleID):JsonResponse
    {
        try{
            $validator = Validator::make(
                $request->all(),[
                    'permission'=>'required|array'
            ],[
                'permission.required' => 'Permission is Required',
                'permission.array' => 'Permission is must be an Array',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $role = Role::findOrFail($roleID);
            if ($role == '' && $role == null) throw new Exception('No data found', 404);
            $role->syncPermissions($request->permission);
            return response()->json('success',200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()],  400);
        }
    }
}
