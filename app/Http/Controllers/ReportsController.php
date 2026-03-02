<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessHasAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryNoteItem;
use App\Models\Employee;
use App\Models\GoodsReceiveNote;
use App\Models\GoodsReceiveNoteItem;
use App\Models\InventoryDetail;
use App\Models\Lot;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\SaleOrderItem;
use App\Models\SaleReceipt;
use App\Models\SaleVoucher;
use App\Models\Vendor;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function inventoryReport(Request $request): JsonResponse
    {

        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'inventory report')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $perpage = $request->input('perpage', 10);
            $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::parse('1970-01-01')->startOfDay();

            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : now()->endOfDay();

            // Sum quantities of products from purchase orders (IN)
            $purchaseItems = GoodsReceiveNoteItem::select('product_id', DB::raw('SUM(receive) as total_in'))
                ->join('goods_receive_notes', 'goods_receive_notes.id', '=', 'goods_receive_note_items.goods_receive_note_id')
                ->where('goods_receive_notes.business_id', $businessId)
                ->groupBy('product_id');

            // Sum quantities of products from sales orders (OUT)
            $soldItems = DeliveryNoteItem::select('product_id', DB::raw('SUM(delivered) as total_out'))
                ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->where('delivery_notes.business_id', $businessId)
                ->groupBy('product_id');

            // Join the data with the Product table
            $inventoryReport = Product::
                leftJoinSub($purchaseItems, 'purchased', 'products.id', '=', 'purchased.product_id')
                ->leftJoinSub($soldItems, 'sold', 'products.id', '=', 'sold.product_id')
                ->where(function($query) {
                    $query->whereNotNull('purchased.total_in')
                        ->orWhereNotNull('sold.total_out');
                })
                ->whereBetween(DB::raw('DATE(products.created_at)'), [$startDate, $endDate])
                ->select(
                    'products.id as product_id',
                    'products.title',
                    'products.image',
                    DB::raw('COALESCE(purchased.total_in, 0) as total_in'),
                    DB::raw('COALESCE(sold.total_out, 0) as total_out')
                )
                ->paginate($perpage);

            return response()->json($inventoryReport);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function inventoryReportDetail(Request $request): JsonResponse
    {
        try {

            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'inventory report')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Fetch the product details
            $product = Product::select('id', 'title', 'image', 'description')
                ->find($request->id);

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            // Fetch purchase (IN) data
            $inData = GoodsReceiveNoteItem::where('product_id', $product->id)
                ->select('receive  as quantity', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Fetch sales (OUT) data
            $outData = DeliveryNoteItem::where('product_id', $product->id)
                ->select('delivered as quantity', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Construct the response
            $response = [
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'image' => $product->image,
                    'description' => $product->description,
                ],
                'in_data' => $inData,
                'out_data' => $outData,
            ];

            return response()->json($response);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function purchaseSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'purchase summary')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $start_date = Carbon::parse($request->start_date)->startOfDay();
            $end_date   = Carbon::parse($request->end_date)->endOfDay();

            // Query purchase vouchers with vendor names
            $query = PurchaseVoucher::select('purchase_vouchers.*', 'vendors.name as vendor_name')
                ->join('vendors', 'purchase_vouchers.vendor_id', '=', 'vendors.id')
                ->whereBetween('purchase_vouchers.voucher_date', [$start_date, $end_date])
                ->where('purchase_vouchers.status', 1);

            // Apply business_id filter if the user is not an admin
            if (!empty($businessId)) {
                $query->where('purchase_vouchers.business_id', $businessId);
            }

            // Fetch data ordered by voucher date (descending)
            $purchaseData = $query->orderBy('purchase_vouchers.voucher_date', 'desc')->get();

            // Calculate total voucher amount with business filter if applicable
            $total = $query->sum('purchase_vouchers.voucher_amount');

            return response()->json(["data" => $purchaseData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    public function partyPurchaseSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'party purchase summary')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $start_date = $request->input('start_date', PurchaseVoucher::min('voucher_date')); // Default: earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default: today
            $vendorId = $request->input('vendor_id'); // Default: earliest transaction date

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($end_date)->endOfDay()->toDateTimeString();

            // Query purchase vouchers with vendor names
            $query = PurchaseVoucher::select('purchase_vouchers.*', 'vendors.name as vendor_name')
                ->join('vendors', 'purchase_vouchers.vendor_id', '=', 'vendors.id')
                ->whereBetween('purchase_vouchers.voucher_date', [$start_date, $end_date])
                ->where('purchase_vouchers.vendor_id', $vendorId)
                ->where('purchase_vouchers.status', 1);

            // Apply business_id filter if the user is not an admin
            if (!empty($businessId)) {
                $query->where('purchase_vouchers.business_id', $businessId);
            }

            // Fetch data ordered by voucher date (descending)
            $purchaseData = $query->orderBy('purchase_vouchers.voucher_date', 'desc')->get();

            // Calculate total voucher amount with business filter if applicable
            $total = $query->sum('purchase_vouchers.voucher_amount');

            return response()->json(["data" => $purchaseData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function purchaseReport(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;

            // Permission check
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'purchase report')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Input filters
            $start_date = $request->input('start_date') ?? null;
            $end_date = $request->input('end_date')?? null;
            $vendorId = $request->input('vendor_id');
            $cityId = $request->input('city_id');

            // Get vendors from city if city_id provided
            $vendorIds = [];
            if (!empty($cityId)) {
                $vendorIds = Vendor::where('city_id', $cityId)->pluck('id')->toArray();
            }

            // Build query
            $query = PurchaseInvoice::
            select('purchase_invoices.*','vendors.name as vendor','cities.name as city')
            ->join('vendors','purchase_invoices.vendor_id','vendors.id')
            ->join('cities','vendors.city_id','cities.id');
            
            if (!empty($start_date) && !empty($end_date)) {
                $query->whereBetween('purchase_invoices.created_at', [$start_date, $end_date]);
            }

            if (!empty($businessId)) {
                $query->where('purchase_invoices.business_id', $businessId);
            }

            if (!empty($vendorId)) {
                $query->where('purchase_invoices.vendor_id', $vendorId);
            }

            if (!empty($vendorIds)) {
                $query->whereIn('purchase_invoices.vendor_id', $vendorIds);
            }

            // Order and fetch
            $purchaseData = $query->orderBy('purchase_invoices.created_at', 'desc')->get();

            // Calculate total price (optional if needed)
            $totalPrice = $query->sum('purchase_invoices.total');

            return response()->json(['data' => $purchaseData,'total_price' => $totalPrice],200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    public function salesSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'sales summary')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }


            $start_date = $request->input('start_date', SaleVoucher::min('created_at')); // Default: earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default: today

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($end_date)->endOfDay()->toDateTimeString();

            // Query sale vouchers with customer names
            $query = SaleVoucher::select('sale_vouchers.*', 'customers.name as customer_name')
                ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                ->whereBetween('sale_vouchers.voucher_date', [$start_date, $end_date])
                ->where('sale_vouchers.status', 1);

            // Filter by business_id if provided
            if (!empty($businessId)) {
                $query->where('sale_vouchers.business_id', $businessId);
            }

            // Fetch data and order by voucher date (descending)
            $salesData = $query->orderBy('sale_vouchers.voucher_date', 'desc')->get();

            $total = $query->sum('sale_vouchers.voucher_amount');

            return response()->json(["data" => $salesData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function partySalesSummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'party sales summary')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }


            $start_date = $request->input('start_date', SaleVoucher::min('voucher_date')); // Default: earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default: today
            $customerId = $request->input('customer_id'); // customer id

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($end_date)->endOfDay()->toDateTimeString();

            // Query sale vouchers with customer names
            $query = SaleVoucher::select('sale_vouchers.*', 'customers.name as customer_name')
                ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                ->whereBetween('sale_vouchers.voucher_date', [$start_date, $end_date])
                ->where('sale_vouchers.customer_id', $customerId)
                ->where('sale_vouchers.status', 1);

            // Filter by business_id if provided
            if (!empty($businessId)) {
                $query->where('sale_vouchers.business_id', $businessId);
            }

            // Fetch data and order by voucher date (descending)
            $salesData = $query->orderBy('sale_vouchers.voucher_date', 'desc')->get();

            $total = $query->sum('sale_vouchers.voucher_amount');

            return response()->json(["data" => $salesData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function salesChart(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
            // Permission check for non-admin users
            if ($user->role != 'admin' && !$user->hasBusinessPermission($businessId, 'sales chart')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            $filterType = $request->input('filter_type'); // e.g., 'customer', 'month', 'item'
            $startDate = $request->start_date ?? '1970-01-01';
            $endDate = $request->end_date ?? now()->format('Y-m-d');

            $query = DB::table('sale_receipts AS sr')
            ->join('sale_receipt_items AS sri', 'sr.id', '=', 'sri.sale_receipt_id')
            ->join('customers AS c', 'sr.customer_id', '=', 'c.id')
            ->join('products AS p', 'sri.product_id', '=', 'p.id')
            ->where('sr.business_id', $businessId)
            ->whereBetween('sr.receipt_date', [$startDate, $endDate])
            ->where('sr.status', 1); // Consider only completed sales
        
            // Apply filters based on request
            if ($filterType === 'customer') {
                $salesData = $query
                    ->select('c.name as customer_name', DB::raw('SUM(sri.total) as total_sales'))
                    ->groupBy('c.name')
                    ->orderByDesc('total_sales')
                    ->get();
                    return response()->json(['sales_data' => $salesData], 200);
            } elseif ($filterType === 'month') {
                $salesData = $query
                    ->select(DB::raw("DATE_FORMAT(sr.receipt_date, '%Y-%m') as month"), DB::raw('SUM(sri.total) as total_sales'))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            } elseif ($filterType === 'item') {
                $salesData = $query
                    ->select('p.title as item_name', DB::raw('SUM(sri.total) as total_sales'))
                    ->groupBy('p.title')
                    ->orderByDesc('total_sales')
                    ->get();
            } else {
                return response()->json(['error' => 'Invalid filter type provided.'], 400);
            }

            return response()->json(['sales_data' => $salesData], 200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function salesChartByItemAndMonth(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
    
            if ($user->role != 'admin' && !$user->hasBusinessPermission($businessId, 'sales chart')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }
    
            // Get the year from the request, or use the current year if not provided
            $year = $request->year ?? now()->year;
    
            $startDate = Carbon::createFromFormat('Y', $year)->startOfYear()->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Y', $year)->endOfYear()->addDay()->format('Y-m-d'); // add one day to include the entire year
    
            // Query the sales data
            $rawData = DB::table('sale_receipts AS sr')
                ->join('sale_receipt_items AS sri', 'sr.id', '=', 'sri.sale_receipt_id')
                ->join('products AS p', 'sri.product_id', '=', 'p.id')
                ->where('sr.business_id', $businessId)
                ->whereBetween('sr.receipt_date', [$startDate, $endDate])
                ->where('sr.status', 1)
                ->select(
                    DB::raw("DATE_FORMAT(sr.receipt_date, '%b') as month"),
                    'p.title as item_name',
                    DB::raw('SUM(sri.total) as total_sales')
                )
                ->groupBy('month', 'p.title')
                ->orderByRaw("STR_TO_DATE(month, '%b')")
                ->get();
    
            // Initialize the structured data array with empty arrays for each month
            $structuredData = [
                'Jan' => [],
                'Feb' => [],
                'Mar' => [],
                'Apr' => [],
                'May' => [],
                'Jun' => [],
                'Jul' => [],
                'Aug' => [],
                'Sep' => [],
                'Oct' => [],
                'Nov' => [],
                'Dec' => []
            ];
    
            // Populate the structuredData with sales data
            foreach ($rawData as $entry) {
                $month = $entry->month;
                if (isset($structuredData[$month])) {
                    $structuredData[$month][] = [
                        'item_name' => $entry->item_name,
                        'total_sales' => number_format($entry->total_sales, 2),
                    ];
                }
            }
    
            // Return the response with structured sales data
            return response()->json(['sales_data' => $structuredData], 200);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    public function saleReport(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;

            // Permission check
            if ($user->role != 'admin') {
                if (!$user->hasBusinessPermission($businessId, 'sales report')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Input filters
            $start_date = $request->input('start_date') ?? null;
            $end_date = $request->input('end_date') ?? null;
            $customerId = $request->input('customer_id');
            $cityId = $request->input('city_id');

            // Get vendors from city if city_id provided
            $customerIds = [];
            if (!empty($cityId)) {
                $customerIds = Customer::where('city_id', $cityId)->pluck('id')->toArray();
            }

            // Build query
            $query = SaleReceipt::
            select('sale_receipts.*','customers.name as customer','cities.name as city')
            ->join('customers','sale_receipts.customer_id','customers.id')
            ->join('cities','customers.city_id','cities.id');

            if(!empty($start_date) && !empty($end_date)){
                $query->whereBetween('sale_receipts.receipt_date', [$start_date, $end_date]);
            }            

            if (!empty($businessId)) {
                $query->where('sale_receipts.business_id', $businessId);
            }

            if (!empty($customerId)) {
                $query->where('sale_receipts.customer_id', $customerId);
            }

            if (!empty($customerIds)) {
                $query->whereIn('sale_receipts.customer_id', $customerIds);
            }

            // Order and fetch
            $saleData = $query->orderBy('sale_receipts.created_at', 'desc')->get();

            // Calculate total price (optional if needed)
            $totalPrice = $saleData->sum('total');

            return response()->json(['data' => $saleData,'total_price' => $totalPrice],200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function financialReport(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;

            // Permission check for non-admin users
            if ($user->role != 'admin' && !$user->hasBusinessPermission($businessId, 'financial report')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            // Handle optional start_date and end_date
            $start_date = $request->input('start_date', DB::table('transactions')->min('created_at')); // Earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default to today

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->startOfDay()->toDateTimeString();
            $end_date = Carbon::parse($end_date)->endOfDay()->toDateTimeString();

            // Common query filter for business scope
            $businessFilter = function ($query) use ($user, $businessId) {
                if ($user->role !== 'admin') {
                    $query->where('business_id', $businessId);
                }
            };

            // Transaction Types Mapping
            $transactionTypes = [
                'purchases' => ['type' => 0, 'column' => 'debit'],
                'sales' => ['type' => 1, 'column' => 'credit'],
                'expenses' => ['type' => 2, 'column' => 'debit'],
                'income' => ['type' => 3, 'column' => 'credit'],
            ];

            $results = [];

            foreach ($transactionTypes as $key => $type) {
                $data = DB::table('transactions')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->where('transaction_type', $type['type'])
                    ->when($user->role !== 'admin', fn($q) => $q->where('business_id', $businessId))
                    ->get();

                $total = $data->sum($type['column']);

                $results[$key] = [
                    'data' => $data,
                    'total' => $total
                ];
            }

            // Net profit calculation
            $netProfit = ($results['sales']['total'] + $results['income']['total']) - ($results['purchases']['total'] + $results['expenses']['total']);

            return response()->json([
                'purchases' => $results['purchases'],
                'sales' => $results['sales'],
                'expenses' => $results['expenses'],
                'income' => $results['income'],
                'net_profit' => $netProfit,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function balanceSheet(Request $request)
    {
        try{
            $user = Auth::user();
            $businessId = null;
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'balance sheet')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }else{
                $businessId = $request->input('business_id');
                if (empty($businessId)) throw new Exception('business id required', 404);
            }

            $date = $request->input('date', Carbon::now()->toDateString()); // Default to today

            // get cash accounts
            $cash_code = ChartOfAccount::where('name','CASH')->value('code');
            $cash_accs = ChartOfAccount::where('parent_code',$cash_code)
            ->join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
            ->where('business_has_accounts.business_id', $businessId)
            ->pluck('chart_of_accounts.id');
            // end

            // get bank accounts
            $bank_code = ChartOfAccount::where('name','BANK')->value('code');
            $bank_accs = ChartOfAccount::where('parent_code',$bank_code)
            ->join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
            ->where('business_has_accounts.business_id', $businessId)
            ->pluck('chart_of_accounts.id');
            // end

            // get customer accounts
            $customer_accs = Customer::where('business_id',$businessId)->pluck('acc_id');
            // end

            // get inventory
            $grn_ids = GoodsReceiveNote::where('business_id', $businessId)->pluck('id');
            $lotsData = Lot::whereIn('grn_id', $grn_ids)
                ->select(
                    'product_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(quantity * sale_unit_price) as total_value')
                )
                ->groupBy('product_id')
                ->get();


            // This is simply the sum of total values of all products.
            $inventory_total = $lotsData->sum('total_value');

            // getting balance of accounts and total
            $bank_total = 0;
            foreach ($bank_accs as $key => $bank_acc) {
                $bank = currentBalance($bank_acc, $date);
                $bank_total += $bank;
            }

            $cash_total = 0;
            foreach ($cash_accs as $key => $cash_acc) {
                $cash = currentBalance($cash_acc, $date);
                $cash_total += $cash;
            }

            $customer_total = 0;
            foreach ($customer_accs as $key => $customer_acc) {
                $customer = currentBalance($customer_acc, $date);
                $customer_total += $customer;
            }

            

            $business = Business::find($businessId);

            $data = [
                'bank_total' => $bank_total,
                'cash_total' => $cash_total,
                'customer_total' => $customer_total,
                'inventory_total' => $inventory_total,
                'total_assets' => $bank_total + $cash_total + $customer_total + $inventory_total,
                'date' => $date,
                'business_name' => $business->name,
            ];

            $pdf = PDF::loadView('reports.balance-sheet', compact('data'));

            $fileName = 'balance-sheet-' . $businessId . '-' . $date . '.pdf';
            $directory = public_path('storage/invoices');
            $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            // Save the PDF file
            $pdf->save($filePath);
            return response()->file($filePath);
            // return response()->json($data, 200);


        }catch(Exception $e){
            return response()->json(['error'=> $e->getMessage()], 400);
        }
    }


    public function customerBalances(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = null;
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'customer balance')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }else{
                $businessId = $request->input('business_id');
                if (empty($businessId)) throw new Exception('business id required', 404);
            }

            $perpage = $request->input('perpage', 10);

            $customers = Customer::select(
                'customers.id',
                'customers.name',
                'customers.acc_id',
                'customers.c_code',
                'customers.address',
                'opening_balances.amount as opening_balance',
                'transactions.current_balance'
            )
            ->when($businessId, function ($query) use ($businessId) {
                return $query->where('customers.business_id', $businessId);
            })
            ->join('opening_balances', 'customers.acc_id', '=', 'opening_balances.acc_id')
            ->join(DB::raw('(SELECT t1.* FROM transactions t1 
                             INNER JOIN (
                                 SELECT acc_id, MAX(id) as max_id 
                                 FROM transactions 
                                 GROUP BY acc_id
                             ) t2 ON t1.id = t2.max_id
                         ) as transactions'), 'customers.acc_id', '=', 'transactions.acc_id')
            ->orderBy('transactions.id', 'desc')
            ->paginate($perpage);


            return response()->json($customers);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cnbBalances(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = null;
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'cashnbank balance')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }else{
                $businessId = $request->input('business_id');
                if (empty($businessId)) throw new Exception('business id required', 404);
            }

            $perpage = $request->input('perpage', 10);
            // where name cash and bank select code value
            $acc_code = ChartOfAccount::whereIn('name', ['CASH', 'BANK'])
    ->pluck('code')
    ->toArray();
            // Define the query with specific columns to fetch
            $accounts  = BusinessHasAccount::where('business_has_accounts.business_id', $businessId)
                ->join('chart_of_accounts', 'business_has_accounts.chart_of_account_id', '=', 'chart_of_accounts.id')
                ->join('opening_balances', 'business_has_accounts.chart_of_account_id', '=', 'opening_balances.acc_id')
                ->whereIn('chart_of_accounts.parent_code', $acc_code)
                ->select([
                    'business_has_accounts.chart_of_account_id as acc_id', // Account ID from business has accounts
                    'chart_of_accounts.name as account_name', // Account name from chart of accounts
                    'chart_of_accounts.code as account_code'
                ])->get();
            
                $data = $accounts->map(function ($account) {
                    return [
                        'acc_id' => $account->acc_id,
                        'account_name' => $account->account_name,
                        'account_code' => $account->account_code,
                        'opening_balance' => $account->opening_balance,
                        'current balance' => currentBalance($account->acc_id) // Use helper function
                    ];
                });

                return response()->json($data);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function vendorBalances(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'vendor balance')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perpage = $request->input('perpage', 10);

            $vendors = Vendor::select(
                'vendors.id',
                'vendors.name',
                'vendors.acc_id',
                'vendors.v_code',
                'vendors.address',
                'opening_balances.amount as opening_balance',
                'transactions.current_balance'
            )
            ->join('opening_balances', 'vendors.acc_id', '=', 'opening_balances.acc_id')
            ->join(DB::raw('(SELECT t1.* FROM transactions t1 
                             INNER JOIN (
                                 SELECT acc_id, MAX(id) as max_id 
                                 FROM transactions 
                                 GROUP BY acc_id
                             ) t2 ON t1.id = t2.max_id
                         ) as transactions'), 'vendors.acc_id', '=', 'transactions.acc_id')
            ->orderBy('transactions.id', 'desc')
            ->paginate($perpage);


            return response()->json($vendors);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function employeeBalances(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'employee balance')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perpage = $request->input('perpage', 10);

            $employees = Employee::select(
                'employees.id',
                'employees.name',
                'employees.acc_id',
                'employees.e_code',
                'employees.address',
                'opening_balances.amount as opening_balance',
                'transactions.current_balance'
            )
            ->join('opening_balances', 'employees.acc_id', '=', 'opening_balances.acc_id')
            ->join(DB::raw('(SELECT t1.* FROM transactions t1 
                             INNER JOIN (
                                 SELECT acc_id, MAX(id) as max_id 
                                 FROM transactions 
                                 GROUP BY acc_id
                             ) t2 ON t1.id = t2.max_id
                         ) as transactions'), 'employees.acc_id', '=', 'transactions.acc_id')
            ->orderBy('transactions.id', 'desc')
            ->paginate($perpage);


            return response()->json($employees);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }



    // profit n lose

    public function pnlOld(Request $request): JsonResponse
    {
        try {
            
    
            $start_date = Carbon::parse($request->start_date)->startOfDay();
            $end_date   = Carbon::parse($request->end_date)->endOfDay();
    
            // here is the logic code
    
            $pnlQuery = DeliveryNoteItem::with('product', 'deliveryNote');
    
            // If start_date and end_date are provided, apply the date filter
            if ($start_date && $end_date) {
                $pnlQuery->whereHas('deliveryNote', function ($q) use ($start_date, $end_date) {
                    $q->whereBetween('dn_date', [$start_date, $end_date]);
                });
            }
    
            $pnlData = $pnlQuery->get()
                ->groupBy('product_id')
                ->map(function ($items, $productId) {
                    $productName = optional($items->first()->product)->title;
    
                    $totalQuantity = $items->sum('quantity');
    
                    // Get average purchase price from lots table
                    $lots = Lot::where('product_id', $productId)->get();
                    $totalLotQty = $lots->sum('quantity');
                    $totalLotCost = $lots->sum(function ($lot) {
                        return $lot->purchase_unit_price * $lot->quantity;
                    });
    
                    $averagePurchasePrice = $totalLotQty > 0 ? $totalLotCost / $totalLotQty : 0;
    
                    // Sale calculation
                    $totalSale = $items->sum(function ($item) {
                        return $item->unit_price * $item->quantity;
                    });
    
                    $totalPurchase = $averagePurchasePrice * $totalQuantity;
    
                    return [
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'quantity_sold' => $totalQuantity,
                        'avg_purchase_price' => round($averagePurchasePrice, 2),
                        'total_sale' => round($totalSale, 2),
                        'total_purchase' => round($totalPurchase, 2),
                        'profit' => round($totalSale - $totalPurchase, 2),
                    ];
                })->values();
    
            $overall = [
                'total_sale' => $pnlData->sum('total_sale'),
                'total_purchase' => $pnlData->sum('total_purchase'),
                'total_profit' => $pnlData->sum('profit'),
            ];
    
            return response()->json(['data' => $pnlData, 'summary' => $overall], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function pnl(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
                if ($user->role != 'admin') {
                    $businessId = $user->login_business;
                    if (!$user->hasBusinessPermission($businessId, 'pnl report')) {
                        return response()->json([
                            'error' => 'User does not have the required permission.'
                        ], 403);
                    }
                }
            $start_date = Carbon::parse($request->start_date)->startOfDay();
            $end_date   = Carbon::parse($request->end_date)->endOfDay();

            // ---------------------------
            // PURCHASE DATA
            // ---------------------------
            $purchaseData = GoodsReceiveNoteItem::whereHas('goodsReceiveNote', function ($q) use ($start_date, $end_date) {
                $q->whereBetween('grn_date', [$start_date, $end_date]);
            })
            ->selectRaw('product_id,
                        SUM(quantity) as total_purchase_qty,
                        SUM(quantity * purchase_unit_price) as total_purchase_amount')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');


            // ---------------------------
            // SALE DATA
            // ---------------------------
            $saleData = DeliveryNoteItem::whereHas('deliveryNote', function ($q) use ($start_date, $end_date) {
                $q->whereBetween('dn_date', [$start_date, $end_date]);
            })
            ->selectRaw('product_id,
                        SUM(quantity) as total_sale_qty,
                        SUM(quantity * unit_price) as total_sale_amount')
            ->groupBy('product_id')
            ->get();


            $report = $saleData->map(function ($sale) use ($purchaseData) {

                $productId = $sale->product_id;
                $productName = optional($sale->product)->title;                

                $purchaseQty = $purchaseData[$productId]->total_purchase_qty ?? 0;
                $purchaseAmount = $purchaseData[$productId]->total_purchase_amount ?? 0;

                $avgPurchasePrice = $purchaseQty > 0 ? $purchaseAmount / $purchaseQty : 0;

                $cogs = $avgPurchasePrice * $sale->total_sale_qty;

                $profit = $sale->total_sale_amount - $cogs;

                return [
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'quantity_sold' => $sale->total_sale_qty,
                    'total_purchase' => round($purchaseAmount, 2),
                    'total_sale' => round($sale->total_sale_amount, 2),
                    'avg_purchase_price' => round($avgPurchasePrice, 2),
                    // 'cogs' => round($cogs, 2),
                    'profit' => round($profit, 2),
                ];
            });

            $summary = [
                'total_sale' => $report->sum('total_sale'),
                'total_purchase' => $report->sum('cogs'),
                'total_profit' => $report->sum('profit'),
            ];

            return response()->json([
                'data' => $report->values(),
                'summary' => $summary
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    

}
