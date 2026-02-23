<?php
/**
 * Report Controller — Revenue, product performance, expiry forecast
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;

class ReportController extends BaseController
{
    private $providerNames = [
        'nicsrs' => 'NicSRS', 'gogetssl' => 'GoGetSSL',
        'thesslstore' => 'TheSSLStore', 'ssl2buy' => 'SSL2Buy',
    ];

    public function render(string $action = ''): void
    {
        $type = $this->input('type', 'revenue');
        $period = $this->input('period', '30');

        $data = $this->getReportData($type, $period);

        $this->renderTemplate('reports.php', [
            'reportType' => $type,
            'reportData' => $data,
            'period'     => $period,
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        if ($action === 'export') {
            return $this->exportCsv();
        }
        return ['success' => false, 'message' => 'Unknown action'];
    }

    // ─── Data Fetchers ─────────────────────────────────────────────

    private function getReportData(string $type, string $period): array
    {
        $dateFrom = $this->getDateFrom($period);

        switch ($type) {
            case 'revenue':
                return $this->getRevenueData($dateFrom);
            case 'products':
                return $this->getProductPerformance($dateFrom);
            case 'expiry':
                return $this->getExpiryForecast();
            default:
                return [];
        }
    }

    private function getRevenueData(?string $dateFrom): array
    {
        $results = [];
        $modules = [
            'aio_ssl' => 'aio', 'nicsrs_ssl' => 'nicsrs',
            'SSLCENTERWHMCS' => 'gogetssl', 'thesslstore_ssl' => 'thesslstore',
            'ssl2buy' => 'ssl2buy',
        ];

        try {
            $q = Capsule::table('tblsslorders')
                ->join('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
                ->whereIn('tblsslorders.module', array_keys($modules));

            if ($dateFrom) {
                $q->where('tblsslorders.created_at', '>=', $dateFrom);
            }

            $rows = $q->select([
                    'tblsslorders.module',
                    Capsule::raw('COUNT(*) as orders'),
                    Capsule::raw('COALESCE(SUM(tblhosting.amount), 0) as revenue'),
                ])
                ->groupBy('tblsslorders.module')
                ->get();

            foreach ($rows as $row) {
                $slug = $modules[$row->module] ?? $row->module;
                $results[] = [
                    'slug'    => $slug,
                    'name'    => $this->providerNames[$slug] ?? ucfirst($slug),
                    'orders'  => (int)$row->orders,
                    'revenue' => (float)$row->revenue,
                    'cost'    => 0, // Cost tracking requires provider price data integration
                ];
            }
        } catch (\Exception $e) {
            // Return empty
        }

        return $results;
    }

    private function getProductPerformance(?string $dateFrom): array
    {
        $results = [];
        try {
            $q = Capsule::table('tblsslorders')
                ->join('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id');

            if ($dateFrom) {
                $q->where('tblsslorders.created_at', '>=', $dateFrom);
            }

            $rows = $q->select([
                    'tblproducts.name as product_name',
                    'tblsslorders.module as provider',
                    Capsule::raw('COUNT(*) as orders'),
                    Capsule::raw('COALESCE(SUM(tblhosting.amount), 0) as revenue'),
                ])
                ->groupBy('tblproducts.name', 'tblsslorders.module')
                ->orderBy('orders', 'desc')
                ->limit(20)
                ->get();

            $moduleToSlug = [
                'aio_ssl'=>'aio','nicsrs_ssl'=>'nicsrs','SSLCENTERWHMCS'=>'gogetssl',
                'thesslstore_ssl'=>'thesslstore','ssl2buy'=>'ssl2buy',
            ];

            foreach ($rows as $row) {
                $results[] = [
                    'product_name' => $row->product_name,
                    'provider'     => $moduleToSlug[$row->provider] ?? $row->provider,
                    'orders'       => (int)$row->orders,
                    'revenue'      => (float)$row->revenue,
                ];
            }
        } catch (\Exception $e) {}

        return $results;
    }

    private function getExpiryForecast(): array
    {
        $results = ['7' => 0, '30' => 0, '60' => 0, '90' => 0, 'details' => []];
        $now = date('Y-m-d');

        try {
            // Check mod_aio_ssl_orders for expiry dates in configdata
            $orders = Capsule::table('mod_aio_ssl_orders')
                ->whereIn('status', ['Completed', 'Issued', 'Active'])
                ->whereNotNull('configdata')
                ->get();

            foreach ($orders as $order) {
                $cfg = json_decode($order->configdata ?? '{}', true) ?: [];
                $expiry = $cfg['end_date'] ?? $cfg['endDate'] ?? null;
                if (!$expiry) continue;

                $expiryTs = strtotime($expiry);
                if (!$expiryTs || $expiryTs < time()) continue;

                $daysLeft = (int)(($expiryTs - time()) / 86400);
                if ($daysLeft > 90) continue;

                if ($daysLeft <= 7) $results['7']++;
                elseif ($daysLeft <= 30) $results['30']++;
                elseif ($daysLeft <= 60) $results['60']++;
                else $results['90']++;

                // Gather details for first 50
                if (count($results['details']) < 50) {
                    $results['details'][] = [
                        'order_id'  => $order->id,
                        'domain'    => $cfg['domain'] ?? $order->domain ?? '—',
                        'provider'  => $order->provider_slug ?? '',
                        'client'    => '',
                        'expiry'    => $expiry,
                        'days_left' => $daysLeft,
                    ];
                }
            }

            // Sort details by days_left ascending
            usort($results['details'], function ($a, $b) {
                return $a['days_left'] - $b['days_left'];
            });

        } catch (\Exception $e) {}

        return $results;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function getDateFrom(string $period): ?string
    {
        if ($period === 'all') return null;
        $days = (int)$period ?: 30;
        return date('Y-m-d', strtotime("-{$days} days"));
    }

    private function exportCsv(): array
    {
        $type = $this->input('type', 'revenue');
        $period = $this->input('period', '30');
        $data = $this->getReportData($type, $period);

        $csv = '';
        if ($type === 'revenue') {
            $csv = "Provider,Orders,Revenue,Cost,Profit,Margin%\n";
            foreach ($data as $d) {
                $profit = ($d['revenue'] ?? 0) - ($d['cost'] ?? 0);
                $margin = ($d['revenue'] ?? 0) > 0 ? round($profit / $d['revenue'] * 100, 1) : 0;
                $csv .= implode(',', [$d['name'], $d['orders'], $d['revenue'], $d['cost'], $profit, $margin]) . "\n";
            }
        } elseif ($type === 'products') {
            $csv = "Product,Provider,Orders,Revenue\n";
            foreach ($data as $d) {
                $csv .= '"' . ($d['product_name'] ?? '') . '",' . ($d['provider'] ?? '') . ',' . ($d['orders'] ?? 0) . ',' . ($d['revenue'] ?? 0) . "\n";
            }
        } elseif ($type === 'expiry') {
            $csv = "Domain,Provider,Expiry,Days Left\n";
            foreach ($data['details'] ?? [] as $d) {
                $csv .= implode(',', [$d['domain'], $d['provider'], $d['expiry'], $d['days_left']]) . "\n";
            }
        }

        return [
            'success'  => true,
            'csv'      => $csv,
            'filename' => "aio_ssl_{$type}_{$period}d_" . date('Ymd') . '.csv',
        ];
    }
}