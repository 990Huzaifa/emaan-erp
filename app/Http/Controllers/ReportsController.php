<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNoteItem;
use App\Models\GoodsReceiveNoteItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\SaleOrderItem;
use App\Models\SaleVoucher;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function inventoryReport(Request $request): JsonResponse
    {

        try{
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
            $startDate = $request->start_date ?? '1970-01-01';
            $endDate = $request->end_date ?? now()->format('Y-m-d');
        
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
                ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]) // Ensure date comparison
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
        try{

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

    public function purchaseSummary(): JsonResponse
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

            // Query purchase vouchers with vendor names
            $query = PurchaseVoucher::select('purchase_vouchers.*', 'vendors.name as vendor_name')
                ->join('vendors', 'purchase_vouchers.vendor_id', '=', 'vendors.id')
                ->where('purchase_vouchers.status', 1);

            // Apply business_id filter if the user is not an admin
            if (!empty($businessId)) {
                $query->where('purchase_vouchers.business_id', $businessId);
            }

            // Fetch data ordered by voucher date (descending)
            $purchaseData = $query->orderBy('purchase_vouchers.voucher_date', 'desc')->get();

            // Calculate total voucher amount with business filter if applicable
            $total = PurchaseVoucher::where('status', 1)
                ->when(!empty($businessId), function ($q) use ($businessId) {
                    return $q->where('business_id', $businessId);
                })
                ->sum('voucher_amount');

            return response()->json(["data" => $purchaseData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function salesSummary(): JsonResponse
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

            // Query sale vouchers with customer names
            $query = SaleVoucher::select('sale_vouchers.*', 'customers.name as customer_name')
                ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                ->where('sale_vouchers.status', 1);

            // Filter by business_id if provided
            if (!empty($businessId)) {
                $query->where('sale_vouchers.business_id', $businessId);
            }

            // Fetch data and order by voucher date (descending)
            $salesData = $query->orderBy('sale_vouchers.voucher_date', 'desc')->get();

            // Calculate total voucher amount for active sales
            $total = SaleVoucher::where('status', 1)
                ->when(!empty($businessId), function ($q) use ($businessId) {
                    return $q->where('business_id', $businessId);
                })
                ->sum('voucher_amount');

            return response()->json(["data" => $salesData, "total" => $total]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function financialReport(): JsonResponse
    {
        $totalPurchases = DB::table('transactions')->where('transaction_type', 0)->sum('debit');
        $totalSales = DB::table('transactions')->where('transaction_type', 1)->sum('credit');
        $totalExpenses = DB::table('transactions')->where('transaction_type', 2)->sum('debit');
        $totalIncome = DB::table('transactions')->where('transaction_type', 3)->sum('credit');

        $Purchases = DB::table('transactions')->where('transaction_type', 0)->get();
        $Sales = DB::table('transactions')->where('transaction_type', 1)->get();
        $Expenses = DB::table('transactions')->where('transaction_type', 2)->get();
        $Income = DB::table('transactions')->where('transaction_type', 3)->get();

        $netProfit = ($totalSales + $totalIncome) - ($totalPurchases + $totalExpenses);

        return response()->json([
            'purchases' => [
                'data' => $Purchases, 'total' => $totalPurchases
            ],
            'sales' => [
                'data' => $Sales, 'total' => $totalSales
            ],
            'expenses' => [
                'data' => $Expenses, 'total' => $totalExpenses
            ],
            'income' => [
                'data' => $Income, 'total' => $totalIncome
            ],
            'net_profit' => $netProfit,
        ]);
    }

}
