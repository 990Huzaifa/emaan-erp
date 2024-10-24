<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'list quotation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);
            $query = $request->query('query', null);
            $quotation = Quotation::where('business_id', $businessId);
            if ($query) {
                $quotation = $quotation->where('quotation_no', 'like', '%' . $query . '%');
            }
            $quotation = $quotation->paginate($perPage, ['*'], 'page', $page);
            return response()->json($quotation);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);            
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role == 'user') {
                if (!$user->hasBusinessPermission($businessId, 'create quotation')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validate = Validator::make(
                $request->all(),[
                    'quotation_date'=>'required|date',
                    'customer_id'=>'required|exists:customers,id',
                    'products' => 'required|array',

            ],[
                'quotation_date.required'=>'Quotation date is required',
            ]
            );
            if ($validate->fails()) throw new Exception($validate->errors()->first(), 400);
            $quotation = Quotation::create([
                'quotation_date'=>$request->quotation_date,
                'customer_id'=>$request->customer_id,
                'business_id'=>$businessId
            ]);
            return response()->json($quotation);
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
