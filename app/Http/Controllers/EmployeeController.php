<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = Employee::orderBy('id', 'desc')->join('cities', 'employees.city_id', '=', 'cities.id')
            ->select('employees.*', 'cities.name as city');
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list customers')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
                $query = $query->where('business_id',$businessId);
            }
            

            if (!empty($searchQuery)) {
                $customerIds = Employee::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('e_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found Employees IDs
                    $query = $query->whereIn('id', $customerIds);
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);

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
    public function store(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'phone' => 'required|digits:11|regex:/^([0-9\s\-\+\(\)]*)$/',
                'email' => 'required',
                'city_id' => 'required|exists:cities,id',
                'address' => 'required|max:255',
            ],[
                'name.required' => 'Name is required',
                
                'phone.required' => 'Phone is required',
                'phone.digits' => 'Phone must be 11 digits',
                'phone.regex' => 'Phone must be a valid phone number',

                'email.required' => 'Email is required',

                'city_id.required' => 'City is required',
                'city_id.exists' => 'City does not exist',

                'address.required' => 'Address is required',
                'address.max' => 'Address must be less than 255 characters',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            do {
                $e_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Employee::where('e_code', $e_code)->exists());

            // validate coa
            $acc = ChartOfAccount::Where('name','EMPLOYEE')->first();
            if(empty($acc)) throw new Exception('Employee COA not found', 404);

            $COA = createCOA($request->name,$acc->code);
            $employee = Employee::create([
                'name' => $request->name,
                'e_code' => $e_code,
                'phone' => $request->phone,
                'email' => $request->email,
                'city_id' => $request->city_id,
            ]);
            $COA->update([
                'ref_id' => $employee->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create employee',
            ]);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
