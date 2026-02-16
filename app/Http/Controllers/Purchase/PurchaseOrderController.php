<?php

namespace App\Http\Controllers\Purchase;

use App\Services\WhatsAppService;
use DB;
use Exception;
use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::with([
                'items.product' => function ($query) {
                    $query->select('id', 'title'); // Select product name and id
                }
            ])
                ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
                ->where('business_id', $businessId)
                ->select('purchase_orders.*', 'vendors.name as vendor_name') // Select fields including vendor name
                ->orderBy('purchase_orders.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->paginate($perPage);
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'create purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            DB::beginTransaction();
            $validator = Validator::make(
                $request->all(),
                [
                    'vendor_id' => 'required|exists:vendors,id',
                    'order_date' => 'required',
                    'due_date' => 'required',
                    'delivery_cost' => 'required|numeric',
                    'total' => 'required|numeric',
                    'total_discount' => 'required|numeric',
                    'total_tax' => 'required|numeric',

                    'items' => 'required|array',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.measurement_unit' => 'required|string',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.discount' => 'required|numeric',
                    'items.*.discount_in_percentage' => 'required|numeric|in:0,1',
                    'items.*.tax' => 'required|numeric',

                ],
                [

                    'vendor_id.required' => 'Vendor is required.',
                    'vendor_id.exists' => 'Vendor does not exist.',

                    'order_date.required' => 'Order date is required.',

                    'due_date.required' => 'Due date is required.',

                    'total.required' => 'Total is required.',
                    'total.numeric' => 'Total must be a number.',

                    'total_discount.required' => 'Total Discount is required.',
                    'total_discount.numeric' => 'Total Discount must be a number.',

                    'delivery_cost.required' => 'Delivery cost is required.',
                    'delivery_cost.numeric' => 'Delivery cost must be a number.',

                    'total_tax.required' => 'Total Tax is required.',
                    'total_tax.numeric' => 'Total Tax must be a number.',

                    'items.required' => 'Items are required.',
                    'items.array' => 'Items must be an array.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',

                    'items.*.measurement_unit.required' => 'Measurement unit is required.',
                    'items.*.measurement_unit.string' => 'Measurement unit must be a string.',

                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.unit_price.required' => 'Unit price is required.',
                    'items.*.unit_price.numeric' => 'Unit price must be a number.',

                    'items.*.total_price.required' => 'Total price is required.',
                    'items.*.total_price.numeric' => 'Total price must be a number.',

                    'items.*.discount.required' => 'Discount is required.',
                    'items.*.discount.numeric' => 'Discount must be a number.',

                    'items.*.discount_in_percentage.required' => 'Discount in percentage is required.',
                    'items.*.discount_in_percentage.numeric' => 'Discount in percentage must be a number.',
                    'items.*.discount_in_percentage.in' => 'Discount in percentage must be 0 or 1.',

                    'items.*.tax.required' => 'Tax is required.',
                    'items.*.tax.numeric' => 'Tax must be a number.',
                ]
            );
            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);
            do {
                $order_code = 'PO-' . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (PurchaseOrder::where('order_code', $order_code)->exists());
            $data = PurchaseOrder::create([
                'order_code' => $order_code,
                'vendor_id' => $request->vendor_id,
                'business_id' => $user->login_business,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'delivery_cost' => $request->delivery_cost,
                'total' => $request->total,
                'total_discount' => $request->total_discount,
                'total_tax' => $request->total_tax,
                'terms_of_payment' => $request->terms_of_payment ?? null,
                'remarks' => $request->remarks ?? null,
                'status' => $request->status ?? 0
            ]);
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $data->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'discount' => $item['discount'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'tax' => $item['tax'],
                ]);
            }
            $n_url = 'view-purchase-order/' . $data->id;
            if ($request->status == 1) {
                notifyUser($user->id, $businessId, 'create goods received notes', 'New purchase order created and approved', $n_url);
            } else {
                notifyUser($user->id, $businessId, 'approve purchase orders', 'New purchase order created', $n_url);
            }
            Log::create([
                'user_id' => $user->id,
                'description' => 'Create Purchase Order code: ' . $data->order_code,
            ]);

            // save pdf
            $path = $this->savePO($data->id);
            

            $data->update([
                'pdf' => $path
            ]);
            DB::commit();
            return response()->json($data);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = PurchaseOrder::with([
                'items.product' => function ($query) {
                    $query->select('id', 'title'); // Select product name and id
                }
            ])
                ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id') // Join with the vendors table
                ->select('purchase_orders.*', 'vendors.name as vendor_name') // Select fields including vendor name
                ->where('purchase_orders.id', $id) // Filter by the specific purchase order ID
                ->firstOrFail();
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'vendor_id' => 'required|exists:vendors,id',
                    'order_date' => 'required',
                    'due_date' => 'required',
                    'delivery_cost' => 'required|numeric',
                    'total' => 'required|numeric',
                    'total_tax' => 'required|numeric',
                    'total_discount' => 'required|numeric',
                    'terms_of_payment' => 'nullable|string',
                    'remarks' => 'nullable|string',

                    'items' => 'required|array',
                    'items.*.product_id' => 'required|exists:products,id',
                    'items.*.quantity' => 'required|numeric',
                    'items.*.measurement_unit' => 'required|string',
                    'items.*.unit_price' => 'required|numeric',
                    'items.*.total_price' => 'required|numeric',
                    'items.*.discount' => 'required|numeric',
                    'items.*.discount_in_percentage' => 'required|numeric|in:0,1',
                    'items.*.tax' => 'required|numeric',

                ],
                [
                    'vendor_id.required' => 'Vendor is required.',
                    'vendor_id.exists' => 'Vendor does not exist.',

                    'order_date.required' => 'Order date is required.',

                    'due_date.required' => 'Due date is required.',

                    'total.required' => 'Total is required.',
                    'total.numeric' => 'Total must be a number.',

                    'total_discount.required' => 'Total Discount is required.',
                    'total_discount.numeric' => 'Total Discount must be a number.',

                    'delivery_cost.required' => 'Delivery cost is required.',
                    'delivery_cost.numeric' => 'Delivery cost must be a number.',

                    'total_tax.required' => 'Total Tax is required.',
                    'total_tax.numeric' => 'Total Tax must be a number.',

                    'items.required' => 'Items are required.',

                    'items.*.product_id.required' => 'Product is required.',
                    'items.*.product_id.exists' => 'Product does not exist.',

                    'items.*.measurement_unit.required' => 'Measurement unit is required.',
                    'items.*.measurement_unit.string' => 'Measurement unit must be a string.',

                    'items.*.quantity.required' => 'Quantity is required.',
                    'items.*.quantity.numeric' => 'Quantity must be a number.',

                    'items.*.unit_price.required' => 'Unit price is required.',
                    'items.*.unit_price.numeric' => 'Unit price must be a number.',

                    'items.*.total_price.required' => 'Total price is required.',
                    'items.*.total_price.numeric' => 'Total price must be a number.',

                    'items.*.discount.required' => 'Discount is required.',
                    'items.*.discount.numeric' => 'Discount must be a number.',

                    'items.*.discount_in_percentage.required' => 'Discount in percentage is required.',
                    'items.*.discount_in_percentage.numeric' => 'Discount in percentage must be a number.',
                    'items.*.discount_in_percentage.in' => 'Discount in percentage must be 0 or 1.',

                    'items.*.tax.required' => 'Tax is required.',
                    'items.*.tax.numeric' => 'Tax must be a number.',
                ]
            );
            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);
            $data = PurchaseOrder::find($id);
            if (empty($data))
                throw new Exception('No PO found', 404);
            $data->update([
                'vendor_id' => $request->vendor_id,
                'order_date' => $request->order_date,
                'due_date' => $request->due_date,
                'total' => $request->total,
                'total_tax' => $request->total_tax,
                'total_discount' => $request->total_discount,
                'delivery_cost' => $request->delivery_cost,
                'terms_of_payment' => $request->terms_of_payment,
                'remarks' => $request->remarks ?? $data->remarks,
                'status' => 0,
            ]);
            $existingItems = PurchaseOrderItem::where('purchase_order_id', $id)->get()->keyBy('id');
            $requestItemIds = [];
            foreach ($request->items as $item) {
                if (isset($item['id']) && isset($existingItems[$item['id']])) {
                    // Update existing item
                    $existingItems[$item['id']]->update([
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                    $requestItemIds[] = $item['id'];  // Keep track of updated items
                } else {
                    // Create new item
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $id,
                        'product_id' => $item['product_id'],
                        'measurement_unit' => $item['measurement_unit'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'tax' => $item['tax'],
                    ]);
                }
            }
            $itemsToDelete = $existingItems->keys()->diff($requestItemIds);  // Find items not present in request
            PurchaseOrderItem::destroy($itemsToDelete);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Order. code: ' . $data->order_code,
            ]);
            $n_url = 'view-purchase-order/' . $id;
            notifyUser($user->id, $businessId, 'view purchase orders', 'purchase order has been updated', $n_url);
            return response()->json($data);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'approve purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            if ($request->status == 0)
                throw new Exception('Invalid status', 400);
            $data = PurchaseOrder::find($id);
            if ($data->status != 0)
                throw new Exception("Status can't change", 400);
            $data->update([
                'status' => $request->status
            ]);
            Log::create([
                'user_id' => $user->id,
                'description' => 'Update Purchase Order Status. code: ' . $data->order_code,
            ]);
            $n_url = 'view-purchase-order/' . $id;
            if ($request->status == 1) {
                notifyUser($user->id, $businessId, 'create goods received notes', 'purchase order approved successfully', $n_url);
            } elseif ($request->status == 2) {
                notifyUser($user->id, $businessId, 'view purchase orders', 'purchase order Rejected', $n_url);
            }
            return response()->json($data);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::select('id', 'order_code')->where('status', 1)->where('business_id', $businessId)
                ->orderBy('purchase_orders.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->get();
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function list2(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list purchase orders')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $searchQuery = $request->query('search');
            $query = PurchaseOrder::select('id', 'order_code')->where('status', 1)->where('vendor_id', $request->vendor_id)->where('business_id', $businessId)
                ->orderBy('purchase_orders.id', 'desc');
            if (!empty($searchQuery)) {
                $query = $query->where('order_code', 'like', '%' . $searchQuery . '%');
            }
            // Execute the query with pagination
            $data = $query->get();
            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    function savePO($id)
    {
        $data = PurchaseOrder::with([
            'items.product' => function ($query) {
                $query->select('id', 'title'); // Select product name and id
            }
        ])
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id') // Join with the vendors table
            ->select('purchase_orders.*', 'vendors.name as vendor_name', 'vendors.email as vendor_email', 'vendors.phone as vendor_phone','vendors.address as vendor_address') // Select fields including vendor name
            ->where('purchase_orders.id', $id) // Filter by the specific purchase order ID
            ->firstOrFail();

        $pdf = PDF::loadView('orders.purchase-order', compact('data'));

        $fileName = 'purchase-order-' . $data->order_code . '-' . now() . '.pdf';
        $directory = public_path('storage/orders');
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        // Save the PDF file
        $pdf->save($filePath);
        $body = [
            $data->vendor_name,
            $data->order_code,
        ];
        $newMessage = new WhatsAppService();
        $res = $newMessage->sendTemplateMessage($data->vendor_phone, 'purchase_invoice_1', $body, 'document', url('public/storage/orders/' . $fileName), $fileName);
        return url('public/storage/orders/' . $fileName);
    }

}
