<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVoucher;
use App\Models\SaleOrder;
use App\Models\SaleReceipt;
use App\Models\SaleReceiptItem;
use App\Models\SaleVoucher;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    
    public function nonPaidCustomer(Request $request): JsonResponse
    {
        try {
            $month = $request->input('month');
            $filter = $request->input('filter'); // 'non-paid' or 'overdue'

            // Subquery to get latest voucher per customer
            $latestVouchers = DB::table('sale_vouchers as sv1')
            ->select('sv1.customer_id', DB::raw('MAX(sv1.voucher_date) as latest_voucher_date'))
            ->groupBy('sv1.customer_id');

            // Base Query
            $query = SaleVoucher::select(
                'sale_vouchers.customer_id',
                'sale_vouchers.voucher_date',
                'sale_vouchers.status',
                'customers.name as customer_name',
                'sale_vouchers.voucher_amount',
                'cities.name as city',
                DB::raw("transactions.current_balance as balance")
            )
            ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
            ->join('chart_of_accounts', 'customers.id', '=', 'chart_of_accounts.ref_id')
            ->join(
                DB::raw('(SELECT acc_id, current_balance FROM transactions WHERE id IN (SELECT MAX(id) FROM transactions GROUP BY acc_id)) as transactions'),
                'chart_of_accounts.id', '=', 'transactions.acc_id'
            )
            ->where('transactions.current_balance', '>', 0)
            ->join('cities', 'customers.city_id', '=', 'cities.id')
            ->joinSub($latestVouchers, 'lv', function($join) {
                $join->on('sale_vouchers.customer_id', '=', 'lv.customer_id')
                     ->on('sale_vouchers.voucher_date', '=', 'lv.latest_voucher_date');
            });
            
            if(Auth::user()->role != 'admin'){
                $query->where('customers.business_id', Auth::user()->login_business);
            }
            // Apply filters
            if ($filter === 'non-paid') {
                $query->where('sale_vouchers.status', 0); // Unpaid vouchers
            } elseif ($filter === 'overdue') {
                $query->where('sale_vouchers.days', '>', 30);
            }

            // Filter by month if provided
            if ($month) {
                $query->whereMonth('sale_vouchers.voucher_date', $month);
            }

            // Make sure we only get the latest voucher for each customer
            $query->groupBy('sale_vouchers.customer_id','sale_vouchers.voucher_date','sale_vouchers.status','customers.name','cities.name', 'sale_vouchers.voucher_amount') // Group by customer_id to get unique customers
                  ->orderBy('sale_vouchers.voucher_date', 'desc'); // Sort by voucher date to get the latest one

            $data = $query->get();


            return response()->json($data);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function lowPayCustomers(Request $request): JsonResponse
    {
        try {
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
    
            $dateCondition = '';
            if ($start_date && $end_date) {
                $dateCondition = "WHERE t1.created_at BETWEEN '$start_date' AND '$end_date'";
            }
    
            $customers = Customer::select(
                    'customers.id',
                    'customers.name',
                    'customers.acc_id',
                    'cities.name as city_name',
                    DB::raw("
                        CASE
                            WHEN ob.amount IS NOT NULL THEN (t.debit + ob.amount)
                            ELSE t.debit
                        END as balance
                    "),
                    DB::raw('t.debit as payment')
                )
                ->leftJoin('cities', 'customers.city_id', '=', 'cities.id')
                ->join(DB::raw("
                    (
                        SELECT t1.*
                        FROM transactions t1
                        INNER JOIN (
                            SELECT acc_id, MAX(id) as max_id
                            FROM transactions
                            " . ($dateCondition ? $dateCondition : '') . "
                            GROUP BY acc_id
                        ) t2 ON t1.acc_id = t2.acc_id AND t1.id = t2.max_id
                    ) as t
                "), 'customers.acc_id', '=', 't.acc_id') // changed to INNER JOIN to ensure only customers with transactions
                ->leftJoin('opening_balances as ob', 'customers.acc_id', '=', 'ob.acc_id')
                ->get();
    
            return response()->json($customers, 200);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }  

    public function inventoryProducts(): JsonResponse
    {
        try{
            $outOfStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image','products.p_code as code')
            ->where('inventory_details.in_stock', 0)
            ->join('products','inventory_details.product_id','=','products.id')->get();
            $inStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image','products.p_code as code')
            ->where('inventory_details.in_stock', 1)
            ->join('products','inventory_details.product_id','=','products.id')->get();
            $lowInStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image','products.p_code as code')
            ->where('inventory_details.in_stock', 2)
            ->join('products','inventory_details.product_id','=','products.id')->get();

            $data = [
                'outOfStockProducts' => $outOfStockProducts,
                'inStockProducts' => $inStockProducts,
                'lowInStockProducts' => $lowInStockProducts
            ];

            return response()->json($data);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function salesAnalysis(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;

            $date = $request->input('date') ?? Carbon::now()->format('Y-m');
            $year = substr($date, 0, 4); // Extract year
            $month = substr($date, 5); // Extract month
            $graphFilter = request('graph_filter');

            // Sales Graph: Filtered by Year and Month
            $query = SaleVoucher::where('status', 1)
                ->where('business_id', $businessId)
                ->whereYear('voucher_date', $year)
                ->whereMonth('voucher_date', $month);

            // Handle Daily Data
            if ($graphFilter === 'daily') {
                $salesGraph = $query->selectRaw('DAY(voucher_date) as day, SUM(voucher_amount) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->get()
                    ->keyBy('day');

                // Fill missing days with 0
                $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                $formattedData = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $formattedData[] = [
                        'day' => $day,
                        'total' => $salesGraph[$day]->total ?? 0
                    ];
                }
            }

            // Handle Weekly Data
            elseif ($graphFilter === 'weekly') {
                $salesGraph = $query->selectRaw('FLOOR((DAY(voucher_date)-1)/7)+1 as week, SUM(voucher_amount) as total')
                    ->groupBy('week')
                    ->orderBy('week')
                    ->get()
                    ->keyBy('week');
            
                // Calculate total weeks in the month
                $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                $weeksInMonth = ceil($daysInMonth / 7);
            
                $formattedData = [];
                for ($week = 1; $week <= $weeksInMonth; $week++) {
                    $formattedData[] = [
                        'week' => $week,
                        'total' => $salesGraph[$week]->total ?? 0
                    ];
                }
            }

            // Sales Data: Grouped by Month & Year (Pending, Approved, Delivered)
            $totalSaleOrderPending = SaleOrder::whereYear('order_date', $year)
                ->where('business_id', $businessId)
                ->where('status', 0)
                ->selectRaw('MONTH(order_date) as month, YEAR(order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderApproved = SaleOrder::whereYear('order_date', $year)
                ->where('business_id', $businessId)
                ->where('status', 1)
                ->selectRaw('MONTH(order_date) as month, YEAR(order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderDelivered = SaleOrder::whereYear('sale_orders.order_date', $year)
                ->where('sale_orders.business_id', $businessId)
                ->where('delivery_notes.status', 1)
                ->join('delivery_notes', 'sale_orders.id', '=', 'delivery_notes.sale_order_id')
                ->selectRaw('MONTH(sale_orders.order_date) as month, YEAR(sale_orders.order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            // Get the latest month from sales data in the given year
            $latestMonth = SaleVoucher::where('status', 1)
            ->where('business_id', $businessId)
            ->whereYear('voucher_date', $year)
            ->selectRaw('MAX(MONTH(voucher_date)) as latestMonth')
            ->value('latestMonth');

            // Get total sales for the entire year (up to the latest month)
            $totalYearSale = SaleVoucher::where('status', 1)
            ->where('business_id', $businessId)
            ->whereYear('voucher_date', $year)
            ->sum('voucher_amount');

            // Get total sales for the previous month (if available)
            $previousMonthTotalSale = SaleVoucher::where('status', 1)
            ->where('business_id', $businessId)
            ->whereYear('voucher_date', $year)
            ->whereMonth('voucher_date', $latestMonth - 1) // Previous month
            ->sum('voucher_amount');

            // Calculate percentage increase
            $percentageIncrease = ($previousMonthTotalSale > 0)
            ? (($totalYearSale - $previousMonthTotalSale) / $previousMonthTotalSale) * 100
            : 0;

            // total of sales, current month sales, today sale, last month sales increase percentage
            $totalSale = SaleVoucher::where('status', 1)->where('business_id', $businessId)->sum('voucher_amount');

            // Current month sales
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;
            $currentMonthSale = SaleVoucher::where('status', 1)->where('business_id', $businessId)->whereYear('voucher_date', $currentYear)->whereMonth('voucher_date', $currentMonth)->sum('voucher_amount');

            // Today's sales
            $todaySale = SaleVoucher::where('status', 1)->where('business_id', $businessId)->whereDate('voucher_date', Carbon::now()->toDateString())->sum('voucher_amount');

            // Last month's sales
            $lastMonth = Carbon::now()->subMonth()->month;
            $lastMonthSale = SaleVoucher::where('status', 1)->where('business_id', $businessId)->whereYear('voucher_date', $currentYear)->whereMonth('voucher_date', $lastMonth)->sum('voucher_amount');

            // Calculate percentage increase from last month
            $percentageInc = ($lastMonthSale > 0)
                ? (($currentMonthSale - $lastMonthSale) / $lastMonthSale) * 100
                : 0; // Avoid division by zero 

            $sale_trend = $this->checkTrend($currentMonthSale,$lastMonthSale);

            $salesView = [
                'totalSale' => $totalSale,
                'currentMonthSale' => $currentMonthSale,
                'todaySale' => $todaySale,
                'trend' => $sale_trend,
                'percentageIncrease' => round($percentageInc, 2)
            ];

            $data = [
                'salesGraph' => $formattedData,
                'salesData' => [
                    'totalSaleOrderPending' => $totalSaleOrderPending,
                    'totalSaleOrderApproved' => $totalSaleOrderApproved,
                    'totalSaleOrderDelivered' => $totalSaleOrderDelivered,
                ],
                'sales' => [
                    'totalSale' => $totalSale,
                    'previousMonthTotalSale' => $previousMonthTotalSale,
                    'percentageIncrease' => $percentageIncrease,
                ],
                'salesView' => $salesView
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            // here we count  total and increasing percentage in that total from last month
            $last_month_date = Carbon::now()->subMonth();

            $total_customers = Customer::where('business_id',$businessId)->count();
            $lm_customers = Customer::where('business_id',$businessId)->whereMonth('created_at', $last_month_date->month)->whereYear('created_at', $last_month_date->year)->count();
            $cm_customers = Customer::where('business_id', $businessId)->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year)->count();
            $ipc_customers = ($lm_customers > 0) ? (($cm_customers - $lm_customers) / $lm_customers) * 100 : 0;

            $trend_customers = $this->checkTrend($cm_customers,$lm_customers);

            $total_inventory = InventoryDetail::distinct('product_id')->count();
            $lm_inventory = InventoryDetail::whereMonth('created_at', $last_month_date->month)->whereYear('created_at', $last_month_date->year)->distinct('product_id')->count();
            $cm_inventory = InventoryDetail::whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year)->distinct('product_id')->count();
            $ipc_inventory = ($lm_inventory > 0) ? (($cm_inventory - $lm_inventory) / $lm_inventory) * 100 : 0;

            $trend_inventory = $this->checkTrend($cm_inventory,$lm_inventory);

            $total_sales = SaleVoucher::where('business_id',$businessId)->where('status',1)->sum('voucher_amount');

            $cm_sales = SaleVoucher::where('business_id', $businessId)->where('status',1)->whereMonth('voucher_date', Carbon::now()->month)->whereYear('voucher_date', Carbon::now()->year)->sum('voucher_amount');
            $lm_sales = SaleVoucher::where('business_id',$businessId)->where('status',1)->whereMonth('voucher_date', $last_month_date->month)->whereYear('voucher_date', $last_month_date->year)->sum('voucher_amount');
            $ipc_sale = ($lm_sales > 0) ? (($cm_sales - $lm_sales) / $lm_sales) * 100 : 0;

            $trend_sales  = $this->checkTrend($cm_sales, $lm_sales);

            $total_purchases = PurchaseVoucher::where('business_id',$businessId)->where('status',1)->sum('voucher_amount');

            $cm_purchase = PurchaseVoucher::where('business_id', $businessId)->where('status',1)->whereMonth('voucher_date', Carbon::now()->month)->whereYear('voucher_date', Carbon::now()->year)->sum('voucher_amount');
            $lm_purchases = PurchaseVoucher::where('business_id',$businessId)->where('status',1)->whereMonth('voucher_date', $last_month_date->month)->whereYear('voucher_date', $last_month_date->year)->sum('voucher_amount');
            $ipc_purchases = ($lm_purchases > 0) ? (($cm_purchase - $lm_purchases) / $lm_purchases) * 100 : 0;

            $trend_purchases  = $this->checkTrend($cm_purchase, $lm_purchases);

            $data = [
                'Customers' => [
                    'total' => $total_customers,
                    'trend' => $trend_customers,
                    'ipc' => $ipc_customers,

                ],
                'Products' => [
                    'total' => $total_inventory,
                    'trend' => $trend_inventory,
                    'ipc' => $ipc_inventory
                ],
                'Sales' => [
                    'total' => $total_sales,
                    'trend' => $trend_sales,
                    'ipc' => $ipc_sale
                ],
                'Purchases' => [
                    'total' => $total_purchases,
                    'trend' => $trend_purchases,
                    'ipc' => $ipc_purchases
                ]
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function checkTrend($current, $last)
    {
        if ($last == 0) {
            return 2; // If there are sales this month, it's an increase; otherwise, it's stable.
        }
        return ($current >= $last) ? 1 : 0; // Increase if equal or greater, otherwise decrease.
    }

    // after new dashboard UI

    public function saleByProduct(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');

            $query = SaleReceipt::where('business_id', $user->login_business)  // Filter SaleReceipt by business_id
            ->where('status', 1)  // Ensure the status is active
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id') // Join with SaleReceiptItems
            ->join('products', 'sale_receipt_items.product_id', '=', 'products.id') // Join with Products to get the product title
            ->select('sale_receipt_items.product_id', 'products.title', DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) as total_sales')) // Select product_id, product title and total sales
            ->groupBy('sale_receipt_items.product_id', 'products.title')  // Group by product_id and product title
            ->orderBy(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price)'), 'desc');

            if ($start_date && $end_date) {
                $query->whereBetween('sale_receipts.receipt_date', [$start_date, $end_date]);
            }

            $query = $query->get();

            return response()->json($query, 200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function saleByCity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list products')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            // Step 1: Get the total sales for all vouchers
            $totalSales = SaleVoucher::where('status', 1)  // Only paid vouchers
                ->where('business_id', $user->login_business) // Sales for the logged-in business
                ->sum('voucher_amount'); // Sum of all voucher amounts

            // Step 2: Get sales data grouped by city
            $salesByCity = SaleVoucher::select('customers.city_id', 'cities.name as city', DB::raw('SUM(sale_vouchers.voucher_amount) as total_sales'))
                ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                ->join('cities', 'customers.city_id', '=', 'cities.id')
                ->where('sale_vouchers.status', 1)  // Only paid vouchers
                ->where('sale_vouchers.business_id', $user->login_business) // Sales for the logged-in business
                ->groupBy('customers.city_id', 'cities.name'); // Group by city

            if ($start_date && $end_date) {
                $salesByCity->whereBetween('sale_vouchers.voucher_date', [$start_date, $end_date]);
            }

            $salesByCity = $salesByCity->get();
            // Step 3: Calculate percentage for each city
            $result = $salesByCity->map(function($cityData) use ($totalSales) {
                $cityData->percentage = ($totalSales > 0) ? ($cityData->total_sales / $totalSales) * 100 : 0;
                return $cityData;
            });

            // Step 4: Return the response
            return response()->json($result);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');

            $totalPurchaseOrders = PurchaseOrder::where('business_id', $businessId);
        
            if ($start_date && $end_date) {
                $totalPurchaseOrders->whereBetween('created_at', [$start_date, $end_date]);
            }

            $totalCount = $totalPurchaseOrders->count();

            // pending, approved, rejected in percentage
            $pending = PurchaseOrder::where('business_id', $user->login_business)
            ->where('status', 0);
            if($start_date && $end_date){
                $pending->whereBetween('created_at', [$start_date, $end_date]);
            }
            $pendingCount = $pending->count();


            // approved
            $approved = PurchaseOrder::where('purchase_orders.business_id', $user->login_business)
            ->join('goods_receive_notes', 'purchase_orders.id', '=', 'goods_receive_notes.purchase_order_id')
            ->where('goods_receive_notes.status', 1);
            if($start_date && $end_date){
                $approved->whereBetween('purchase_orders.created_at', [$start_date, $end_date]);
            }
            $approvedCount = $approved->count();


            // rejected
            $rejected = PurchaseOrder::where('business_id', $user->login_business)
            ->where('status', 2);
            if($start_date && $end_date){
                $rejected->whereBetween('created_at', [$start_date, $end_date]);
            }
            $rejectedCount = $rejected->count();

            // make percentage

            $pendingPercentage = ($totalCount > 0) ? ($pendingCount / $totalCount) * 100 : 0;
            $approvedPercentage = ($totalCount > 0) ? ($approvedCount / $totalCount) * 100 : 0;
            $rejectedPercentage = ($totalCount > 0) ? ($rejectedCount / $totalCount) * 100 : 0;

            // Return the response with the percentages
            return response()->json([
                'pending_percentage' => round($pendingPercentage,2),
                'approved_percentage' => round($approvedPercentage,2),
                'rejected_percentage' => round($rejectedPercentage,2),
            ]);


        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function saleOrders(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');

            $totalPurchaseOrders = SaleOrder::where('business_id', $businessId);
        
            if ($start_date && $end_date) {
                $totalPurchaseOrders->whereBetween('created_at', [$start_date, $end_date]);
            }

            $totalCount = $totalPurchaseOrders->count();

            // pending, approved, rejected in percentage
            $pending = SaleOrder::where('business_id', $user->login_business)
            ->where('status', 0);
            if($start_date && $end_date){
                $pending->whereBetween('created_at', [$start_date, $end_date]);
            }
            $pendingCount = $pending->count();


            // approved
            $approved = SaleOrder::where('sale_orders.business_id', $user->login_business)
            ->join('delivery_notes', 'sale_orders.id', '=', 'delivery_notes.sale_order_id')
            ->where('delivery_notes.status', 1);
            if($start_date && $end_date){
                $approved->whereBetween('sale_orders.created_at', [$start_date, $end_date]);
            }
            $approvedCount = $approved->count();


            // rejected
            $rejected = SaleOrder::where('business_id', $user->login_business)
            ->where('status', 2);
            if($start_date && $end_date){
                $rejected->whereBetween('created_at', [$start_date, $end_date]);
            }
            $rejectedCount = $rejected->count();

            // make percentage

            $pendingPercentage = ($totalCount > 0) ? ($pendingCount / $totalCount) * 100 : 0;
            $approvedPercentage = ($totalCount > 0) ? ($approvedCount / $totalCount) * 100 : 0;
            $rejectedPercentage = ($totalCount > 0) ? ($rejectedCount / $totalCount) * 100 : 0;

            // Return the response with the percentages
            return response()->json([
                'pending_percentage' => round($pendingPercentage, 2),
                'approved_percentage' => round($approvedPercentage, 2),
                'rejected_percentage' => round($rejectedPercentage, 2),
            ]);


        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function AOV(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');

            $start_date = Carbon::parse($start_date);
            $end_date = Carbon::parse($end_date);

            // today hightest amount order
            $todayHighestAmountOrder = SaleReceipt::where('business_id', $businessId)
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id')
            ->where('sale_receipts.status', 1)
            ->where('sale_receipts.created_at', '>=', Carbon::now()->startOfDay())
            ->where('sale_receipts.created_at', '<=', Carbon::now()->endOfDay())
            ->select(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) as total_amount'))
            ->orderBy(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price)'), 'desc')
            ->first();

            // last 30 days highest amount order
            $last30DaysHighestAmountOrder = SaleReceipt::where('business_id', $businessId)
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id')
            ->where('sale_receipts.status', 1)
            ->where('sale_receipts.created_at', '>=', Carbon::now()->subDays(30))
            ->where('sale_receipts.created_at', '<=', Carbon::now())
            ->select(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) as total_amount'))
            ->orderBy(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price)'), 'desc')
            ->first();

            // last 60 days highest amount order
            $last60DaysHighestAmountOrder = SaleReceipt::where('business_id', $businessId)
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id')
            ->where('sale_receipts.status', 1)
            ->where('sale_receipts.created_at', '>=', Carbon::now()->subDays(60))
            ->where('sale_receipts.created_at', '<=', Carbon::now())
            ->select(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) as total_amount'))
            ->orderBy(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price)'), 'desc')
            ->first();

            // graph of orders
            $query = SaleReceipt::where('sale_receipts.business_id', $businessId)
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id')
            ->where('sale_receipts.status', 1)
            ->whereBetween('sale_receipts.created_at', [$start_date, $end_date]);
        
            $daysDiff = $start_date->diffInDays($end_date);
            $groupBy = $daysDiff > 31 ? 'month' : 'date';
            if ($groupBy === 'month') {
                $query->selectRaw("
                        DATE_FORMAT(sale_receipts.created_at, '%b') as label,
                        DATE_FORMAT(sale_receipts.created_at, '%m') as group_key,
                        SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) / COUNT(DISTINCT sale_receipts.id) as average_order_value
                    ")
                    ->groupBy('label', 'group_key')
                    ->orderBy('group_key');
            } else {
                $query->selectRaw("
                        DATE(sale_receipts.created_at) as label,
                        DATE(sale_receipts.created_at) as group_key,
                        SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) / COUNT(DISTINCT sale_receipts.id) as average_order_value
                    ")
                    ->groupBy('label', 'group_key')
                    ->orderBy('group_key');
            }
            
            $orders = $query->get()->map(function ($row) {
                return [
                    'label' => $row->label,
                    'group_key' => $row->group_key,
                    'average_order_value' => number_format((float) $row->average_order_value, 6, '.', '')
                ];
            });

            return response()->json([
                'today_highest_amount_order' => $todayHighestAmountOrder,
                'last_30_days_highest_amount_order' => $last30DaysHighestAmountOrder,
                'last_60_days_highest_amount_order' => $last60DaysHighestAmountOrder,
                'orders' => $orders,
            ]);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
