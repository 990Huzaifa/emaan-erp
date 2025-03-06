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
        try{
            $date = $request->input('date') ?? Carbon::now()->format('Y-m');

            $salesGraph = SaleVoucher::where('status', 1)
            ->whereYear('voucher_date', substr($date, 0, 4))
            ->whereMonth('voucher_date', substr($date, 5))
            ->selectRaw('MONTH(voucher_date) as month, YEAR(voucher_date) as year, sum(voucher_amount) as total')
            ->groupBy('month', 'year')
            ->get();

            $totalSaleOrderPending = SaleOrder::select('sale_orders.*')->where('status', 0)->count();
            $totalSaleOrderApproved = SaleOrder::select('sale_orders.*')->where('status', 1)->count();
            $totalSaleOrderDelivered = SaleOrder::select('sale_orders.*')->where('delivery_notes.status', 1)
            ->join('delivery_notes', 'sale_orders.id', '=', 'delivery_notes.sale_order_id')->count();

            $salesData = [
                'totalSaleOrderPending' => $totalSaleOrderPending,
                'totalSaleOrderApproved' => $totalSaleOrderApproved,
                'totalSaleOrderDelivered' => $totalSaleOrderDelivered,
            ];

            $totalSale = SaleVoucher::where('status', 1)->sum('voucher_amount');

            $data = [
                'salesGraph' => $salesGraph,
                'salesData' => $salesData,
                'totalSale' => $totalSale,
            ];

            
            return response()->json($data,200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }
        catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
