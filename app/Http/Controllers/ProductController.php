<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected $user;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            
            if (!$user->can('list product')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $perPage = $request->query('per_page', 10);

            $data = Product::paginate($perPage);

            if ($data->isEmpty()) throw new Exception('No data found', 404);
            return response()->json($data);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if (!$user->can('create product')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $validator = Validator::make(
                $request->all(),[
                    'title'=>'required|string',
                    'descripton'=>'required|string',
                    'added_by'=>'required|string',
                    'category_id'=>'required|string',
                    'sub_category_id'=>'required|string',
                    'purchase_price'=>'required|string',
                    'sale_price'=>'required|numeric',
    
    
            ],[
    
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
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
        
            if (!$user->can('view products')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            $product = Product::find($id);
        
            return response()->json($product);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try{
            $product = Product::find($id);
        if (empty($product)) throw new Exception('No Product found', 404);
        $validator = Validator::make(
            $request->all(),[
                'title'=>'required|string',
                'descripton'=>'required|string',
                'added_by'=>'required|string',
                'category_id'=>'required|string',
                'sub_category_id'=>'required|string',


        ],[

        ]);
        if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
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
        try{
            $user = Auth::user();
        
            if (!$user->can('delete products')){
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
            $product = Product::find($id);
            if (empty($product)) throw new Exception('No Product found', 404);
            $product->delete();
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400); 
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
