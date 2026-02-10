<?php
/**
 * Report Controller
 * Handles all report-related requests
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\ReportService;
use NicsrsAdmin\Helper\ViewHelper;
use NicsrsAdmin\Helper\CurrencyHelper;

class ReportController extends BaseController
{
    /** @var ReportService */
    private $reportService;

    /** @var ViewHelper */
    private $helper;

    /**
     * Constructor
     * 
     * @param array $vars Module variables
     */
    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->reportService = new ReportService();
        $this->helper = new ViewHelper();
    }

    /**
     * Render report page based on action
     * 
     * @param string $action Current action
     * @return void
     */
    public function render(string $action): void
    {
        $report = isset($_GET['report']) ? $this->sanitize($_GET['report']) : 'index';
        
        // Handle export requests
        if (isset($_GET['export'])) {
            $this->handleExport($report, $_GET['export']);
            return;
        }

        switch ($report) {
            case 'sales':
                $this->renderSalesReport();
                break;
            case 'profit':
                $this->renderProfitReport();
                break;
            case 'performance':
                $this->renderPerformanceReport();
                break;
            case 'brand':
                $this->renderBrandReport();
                break;
            default:
                $this->renderIndex();
        }
    }

    /**
     * Reports dashboard/menu
     * 
     * @return void
     */
    private function renderIndex(): void
    {
        $quickStats = $this->getQuickStats();

        $data = [
            'quickStats' => $quickStats,
        ];

        $this->includeTemplate('reports/index', $data);
    }

    /**
     * SSL Sales Report
     * 
     * @return void
     */
    private function renderSalesReport(): void
    {
        $filters = $this->getFiltersFromRequest();

        $reportData = $this->reportService->getSalesReport($filters);
        $chartData = $this->reportService->getSalesByPeriod(
            $filters['period'] ?? 'month', 
            $filters
        );
        $productData = $this->reportService->getSalesByProduct($filters);

        $vendors = $this->reportService->getAvailableVendors();
        $products = $this->reportService->getAvailableProducts();
        $datePresets = $this->reportService->getDatePresets();

        $data = [
            'reportData' => $reportData,
            'chartData' => $chartData,
            'productData' => $productData,
            'vendors' => $vendors,
            'products' => $products,
            'datePresets' => $datePresets,
            'filters' => $filters,
        ];

        $this->includeTemplate('reports/sales', $data);
    }

    /**
     * SSL Profit Report
     * 
     * @return void
     */
    private function renderProfitReport(): void
    {
        $filters = $this->getFiltersFromRequest();

        $reportData = $this->reportService->getProfitReport($filters);
        $chartData = $this->reportService->getProfitByPeriod(
            $filters['period'] ?? 'month',
            $filters
        );

        $rateInfo = CurrencyHelper::getRateInfo();
        $displayMode = CurrencyHelper::getDisplayMode();

        $vendors = $this->reportService->getAvailableVendors();
        $products = $this->reportService->getAvailableProducts();
        $datePresets = $this->reportService->getDatePresets();

        $data = [
            'reportData' => $reportData,
            'chartData' => $chartData,
            'rateInfo' => $rateInfo,
            'displayMode' => $displayMode,
            'vendors' => $vendors,
            'products' => $products,
            'datePresets' => $datePresets,
            'filters' => $filters,
        ];

        $this->includeTemplate('reports/profit', $data);
    }

    /**
     * Product Performance Report
     * 
     * @return void
     */
    private function renderPerformanceReport(): void
    {
        $filters = $this->getFiltersFromRequest();

        $reportData = $this->reportService->getProductPerformance($filters);

        $vendors = $this->reportService->getAvailableVendors();
        $datePresets = $this->reportService->getDatePresets();

        $data = [
            'reportData' => $reportData,
            'vendors' => $vendors,
            'datePresets' => $datePresets,
            'filters' => $filters,
        ];

        $this->includeTemplate('reports/performance', $data);
    }

    /**
     * Revenue by Brand Report
     * 
     * @return void
     */
    private function renderBrandReport(): void
    {
        $filters = $this->getFiltersFromRequest();

        $reportData = $this->reportService->getRevenueByBrand($filters);
        $trendData = $this->reportService->getRevenueByBrandOverTime(
            $filters['period'] ?? 'month',
            $filters
        );

        $datePresets = $this->reportService->getDatePresets();

        $data = [
            'reportData' => $reportData,
            'trendData' => $trendData,
            'datePresets' => $datePresets,
            'filters' => $filters,
        ];

        $this->includeTemplate('reports/brand', $data);
    }

    /**
     * Handle AJAX requests
     * 
     * @param array $post POST data
     * @return string JSON response
     */
    public function handleAjax(array $post): string
    {
        $action = isset($post['ajax_action']) ? $this->sanitize($post['ajax_action']) : '';
        $filters = $this->getFiltersFromRequest();

        try {
            switch ($action) {
                case 'get_sales_data':
                    $data = $this->reportService->getSalesReport($filters);
                    return $this->jsonSuccess('Data loaded', $data);

                case 'get_sales_chart':
                    $data = $this->reportService->getSalesByPeriod($filters['period'] ?? 'month', $filters);
                    return $this->jsonSuccess('Chart data loaded', $data);

                case 'get_profit_data':
                    $data = $this->reportService->getProfitReport($filters);
                    return $this->jsonSuccess('Data loaded', $data);

                case 'get_profit_chart':
                    $data = $this->reportService->getProfitByPeriod($filters['period'] ?? 'month', $filters);
                    return $this->jsonSuccess('Chart data loaded', $data);

                case 'get_performance_data':
                    $data = $this->reportService->getProductPerformance($filters);
                    return $this->jsonSuccess('Data loaded', $data);

                case 'get_brand_data':
                    $data = $this->reportService->getRevenueByBrand($filters);
                    return $this->jsonSuccess('Data loaded', $data);

                case 'get_brand_trend':
                    $data = $this->reportService->getRevenueByBrandOverTime($filters['period'] ?? 'month', $filters);
                    return $this->jsonSuccess('Trend data loaded', $data);

                case 'update_exchange_rate':
                    $result = CurrencyHelper::updateRateFromApi();
                    if ($result['success']) {
                        return $this->jsonSuccess($result['message'], $result);
                    }
                    return $this->jsonError($result['message']);

                case 'save_currency_settings':
                    return $this->saveCurrencySettings($post);

                default:
                    return $this->jsonError('Unknown action: ' . $action);
            }
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Handle export requests
     * 
     * @param string $report Report type
     * @param string $format Export format (csv)
     * @return void
     */
    private function handleExport(string $report, string $format): void
    {
        $filters = $this->getFiltersFromRequest();

        if ($format !== 'csv') {
            echo 'Unsupported export format';
            exit;
        }

        $filename = "nicsrs_ssl_{$report}_" . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        switch ($report) {
            case 'sales':
                $this->exportSalesCsv($output, $filters);
                break;
            case 'profit':
                $this->exportProfitCsv($output, $filters);
                break;
            case 'performance':
                $this->exportPerformanceCsv($output, $filters);
                break;
            case 'brand':
                $this->exportBrandCsv($output, $filters);
                break;
        }

        fclose($output);
        exit;
    }

    /**
     * Export Sales Report to CSV
     */
    private function exportSalesCsv($output, array $filters): void
    {
        $data = $this->reportService->getSalesReport($filters);

        fputcsv($output, [
            'Order ID', 'Date', 'Product Code', 'Product Name', 'Vendor',
            'Client', 'Sale Amount (USD)', 'Recurring (USD)', 'Billing Cycle', 'Status'
        ]);

        foreach ($data['orders'] as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['provision_date'],
                $order['product_code'],
                $order['product_name'],
                $order['vendor'],
                $order['client_name'],
                $order['sale_amount'],
                $order['recurring_amount'],
                $order['billing_cycle'],
                $order['status'],
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Orders', $data['summary']['order_count']]);
        fputcsv($output, ['Total Sales (USD)', $data['summary']['total_sales']]);
        fputcsv($output, ['Average Order (USD)', $data['summary']['avg_order_value']]);
    }

    /**
     * Export Profit Report to CSV
     */
    private function exportProfitCsv($output, array $filters): void
    {
        $data = $this->reportService->getProfitReport($filters);
        $rate = CurrencyHelper::getUsdVndRate();

        fputcsv($output, [
            'Order ID', 'Date', 'Product Code', 'Product Name', 'Vendor',
            'Sale (USD)', 'Cost (USD)', 'Profit (USD)', 'Margin (%)', 'Profit (VND)'
        ]);

        foreach ($data['orders'] as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['provision_date'],
                $order['product_code'],
                $order['product_name'],
                $order['vendor'],
                $order['sale_amount_usd'],
                $order['cost_usd'],
                $order['profit_usd'],
                $order['profit_margin'],
                $order['profit_usd'] * $rate,
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Exchange Rate', "1 USD = {$rate} VND"]);
        fputcsv($output, ['Total Revenue (USD)', $data['summary']['total_revenue_usd']]);
        fputcsv($output, ['Total Cost (USD)', $data['summary']['total_cost_usd']]);
        fputcsv($output, ['Total Profit (USD)', $data['summary']['total_profit_usd']]);
        fputcsv($output, ['Total Profit (VND)', $data['summary']['total_profit_usd'] * $rate]);
        fputcsv($output, ['Overall Margin (%)', $data['summary']['profit_margin']]);
    }

    /**
     * Export Performance Report to CSV
     */
    private function exportPerformanceCsv($output, array $filters): void
    {
        $data = $this->reportService->getProductPerformance($filters);

        fputcsv($output, [
            'Product Code', 'Product Name', 'Vendor', 'Type', 'Total Orders',
            'Active', 'Cancelled', 'Pending', 'Revenue (USD)', 'Avg Order (USD)',
            'Renewal Rate (%)', 'Completion Rate (%)'
        ]);

        foreach ($data['products'] as $product) {
            fputcsv($output, [
                $product['product_code'],
                $product['product_name'],
                $product['vendor'],
                $product['validation_type'],
                $product['total_orders'],
                $product['active_count'],
                $product['cancelled_count'],
                $product['pending_count'],
                $product['total_revenue'],
                $product['avg_order_value'],
                $product['renewal_rate'],
                $product['completion_rate'],
            ]);
        }
    }

    /**
     * Export Brand Report to CSV
     */
    private function exportBrandCsv($output, array $filters): void
    {
        $data = $this->reportService->getRevenueByBrand($filters);

        fputcsv($output, [
            'Brand/Vendor', 'Total Orders', 'Active Certs', 'Revenue (USD)',
            'Avg Order (USD)', 'Revenue Share (%)', 'Order Share (%)'
        ]);

        foreach ($data['brands'] as $brand) {
            fputcsv($output, [
                $brand['vendor'],
                $brand['order_count'],
                $brand['active_count'],
                $brand['total_revenue'],
                $brand['avg_order_value'],
                $brand['revenue_percentage'],
                $brand['order_percentage'],
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Brands', $data['summary']['total_brands']]);
        fputcsv($output, ['Total Orders', $data['summary']['total_orders']]);
        fputcsv($output, ['Total Revenue (USD)', $data['summary']['total_revenue']]);
    }

    /**
     * Save currency settings
     */
    private function saveCurrencySettings(array $post): string
    {
        $rate = isset($post['usd_vnd_rate']) ? (float) $post['usd_vnd_rate'] : null;
        $displayMode = isset($post['currency_display']) ? $this->sanitize($post['currency_display']) : null;

        $success = true;
        $messages = [];

        if ($rate !== null && $rate > 0) {
            if (CurrencyHelper::setUsdVndRate($rate)) {
                $messages[] = 'Exchange rate updated';
            } else {
                $success = false;
                $messages[] = 'Failed to update exchange rate';
            }
        }

        if ($displayMode !== null) {
            if (CurrencyHelper::setDisplayMode($displayMode)) {
                $messages[] = 'Display mode updated';
            } else {
                $success = false;
                $messages[] = 'Failed to update display mode';
            }
        }

        if ($success) {
            return $this->jsonSuccess(implode('. ', $messages), [
                'rate_info' => CurrencyHelper::getRateInfo(),
            ]);
        }

        return $this->jsonError(implode('. ', $messages));
    }

    /**
     * Get filters from request
     */
    private function getFiltersFromRequest(): array
    {
        $filters = [];

        // Date from
        $dateFrom = $_GET['date_from'] ?? $_POST['date_from'] ?? '';
        if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }

        // Date to
        $dateTo = $_GET['date_to'] ?? $_POST['date_to'] ?? '';
        if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        // Product code
        $productCode = $_GET['product_code'] ?? $_POST['product_code'] ?? '';
        if (!empty($productCode)) {
            $filters['product_code'] = $this->sanitize($productCode);
        }

        // Vendor
        $vendor = $_GET['vendor'] ?? $_POST['vendor'] ?? '';
        if (!empty($vendor)) {
            $filters['vendor'] = $this->sanitize($vendor);
        }

        // Status
        $status = $_GET['status'] ?? $_POST['status'] ?? '';
        if (!empty($status)) {
            $filters['status'] = $this->sanitize($status);
        }

        // Period
        $period = $_GET['period'] ?? $_POST['period'] ?? '';
        if (!empty($period) && in_array($period, ['day', 'week', 'month', 'quarter', 'year'])) {
            $filters['period'] = $period;
        }

        // Handle preset date ranges
        $preset = $_GET['preset'] ?? $_POST['preset'] ?? '';
        if (!empty($preset)) {
            $presets = $this->reportService->getDatePresets();
            if (isset($presets[$preset])) {
                $filters['date_from'] = $presets[$preset]['from'];
                $filters['date_to'] = $presets[$preset]['to'];
            }
        }

        return $filters;
    }

    /**
     * Get quick stats for reports dashboard
     */
    private function getQuickStats(): array
    {
        $thisMonth = date('Y-m-01');
        $today = date('Y-m-d');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

        // This month sales (VND) - use tblhosting.regdate as primary date
        $thisMonthSalesVnd = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->where('h.regdate', '>=', $thisMonth)
            ->sum('h.firstpaymentamount') ?: 0;

        // Last month sales (VND)
        $lastMonthSalesVnd = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->where('h.regdate', '>=', $lastMonth)
            ->where('h.regdate', '<=', $lastMonthEnd)
            ->sum('h.firstpaymentamount') ?: 0;

        // This month orders - use tblhosting.regdate
        $thisMonthOrders = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->where('h.regdate', '>=', $thisMonth)
            ->count();

        // Last month orders
        $lastMonthOrders = Capsule::table('nicsrs_sslorders as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->where('h.regdate', '>=', $lastMonth)
            ->where('h.regdate', '<=', $lastMonthEnd)
            ->count();

        // Active certificates (completed orders)
        $activeCerts = Capsule::table('nicsrs_sslorders')
            ->whereIn('status', ['complete', 'Complete'])
            ->count();

        // Convert to USD (after removing VAT)
        $thisMonthSalesUsd = CurrencyHelper::revenueVndToUsd((float) $thisMonthSalesVnd);
        $lastMonthSalesUsd = CurrencyHelper::revenueVndToUsd((float) $lastMonthSalesVnd);

        // Calculate growth based on VND
        $salesGrowth = $lastMonthSalesVnd > 0 
            ? (($thisMonthSalesVnd - $lastMonthSalesVnd) / $lastMonthSalesVnd) * 100 
            : 0;

        return [
            // VND amounts (original from WHMCS, with VAT)
            'this_month_sales_vnd' => (float) $thisMonthSalesVnd,
            'last_month_sales_vnd' => (float) $lastMonthSalesVnd,
            // USD amounts (converted, without VAT)
            'this_month_sales_usd' => round($thisMonthSalesUsd, 2),
            'last_month_sales_usd' => round($lastMonthSalesUsd, 2),
            // For backward compatibility
            'this_month_sales' => round($thisMonthSalesUsd, 2),
            'last_month_sales' => round($lastMonthSalesUsd, 2),
            // Growth and counts
            'sales_growth' => round($salesGrowth, 1),
            'this_month_orders' => (int) $thisMonthOrders,
            'last_month_orders' => (int) $lastMonthOrders,
            'active_certificates' => (int) $activeCerts,
        ];
    }
}