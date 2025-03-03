<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessHasAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryNoteItem;
use App\Models\GoodsReceiveNote;
use App\Models\GoodsReceiveNoteItem;
use App\Models\InventoryDetail;
use App\Models\Lot;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\SaleOrderItem;
use App\Models\SaleVoucher;
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

            $start_date = $request->input('start_date', PurchaseVoucher::min('voucher_date')); // Default: earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default: today

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->toDateString();
            $end_date = Carbon::parse($end_date)->toDateString();

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


            $start_date = $request->input('start_date', SaleVoucher::min('voucher_date')); // Default: earliest transaction date
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default: today

            // Ensure valid date format
            $start_date = Carbon::parse($start_date)->toDateString();
            $end_date = Carbon::parse($end_date)->toDateString();

            // Query sale vouchers with customer names
            $query = SaleVoucher::select('sale_vouchers.*', 'customers.name as customer_name')
                ->join('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                ->where('sale_vouchers.voucher_date', '>=', $start_date)
                ->where('sale_vouchers.voucher_date', '<=', $end_date)
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
            if ($user->role != 'admin' && !$user->hasBusinessPermission($businessId, 'sales summary')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            $filterType = $request->input('filter_type'); // e.g., 'customer', 'month', 'item'

            $query = DB::table('sale_receipts AS sr')
            ->join('sale_receipt_items AS sri', 'sr.id', '=', 'sri.sale_receipt_id')
            ->join('customers AS c', 'sr.customer_id', '=', 'c.id')
            ->join('products AS p', 'sri.product_id', '=', 'p.id')
            ->where('sr.business_id', $businessId)
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
            $start_date = Carbon::parse($start_date)->toDateString();
            $end_date = Carbon::parse($end_date)->toDateString();

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
                if (!$user->hasBusinessPermission($businessId, 'sales summary')) {
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
            $lots_ids = Lot::whereIn('grn_id', $grn_ids)->pluck('id');
            $inventory = InventoryDetail::whereIn('lot_id', $lots_ids)->get();

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

            $inventory_total = 0;
            foreach ($inventory as $key => $item) {
                $inventory_total += $item->stock * $item->unit_price;
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
    

}
