<?php
/**
 * Dashboard Controller — Unified statistics across 3 tables
 *
 * Reads from:
 *   1. mod_aio_ssl_orders (AIO native + claimed)
 *   2. tblsslorders (GoGetSSL, TheSSLStore, SSL2Buy legacy)
 *   3. nicsrs_sslorders (NicSRS legacy)
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;

class DashboardController extends BaseController
{
    /** Legacy modules in tblsslorders */
    private const TBLSSL_MODULES = ['SSLCENTERWHMCS', 'thesslstore_ssl', 'thesslstorefullv2', 'ssl2buy'];

    /** module → provider slug */
    private const MODULE_SLUG = [
        'nicsrs_ssl'        => 'nicsrs',
        'SSLCENTERWHMCS'    => 'gogetssl',
        'thesslstore_ssl'   => 'thesslstore',
        'thesslstorefullv2' => 'thesslstore',
        'ssl2buy'           => 'ssl2buy',
    ];

    /** Statuses considered "active/issued" */
    private const ACTIVE_STATUSES = [
        'active', 'complete', 'completed', 'issued',
        'Active', 'Complete', 'Completed', 'Issued',
        'Configuration Submitted',
    ];

    /** Statuses considered "pending" */
    private const PENDING_STATUSES = [
        'pending', 'processing', 'awaiting', 'draft',
        'Pending', 'Processing', 'Awaiting Configuration', 'Draft',
    ];

    public function render(string $action = ''): void
    {
        $stats = $this->getStats();
        $providers = [];
        try { $providers = ProviderRegistry::getAllRecords(true); } catch (\Exception $e) {}

        $this->renderTemplate('dashboard.php', [
            'stats'          => $stats,
            'providers'      => $providers,
            'expiringCerts'  => $this->getExpiringCertificates(30, 10),
            'recentOrders'   => $this->getRecentOrders(10),
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

    // ─── Unified Stats ─────────────────────────────────────────────

    private function getStats(): array
    {
        $total = 0;
        $active = 0;
        $pending = 0;
        $expiring = 0;
        $byProvider = [];
        $byStatus = [];

        $claimedKeys = $this->getClaimedLegacyKeys();

        // ── 1. mod_aio_ssl_orders ──
        try {
            $aioRows = Capsule::table('mod_aio_ssl_orders')
                ->select(['status', 'provider_slug', 'end_date'])
                ->get();

            foreach ($aioRows as $r) {
                $total++;
                $slug = $r->provider_slug ?: 'aio';
                $byProvider[$slug] = ($byProvider[$slug] ?? 0) + 1;

                $normStatus = $this->normalizeStatus($r->status);
                $byStatus[$normStatus] = ($byStatus[$normStatus] ?? 0) + 1;

                if (in_array($r->status, self::ACTIVE_STATUSES)) $active++;
                if (in_array($r->status, self::PENDING_STATUSES)) $pending++;

                if ($this->isExpiringSoon($r->end_date)) $expiring++;
            }
        } catch (\Exception $e) {}

        // ── 2. nicsrs_sslorders (not claimed) ──
        try {
            if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
                $nicsrsRows = Capsule::table('nicsrs_sslorders')
                    ->select(['id', 'status', 'configdata'])
                    ->get();

                foreach ($nicsrsRows as $r) {
                    if (isset($claimedKeys['nicsrs_sslorders:' . $r->id])) continue;
                    $total++;
                    $byProvider['nicsrs'] = ($byProvider['nicsrs'] ?? 0) + 1;

                    $normStatus = $this->normalizeStatus($r->status);
                    $byStatus[$normStatus] = ($byStatus[$normStatus] ?? 0) + 1;

                    if (in_array($r->status, self::ACTIVE_STATUSES)) $active++;
                    if (in_array($r->status, self::PENDING_STATUSES)) $pending++;

                    // Check expiry from configdata
                    $cfg = json_decode($r->configdata ?? '', true) ?: [];
                    $endDate = $cfg['applyReturn']['endDate'] ?? $cfg['end_date'] ?? null;
                    if ($this->isExpiringSoon($endDate)) $expiring++;
                }
            }
        } catch (\Exception $e) {}

        // ── 3. tblsslorders legacy (not claimed) ──
        try {
            $legacyRows = Capsule::table('tblsslorders')
                ->whereIn('module', self::TBLSSL_MODULES)
                ->select(['id', 'module', 'status', 'configdata'])
                ->get();

            foreach ($legacyRows as $r) {
                if (isset($claimedKeys['tblsslorders:' . $r->id])) continue;
                $total++;
                $slug = self::MODULE_SLUG[$r->module] ?? 'unknown';
                $byProvider[$slug] = ($byProvider[$slug] ?? 0) + 1;

                $normStatus = $this->normalizeStatus($r->status);
                $byStatus[$normStatus] = ($byStatus[$normStatus] ?? 0) + 1;

                if (in_array($r->status, self::ACTIVE_STATUSES)) $active++;
                if (in_array($r->status, self::PENDING_STATUSES)) $pending++;

                $cfg = json_decode($r->configdata ?? '', true) ?: [];
                $endDate = $cfg['end_date'] ?? $cfg['valid_till']
                    ?? $cfg['CertificateEndDate'] ?? null;
                if ($this->isExpiringSoon($endDate)) $expiring++;
            }
        } catch (\Exception $e) {}

        return [
            'total'      => $total,
            'active'     => $active,
            'pending'    => $pending,
            'expiring'   => $expiring,
            'byProvider' => $byProvider,
            'byStatus'   => $byStatus,
        ];
    }

    // ─── Expiring Certificates ─────────────────────────────────────

    private function getExpiringCertificates(int $days = 30, int $limit = 10): array
    {
        $certs = [];
        $now = date('Y-m-d');
        $threshold = date('Y-m-d', strtotime("+{$days} days"));
        $claimedKeys = $this->getClaimedLegacyKeys();

        // 1. mod_aio_ssl_orders
        try {
            $rows = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->whereNotNull('o.end_date')
                ->where('o.end_date', '>=', $now)
                ->where('o.end_date', '<=', $threshold)
                ->select([
                    'o.id', 'o.domain', 'o.provider_slug', 'o.status',
                    'o.end_date', 'o.service_id as serviceid',
                    'o.userid', 'c.id as client_id',
                    Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                    Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                    Capsule::raw("'mod_aio_ssl_orders' as _source_table"),
                ])
                ->orderBy('o.end_date')
                ->get()->toArray();

            foreach ($rows as $r) {
                $r->_provider_slug = $r->provider_slug;
                $r->days_left = max(0, (int)((strtotime($r->end_date) - time()) / 86400));
                $certs[] = $r;
            }
        } catch (\Exception $e) {}

        // 2. nicsrs_sslorders (parse configdata for endDate)
        try {
            if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
                $rows = Capsule::table('nicsrs_sslorders as o')
                    ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                    ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                    ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                    ->where('o.status', 'complete')
                    ->select([
                        'o.id', 'o.serviceid', 'o.userid', 'o.configdata', 'o.status',
                        'h.domain', 'c.id as client_id',
                        Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                        Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                    ])
                    ->get();

                foreach ($rows as $r) {
                    if (isset($claimedKeys['nicsrs_sslorders:' . $r->id])) continue;
                    $cfg = json_decode($r->configdata ?? '', true) ?: [];
                    $endDate = $cfg['applyReturn']['endDate'] ?? $cfg['end_date'] ?? null;
                    if (!$endDate || strtotime($endDate) < time() || strtotime($endDate) > strtotime($threshold)) continue;

                    $r->end_date = $endDate;
                    $r->_source_table = 'nicsrs_sslorders';
                    $r->_provider_slug = 'nicsrs';
                    $r->provider_slug = 'nicsrs';
                    $r->days_left = max(0, (int)((strtotime($endDate) - time()) / 86400));
                    $certs[] = $r;
                }
            }
        } catch (\Exception $e) {}

        // 3. tblsslorders legacy
        try {
            $rows = Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->whereIn('o.module', self::TBLSSL_MODULES)
                ->whereIn('o.status', self::ACTIVE_STATUSES)
                ->select([
                    'o.id', 'o.module', 'o.serviceid', 'o.userid', 'o.configdata', 'o.status',
                    'h.domain', 'c.id as client_id',
                    Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                    Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                ])
                ->get();

            foreach ($rows as $r) {
                if (isset($claimedKeys['tblsslorders:' . $r->id])) continue;
                $cfg = json_decode($r->configdata ?? '', true) ?: [];
                $endDate = $cfg['end_date'] ?? $cfg['valid_till']
                    ?? $cfg['CertificateEndDate'] ?? null;
                if (!$endDate || strtotime($endDate) < time() || strtotime($endDate) > strtotime($threshold)) continue;

                $r->end_date = $endDate;
                $r->_source_table = 'tblsslorders';
                $r->_provider_slug = self::MODULE_SLUG[$r->module] ?? 'unknown';
                $r->provider_slug = $r->_provider_slug;
                $r->days_left = max(0, (int)((strtotime($endDate) - time()) / 86400));
                $certs[] = $r;
            }
        } catch (\Exception $e) {}

        // Sort by days_left ASC, limit
        usort($certs, fn($a, $b) => ($a->days_left ?? 999) <=> ($b->days_left ?? 999));
        return array_slice($certs, 0, $limit);
    }

    // ─── Recent Orders ─────────────────────────────────────────────

    private function getRecentOrders(int $limit = 10): array
    {
        $orders = [];
        $claimedKeys = $this->getClaimedLegacyKeys();

        // 1. mod_aio_ssl_orders
        try {
            $rows = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->select([
                    'o.id', 'o.userid', 'o.service_id as serviceid',
                    'o.domain', 'o.provider_slug', 'o.status',
                    'o.created_at', 'o.updated_at',
                    'c.id as client_id',
                    Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                    Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                    Capsule::raw("'mod_aio_ssl_orders' as _source_table"),
                    Capsule::raw("COALESCE(o.updated_at, o.created_at) as _sort_date"),
                ])
                ->orderBy('o.id', 'desc')
                ->limit($limit)
                ->get()->toArray();

            foreach ($rows as $r) {
                $r->_provider_slug = $r->provider_slug;
                $orders[] = $r;
            }
        } catch (\Exception $e) {}

        // 2. nicsrs_sslorders
        try {
            if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
                $rows = Capsule::table('nicsrs_sslorders as o')
                    ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                    ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                    ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                    ->select([
                        'o.id', 'o.userid', 'o.serviceid',
                        'h.domain', 'o.status',
                        'o.provisiondate as created_at',
                        'c.id as client_id',
                        Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                        Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                        Capsule::raw("'nicsrs_sslorders' as _source_table"),
                        Capsule::raw("o.provisiondate as _sort_date"),
                    ])
                    ->orderBy('o.id', 'desc')
                    ->limit($limit)
                    ->get()->toArray();

                foreach ($rows as $r) {
                    if (isset($claimedKeys['nicsrs_sslorders:' . $r->id])) continue;
                    $r->_provider_slug = 'nicsrs';
                    $r->provider_slug = 'nicsrs';
                    $orders[] = $r;
                }
            }
        } catch (\Exception $e) {}

        // 3. tblsslorders legacy
        try {
            $rows = Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->whereIn('o.module', self::TBLSSL_MODULES)
                ->select([
                    'o.id', 'o.userid', 'o.serviceid', 'o.module',
                    'h.domain', 'o.status',
                    'o.created_at', 'o.updated_at',
                    'c.id as client_id',
                    Capsule::raw("CONCAT(c.firstname,' ',c.lastname) as client_name"),
                    Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                    Capsule::raw("'tblsslorders' as _source_table"),
                    Capsule::raw("COALESCE(o.updated_at, o.created_at) as _sort_date"),
                ])
                ->orderBy('o.id', 'desc')
                ->limit($limit)
                ->get()->toArray();

            foreach ($rows as $r) {
                if (isset($claimedKeys['tblsslorders:' . $r->id])) continue;
                $r->_provider_slug = self::MODULE_SLUG[$r->module] ?? 'unknown';
                $r->provider_slug = $r->_provider_slug;
                $orders[] = $r;
            }
        } catch (\Exception $e) {}

        // Sort by date desc, limit
        usort($orders, fn($a, $b) => ($b->_sort_date ?? '') <=> ($a->_sort_date ?? ''));
        return array_slice($orders, 0, $limit);
    }

    // ─── Chart Data ────────────────────────────────────────────────

    private function getChartData(): array
    {
        $stats = $this->getStats();

        // Provider display names for chart labels
        $providerNames = [
            'nicsrs'      => 'NicSRS',
            'gogetssl'    => 'GoGetSSL',
            'thesslstore' => 'TheSSLStore',
            'ssl2buy'     => 'SSL2Buy',
            'aio'         => 'AIO SSL',
        ];

        // Normalize provider labels
        $ordersByProvider = [];
        foreach ($stats['byProvider'] as $slug => $count) {
            $label = $providerNames[$slug] ?? ucfirst($slug);
            $ordersByProvider[$label] = $count;
        }

        // Normalize status labels
        $statusDistribution = [];
        $statusLabels = [
            'active'    => 'Active',
            'pending'   => 'Pending',
            'awaiting'  => 'Awaiting',
            'expired'   => 'Expired',
            'cancelled' => 'Cancelled',
            'revoked'   => 'Revoked',
            'draft'     => 'Draft',
            'unknown'   => 'Other',
        ];
        foreach ($stats['byStatus'] as $key => $count) {
            $label = $statusLabels[$key] ?? ucfirst($key);
            $statusDistribution[$label] = ($statusDistribution[$label] ?? 0) + $count;
        }

        // Monthly trend (last 6 months) — from mod_aio_ssl_orders + rough estimate
        $monthlyOrders = $this->getMonthlyTrend(6);

        return [
            'statusDistribution' => $statusDistribution,
            'ordersByProvider'   => $ordersByProvider,
            'monthlyOrders'      => $monthlyOrders,
        ];
    }

    /**
     * Monthly order trend from mod_aio_ssl_orders
     */
    private function getMonthlyTrend(int $months = 6): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t 23:59:59', strtotime("-{$i} months"));
            $count = 0;

            try {
                $count += Capsule::table('mod_aio_ssl_orders')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
            } catch (\Exception $e) {}

            // Also count from nicsrs_sslorders
            try {
                if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
                    $count += Capsule::table('nicsrs_sslorders')
                        ->where('provisiondate', '>=', $monthStart)
                        ->where('provisiondate', '<=', substr($monthEnd, 0, 10))
                        ->count();
                }
            } catch (\Exception $e) {}

            // And tblsslorders
            try {
                $count += Capsule::table('tblsslorders')
                    ->whereIn('module', self::TBLSSL_MODULES)
                    ->whereNotNull('created_at')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
            } catch (\Exception $e) {}

            $data[] = [
                'month' => date('M Y', strtotime($monthStart)),
                'short' => date('M', strtotime($monthStart)),
                'count' => $count,
            ];
        }
        return $data;
    }

    // ─── Provider Health Test ──────────────────────────────────────

    private function testAllProviders(): array
    {
        $results = [];
        try {
            $providers = ProviderRegistry::getAllEnabled();
            foreach ($providers as $slug => $provider) {
                try {
                    $results[$slug] = $provider->testConnection();
                } catch (\Exception $e) {
                    $results[$slug] = ['success' => false, 'message' => $e->getMessage()];
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'results' => $results];
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Get already-claimed legacy keys
     */
    private function getClaimedLegacyKeys(): array
    {
        $keys = [];
        try {
            $rows = Capsule::table('mod_aio_ssl_orders')
                ->whereNotNull('legacy_table')
                ->whereNotNull('legacy_order_id')
                ->select(['legacy_table', 'legacy_order_id'])
                ->get();
            foreach ($rows as $r) {
                $keys[$r->legacy_table . ':' . $r->legacy_order_id] = true;
            }
        } catch (\Exception $e) {}
        return $keys;
    }

    /**
     * Normalize status to canonical key
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'                  => 'active',
            'complete'                => 'active',
            'completed'               => 'active',
            'issued'                  => 'active',
            'configuration submitted' => 'active',
            'pending'                 => 'pending',
            'processing'              => 'pending',
            'awaiting configuration'  => 'awaiting',
            'awaiting'                => 'awaiting',
            'draft'                   => 'draft',
            'expired'                 => 'expired',
            'cancelled'               => 'cancelled',
            'canceled'                => 'cancelled',
            'revoked'                 => 'revoked',
            'rejected'                => 'cancelled',
        ];
        return $map[strtolower(trim($status))] ?? 'unknown';
    }

    /**
     * Check if a date is within 30 days from now
     */
    private function isExpiringSoon(?string $date, int $days = 30): bool
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return false;
        }
        $ts = strtotime($date);
        if (!$ts) return false;
        $now = time();
        return $ts >= $now && $ts <= strtotime("+{$days} days");
    }
}