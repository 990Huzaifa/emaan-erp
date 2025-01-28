<?php

namespace App\Http\Controllers;

use App\Models\OpeningBalance;
use App\Models\Balance;
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = Employee::orderBy('id', 'desc')
            ->join('cities', 'employees.city_id', '=', 'cities.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('employees.*', 'cities.name as city', 'departments.name as department', 'designations.name as designation');
            $query = $query->where('business_id',$user->login_business);
            

            if (!empty($searchQuery)) {
                $query = $query->where('employees.e_code', 'like', '%' . $searchQuery . '%');
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
            if ($user->role != 'admin') {
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
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'cnic_front' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'cnic_back' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'pay_policy_id' => 'required|exists:pay_policies,id',
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

                'image.image' => 'Image must be an image',
                'image.mimes' => 'Image must be a file of type: jpeg, png, jpg, gif, svg',
                'image.max' => 'Image must not exceed 2MB',

                'cnic_front.image' => 'CNIC Front must be an image',
                'cnic_front.mimes' => 'CNIC Front must be a file of type: jpeg, png, jpg, gif, svg',
                'cnic_front.max' => 'CNIC Front must not exceed 2MB',

                'cnic_back.image' => 'CNIC Back must be an image',
                'cnic_back.mimes' => 'CNIC Back must be a file of type: jpeg, png, jpg, gif, svg',
                'cnic_back.max' => 'CNIC Back must not exceed 2MB',

                'department_id.required' => 'Department is required',
                'department_id.exists' => 'Department does not exist',

                'designation_id.required' => 'Designation is required',
                'designation_id.exists' => 'Designation does not exist',

                'pay_policy_id.required' => 'Pay Policy is required',
                'pay_policy_id.exists' => 'Pay Policy does not exist',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            do {
                $e_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Employee::where('e_code', $e_code)->exists());

            // validate coa

            DB::beginTransaction();
            $acc = ChartOfAccount::Where('name','EMPLOYEES SALARY')->first();
            if(empty($acc)) throw new Exception('EMPLOYEES SALARY COA not found', 404);

            $name = strtoupper($request->name);
            $COA = createCOA($name,$acc->code);
            
            // images upload creation
            
            $profilePic = null;
            $cnic_front = null;
            $cnic_back = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = 'profile-' . $COA->id . '-' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('employee-image'), $image_name);
                $profilePic = 'employee-image/' . $image_name;
            }
            if ($request->hasFile('cnic_front')) {
                $front_image = $request->file('cnic_front');
                $front_image_name = 'cnic_' . $COA->id . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('employee-cnic'), $front_image_name);
                $cnic_front = 'employee-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . $COA->id . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('employee-cnic'), $back_image_name);
                $cnic_back = 'employee-cnic/' . $back_image_name;
            }
            $cnic_images = [$cnic_front, $cnic_back];
            
            
            $employee = Employee::create([
                'name' => $name,
                'e_code' => $e_code,
                'business_id' => $businessId,
                'acc_id' => $COA->id,
                'pay_policy_id' => $request->pay_policy_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'phone' => $request->phone,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'address' => $request->address,
                'cnic'=>$request->cnic ?? null,
                'image' => $profilePic,
                'cnic_images'=> json_encode($cnic_images),
                'joining_date' => $request->joining_date,
                'added_by' => $user->id
                ]);
            $COA->update([
                'ref_id' => $employee->id,
            ]);
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            Balance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User create employee',
            ]);
            DB::commit();
            return response()->json($employee);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
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
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $employee = Employee::orderBy('employees.id', 'desc')
            ->leftjoin('pay_policies', 'employees.pay_policy_id', '=', 'pay_policies.id')
            ->join('cities', 'employees.city_id', '=', 'cities.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('employees.*', 'cities.name as city', 'departments.name as department', 'designations.name as designation', 'pay_policies.*')
            ->where('employees.id', $id)
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
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit employee')) {
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
                'designation_id' => 'required|exists:designations,id',
                'department_id' => 'required|exists:departments,id',
                'pay_policy_id' => 'required|exists:pay_policies,id',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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

                'designation_id.required' => 'Designation is required',
                'designation_id.exists' => 'Designation does not exist',

                'department_id.required' => 'Department is required',
                'department_id.exists' => 'Department does not exist',

                'image.image' => 'Image must be an image',
                'image.mimes' => 'Image must be a JPEG, PNG, JPG, GIF or SVG file',
                'image.max' => 'Image size must not exceed 2MB',
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
            $name = strtoupper($request->name);
            $employee->update([
                'name' => $name,
                'phone' => $request->phone,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'address' => $request->address,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'pay_policy_id' => $request->pay_policy_id,
            ]);
            ChartOfAccount::where('id', $employee->acc_id)->update([
                'name' => $name
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

    public function list():JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list employee')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $employee = Employee::select('id','name')->get();

            return response()->json($employee,200);
            
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
