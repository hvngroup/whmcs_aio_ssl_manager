<?php
/**
 * Report Service
 * Handles report data queries and calculations
 * 
 * IMPORTANT CURRENCY NOTES:
 * - WHMCS default currency is VND
 * - tblhosting.firstpaymentamount = VND (including 10% VAT)
 * - NicSRS cost from mod_nicsrs_products.price_data = USD (no VAT)
 * - For profit calculation: Convert VND revenue to USD (after removing VAT)
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Helper\CurrencyHelper;

class ReportService
{
    /**
     * Billing cycle to months mapping
     */
    const BILLING_CYCLE_MONTHS = [
        'Monthly' => 1,
        'Quarterly' => 3,
        'Semi-Annually' => 6,
        'Annually' => 12,
        'Biennially' => 24,
        'Triennially' => 36,
    ];

    /**
     * Billing cycle to price key mapping (NicSRS API)
     */
    const BILLING_CYCLE_PRICE_KEY = [
        'Monthly' => 'price001',
        'Quarterly' => 'price003',
        'Semi-Annually' => 'price006',
        'Annually' => 'price012',
        'Biennially' => 'price024',
        'Triennially' => 'price036',
    ];

    // =========================================================================
    // SALES REPORT METHODS
    // =========================================================================

    /**
     * Get SSL Sales data with filters
     * 
     * NOTE: sale_amount from tblhosting is in VND (including VAT)
     * We convert to USD for display consistency
     * 
     * @param array $filters ['date_from', 'date_to', 'product_code', 'vendor', 'status']
     * @return array Sales data with orders and summary
     */
    public function getSalesReport(array $filters = []): array
    {
        $query = $this->buildBaseQuery();

        // Apply filters
        $this->applyDateFilters($query, $filters);
        $this->applyProductFilters($query, $filters);

        if (!empty($filters['status'])) {
            $query->where('o.status', $filters['status']);
        }

        $orders = $query->orderBy('o.provisiondate', 'desc')->get();

        // Calculate totals
        $totalSalesVnd = 0;
        $totalSalesUsd = 0;
        $totalRecurringVnd = 0;
        $orderCount = count($orders);

        $processedOrders = [];
        foreach ($orders as $order) {
            // Original amounts in VND (with VAT)
            $saleAmountVnd = (float) ($order->sale_amount ?? 0);
            $recurringAmountVnd = (float) ($order->recurring_amount ?? 0);
            
            // Convert to USD (after removing VAT) for reporting
            $saleAmountUsd = CurrencyHelper::revenueVndToUsd($saleAmountVnd);
            
            $totalSalesVnd += $saleAmountVnd;
            $totalSalesUsd += $saleAmountUsd;
            $totalRecurringVnd += $recurringAmountVnd;

            $processedOrders[] = [
                'order_id' => $order->order_id,
                'serviceid' => $order->serviceid,
                'product_code' => $order->product_code,
                'product_name' => $order->product_name ?? $order->product_code,
                'vendor' => $order->vendor ?? 'Unknown',
                'validation_type' => $order->validation_type ?? 'dv',
                'status' => $order->status,
                'provision_date' => $order->service_date ?: $order->provisiondate, // Prefer tblhosting.regdate
                'completion_date' => $order->completiondate,
                'service_date' => $order->service_date,
                // Store both VND and USD amounts
                'sale_amount_vnd' => $saleAmountVnd,
                'sale_amount' => $saleAmountUsd, // USD (for backward compat)
                'sale_amount_usd' => $saleAmountUsd,
                'recurring_amount_vnd' => $recurringAmountVnd,
                'recurring_amount' => CurrencyHelper::revenueVndToUsd($recurringAmountVnd),
                'billing_cycle' => $order->billingcycle,
                'client_name' => trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? '')),
                'company_name' => $order->companyname ?? '',
            ];
        }

        return [
            'orders' => $processedOrders,
            'summary' => [
                // VND totals (original from WHMCS, with VAT)
                'total_sales_vnd' => $totalSalesVnd,
                'total_recurring_vnd' => $totalRecurringVnd,
                // USD totals (converted, without VAT)
                'total_sales' => $totalSalesUsd, // for backward compat
                'total_sales_usd' => $totalSalesUsd,
                'total_recurring_usd' => CurrencyHelper::revenueVndToUsd($totalRecurringVnd),
                'order_count' => $orderCount,
                'avg_order_value' => $orderCount > 0 ? $totalSalesUsd / $orderCount : 0,
                'avg_order_value_vnd' => $orderCount > 0 ? $totalSalesVnd / $orderCount : 0,
            ],
        ];
    }

    /**
     * Get sales data grouped by period (for charts)
     * 
     * IMPORTANT: First param is $groupBy (string), second is $filters (array)
     * This matches the call from ReportController line 99
     * 
     * @param string $groupBy 'day', 'week', 'month', 'quarter', 'year'
     * @param array $filters Filters
     * @return array Chart data
     */
    public function getSalesByPeriod(string $groupBy = 'month', array $filters = []): array
    {
        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-W%V',
            'month' => '%Y-%m',
            'quarter' => 'quarter',
            'year' => '%Y',
            default => '%Y-%m'
        };

        $query = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id');

        if ($groupBy === 'quarter') {
            $query->selectRaw("CONCAT(YEAR(h.regdate), '-Q', QUARTER(h.regdate)) as period");
        } else {
            $query->selectRaw("DATE_FORMAT(h.regdate, '{$dateFormat}') as period");
        }

        $query->selectRaw('SUM(COALESCE(h.firstpaymentamount, 0)) as total_revenue_vnd')
            ->selectRaw('COUNT(*) as order_count')
            ->whereNotNull('h.regdate')
            ->where('h.regdate', '!=', '0000-00-00')
            ->groupBy('period')
            ->orderBy('period', 'asc');

        $this->applyDateFilters($query, $filters, 'h.regdate');
        $this->applyProductFilters($query, $filters);

        $results = $query->get();

        $labels = [];
        $revenueVndData = [];
        $revenueUsdData = [];
        $orderCountData = [];

        foreach ($results as $row) {
            $labels[] = $row->period;
            $revenueVnd = (float) $row->total_revenue_vnd;
            $revenueUsd = CurrencyHelper::revenueVndToUsd($revenueVnd);
            
            $revenueVndData[] = $revenueVnd;
            $revenueUsdData[] = round($revenueUsd, 2);
            $orderCountData[] = (int) $row->order_count;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                'revenue_vnd' => $revenueVndData,
                'revenue_usd' => $revenueUsdData,
                'revenue' => $revenueUsdData, // backward compat
                'orders' => $orderCountData,
            ],
        ];
    }

    /**
     * Get sales data grouped by product
     * 
     * @param array $filters Filters
     * @return array Product sales data
     */
    public function getSalesByProduct(array $filters = []): array
    {
        $query = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code')
            ->select([
                'o.certtype as product_code',
                'p.name as product_name',
                'np.vendor',
            ])
            ->selectRaw('SUM(COALESCE(h.firstpaymentamount, 0)) as total_sales_vnd')
            ->selectRaw('COUNT(*) as order_count')
            ->whereNotNull('h.regdate')
            ->where('h.regdate', '!=', '0000-00-00')
            ->groupBy('o.certtype', 'p.name', 'np.vendor')
            ->orderBy('total_sales_vnd', 'desc');

        $this->applyDateFilters($query, $filters, 'h.regdate');

        $results = $query->get();

        // Add USD conversion
        foreach ($results as $row) {
            $row->total_sales_vnd = (float) $row->total_sales_vnd;
            $row->total_sales = CurrencyHelper::revenueVndToUsd($row->total_sales_vnd);
        }

        return $results->toArray();
    }

    // =========================================================================
    // PROFIT REPORT METHODS
    // =========================================================================

    /**
     * Get SSL Profit data
     * 
     * CALCULATION LOGIC:
     * - Revenue (VND with VAT) from tblhosting.firstpaymentamount
     * - Revenue (USD) = Revenue (VND) / 1.1 (remove VAT) / exchange_rate
     * - Cost (USD) from mod_nicsrs_products.price_data (NicSRS wholesale price)
     * - Profit (USD) = Revenue (USD) - Cost (USD)
     * 
     * @param array $filters Filters
     * @return array Profit report data
     */
    public function getProfitReport(array $filters = []): array
    {
        $query = $this->buildProfitQuery();
        
        $this->applyDateFilters($query, $filters, 'h.regdate');
        $this->applyProductFilters($query, $filters);

        // Only include completed orders for profit calculation
        if (empty($filters['include_all_status'])) {
            $query->where('o.status', 'complete');
        }

        $orders = $query->orderBy('h.regdate', 'desc')->get();

        $results = [];
        $totalRevenueVnd = 0;
        $totalRevenueUsd = 0;
        $totalCostUsd = 0;
        $totalProfitUsd = 0;

        foreach ($orders as $order) {
            // Revenue from WHMCS (VND with VAT)
            $revenueVndWithVat = (float) ($order->sale_amount ?? 0);
            
            // Convert to USD (remove VAT first, then convert)
            $revenueUsd = CurrencyHelper::revenueVndToUsd($revenueVndWithVat);
            
            // Cost from NicSRS (USD, no VAT)
            $costUsd = $this->calculateNicsrsCost($order->price_data, $order->billingcycle);
            
            // Profit calculation
            $profitUsd = $revenueUsd - $costUsd;
            $profitMargin = $revenueUsd > 0 ? ($profitUsd / $revenueUsd) * 100 : 0;

            $results[] = [
                'order_id' => $order->order_id,
                'serviceid' => $order->serviceid,
                'product_code' => $order->product_code,
                'product_name' => $order->product_name ?? $order->product_code,
                'vendor' => $order->vendor ?? 'Unknown',
                'date' => $order->service_date,
                'service_date' => $order->service_date, // Explicit service date                
                'provision_date' => $order->provisiondate,
                'status' => $order->status,
                'billing_cycle' => $order->billingcycle,
                // Revenue breakdown
                'revenue_vnd_with_vat' => $revenueVndWithVat,
                'revenue_vnd_without_vat' => CurrencyHelper::removeVat($revenueVndWithVat),
                'sale_amount_usd' => round($revenueUsd, 2), // Revenue in USD (for display)
                // Cost and profit
                'cost_usd' => round($costUsd, 2),
                'profit_usd' => round($profitUsd, 2),
                'profit_vnd' => CurrencyHelper::usdToVnd($profitUsd),
                'profit_margin' => round($profitMargin, 2),
            ];

            $totalRevenueVnd += $revenueVndWithVat;
            $totalRevenueUsd += $revenueUsd;
            $totalCostUsd += $costUsd;
            $totalProfitUsd += $profitUsd;
        }

        $overallMargin = $totalRevenueUsd > 0 ? ($totalProfitUsd / $totalRevenueUsd) * 100 : 0;

        return [
            'orders' => $results,
            'summary' => [
                // VND totals
                'total_revenue_vnd' => $totalRevenueVnd,
                'total_revenue_vnd_without_vat' => CurrencyHelper::removeVat($totalRevenueVnd),
                'total_vat_vnd' => CurrencyHelper::calculateVatAmount($totalRevenueVnd),
                // USD totals
                'total_revenue_usd' => round($totalRevenueUsd, 2),
                'total_cost_usd' => round($totalCostUsd, 2),
                'total_profit_usd' => round($totalProfitUsd, 2),
                'total_profit_vnd' => CurrencyHelper::usdToVnd($totalProfitUsd),
                'profit_margin' => round($overallMargin, 2),
                'order_count' => count($orders),
            ],
            'currency_info' => CurrencyHelper::getRateInfo(),
        ];
    }

    /**
     * Get profit data grouped by period (for charts)
     * 
     * IMPORTANT: First param is $groupBy (string), second is $filters (array)
     * This matches the call from ReportController
     * 
     * @param string $groupBy 'day', 'week', 'month', 'quarter', 'year'
     * @param array $filters Filters
     * @return array Chart data
     */
    public function getProfitByPeriod(string $groupBy = 'month', array $filters = []): array
    {
        // Get profit report data first
        $profitData = $this->getProfitReport($filters);
        $orders = $profitData['orders'];

        $grouped = [];

        foreach ($orders as $order) {
            $date = $order['service_date'] ?? $order['date'];
            if (empty($date) || $date === '0000-00-00') {
                continue;
            }

            $periodKey = match ($groupBy) {
                'day' => date('Y-m-d', strtotime($date)),
                'week' => date('Y-\WW', strtotime($date)),
                'month' => date('Y-m', strtotime($date)),
                'quarter' => date('Y', strtotime($date)) . '-Q' . ceil(date('n', strtotime($date)) / 3),
                'year' => date('Y', strtotime($date)),
                default => date('Y-m', strtotime($date))
            };

            if (!isset($grouped[$periodKey])) {
                $grouped[$periodKey] = [
                    'period' => $periodKey,
                    'revenue_usd' => 0,
                    'cost_usd' => 0,
                    'profit_usd' => 0,
                    'order_count' => 0,
                ];
            }

            $grouped[$periodKey]['revenue_usd'] += $order['sale_amount_usd'];
            $grouped[$periodKey]['cost_usd'] += $order['cost_usd'];
            $grouped[$periodKey]['profit_usd'] += $order['profit_usd'];
            $grouped[$periodKey]['order_count']++;
        }

        ksort($grouped);

        $labels = [];
        $revenueData = [];
        $costData = [];
        $profitData = [];

        foreach ($grouped as $period => $data) {
            $labels[] = $period;
            $revenueData[] = round($data['revenue_usd'], 2);
            $costData[] = round($data['cost_usd'], 2);
            $profitData[] = round($data['profit_usd'], 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                'revenue' => $revenueData,
                'cost' => $costData,
                'profit' => $profitData,
            ],
            'raw' => array_values($grouped),
        ];
    }

    /**
     * Calculate NicSRS cost from price_data JSON
     * 
     * @param string|null $priceDataJson JSON price data from mod_nicsrs_products
     * @param string|null $billingCycle Billing cycle
     * @return float Cost in USD
     */
    public function calculateNicsrsCost(?string $priceDataJson, ?string $billingCycle): float
    {
        if (!$priceDataJson) {
            return 0;
        }

        $priceData = json_decode($priceDataJson, true);
        if (!$priceData || !isset($priceData['basePrice'])) {
            return 0;
        }

        // Get price key based on billing cycle
        $periodKey = self::BILLING_CYCLE_PRICE_KEY[$billingCycle] ?? 'price012';

        return isset($priceData['basePrice'][$periodKey]) 
            ? (float) $priceData['basePrice'][$periodKey] 
            : 0;
    }

    // =========================================================================
    // PRODUCT PERFORMANCE METHODS
    // =========================================================================

    /**
     * Get Product Performance data
     * 
     * @param array $filters Filters
     * @return array Performance data
     */
    public function getProductPerformance(array $filters = []): array
    {
        $query = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code')
            ->select([
                'o.certtype as product_code',
                'p.name as product_name',
                'np.vendor',
                'np.validation_type',
            ])
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(COALESCE(h.firstpaymentamount, 0)) as total_revenue_vnd')
            ->selectRaw("SUM(CASE WHEN o.status = 'complete' THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->selectRaw("SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->groupBy('o.certtype', 'p.name', 'np.vendor', 'np.validation_type')
            ->orderBy('total_orders', 'desc');

        $this->applyDateFilters($query, $filters, 'o.provisiondate');

        if (!empty($filters['vendor'])) {
            $query->where('np.vendor', $filters['vendor']);
        }

        $products = $query->get();

        $results = [];
        foreach ($products as $product) {
            $totalRevenueVnd = (float) $product->total_revenue_vnd;
            $totalRevenueUsd = CurrencyHelper::revenueVndToUsd($totalRevenueVnd);
            $totalOrders = (int) $product->total_orders;
            $activeCount = (int) $product->active_count;

            // Calculate renewal rate
            $renewalRate = $this->calculateRenewalRate($product->product_code, $filters);
            
            // Completion rate
            $completionRate = $totalOrders > 0 ? ($activeCount / $totalOrders) * 100 : 0;

            $results[] = [
                'product_code' => $product->product_code,
                'product_name' => $product->product_name ?? $product->product_code,
                'vendor' => $product->vendor ?? 'Unknown',
                'validation_type' => $product->validation_type ?? 'dv',
                'total_orders' => $totalOrders,
                'active_count' => $activeCount,
                'cancelled_count' => (int) $product->cancelled_count,
                'pending_count' => (int) $product->pending_count,
                'total_revenue_vnd' => $totalRevenueVnd,
                'total_revenue_usd' => round($totalRevenueUsd, 2),
                'avg_order_value_usd' => $totalOrders > 0 ? round($totalRevenueUsd / $totalOrders, 2) : 0,
                'renewal_rate' => round($renewalRate, 2),
                'completion_rate' => round($completionRate, 2),
            ];
        }

        return [
            'products' => $results,
            'summary' => [
                'total_products' => count($results),
                'total_orders' => array_sum(array_column($results, 'total_orders')),
                'total_revenue_usd' => array_sum(array_column($results, 'total_revenue_usd')),
            ],
        ];
    }

    /**
     * Calculate renewal rate for a product
     * 
     * @param string $productCode Product code
     * @param array $filters Date filters
     * @return float Renewal rate percentage
     */
    public function calculateRenewalRate(string $productCode, array $filters = []): float
    {
        // Get orders that were eligible for renewal (completed > 30 days ago)
        $eligibleQuery = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->where('o.certtype', $productCode)
            ->where('o.status', 'complete')
            ->where('o.completiondate', '<=', date('Y-m-d', strtotime('-30 days')));

        $eligibleCount = $eligibleQuery->count();

        if ($eligibleCount === 0) {
            return 0;
        }

        // Get renewed orders (same user, same product, newer order)
        $renewedCount = Capsule::table('nicsrs_sslorders as o1')
            ->join('nicsrs_sslorders as o2', function ($join) {
                $join->on('o1.userid', '=', 'o2.userid')
                    ->on('o1.certtype', '=', 'o2.certtype')
                    ->whereRaw('o2.provisiondate > o1.completiondate');
            })
            ->where('o1.certtype', $productCode)
            ->where('o1.status', 'complete')
            ->distinct('o1.id')
            ->count('o1.id');

        return ($renewedCount / $eligibleCount) * 100;
    }

    // =========================================================================
    // BRAND/VENDOR REPORT METHODS
    // =========================================================================

    /**
     * Get Revenue by Brand/Vendor
     * 
     * @param array $filters Filters
     * @return array Brand revenue data
     */
    public function getRevenueByBrand(array $filters = []): array
    {
        $query = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code')
            ->selectRaw('COALESCE(np.vendor, "Unknown") as vendor')
            ->selectRaw('SUM(COALESCE(h.firstpaymentamount, 0)) as total_revenue_vnd')
            ->selectRaw('COUNT(*) as order_count')
            ->whereNotNull('h.regdate')
            ->where('h.regdate', '!=', '0000-00-00')
            ->groupBy('np.vendor')
            ->orderBy('total_revenue_vnd', 'desc');

        $this->applyDateFilters($query, $filters, 'h.regdate');

        $brands = $query->get();

        $totalRevenueVnd = $brands->sum('total_revenue_vnd');
        $totalRevenueUsd = CurrencyHelper::revenueVndToUsd($totalRevenueVnd);

        $results = [];
        foreach ($brands as $brand) {
            $revenueVnd = (float) $brand->total_revenue_vnd;
            $revenueUsd = CurrencyHelper::revenueVndToUsd($revenueVnd);
            
            $results[] = [
                'vendor' => $brand->vendor,
                'total_revenue_vnd' => $revenueVnd,
                'total_revenue_usd' => round($revenueUsd, 2),
                'order_count' => (int) $brand->order_count,
                'percentage' => $totalRevenueVnd > 0 ? round(($revenueVnd / $totalRevenueVnd) * 100, 2) : 0,
            ];
        }

        return [
            'brands' => $results,
            'summary' => [
                'total_revenue_vnd' => $totalRevenueVnd,
                'total_revenue_usd' => round($totalRevenueUsd, 2),
                'total_orders' => $brands->sum('order_count'),
                'brand_count' => count($results),
            ],
        ];
    }

    /**
     * Get brand revenue by period (for stacked chart)
     * Alias for getRevenueByBrandOverTime
     * 
     * @param string $groupBy Period grouping
     * @param array $filters Filters
     * @return array Chart data
     */
    public function getRevenueByBrandOverTime(string $groupBy = 'month', array $filters = []): array
    {
        $dateFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-W%V',
            'month' => '%Y-%m',
            'quarter' => 'quarter',
            'year' => '%Y',
            default => '%Y-%m'
        };

        $query = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code');

        if ($groupBy === 'quarter') {
            $query->selectRaw("CONCAT(YEAR(h.regdate), '-Q', QUARTER(h.regdate)) as period");
        } else {
            $query->selectRaw("DATE_FORMAT(h.regdate, '{$dateFormat}') as period");
        }

        $query->selectRaw('COALESCE(np.vendor, "Unknown") as vendor')
            ->selectRaw('SUM(COALESCE(h.firstpaymentamount, 0)) as total_revenue_vnd')
            ->selectRaw('COUNT(*) as order_count')
            ->whereNotNull('h.regdate')
            ->where('h.regdate', '!=', '0000-00-00')
            ->groupBy('period', 'vendor')
            ->orderBy('period', 'asc');

        $this->applyDateFilters($query, $filters, 'h.regdate');

        $results = $query->get();

        // Pivot data for chart
        $periods = [];
        $brandData = [];

        foreach ($results as $row) {
            if (!in_array($row->period, $periods)) {
                $periods[] = $row->period;
            }
            
            $vendor = $row->vendor ?? 'Unknown';
            if (!isset($brandData[$vendor])) {
                $brandData[$vendor] = [];
            }
            
            // Convert VND to USD
            $revenueUsd = CurrencyHelper::revenueVndToUsd((float) $row->total_revenue_vnd);
            $brandData[$vendor][$row->period] = round($revenueUsd, 2);
        }

        // Fill missing periods with 0
        $datasets = [];
        foreach ($brandData as $vendor => $data) {
            $values = [];
            foreach ($periods as $period) {
                $values[] = $data[$period] ?? 0;
            }
            $datasets[$vendor] = $values;
        }

        return [
            'labels' => $periods,
            'datasets' => $datasets,
            'raw' => $results->toArray(),
        ];
    }

    // =========================================================================
    // UTILITY METHODS (Required by Controller)
    // =========================================================================

    /**
     * Get list of available vendors
     * 
     * @return array
     */
    public function getAvailableVendors(): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->distinct()
            ->pluck('vendor')
            ->toArray();
    }

    /**
     * Get list of available products
     * 
     * @return array
     */
    public function getAvailableProducts(): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->select(['product_code', 'product_name', 'vendor'])
            ->orderBy('product_name')
            ->get()
            ->toArray();
    }

    /**
     * Get date range presets
     * 
     * @return array Preset date ranges
     */
    public function getDatePresets(): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
        $thisYear = date('Y-01-01');
        $lastYear = date('Y-01-01', strtotime('-1 year'));
        $lastYearEnd = date('Y-12-31', strtotime('-1 year'));

        return [
            'today' => [
                'label' => 'Today',
                'from' => $today,
                'to' => $today,
            ],
            'this_week' => [
                'label' => 'This Week',
                'from' => date('Y-m-d', strtotime('monday this week')),
                'to' => $today,
            ],
            'this_month' => [
                'label' => 'This Month',
                'from' => $thisMonth,
                'to' => $today,
            ],
            'last_month' => [
                'label' => 'Last Month',
                'from' => $lastMonth,
                'to' => $lastMonthEnd,
            ],
            'this_quarter' => [
                'label' => 'This Quarter',
                'from' => date('Y-m-01', strtotime('first day of ' . ceil(date('n') / 3) * 3 - 2 . ' month')),
                'to' => $today,
            ],
            'this_year' => [
                'label' => 'This Year',
                'from' => $thisYear,
                'to' => $today,
            ],
            'last_year' => [
                'label' => 'Last Year',
                'from' => $lastYear,
                'to' => $lastYearEnd,
            ],
            'last_7_days' => [
                'label' => 'Last 7 Days',
                'from' => date('Y-m-d', strtotime('-7 days')),
                'to' => $today,
            ],
            'last_30_days' => [
                'label' => 'Last 30 Days',
                'from' => date('Y-m-d', strtotime('-30 days')),
                'to' => $today,
            ],
            'last_90_days' => [
                'label' => 'Last 90 Days',
                'from' => date('Y-m-d', strtotime('-90 days')),
                'to' => $today,
            ],
            'last_365_days' => [
                'label' => 'Last 365 Days',
                'from' => date('Y-m-d', strtotime('-365 days')),
                'to' => $today,
            ],
        ];
    }

    // =========================================================================
    // HELPER METHODS (Private)
    // =========================================================================

    /**
     * Build base query for reports
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildBaseQuery()
    {
        return Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->select([
                'o.id as order_id',
                'o.serviceid',
                'o.certtype as product_code',
                'o.status',
                'o.provisiondate',
                'o.completiondate',
                'p.name as product_name',
                'np.vendor',
                'np.validation_type',
                'h.firstpaymentamount as sale_amount', // VND with VAT
                'h.amount as recurring_amount',        // VND with VAT
                'h.billingcycle',
                'h.regdate as service_date', // Use this as primary date
                'c.firstname',
                'c.lastname',
                'c.companyname',
            ]);
    }

    /**
     * Build query for profit report (includes price_data)
     * 
     * IMPORTANT: Join mod_nicsrs_products using o.certtype (order's product code)
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildProfitQuery()
    {
        return Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('mod_nicsrs_products as np', 'o.certtype', '=', 'np.product_code')
            ->select([
                'o.id as order_id',
                'o.serviceid',
                'o.certtype as product_code',
                'o.status',
                'o.provisiondate',
                'p.name as product_name',
                'np.vendor',
                'np.price_data', // JSON with NicSRS cost
                'h.firstpaymentamount as sale_amount', // VND with VAT
                'h.billingcycle',
                'h.regdate as service_date',
            ]);
    }

    /**
     * Apply date filters to query
     * 
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $filters
     * @param string $dateColumn Column to filter (default: h.regdate)
     * @return void
     */
    private function applyDateFilters($query, array $filters, string $dateColumn = 'h.regdate'): void
    {
        if (!empty($filters['date_from'])) {
            $query->where($dateColumn, '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where($dateColumn, '<=', $filters['date_to'] . ' 23:59:59');
        }
    }

    /**
     * Apply product/vendor filters to query
     * 
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyProductFilters($query, array $filters): void
    {
        if (!empty($filters['product_code'])) {
            $query->where('o.certtype', $filters['product_code']);
        }
        if (!empty($filters['vendor'])) {
            $query->where('np.vendor', $filters['vendor']);
        }
    }
}