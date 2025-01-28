<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class MeasureUnitController extends Controller
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
                if (!$user->hasBusinessPermission($businessId, 'list measurement unit')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            $data = MeasurementUnit::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

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
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'create measurement unit')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'slug'=>'required|string',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'slug.required'=>'Slug is Required',
                'slug.string'=>'Slug is must be a string',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $data = MeasurementUnit::create([
                'name' => $request->name,
                'slug' => $request->slug,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Measurement Unit created successfully',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view measurement unit')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $data = MeasurementUnit::find($id);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User show measurement unit',
            ]);
            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'edit measurement unit')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'slug'=>'required|string',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'slug.required'=>'Slug is Required',
                'slug.string'=>'Slug is must be a string',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $data = MeasurementUnit::find($id);
            $data->update([
                'name' => $request->name,
                'slug' => $request->slug,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Measurement Unit updated successfully',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function list(): JsonResponse
    {
        try{
            $data = MeasurementUnit::all();

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
