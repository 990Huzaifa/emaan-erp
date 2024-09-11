<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

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
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
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
            return response()->json(['DB error' => $e->getMessage()], $e->getCode() ?:400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
