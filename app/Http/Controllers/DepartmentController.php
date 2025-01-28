<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Exception;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'list department')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = Department::orderBy('id', 'desc');

            if (!empty($searchQuery)) {
                $query = $query->where('dpt_code', 'like', '%' . $searchQuery . '%')
                ->orWhere('name', 'like', '%' . $searchQuery . '%');
            }
            $data = $query->paginate($perPage);

            Log::create([
                'user_id' => $user->id,
                'description' => 'department listed successfully',
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create department')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                        "name" => "required|string|unique:departments,name",
                        "description" => "nullable|string",
                    ],[
                        "name.required" => "Name is required",
                        "name.string" => "Name must be a string",
                        "name.unique" => "Name already exists",
                        "description.string" => "Description must be a string",
                    ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $dpt_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Department::where('dpt_code', $dpt_code)->exists());
            $data = Department::create([
                "name" => strtoupper($request->name),
                "description" => $request->description ?? null,
                "dpt_code" => $dpt_code
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Department created successfully',
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view department')) {
                    return response()->json([ 
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Department::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Department view successfully',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit department')) {
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
            $data = Department::find($id);
            if(empty($data)) throw new Exception('No data found', 404);
            if (empty($data)) throw new Exception('No data found', 404);
            $data->update([
                "name" => strtoupper($request->name),
                "description" => $request->description ?? null,
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Department updated successfully',
            ]);
            return response()->json($data, 200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage(),], 400);
        
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit department')) {
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
            $data = Department::find($id);
            if(empty($data)) throw new Exception('No data found', 404);
            if($data->status == $request->status) throw new Exception('Status already updated', 400);
            $data->update([
                "status" => $request->status
            ]);
            DB::commit();
            Log::create([
                'user_id' => $user->id,
                'description' => 'Department status updated successfully',
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
            $data = Department::select('id','name')->get();

            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
