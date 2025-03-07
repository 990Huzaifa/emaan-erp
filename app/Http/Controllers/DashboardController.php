<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryDetail;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
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
                'sale_vouchers.*',
                'customers.name as customer_name',
                'customers.c_code as customer_code',
                'cities.name as city',
                DB::raw("DATEDIFF(NOW(), sale_vouchers.voucher_date) as no_of_days")
            )
            ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
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

            // Sales Graph: Filtered by Year and Month
            $salesGraph = SaleVoucher::where('status', 1)
                ->whereYear('voucher_date', $year)
                ->whereMonth('voucher_date', $month)
                ->selectRaw('MONTH(voucher_date) as month, YEAR(voucher_date) as year, sum(voucher_amount) as total')
                ->groupBy('month', 'year')
                ->get();

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

            $salesView = [
                'totalSale' => $totalSale,
                'currentMonthSale' => $currentMonthSale,
                'todaySale' => $todaySale,
                'percentageIncrease' => round($percentageInc, 2)
            ];

            $data = [
                'salesGraph' => $salesGraph,
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
            $last_month = Carbon::now()->subMonth()->month;

            $total_customers = Customer::where('business_id',$businessId)->count();
            $lm_customers = Customer::where('business_id',$businessId)->whereMonth('created_at', $last_month)->count();
            $ipc_customers = ($lm_customers > 0) ? (($total_customers - $lm_customers) / $lm_customers) * 100 : 0;

            $total_inventory = InventoryDetail::groupBy('product_id')->count();
            $lm_inventory = InventoryDetail::whereMonth('created_at', $last_month)->groupBy('product_id')->count();
            $ipc_inventory = ($lm_inventory > 0) ? (($total_inventory - $lm_inventory) / $lm_inventory) * 100 : 0;

            $total_sale_orders = SaleOrder::where('business_id',$businessId)->count();
            $lm_sale_orders = SaleOrder::where('business_id',$businessId)->whereMonth('voucher_date', $last_month)->count();
            $ipc_sale_orders = ($lm_sale_orders > 0) ? (($total_sale_orders - $lm_sale_orders) / $lm_sale_orders) * 100 : 0;

            $total_purchases = PurchaseOrder::where('business_id',$businessId)->count();
            $lm_purchases = PurchaseOrder::where('business_id',$businessId)->whereMonth('voucher_date', $last_month)->count();
            $ipc_purchases = ($lm_purchases > 0) ? (($total_purchases - $lm_purchases) / $lm_purchases) * 100 : 0;

            $data = [
                'Customers' => [
                    'total_customers' => $total_customers,
                    'ipc_customers' => $ipc_customers,

                ],
                'Products' => [
                    'total_inventory' => $total_inventory,
                    'ipc_inventory' => $ipc_inventory
                ],
                'SaleOrders' => [
                    'total_sale_orders' => $total_sale_orders,
                    'ipc_sale_orders' => $ipc_sale_orders
                ],
                'Purchases' => [
                    'total_purchases' => $total_purchases,
                    'ipc_purchases' => $ipc_purchases
                ]
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
