<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
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
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list product')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create product')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'title'=>'required|string',
                    'description'=>'required|string',
                    'added_by'=>'required|string',
                    'category_id'=>'required|string',
                    'sub_category_id'=>'required|string',
                    'purchase_price'=>'required|string',
                    'sale_price'=>'required|numeric',
                    'measurement_unit_id'=>'required|string|exists:measurement_units,id',
                    'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:8192',
    
    
            ],[
                'title.required'=>'Title is Required',
                'title.string'=>'Title is must be a string',

                'descripton.required'=>'Description is Required',
                'descripton.string'=>'Description is must be a string',

                'added_by.required'=>'Added By is Required',
                'added_by.string'=>'Added By is must be a string',

                'category_id.required'=>'Category is Required',
                'category_id.string'=>'Category is must be a string',

                'sub_category_id.required'=>'Sub Category is Required',
                'sub_category_id.string'=>'Sub Category is must be a string',

                'purchase_price.required'=>'Purchase Price is Required',
                'purchase_price.string'=>'Purchase Price is must be a string',

                'sale_price.required'=>'Sale Price is Required',
                'sale_price.string'=>'Sale Price is must be a string',

                'measurement_unit_id.required'=>'Measurement Unit is Required',
                'measurement_unit_id.string'=>'Measurement Unit is must be a string',


                'image.image'=>'Image is must be a image',
                'image.mimes'=>'Image is must be a image',
                'image.max'=>'Image is must be a image',


            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $sku_no = generateSKU($request->title,$request->category_id);
            do {
                $p_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Product::where('p_code', $p_code)->exists());
            $acc = ChartOfAccount::where('name',"INVENTORY")->first();
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);
            $COA = createCOA($request->title,$acc->code);
            $image = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('product-image'), $image_name);
                $image = 'product-image/' . $image_name;
            }
            $product = Product::create([
                'title' => $request->title,
                'p_code' => $p_code,
                'sku' => $sku_no,
                'measurement_unit_id' => $request->measurement_unit_id,
                'acc_id' => $acc->id,
                'image' => $image,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'purchase_price' => $request->purchase_price,
                'sale_price' => $request->sale_price,
                'added_by' => $user->id,
            ]);
            
            return response()->json($product);
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
