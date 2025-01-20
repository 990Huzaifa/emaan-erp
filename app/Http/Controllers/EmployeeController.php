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
                'cnic_front' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'cnic_back' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'payroll' => 'required|numeric',
                'is_allowance' => 'required|boolean',
                'allowance_cycle' => 'required_if:is_allowance,true|in:monthly,yearly',
                'allowance' => 'required_if:is_allowance,true|numeric',
                'is_tax' => 'required|boolean',
                'tax_cycle' => 'required_if:is_tax,true|in:monthly,yearly',
                'tax' => 'required_if:is_tax,true|numeric',
                'is_bonus' => 'required|boolean',
                'bonus_cycle' => 'required_if:is_bonus,true|in:monthly,yearly',
                'bonus' => 'required_if:is_bonus,true',
                'is_loan' => 'required|boolean',
                'loan' => 'required_if:is_loan,true'
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

                'payroll.required' => 'Pay roll is required',
                'payroll.numeric' => 'Pay roll must be a number',
                
                'is_allowance.required' => 'Allowance is required',
                'allowance_cycle.required_if' => 'Allowance cycle is required',
                'allowance_cycle.in' => 'Allowance cycle must be either monthly or yearly',

                'allowance.required_if' => 'Allowance is required',
                'allowance.numeric' => 'Allowance must be a number',

                'is_tax.required' => 'Tax is required',
                'tax_cycle.required_if' => 'Tax cycle is required',
                'tax_cycle.in' => 'Tax cycle must be either monthly or yearly',

                'tax.required_if' => 'Tax is required',
                'tax.numeric' => 'Tax must be a number',

                'is_bonus.required' => 'Bonus is required',
                'bonus_cycle.required_if' => 'Bonus cycle is required',
                'bonus_cycle.in' => 'Bonus cycle must be either monthly or yearly',

                'bonus.required_if' => 'Bonus is required',
                'bonus.numeric' => 'Bonus must be a number',

                'is_loan.required' => 'Loan is required',

                'loan.required_if' => 'Loan is required',
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
                'phone' => $request->phone,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'address' => $request->address,
                'cnic'=>$request->cnic ?? null,
                'image' => $profilePic,
                'cnic_images'=> json_encode($cnic_images),
                'designation' => $request->designation,
                'payroll' => $request->payroll,
                'is_allowance' => $request->is_allowance,
                'allowance_cycle' => $request->allowance_cycle ?? null,
                'allowance' => $request->allowance ?? 0.00,
                'is_tax' => $request->is_tax,
                'tax_cycle' => $request->tax_cycle ?? null,
                'tax' => $request->tax ?? 0.00,
                'is_bonus' => $request->is_bonus,
                'bonus_cycle' => $request->bonus_cycle ?? null,
                'bonus' => $request->bonus ?? 0.00,
                'is_loan' => $request->is_loan,
                'loan' => $request->loan ?? 0.00,
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
                'pay_roll' => $request->pay_roll,
                'is_allowance' => $request->is_allowance,
                'allowance_cycle' => $request->allowance_cycle ?? null,
                'allowance' => $request->allowance ?? 0.00,
                'is_tax' => $request->is_tax,
                'tax_cycle' => $request->tax_cycle ?? null,
                'tax' => $request->tax ?? 0.00,
                'is_bonus' => $request->is_bonus,
                'bonus_cycle' => $request->bonus_cycle ?? null,
                'bonus' => $request->bonus ?? 0.00,
                'is_loan' => $request->is_loan,
                'loan' => $request->loan ?? 0.00
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
