<?php

namespace App\Http\Controllers;

use App\Models\BusinessHasAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryNoteItem;
use App\Models\GoodsReceiveNoteItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseVoucher;
use App\Models\SaleOrderItem;
use App\Models\SaleVoucher;
use Carbon\Carbon;
use Exception;
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
            $total = PurchaseVoucher::where('status', 1)
            ->whereBetween('voucher_date', [$start_date, $end_date])
            ->when(!empty($businessId), function ($q) use ($businessId) {
                return $q->where('business_id', $businessId);
            })
            ->sum('voucher_amount');

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
                ->whereBetween('sale_vouchers.voucher_date', [$start_date, $end_date])
                ->where('sale_vouchers.status', 1);

            // Filter by business_id if provided
            if (!empty($businessId)) {
                $query->where('sale_vouchers.business_id', $businessId);
            }

            // Fetch data and order by voucher date (descending)
            $salesData = $query->orderBy('sale_vouchers.voucher_date', 'desc')->get();

            // Calculate total voucher amount for active sales
            $total = SaleVoucher::where('status', 1)
            ->whereBetween('voucher_date', [$start_date, $end_date])
            ->when(!empty($businessId), function ($q) use ($businessId) {
                return $q->where('business_id', $businessId);
            })
            ->sum('voucher_amount');

            return response()->json(["data" => $salesData, "total" => $total]);
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


    public function balanceSheet(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;

            // Permission check for non-admin users
            if ($user->role != 'admin' && !$user->hasBusinessPermission($businessId, 'financial report')) {
                return response()->json([
                    'error' => 'User does not have the required permission.'
                ], 403);
            }

            // Handle optional start_date and end_date
            $start_date = $request->input('start_date', ) ?? '1999-01-01';
            $end_date = $request->input('end_date', Carbon::now()->toDateString()); // Default to today

            $cash_code = ChartOfAccount::where('name','CASH')->value('code');
            $cash_accs = ChartOfAccount::where('parent_code',$cash_code)
            ->join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
            ->where('business_has_accounts.business_id', $businessId)
            ->pluck('chart_of_accounts.id');

            $bank_code = ChartOfAccount::where('name','BANK')->value('code');
            $bank_accs = ChartOfAccount::where('parent_code',$bank_code)
            ->join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
            ->where('business_has_accounts.business_id', $businessId)
            ->pluck('chart_of_accounts.id');





            $customer_accs = Customer::where('business_id',$businessId)->value('acc_id');

            // filter aout by business

            

            return response()->json($bank_accs, 200);


        }catch(Exception $e){
            return response()->json(['error'=> $e->getMessage()], 400);
        }
    }

}
