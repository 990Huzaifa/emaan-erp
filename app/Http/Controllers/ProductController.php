<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Log;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use App\Models\Balance;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Response;
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
            $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list products')) {
            if ($user->role == 'user') {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $isActive = $request->query('is_active');
            $searchQuery = $request->query('search');

            $query = Product::orderBy('id', 'desc');
            $query->select(
                'products.*',
                'product_categories.name as product_category', // Assuming 'name' is the category field
                'product_sub_categories.name as product_sub_category' // Assuming 'name' is the subcategory field
            )
            ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->leftJoin('product_sub_categories', 'products.sub_category_id', '=', 'product_sub_categories.id'); // Assuming left join for optional subcategory;
    

            if ($isActive === 'active') {
                $query = $query->where('is_active', 1);
            } elseif ($isActive === 'inactive') {
                $query = $query->where('is_active', 0);
            }
            if(isset($category_id)){
                $query = $query->where('products.category_id', $category_id);
            }
            if (!empty($searchQuery)) {
                // Check if the search query is numeric to search by order ID
                if (is_numeric($searchQuery)) {
                    $query = $query->where('products.id', $searchQuery);
                } else {
                    // Otherwise, search by user name or email
                    $userIds = Product::where('title', 'like', '%' . $searchQuery . '%')
                        ->orWhere('sku', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                    // Filter orders by the found user IDs
                    $query = $query->whereIn('products.id', $userIds);
                }
            }
            $data = $query->paginate($perPage);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User list product',
            ]);
            
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
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),[
                    'title'=>'required|string',
                    'brand_name'=>'nullable|string',
                    'terms_of_payment'=>'nullable|string',
                    'description'=>'required|string',
                    'category_id'=>'required|string',
                    'sub_category_id'=>'required|string',
                    'purchase_price'=>'required|string',
                    'sale_price'=>'required|numeric',
                    'sales_tax_rate'=>'required|numeric',
                    'measurement_unit_id'=>'required|string|exists:measurement_units,id',
                    'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:8192',
    
    
            ],[
                'title.required'=>'Title is Required',
                'title.string'=>'Title is must be a string',

                'brand_name.string'=>'Brand Name is must be a string',

                'descripton.required'=>'Description is Required',
                'descripton.string'=>'Description is must be a string',

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

                'sales_tax_rate.required'=>'Sales Tax Rate is Required',
                'sales_tax_rate.numeric'=>'Sales Tax Rate is must be a numeric',

                'opening_balance.numeric'=>'Opening Balance is must be a numeric',
                


            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            $sku_no = generateSKU($request->title,$request->category_id);
            do {
                $p_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Product::where('p_code', $p_code)->exists());
            $subcategory = ProductSubCategory::find($request->sub_category_id);
            $acc = ChartOfAccount::find($subcategory->acc_id);
            if(empty($acc)) throw new Exception('Inventory COA not found', 404);

            DB::beginTransaction();
            $title = strtoupper($request->title);
            $COA = createCOA($title,$acc->code);
            $image = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = 'logo' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('product-image'), $image_name);
                $image = 'product-image/' . $image_name;
            }
            $product = Product::create([
                'title' => $title,
                'brand_name' => $request->brand_name ?? null,
                'terms_of_payment' => $request->terms_of_payment ?? null,
                'p_code' => $p_code,
                'sku' => $sku_no,
                'measurement_unit_id' => $request->measurement_unit_id,
                'acc_id' => $COA->id,
                'image' => $image,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'purchase_price' => $request->purchase_price,
                'sale_price' => $request->sale_price,
                'sales_tax_rate' => $request->sales_tax_rate,
                'added_by' => $user->id,
            ]);
            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            Balance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            $COA->update([
                'ref_id' => $product->id,
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User store product',
            ]);
            DB::commit();
            return response()->json($product);
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
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $product = Product::find($id);
            Log::create([
                'user_id' => $user->id,
                'description' => 'User show product',
            ]);
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
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $product = Product::find($id);
            if (empty($product)) throw new Exception('No Product found', 404);
            $validator = Validator::make(
                    $request->all(),[
                        'title'=>'required|string',
                        'brand_name'=>'nullable|string',
                        'terms_of_payment'=>'nullable|string',
                        'description'=>'required|string',
                        'category_id'=>'required|string',
                        'sub_category_id'=>'required|string',
                        'purchase_price'=>'required|string',
                        'sale_price'=>'required|numeric',
                        'measurement_unit_id'=>'required|string|exists:measurement_units,id',
                        'sales_tax_rate'=>'nullable|numeric',
                        'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:8192',
        
        
                ],[
                    'title.required'=>'Title is Required',
                    'title.string'=>'Title is must be a string',

                    'brand_name.string'=>'Brand Name is must be a string',

                    'descripton.required'=>'Description is Required',
                    'descripton.string'=>'Description is must be a string',
    
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
                    
                    'sales_tax_rate.numeric'=>'Sales Tax Rate is must be a numeric',
    
                    'image.image'=>'Image is must be a image',
                    'image.mimes'=>'Image is must be a image',
                    'image.max'=>'Image is must be a image',
    
    
                ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            $subcategory = ProductSubCategory::find($request->sub_category_id);
            $acc = ChartOfAccount::find($subcategory->acc_id);
            if(empty($acc)) throw new Exception('COA not found', 404);

            DB::beginTransaction();
            $title = strtoupper($request->title);
            $COA = updateCOA($product->acc_id,$title,$acc->code);
            $image = $product->image; // Keep current image by default
            $oldImagePath = public_path($product->image); // Path to the old image

            if ($request->hasFile('image')) {
                // Upload the new image
                $uploadedImage = $request->file('image');
                $imageName = 'product_' . time() . '.' . $uploadedImage->getClientOriginalExtension();
                $uploadedImage->move(public_path('product-image'), $imageName);
                $image = 'product-image/' . $imageName;

                // Remove the old image if it exists and is not the default one
                if (file_exists($oldImagePath) && !empty($product->image)) {
                    unlink($oldImagePath);
                }
            }
            
        
            $product->update([
                'title' => $title,
                'brand_name' => $request->brand_name ?? null,
                'terms_of_payment' => $request->terms_of_payment ?? null,
                'p_code' => $product->p_code,
                'sku' => $product->sku,
                'measurement_unit_id' => $request->measurement_unit_id,
                'image' => $image,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'purchase_price' => $request->purchase_price,
                'sale_price' => $request->sale_price,
                'sales_tax_rate' => $request->sales_tax_rate ?? 0,
                'added_by' => $user->id,
            ]);
            
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update product',
            ]);
            DB::commit();
            return response()->json($product,200);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
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

    public function updateStatus($id):JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit product')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $product = Product::findOrFail($id);
            if (empty($product)) throw new Exception('No User found', 404);
            if($product->is_active == 1){
                $product->update([
                    'is_active'=>0,
                ]);
            }else{
                $product->update([
                    'is_active'=>1,
                ]);
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update product status',
            ]);
            return response()->json($product);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function list():JsonResponse
    {
        try{
            $product = Product::select('id','title')->where('is_active',1)->get();
            return response()->json($product);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function csvProduct()
    {

        $filePath = public_path('assets/files/product-sample.csv');

        // Check if the file exists
        if (!file_exists($filePath)) {
            return abort(404, 'File not found.');
        }
        // Define headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product-sample.csv"',
        ];

        // Return the file as a response
        return Response::download($filePath, 'product-sample.csv', $headers);
    }

    public function importProduct(Request $request):JsonResponse
    {
        try{
            $user = Auth::user();

            // Check user permission
            if ($user->role == 'user') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'csv' => 'required|file|mimes:csv,txt|max:8192', // Max 8MB
            ], [
                'csv.required' => 'CSV file is required.',
                'csv.mimes' => 'Only CSV or TXT files are allowed.',
                'csv.max' => 'CSV file size must be less than 8MB.',
            ]);

            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            
            $file = $request->file('csv');
            
            // Read CSV file
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            $headers = array_shift($csvData); // Remove header row

            $products = [];
            $errors = [];
            DB::beginTransaction();
            try{
            foreach ($csvData as $row) {
                $data = array_combine($headers, $row);
                
                try {
                    // Verify category
                    $category = ProductCategory::where('name', $data['category'])->first();
                    if (!$category) {
                        throw new Exception("Category '{$data['category']}' not found");
                    }
    
                    // Verify subcategory
                    $subcategory = ProductSubCategory::where('name', $data['sub_category'])
                        ->where('category_id', $category->id)
                        ->first();
                    if (!$subcategory) {
                        throw new Exception("Subcategory '{$data['sub_category']}' not found for category '{$data['category']}'");
                    }
    
                    // Verify measurement unit
                    $measurementUnit = MeasurementUnit::where('name', $data['measurement_unit'])->first();
                    if (!$measurementUnit) {
                        throw new Exception("Measurement unit '{$data['measurement_unit']}' not found");
                    }
    
                    // Generate SKU and product code
                    $sku_no = generateSKU($data['title'], $category->id);
                    do {
                        $p_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                    } while (Product::where('p_code', $p_code)->exists());
    
                    // Get COA
                    $acc = ChartOfAccount::find($subcategory->acc_id);
                    if(empty($acc)) throw new Exception('Inventory COA not found');
                    $title = strtoupper($data['title']);
                    $COA = createCOA($title, $acc->code);
    
                    // Create product
                    $product = Product::create([
                        'title' => $title,
                        'p_code' => $p_code,
                        'sku' => $sku_no,
                        'measurement_unit_id' => $measurementUnit->id,
                        'acc_id' => $COA->id,
                        'description' => $data['description'],
                        'category_id' => $category->id,
                        'sub_category_id' => $subcategory->id,
                        'sale_price' => $data['sale_price'],
                        'sales_tax_rate' => $data['sales_tax_rate'],
                        'added_by' => $user->id,
                    ]);
    
                    // Create opening balance
                    OpeningBalance::create([
                        'acc_id' => $COA->id,
                        'amount' => 0,
                    ]);

                    Balance::create([
                        'acc_id' => $COA->id,
                        'amount' => 0,
                    ]);
    
                    // Update COA reference
                    $COA->update([
                        'ref_id' => $product->id,
                    ]);
    
                    $products[] = $product;

                    DB::commit();
    
                } catch (Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row error for title '{$data['title']}': " . $e->getMessage();
                }
            }
        

                // Create log entry only if some products were imported successfully
                if (count($products) > 0) {
                    Log::create([
                        'user_id' => $user->id,
                        'description' => 'User imported products via CSV',
                    ]);
                }

                // Commit main transaction if we have any successful imports
                if (count($products) > 0 || count($errors) == count($csvData)) {
                    DB::commit();
                } else {
                    DB::rollBack();
                    throw new Exception('No products were imported successfully');
                }

                return response()->json([
                    'success' => count($products) . ' products imported successfully',
                    'products' => $products,
                    'errors' => $errors,
                ]);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        }catch(QueryException $e){
            return response()->json([' DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function categoryData() 
    {
        $data= ProductSubCategory::select(
            'product_sub_categories.*',
            'product_categories.name as product_category'
        )
        ->join('product_categories', 'product_sub_categories.category_id', '=', 'product_categories.id')
        ->get();

        $pdf = Pdf::loadView('pdf.category', ['data' => $data]);
        // return $pdf;
        return $pdf->download('category-data-list.pdf');
    }

}
