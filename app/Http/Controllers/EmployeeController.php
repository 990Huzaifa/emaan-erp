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
                'designation' => 'required',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'pay_roll' => 'required|numeric'
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

                'designation.required' => 'Designation is required',

                'image.image' => 'Image must be an image',
                'image.mimes' => 'Image must be a file of type: jpeg, png, jpg, gif, svg',
                'image.max' => 'Image must not exceed 2MB',

                'pay_roll.required' => 'Pay roll is required',
                'pay_roll.numeric' => 'Pay roll must be a number',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $profilePic = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = 'profile-' . $user->id . '-' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('employee-image'), $image_name);
                $profilePic = 'employee-image/' . $image_name;
            }
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
                'address' => $request->address,
                'image' => $profilePic,
            ]);
            $COA->update([
                'ref_id' => $employee->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create employee',
            ]);

            return response()->json($employee);

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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $employee = DB::table('employees')
            ->join('cities', 'employees.city_id', '=', 'cities.id')
            ->where('employees.id', $id)
            ->select('employees.*', 'cities.name as city_name') // Select customer fields and city name only
            ->first();
            if (empty($employee)) throw new Exception('No Employee found', 404);
            return response()->json($employee,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'update employee')) {
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
                'designation' => 'required',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'pay_roll' => 'required|numeric'
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

                'designation.required' => 'Designation is required',

                'image.image' => 'Image must be an image',
                'image.mimes' => 'Image must be a JPEG, PNG, JPG, GIF or SVG file',
                'image.max' => 'Image size must not exceed 2MB',

                'pay_roll.required' => 'Pay Roll is required',
                'pay_roll.numeric' => 'Pay Roll must be a number',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $employee = Employee::find($id);
            if (empty($employee)) throw new Exception('No Employee found', 404);
            $oldImage = $employee->image;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = 'profile-' . $user->id . '-' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('employee-image'), $image_name);
                $profilePic = 'employee-image/' . $image_name;
                if (!empty($oldImage)) {
                    unlink(public_path($oldImage));
                }
                $employee->update([
                    'image' => $profilePic,
                ]);
            }
            $employee->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'address' => $request->address,
                'designation' => $request->designation,
                'pay_roll' => $request->pay_roll
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update employee',
            ]);
            return response()->json($employee,200);

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
}
