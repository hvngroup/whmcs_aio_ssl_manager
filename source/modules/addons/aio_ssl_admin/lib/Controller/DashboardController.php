<?php

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;

class DashboardController extends BaseController
{
    public function render(string $action = ''): void
    {
        $stats = $this->getStats();
        $providers = ProviderRegistry::getAllRecords(true);
        $expiringCerts = $this->getExpiringCertificates();
        $recentOrders = $this->getRecentOrders();

        $this->renderTemplate('dashboard.tpl', [
            'stats'          => $stats,
            'providers'      => $providers,
            'expiringCerts'  => $expiringCerts,
            'recentOrders'   => $recentOrders,
            'chartData'      => $this->getChartData(),
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'stats':
                return ['success' => true, 'data' => $this->getStats()];
            case 'test_all':
                return $this->testAllProviders();
            default:
                return parent::handleAjax($action);
        }
    }

    private function getStats(): array
    {
        $modules = ['aio_ssl', 'nicsrs_ssl', 'SSLCENTERWHMCS', 'thesslstore_ssl', 'ssl2buy'];

        $total = Capsule::table('tblsslorders')
            ->whereIn('module', $modules)
            ->count();

        $pending = Capsule::table('tblsslorders')
            ->whereIn('module', $modules)
            ->whereIn('status', ['Pending', 'Processing', 'Awaiting Configuration'])
            ->count();

        $issued = Capsule::table('tblsslorders')
            ->whereIn('module', $modules)
            ->whereIn('status', ['Completed', 'Active', 'Issued'])
            ->count();

        // Expiring within 30 days (check configdata for end_date)
        $expiring = 0;
        try {
            $expiring = Capsule::table('tblsslorders')
                ->whereIn('module', $modules)
                ->whereIn('status', ['Completed', 'Active', 'Issued'])
                ->whereRaw("JSON_EXTRACT(configdata, '$.end_date') IS NOT NULL")
                ->whereRaw("JSON_EXTRACT(configdata, '$.end_date') <= ?", [date('Y-m-d', strtotime('+30 days'))])
                ->whereRaw("JSON_EXTRACT(configdata, '$.end_date') >= ?", [date('Y-m-d')])
                ->count();
        } catch (\Exception $e) {
            // JSON_EXTRACT may not be available on older MySQL
        }

        // Per-provider counts
        $byProvider = [];
        foreach ($modules as $mod) {
            $count = Capsule::table('tblsslorders')->where('module', $mod)->count();
            if ($count > 0) {
                $byProvider[$mod] = $count;
            }
        }

        return [
            'total'      => $total,
            'pending'    => $pending,
            'issued'     => $issued,
            'expiring'   => $expiring,
            'byProvider' => $byProvider,
        ];
    }

    private function getExpiringCertificates(int $limit = 10): array
    {
        try {
            return Capsule::table('tblsslorders')
                ->join('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
                ->join('tblclients', 'tblsslorders.userid', '=', 'tblclients.id')
                ->whereIn('tblsslorders.status', ['Completed', 'Active', 'Issued'])
                ->whereRaw("JSON_EXTRACT(tblsslorders.configdata, '$.end_date') IS NOT NULL")
                ->whereRaw("JSON_EXTRACT(tblsslorders.configdata, '$.end_date') <= ?", [date('Y-m-d', strtotime('+30 days'))])
                ->whereRaw("JSON_EXTRACT(tblsslorders.configdata, '$.end_date') >= ?", [date('Y-m-d')])
                ->select([
                    'tblsslorders.*',
                    'tblhosting.domain',
                    Capsule::raw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) as client_name"),
                ])
                ->orderByRaw("JSON_EXTRACT(tblsslorders.configdata, '$.end_date') ASC")
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getRecentOrders(int $limit = 10): array
    {
        $modules = ['aio_ssl', 'nicsrs_ssl', 'SSLCENTERWHMCS', 'thesslstore_ssl', 'ssl2buy'];

        try {
            return Capsule::table('tblsslorders')
                ->leftJoin('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
                ->leftJoin('tblclients', 'tblsslorders.userid', '=', 'tblclients.id')
                ->whereIn('tblsslorders.module', $modules)
                ->select([
                    'tblsslorders.*',
                    'tblhosting.domain',
                    Capsule::raw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) as client_name"),
                ])
                ->orderBy('tblsslorders.id', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getChartData(): array
    {
        // Status distribution for doughnut chart
        $modules = ['aio_ssl', 'nicsrs_ssl', 'SSLCENTERWHMCS', 'thesslstore_ssl', 'ssl2buy'];
        $statusCounts = [];
        try {
            $rows = Capsule::table('tblsslorders')
                ->whereIn('module', $modules)
                ->select('status', Capsule::raw('COUNT(*) as cnt'))
                ->groupBy('status')
                ->get();
            foreach ($rows as $row) {
                $statusCounts[$row->status] = $row->cnt;
            }
        } catch (\Exception $e) {
            // empty
        }

        // Orders by provider for bar chart
        $providerCounts = [];
        try {
            $rows = Capsule::table('tblsslorders')
                ->whereIn('module', $modules)
                ->select('module', Capsule::raw('COUNT(*) as cnt'))
                ->groupBy('module')
                ->get();
            foreach ($rows as $row) {
                $providerCounts[$row->module] = $row->cnt;
            }
        } catch (\Exception $e) {
            // empty
        }

        return [
            'statusDistribution' => $statusCounts,
            'ordersByProvider'   => $providerCounts,
        ];
    }

    private function testAllProviders(): array
    {
        $results = [];
        $providers = ProviderRegistry::getAllEnabled();

        foreach ($providers as $slug => $provider) {
            try {
                $results[$slug] = $provider->testConnection();
            } catch (\Exception $e) {
                $results[$slug] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return ['success' => true, 'results' => $results];
    }
}