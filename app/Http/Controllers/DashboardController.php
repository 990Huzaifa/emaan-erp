<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryDetail;
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

            // Base Query
            $query = SaleVoucher::select(
                'sale_vouchers.customer_id',
                'sale_vouchers.voucher_date',
                'sale_vouchers.status',
                'customers.name as customer_name',
                'customers.c_code as customer_code',
                'cities.name as city',
                DB::raw("transactions.current_balance as balance"),
                DB::raw("DATEDIFF(NOW(), sale_vouchers.voucher_date) as no_of_days")
            )
            ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
            ->join('chart_of_accounts', 'customers.id', '=', 'chart_of_accounts.ref_id')
            ->join(
                DB::raw('(SELECT acc_id, current_balance FROM transactions WHERE id IN (SELECT MAX(id) FROM transactions GROUP BY acc_id)) as transactions'),
                'chart_of_accounts.id', '=', 'transactions.acc_id'
            )
            ->join('cities', 'customers.city_id', '=', 'cities.id');

            // Apply filters
            if ($filter === 'non-paid') {
                $query->where('sale_vouchers.status', 0); // Unpaid vouchers
            } elseif ($filter === 'overdue') {
                $query->where('sale_vouchers.status', 0)
                  ->having('no_of_days', '>', 30);
            }

            // Filter by month if provided
            if ($month) {
                $query->whereMonth('sale_vouchers.voucher_date', $month);
            }

            $data = $query->get();

            return response()->json($data);
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
            $date = $request->input('date') ?? Carbon::now()->format('Y-m');
            $year = substr($date, 0, 4); // Extract year
            $month = substr($date, 5); // Extract month
            $graphFilter = request('graph_filter');

            // Sales Graph: Filtered by Year and Month
            $query = SaleVoucher::where('status', 1)
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
                ->where('status', 0)
                ->selectRaw('MONTH(order_date) as month, YEAR(order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderApproved = SaleOrder::whereYear('order_date', $year)
                ->where('status', 1)
                ->selectRaw('MONTH(order_date) as month, YEAR(order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderDelivered = SaleOrder::whereYear('sale_orders.order_date', $year)
                ->where('delivery_notes.status', 1)
                ->join('delivery_notes', 'sale_orders.id', '=', 'delivery_notes.sale_order_id')
                ->selectRaw('MONTH(sale_orders.order_date) as month, YEAR(sale_orders.order_date) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            // Get the latest month from sales data in the given year
            $latestMonth = SaleVoucher::where('status', 1)
            ->whereYear('voucher_date', $year)
            ->selectRaw('MAX(MONTH(voucher_date)) as latestMonth')
            ->value('latestMonth');

            // Get total sales for the entire year (up to the latest month)
            $totalYearSale = SaleVoucher::where('status', 1)
            ->whereYear('voucher_date', $year)
            ->sum('voucher_amount');

            // Get total sales for the previous month (if available)
            $previousMonthTotalSale = SaleVoucher::where('status', 1)
            ->whereYear('voucher_date', $year)
            ->whereMonth('voucher_date', $latestMonth - 1) // Previous month
            ->sum('voucher_amount');

            // Calculate percentage increase
            $percentageIncrease = ($previousMonthTotalSale > 0)
            ? (($totalYearSale - $previousMonthTotalSale) / $previousMonthTotalSale) * 100
            : 0;

            // total of sales, current month sales, today sale, last month sales increase percentage
            $totalSale = SaleVoucher::where('status', 1)->sum('voucher_amount');

            // Current month sales
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;
            $currentMonthSale = SaleVoucher::where('status', 1)->whereYear('voucher_date', $currentYear)->whereMonth('voucher_date', $currentMonth)->sum('voucher_amount');

            // Today's sales
            $todaySale = SaleVoucher::where('status', 1)->whereDate('voucher_date', Carbon::now()->toDateString())->sum('voucher_amount');

            // Last month's sales
            $lastMonth = Carbon::now()->subMonth()->month;
            $lastMonthSale = SaleVoucher::where('status', 1)->whereYear('voucher_date', $currentYear)->whereMonth('voucher_date', $lastMonth)->sum('voucher_amount');

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

    public function saleByProduct(): JsonResponse
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
            $query = SaleReceipt::where('business_id', $user->login_business)  // Filter SaleReceipt by business_id
            ->where('status', 1)  // Ensure the status is active
            ->join('sale_receipt_items', 'sale_receipts.id', '=', 'sale_receipt_items.sale_receipt_id') // Join with SaleReceiptItems
            ->join('products', 'sale_receipt_items.product_id', '=', 'products.id') // Join with Products to get the product title
            ->select('sale_receipt_items.product_id', 'products.title', DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price) as total_sales')) // Select product_id, product title and total sales
            ->groupBy('sale_receipt_items.product_id', 'products.title')  // Group by product_id and product title
            ->orderBy(DB::raw('SUM(sale_receipt_items.quantity * sale_receipt_items.unit_price)'), 'desc')
            ->get();

            return response()->json($query, 200);

        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


}
