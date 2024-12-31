<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if (!$user->hasBusinessPermission($businessId, 'list voucher')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $vouchers = Voucher::where('business_id', $businessId)->paginate($perPage);
            return response()->json($vouchers);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 500);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create voucher')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string',
                    'type'=>'required|string|in:sales,purchase,payment,expense',
                    'acc_id' => 'required|exists:chart_of_accounts,id',
                    'ref_id' => 'required',
                    'amount'=>'required|numeric',
                    'description'=>'required|string',
                ],[
                    'name.required'=>'Name is Required',
                    'name.string'=>'Name is must be a string',

                    'type.required'=>'Type is Required',
                    'type.string'=>'Type must be a string',
                    'type.in'=>'Type Invalid',

                    'acc_id.required'=>'Account is Required',
                    'acc_id.exists'=>'Account does not exist',

                    'ref_id.required'=>'Reference is Required',

                    'amount.required'=>'Amount is Required',
                    'amount.numeric'=>'Amount must be a number',

                    'description.required'=>'Description is Required',
                    'description.string'=>'Description must be a string',
                ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            DB::beginTransaction();
            do {
                $code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Voucher::where('code', $code)->exists());  
            $voucher = Voucher::create([
                'name'=>$request->name,
                'business_id'=>$businessId,
                'acc_id'=>$request->acc_id,
                'ref_id'=>$request->ref_id,
                'type'=>$request->type,
                'code'=>$code,
                'amount'=>$request->amount,
                'approved_by'=>$user->id,
                'description'=>$request->description,
            ]);
            DB::commit();
            Log::create([
                'user_id' => auth()->user()->id,
                'description' => 'User created voucher',
            ]);
            return response()->json($voucher);
        }catch(QueryException $e){
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }catch(Exception $e){
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
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
