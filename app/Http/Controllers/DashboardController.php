<?php

namespace App\Http\Controllers;

use App\Models\InventoryDetail;
use App\Models\SaleOrder;
use App\Models\SaleVoucher;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $outOfStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image')
            ->where('inventory_details.in_stock', 0)
            ->join('products','inventory_details.product_id','=','products.id')->get();
            $inStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image')
            ->where('inventory_details.in_stock', 1)
            ->join('products','inventory_details.product_id','=','products.id')->get();
            $lowInStockProducts = InventoryDetail::select('inventory_details.*','products.title','products.image')
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
            $totalSaleOrderPending = SaleOrder::whereYear('created_at', $year)
                ->where('status', 0)
                ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderApproved = SaleOrder::whereYear('created_at', $year)
                ->where('status', 1)
                ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            $totalSaleOrderDelivered = SaleOrder::whereYear('sale_orders.created_at', $year)
                ->where('delivery_notes.status', 1)
                ->join('delivery_notes', 'sale_orders.id', '=', 'delivery_notes.sale_order_id')
                ->selectRaw('MONTH(sale_orders.created_at) as month, YEAR(sale_orders.created_at) as year, COUNT(*) as count')
                ->groupBy('month', 'year')
                ->get();

            // Total Sale: Filtered by Year and Month
            $totalSale = SaleVoucher::where('status', 1)
                ->whereYear('voucher_date', $year)
                ->whereMonth('voucher_date', $month)
                ->sum('voucher_amount');

            $data = [
                'salesGraph' => $salesGraph,
                'salesData' => [
                    'totalSaleOrderPending' => $totalSaleOrderPending,
                    'totalSaleOrderApproved' => $totalSaleOrderApproved,
                    'totalSaleOrderDelivered' => $totalSaleOrderDelivered,
                ],
                'totalSale' => $totalSale,
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
