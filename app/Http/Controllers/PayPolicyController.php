<?php

namespace App\Http\Controllers;

use App\Models\PayPolicy;
use Exception;
use App\Models\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PayPolicyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PayPolicy::select('pay_policies.*');

            $query->where(function ($query) use ($searchQuery) {
                if ($searchQuery) {
                    $query->where('policy_code', 'like', '%' . $searchQuery . '%');
                }
            })
            ->orderBy('id', 'desc');

            // Execute the query with pagination
            $data = $query->paginate($perPage);
            if (empty($data)) throw new Exception('No data found', 404);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Pay policy listed successfully',
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
                if (!$user->hasBusinessPermission($businessId, 'create pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'basic_pay' => 'required',
                'loan_limit' => 'required',
                'bonus_percentage' => 'required',
                'allowance' => 'required',
                'deductions' => 'required',
                'tax_rate' => 'required'
            ],[
                'name.required' => 'Name is required',
                'basic_pay.required' => 'Basic pay is required',
                'loan_limit.required' => 'Loan limit is required',
                'bonus_percentage.required' => 'Bonus percentage is required',
                'allowance.required' => 'Allowance is required',
                'deductions.required' => 'Deductions is required',
                'tax_rate.required' => 'Tax rate is required'
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $policy_code = 'P-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PayPolicy::where('policy_code', $policy_code)->exists());
            $data = PayPolicy::create([
                'name' => strtoupper($request->name),
                'policy_code' => $policy_code,
                'basic_pay' => $request->basic_pay,
                'loan_limit' => $request->loan_limit,
                'bonus_percentage' => $request->bonus_percentage,
                'allowance' => $request->allowance,
                'deductions' => $request->deductions,
                'tax_rate' => $request->tax_rate
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Pay policy created successfully',
            ]);
            DB::commit();
            return response()->json($data,200);

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
                if (!$user->hasBusinessPermission($businessId, 'view pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PayPolicy::find($id);
            if (empty($data)) throw new Exception('No data found', 404);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Pay policy viewed successfully',
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
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'basic_pay' => 'required',
                'loan_limit' => 'required',
                'bonus_percentage' => 'required',
                'allowance' => 'required',
                'deductions' => 'required',
                'tax_rate' => 'required'
            ],[
                'name.required' => 'Name is required',
                'basic_pay.required' => 'Basic pay is required',
                'loan_limit.required' => 'Loan limit is required',
                'bonus_percentage.required' => 'Bonus percentage is required',
                'allowance.required' => 'Allowance is required',
                'deductions.required' => 'Deductions is required',
                'tax_rate.required' => 'Tax rate is required'
            ]);
            if ($validator->fails())throw new Exception($validator->errors(), 400);

            $data = PayPolicy::find($id);
            if(empty($data)) throw new Exception('No data found', 404);
            DB::beginTransaction();
            $data->update([
                'name' => strtoupper($request->name),
                'basic_pay' => $request->basic_pay,
                'loan_limit' => $request->loan_limit,
                'bonus_percentage' => $request->bonus_percentage,
                'allowance' => $request->allowance,
                'deductions' => $request->deductions,
                'tax_rate' => $request->tax_rate
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Pay policy Updated successfully',
            ]);
            DB::commit();
            return response()->json($data,200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required',
            ],[
                'Status.required' => 'Status is required',
            ]);
            if ($validator->fails())throw new Exception($validator->errors(), 400);

            $data = PayPolicy::find($id);
            if(empty($data)) throw new Exception('No data found', 404);
            $data->update([
                'status' => $request->status,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Pay policy Status Updated successfully',
            ]);
            DB::commit();
            return response()->json($data,200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function list(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list pay policy')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = PayPolicy::select('pay_policies.name','pay_policies.id');
            if ($searchQuery) {
                $query->where('pay_policies.name', 'like', '%' . $searchQuery . '%');
            }
            $data = $query->get();
            if (empty($data)) throw new Exception('No data found', 404);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch list of pay policies',
            ]);
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(["DB error" => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(["error" => $e->getMessage()], 400);
        }
    }
}
