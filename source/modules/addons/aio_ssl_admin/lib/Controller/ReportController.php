<?php
/**
 * Report Controller — 6 report types with currency-aware display
 *
 * Types: index, revenue, profit, products, brands, expiry
 * All reports read from 3 tables (C4/C5) via ReportService
 * Currency display respects Settings → Currency → Display Mode
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use AioSSL\Service\ReportService;
use AioSSL\Helper\CurrencyHelper;

class ReportController extends BaseController
{
    /** @var ReportService */
    private $reportService;

    public function __construct(array $vars, array $lang)
    {
        parent::__construct($vars, $lang);
        $this->reportService = new ReportService($this->currencyHelper);
    }

    // ─── Main Render ───────────────────────────────────────────────

    public function render(string $action = ''): void
    {
        $type    = $this->input('type', 'index');
        $period  = $this->input('period', '30');
        $filters = $this->getFiltersFromRequest($period);

        // Base vars available to ALL report templates
        $baseVars = [
            'reportType'   => $type,
            'period'       => $period,
            'filters'      => $filters,
            'currencyInfo' => $this->currencyHelper->getInfo(),
            'providerNames'=> [
                'nicsrs' => 'NicSRS', 'gogetssl' => 'GoGetSSL',
                'thesslstore' => 'TheSSLStore', 'ssl2buy' => 'SSL2Buy', 'aio' => 'AIO',
            ],
            'providerBadge'=> [
                'nicsrs' => 'aio-provider-nicsrs', 'gogetssl' => 'aio-provider-gogetssl',
                'thesslstore' => 'aio-provider-thesslstore', 'ssl2buy' => 'aio-provider-ssl2buy',
                'aio' => 'aio-provider-aio',
            ],
        ];

        switch ($type) {
            case 'revenue':
                $data = $this->prepareRevenue($filters);
                break;
            case 'profit':
                $data = $this->prepareProfit($filters);
                break;
            case 'products':
                $data = $this->prepareProducts($filters);
                break;
            case 'brands':
                $data = $this->prepareBrands($filters);
                break;
            case 'expiry':
                $data = $this->prepareExpiry($filters);
                break;
            default:
                $data = $this->prepareIndex();
                break;
        }

        $this->renderTemplate('reports.php', array_merge($baseVars, $data));
    }

    // ─── AJAX Handler ──────────────────────────────────────────────

    public function handleAjax(string $action = ''): array
    {
        if ($action === 'export') {
            return $this->handleExport();
        }
        return ['success' => false, 'message' => 'Unknown action'];
    }

    // ═══════════════════════════════════════════════════════════════
    // DATA PREPARATION (per report type)
    // ═══════════════════════════════════════════════════════════════

    private function prepareIndex(): array
    {
        return [
            'quickStats' => $this->reportService->getQuickStats(),
        ];
    }

    private function prepareRevenue(array $filters): array
    {
        return [
            'reportData' => $this->reportService->getRevenueByProvider($filters),
            'chartData'  => json_encode($this->reportService->getRevenueChartData($filters)),
        ];
    }

    private function prepareProfit(array $filters): array
    {
        $groupBy = $filters['group_by'] ?? 'month';
        return [
            'reportData' => $this->reportService->getProfitReport($filters),
            'chartData'  => json_encode($this->reportService->getProfitByPeriod($groupBy, $filters)),
        ];
    }

    private function prepareProducts(array $filters): array
    {
        return [
            'reportData' => $this->reportService->getProductPerformance($filters),
        ];
    }

    private function prepareBrands(array $filters): array
    {
        return [
            'reportData' => $this->reportService->getRevenueByBrand($filters),
            'chartData'  => json_encode($this->reportService->getBrandChartData($filters)),
        ];
    }

    private function prepareExpiry(array $filters): array
    {
        return [
            'reportData' => $this->reportService->getExpiryForecast($filters),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // CSV EXPORT
    // ═══════════════════════════════════════════════════════════════

    private function handleExport(): array
    {
        $type    = $this->input('type', 'revenue');
        $period  = $this->input('period', '30');
        $filters = $this->getFiltersFromRequest($period);

        $result = $this->reportService->exportCsv($type, $filters);

        return [
            'success'  => true,
            'csv'      => $result['csv'],
            'filename' => $result['filename'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // FILTER EXTRACTION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Extract and validate filters from GET/POST
     */
    private function getFiltersFromRequest(string $period = '30'): array
    {
        $filters = [];

        // Period → date_from
        if ($period !== 'all') {
            $days = (int)$period ?: 30;
            $filters['date_from'] = date('Y-m-d', strtotime("-{$days} days"));
        }

        // Explicit date range (overrides period)
        $dateFrom = $this->input('date_from');
        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }
        $dateTo = $this->input('date_to');
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        // Provider filter
        $provider = $this->input('provider');
        if ($provider && preg_match('/^[a-z0-9_]+$/', $provider)) {
            $filters['provider'] = $provider;
        }

        // Status filter
        $status = $this->input('status');
        if ($status) {
            $filters['status'] = $status;
        }

        // Group by (for charts)
        $groupBy = $this->input('group_by');
        if ($groupBy && in_array($groupBy, ['day', 'week', 'month', 'quarter', 'year'])) {
            $filters['group_by'] = $groupBy;
        }

        return $filters;
    }
}