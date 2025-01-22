<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Exception;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class DesignationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list designation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = Designation::orderBy('id', 'desc');

            if (!empty($searchQuery)) {
                $query = $query->where('d_code', 'like', '%' . $searchQuery . '%')
                ->orWhere('name', 'like', '%' . $searchQuery . '%');
            }
            $data = $query->paginate($perPage);

            Log::create([
                'user_id' => $user->id,
                'description' => 'designation listed successfully',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(["DB error" => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create designation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                        "name" => "required|string|unique:departments,name",
                        "description" => "nullable|string",
                        'department_id' => 'required|exists:departments,id',
                    ],[
                        "name.required" => "Name is required",
                        "name.string" => "Name must be a string",
                        "name.unique" => "Name already exists",
                        "description.string" => "Description must be a string",
                    ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $d_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Designation::where('d_code', $d_code)->exists());
            $data = Designation::create([
                "name" => strtoupper($request->name),
                "department_id" => $request->department_id,
                "description" => $request->description ?? null,
                "d_code" => $d_code
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Designation created successfully',
            ]);
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view designation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Designation::select('designations.*','departments.name as department_name')
            ->leftJoin('departments', 'departments.id', '=', 'designations.department_id')
            ->find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Designation view successfully',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()], 400);
        }
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
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit designation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                        "name" => "required|string|unique:departments,name,".$id,
                        "description" => "nullable|string",
                    ],[
                        "name.required" => "Name is required",
                        "name.string" => "Name must be a string",
                        "name.unique" => "Name already exists",
                        "description.string" => "Description must be a string",
                    ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $data = Designation::find($id);
            $data->update([
                "name" => strtoupper($request->name),
                "description" => $request->description ?? null,
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Designation updated successfully',
            ]);
            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(["DB error" => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit designation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                "status" => "required"
            ],[
                "status.required" => "Status is required",
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            $data = Designation::find($id);
            if($data->status == $request->status) throw new Exception('Status already updated', 400);
            $data->update([
                "status" => $request->status
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Designation updated successfully',
            ]);
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    public function list(): JsonResponse
    {
        try{
            $data = Designation::select('id','name')->get();

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function filterIndex($id):JsonResponse
    {
        try{
            $data = Designation::where('department_id',$id)->get();
            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
