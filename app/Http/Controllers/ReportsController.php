<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\SaleOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function inventoryReport(Request $request): JsonResponse
    {
        $perpage = $request->input('perpage', 10);
        $startDate = $request->start_date ?? '1970-01-01';
        $endDate = $request->end_date ?? now()->format('Y-m-d');
    
        // Sum quantities of products from purchase orders (IN)
        $purchasedItems = PurchaseOrderItem::select('product_id', DB::raw('SUM(quantity) as total_in'))
            ->groupBy('product_id');
    
        // Sum quantities of products from sales orders (OUT)
        $soldItems = SaleOrderItem::select('product_id', DB::raw('SUM(quantity) as total_out'))
            ->groupBy('product_id');
    
        // Join the data with the Product table
        $inventoryReport = Product::
            leftJoinSub($purchasedItems, 'purchased', 'products.id', '=', 'purchased.product_id')
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
    }


    public function inventoryReportDetail(Request $request): JsonResponse
    {
        // Fetch the product details
        $product = Product::select('id', 'title', 'image', 'description')
            ->find($request->id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Fetch purchase (IN) data
        $inData = PurchaseOrderItem::where('product_id', $product->id)
            ->select('quantity', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch sales (OUT) data
        $outData = SaleOrderItem::where('product_id', $product->id)
            ->select('quantity', 'created_at')
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
