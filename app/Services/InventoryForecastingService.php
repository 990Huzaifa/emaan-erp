<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryForecastingService
{
    protected array $validStatuses = ['1', '4']; 
    // 1 = Approved, 4 = Paid

    public function forecastByProduct(
        int $productId,
        ?int $businessId = null,
        int $historyMonths = 12,
        int $forecastMonths = 3,
        float $safetyStockPercent = 0.25
    ): array {
        $endDate = now()->endOfDay();

        // Last 12 months including current month
        $startDate = now()
            ->startOfMonth()
            ->subMonths($historyMonths - 1);

        $totalSoldQty = $this->getTotalSoldQty(
            $productId,
            $businessId,
            $startDate,
            $endDate
        );

        $totalPurchasedQty = $this->getTotalPurchasedQty(
            $productId,
            $businessId,
            $startDate,
            $endDate
        );

        $currentStock = $this->getCurrentStock($productId);

        $averageMonthlySale = $this->getAverageMonthlySale(
            $totalSoldQty,
            $historyMonths
        );

        $averageDailySale = $this->getAverageDailySale(
            $totalSoldQty,
            $startDate,
            $endDate
        );

        $stockCoverDays = $this->getStockCoverDays(
            $currentStock,
            $averageDailySale
        );

        $stockCoverMonths = $this->getStockCoverMonths(
            $currentStock,
            $averageMonthlySale
        );

        $forecastedDemand = $this->getForecastedDemand(
            $averageMonthlySale,
            $forecastMonths
        );

        $safetyStock = $this->getSafetyStock(
            $averageMonthlySale,
            $safetyStockPercent
        );

        $suggestedPurchaseQty = $this->getSuggestedPurchaseQty(
            $forecastedDemand,
            $safetyStock,
            $currentStock
        );

        $stockStatus = $this->getStockStatus(
            $currentStock,
            $averageMonthlySale,
            $stockCoverMonths,
            $forecastMonths
        );

        $monthlyTrend = $this->getMonthlyTrend(
            $productId,
            $businessId,
            $startDate,
            $endDate,
            $historyMonths
        );

        return [
            'product_id' => $productId,

            'meta' => [
                'business_id' => $businessId,
                'history_months' => $historyMonths,
                'forecast_months' => $forecastMonths,
                'from_date' => $startDate->toDateString(),
                'to_date' => $endDate->toDateString(),
                'safety_stock_percent' => $safetyStockPercent,
            ],

            'numbers' => [
                'total_sold_qty' => $totalSoldQty,
                'total_purchased_qty' => $totalPurchasedQty,
                'current_stock' => $currentStock,

                'average_monthly_sale' => $averageMonthlySale,
                'average_daily_sale' => $averageDailySale,

                'stock_cover_days' => $stockCoverDays,
                'stock_cover_months' => $stockCoverMonths,

                'forecasted_demand' => $forecastedDemand,
                'safety_stock' => $safetyStock,
                'suggested_purchase_qty' => $suggestedPurchaseQty,

                'stock_status' => $stockStatus,
            ],

            'graph' => [
                'monthly_trend' => $monthlyTrend,

                'labels' => collect($monthlyTrend)->pluck('label')->values(),

                'datasets' => [
                    'sold_qty' => collect($monthlyTrend)->pluck('sold_qty')->values(),
                    'purchased_qty' => collect($monthlyTrend)->pluck('purchased_qty')->values(),
                    'closing_stock' => collect($monthlyTrend)->pluck('closing_stock')->values(),
                ],
            ],
        ];
    }

    public function getTotalSoldQty(
        int $productId,
        ?int $businessId,
        Carbon $startDate,
        Carbon $endDate
    ): float {
        return (float) DB::table('sale_receipt_items as sri')
            ->join('sale_receipts as sr', 'sr.id', '=', 'sri.sale_receipt_id')
            ->where('sri.product_id', $productId)
            ->whereIn('sr.status', $this->validStatuses)
            ->whereBetween('sr.receipt_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->when($businessId, function ($query) use ($businessId) {
                $query->where('sr.business_id', $businessId);
            })
            ->sum('sri.quantity');
    }

    public function getTotalPurchasedQty(
        int $productId,
        ?int $businessId,
        Carbon $startDate,
        Carbon $endDate
    ): float {
        return (float) DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pii.product_id', $productId)
            ->whereIn('pi.status', $this->validStatuses)
            ->whereBetween('pi.invoice_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->when($businessId, function ($query) use ($businessId) {
                $query->where('pi.business_id', $businessId);
            })
            ->sum('pii.quantity');
    }

    public function getCurrentStock(int $productId): float
    {
        return (float) DB::table('inventory_details')
            ->where('product_id', $productId)
            ->sum('stock');
    }

    public function getAverageMonthlySale(
        float $totalSoldQty,
        int $historyMonths
    ): float {
        if ($historyMonths <= 0) {
            return 0;
        }

        return round($totalSoldQty / $historyMonths, 2);
    }

    public function getAverageDailySale(
        float $totalSoldQty,
        Carbon $startDate,
        Carbon $endDate
    ): float {
        $days = max(1, $startDate->diffInDays($endDate) + 1);

        return round($totalSoldQty / $days, 2);
    }

    public function getStockCoverDays(
        float $currentStock,
        float $averageDailySale
    ): ?float {
        if ($averageDailySale <= 0) {
            return null;
        }

        return round($currentStock / $averageDailySale, 2);
    }

    public function getStockCoverMonths(
        float $currentStock,
        float $averageMonthlySale
    ): ?float {
        if ($averageMonthlySale <= 0) {
            return null;
        }

        return round($currentStock / $averageMonthlySale, 2);
    }

    public function getForecastedDemand(
        float $averageMonthlySale,
        int $forecastMonths
    ): float {
        return round($averageMonthlySale * $forecastMonths, 2);
    }

    public function getSafetyStock(
        float $averageMonthlySale,
        float $safetyStockPercent
    ): float {
        return ceil($averageMonthlySale * $safetyStockPercent);
    }

    public function getSuggestedPurchaseQty(
        float $forecastedDemand,
        float $safetyStock,
        float $currentStock
    ): float {
        $suggestedQty = ($forecastedDemand + $safetyStock) - $currentStock;

        return max(0, ceil($suggestedQty));
    }

    public function getStockStatus(
        float $currentStock,
        float $averageMonthlySale,
        ?float $stockCoverMonths,
        int $forecastMonths
    ): array {
        if ($averageMonthlySale <= 0 && $currentStock > 0) {
            return [
                'code' => 'NO_RECENT_SALE',
                'label' => 'No Recent Sale',
                'message' => 'Product stock available hai lekin selected period mein sale nahi hui.',
            ];
        }

        if ($averageMonthlySale <= 0 && $currentStock <= 0) {
            return [
                'code' => 'NO_HISTORY',
                'label' => 'No Sale History',
                'message' => 'Product ki sale history aur current stock dono available nahi.',
            ];
        }

        if ($currentStock <= 0) {
            return [
                'code' => 'OUT_OF_STOCK',
                'label' => 'Out of Stock',
                'message' => 'Product ka stock khatam ho chuka hai. Purchase urgently required.',
            ];
        }

        if ($stockCoverMonths !== null && $stockCoverMonths <= 1) {
            return [
                'code' => 'CRITICAL_STOCK',
                'label' => 'Critical Stock',
                'message' => 'Stock almost 1 month ya us se kam cover kar raha hai.',
            ];
        }

        if ($stockCoverMonths !== null && $stockCoverMonths <= 2) {
            return [
                'code' => 'LOW_STOCK',
                'label' => 'Low Stock',
                'message' => 'Stock low hai. Purchase planning required.',
            ];
        }

        if ($stockCoverMonths !== null && $stockCoverMonths <= $forecastMonths) {
            return [
                'code' => 'ENOUGH_FOR_FORECAST',
                'label' => 'Enough for Forecast Period',
                'message' => 'Stock forecast period ke liye almost enough hai.',
            ];
        }

        if ($stockCoverMonths !== null && $stockCoverMonths > ($forecastMonths * 2)) {
            return [
                'code' => 'OVER_STOCK',
                'label' => 'Over Stock',
                'message' => 'Product ka stock demand ke comparison mein zyada hai.',
            ];
        }

        return [
            'code' => 'HEALTHY_STOCK',
            'label' => 'Healthy Stock',
            'message' => 'Product ka stock healthy position mein hai.',
        ];
    }

    public function getMonthlyTrend(
        int $productId,
        ?int $businessId,
        Carbon $startDate,
        Carbon $endDate,
        int $historyMonths
    ): array {
        $sales = DB::table('sale_receipt_items as sri')
            ->join('sale_receipts as sr', 'sr.id', '=', 'sri.sale_receipt_id')
            ->selectRaw("DATE_FORMAT(sr.receipt_date, '%Y-%m') as month")
            ->selectRaw('SUM(sri.quantity) as sold_qty')
            ->where('sri.product_id', $productId)
            ->whereIn('sr.status', $this->validStatuses)
            ->whereBetween('sr.receipt_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->when($businessId, function ($query) use ($businessId) {
                $query->where('sr.business_id', $businessId);
            })
            ->groupBy('month')
            ->pluck('sold_qty', 'month');

        $purchases = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->selectRaw("DATE_FORMAT(pi.invoice_date, '%Y-%m') as month")
            ->selectRaw('SUM(pii.quantity) as purchased_qty')
            ->where('pii.product_id', $productId)
            ->whereIn('pi.status', $this->validStatuses)
            ->whereBetween('pi.invoice_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->when($businessId, function ($query) use ($businessId) {
                $query->where('pi.business_id', $businessId);
            })
            ->groupBy('month')
            ->pluck('purchased_qty', 'month');

        $trend = [];
        $runningStock = 0;

        for ($i = 0; $i < $historyMonths; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $monthKey = $date->format('Y-m');

            $soldQty = (float) ($sales[$monthKey] ?? 0);
            $purchasedQty = (float) ($purchases[$monthKey] ?? 0);

            $runningStock += $purchasedQty - $soldQty;

            $trend[] = [
                'month' => $monthKey,
                'label' => $date->format('M Y'),
                'sold_qty' => $soldQty,
                'purchased_qty' => $purchasedQty,
                'net_movement' => $purchasedQty - $soldQty,
                'closing_stock' => $runningStock,
            ];
        }

        return $trend;
    }
}