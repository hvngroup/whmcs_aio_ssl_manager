<?php
/**
 * Order Controller — Unified order management across 3 tables
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
use AioSSL\Core\ActivityLogger;
use AioSSL\Service\MigrationService;

class OrderController extends BaseController
{
    /**
     * Legacy tblsslorders module names
     */
    private const LEGACY_TBLSSL_MODULES = [
        'SSLCENTERWHMCS',
        'thesslstore_ssl',
        'thesslstorefullv2',
        'ssl2buy',
    ];

    /** Module → provider slug */
    private const MODULE_TO_SLUG = [
        'nicsrs_ssl'        => 'nicsrs',
        'SSLCENTERWHMCS'    => 'gogetssl',
        'thesslstore_ssl'   => 'thesslstore',
        'thesslstorefullv2' => 'thesslstore',
        'ssl2buy'           => 'ssl2buy',
        'aio_ssl'           => 'aio',
    ];

    /** Provider slug → display name */
    private const PROVIDER_NAMES = [
        'nicsrs'      => 'NicSRS',
        'gogetssl'    => 'GoGetSSL',
        'thesslstore' => 'TheSSLStore',
        'ssl2buy'     => 'SSL2Buy',
        'aio'         => 'AIO SSL',
    ];

    public function render(string $action = ''): void
    {
        switch ($action) {
            case 'detail':
                $this->renderDetail();
                break;
            default:
                $this->renderList();
                break;
        }
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'refresh_status':
                return $this->refreshOrderStatus();
            case 'resend_dcv':
                return $this->resendDcv();
            case 'revoke':
                return $this->revokeOrder();
            case 'cancel':
                return $this->cancelOrder();
            case 'claim':
                return $this->claimLegacyOrder();
            case 'bulk_refresh':
                return $this->bulkRefresh();
            default:
                return parent::handleAjax($action);
        }
    }

    // ─── Unified Order List ────────────────────────────────────────

    private function renderList(): void
    {
        $page = $this->getCurrentPage();
        $statusFilter = $this->input('status', '');
        $providerFilter = $this->input('provider', '');
        $sourceFilter = $this->input('source', ''); // 'aio', 'legacy', ''
        $search = $this->input('search', '');

        // Collect orders from all 3 sources
        $allOrders = $this->getUnifiedOrders($statusFilter, $providerFilter, $sourceFilter, $search);

        // Sort by ID desc (most recent first)
        usort($allOrders, fn($a, $b) => ($b->_sort_date ?? '') <=> ($a->_sort_date ?? ''));

        // Manual pagination
        $total = count($allOrders);
        $pagination = $this->paginate($total, $page);
        $orders = array_slice($allOrders, $pagination['offset'], $pagination['limit']);

        $this->renderTemplate('orders.php', [
            'orders'     => $orders,
            'pagination' => $pagination,
            'filters'    => compact('statusFilter', 'providerFilter', 'sourceFilter', 'search'),
        ]);
    }

    /**
     * Get unified orders from all 3 tables
     */
    private function getUnifiedOrders(
        string $status = '',
        string $provider = '',
        string $source = '',
        string $search = ''
    ): array {
        $orders = [];

        // Get already-claimed legacy order IDs to avoid duplicates
        $claimedKeys = $this->getClaimedLegacyKeys();

        // ── Source 1: mod_aio_ssl_orders (AIO native + claimed) ──
        if ($source === '' || $source === 'aio') {
            $orders = array_merge($orders, $this->getAioOrders($status, $provider, $search));
        }

        // ── Source 2: nicsrs_sslorders (NicSRS legacy) ──
        if ($source === '' || $source === 'legacy') {
            if ($provider === '' || $provider === 'nicsrs') {
                $orders = array_merge($orders,
                    $this->getNicsrsLegacyOrders($status, $search, $claimedKeys));
            }
        }

        // ── Source 3: tblsslorders (GoGetSSL, TheSSLStore, SSL2Buy legacy) ──
        if ($source === '' || $source === 'legacy') {
            $orders = array_merge($orders,
                $this->getTblsslLegacyOrders($status, $provider, $search, $claimedKeys));
        }

        return $orders;
    }

    /**
     * Get AIO native orders from mod_aio_ssl_orders
     */
    private function getAioOrders(string $status, string $provider, string $search): array
    {
        try {
            $q = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id');

            if ($status) {
                $q->where('o.status', $status);
            }
            if ($provider) {
                $q->where('o.provider_slug', $provider);
            }
            if ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('o.domain', 'LIKE', "%{$search}%")
                       ->orWhere('o.remote_id', 'LIKE', "%{$search}%")
                       ->orWhere('o.certtype', 'LIKE', "%{$search}%")
                       ->orWhereRaw("CONCAT(c.firstname, ' ', c.lastname) LIKE ?", ["%{$search}%"]);
                });
            }

            $q->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id');

            $rows = $q->select([
                'o.id',
                'o.userid',
                'o.service_id as serviceid',
                'o.remote_id as remoteid',
                'o.provider_slug',
                'o.certtype',
                'o.domain',
                'o.status',
                'o.configdata',
                'o.completiondate',
                'o.begin_date',
                'o.end_date',
                'o.legacy_table',
                'o.legacy_order_id',
                'o.legacy_module',
                'o.created_at',
                'o.updated_at',
                Capsule::raw("COALESCE(h.domain, o.domain) as display_domain"),
                Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                'c.id as client_id',
                Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                Capsule::raw("'aio' as _source"),
                Capsule::raw("'mod_aio_ssl_orders' as _source_table"),
                Capsule::raw("COALESCE(o.updated_at, o.created_at) as _sort_date"),
            ])
            ->orderBy('o.id', 'desc')
            ->get()
            ->toArray();

            // Add provider slug from provider_slug column
            foreach ($rows as &$r) {
                $r->_provider_slug = $r->provider_slug;
                $r->_is_legacy = !empty($r->legacy_table);
                $r->module = $r->legacy_module ?: 'aio_ssl';
            }

            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get NicSRS legacy orders (not yet claimed)
     */
    private function getNicsrsLegacyOrders(string $status, string $search, array $claimedKeys): array
    {
        try {
            if (!Capsule::schema()->hasTable('nicsrs_sslorders')) {
                return [];
            }

            $q = Capsule::table('nicsrs_sslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id');

            if ($status) {
                $q->where('o.status', $status);
            }
            if ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('h.domain', 'LIKE', "%{$search}%")
                       ->orWhere('o.remoteid', 'LIKE', "%{$search}%")
                       ->orWhere('o.certtype', 'LIKE', "%{$search}%")
                       ->orWhereRaw("CONCAT(c.firstname, ' ', c.lastname) LIKE ?", ["%{$search}%"]);
                });
            }

            $rows = $q->select([
                'o.id',
                'o.userid',
                'o.serviceid',
                'o.remoteid',
                Capsule::raw("'nicsrs_ssl' as module"),
                'o.certtype',
                'o.status',
                'o.configdata',
                'o.completiondate',
                'o.provisiondate',
                'h.domain as display_domain',
                Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                'c.id as client_id',
                Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                Capsule::raw("'legacy_nicsrs' as _source"),
                Capsule::raw("'nicsrs_sslorders' as _source_table"),
                Capsule::raw("COALESCE(o.completiondate, o.provisiondate) as _sort_date"),
            ])
            ->orderBy('o.id', 'desc')
            ->get()
            ->toArray();

            // Filter out already claimed & enrich
            $result = [];
            foreach ($rows as $r) {
                $key = 'nicsrs_sslorders:' . $r->id;
                if (isset($claimedKeys[$key])) continue;

                $r->_provider_slug = 'nicsrs';
                $r->_is_legacy = true;
                $r->provider_slug = 'nicsrs';
                $r->domain = $r->display_domain ?: $this->extractDomainFromConfig($r->configdata);
                $result[] = $r;
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get tblsslorders legacy orders (GoGetSSL, TheSSLStore, SSL2Buy)
     */
    private function getTblsslLegacyOrders(
        string $status,
        string $provider,
        string $search,
        array $claimedKeys
    ): array {
        try {
            $modules = self::LEGACY_TBLSSL_MODULES;

            // Filter by provider → narrow down modules
            if ($provider) {
                $slugToModules = [
                    'gogetssl'    => ['SSLCENTERWHMCS'],
                    'thesslstore' => ['thesslstore_ssl', 'thesslstorefullv2'],
                    'ssl2buy'     => ['ssl2buy'],
                ];
                if (isset($slugToModules[$provider])) {
                    $modules = $slugToModules[$provider];
                } elseif (!in_array($provider, ['nicsrs', 'aio', ''])) {
                    // Unknown provider, skip tblsslorders
                    return [];
                }
            }

            $q = Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->whereIn('o.module', $modules);

            if ($status) {
                $q->where('o.status', $status);
            }
            if ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('h.domain', 'LIKE', "%{$search}%")
                       ->orWhere('o.remoteid', 'LIKE', "%{$search}%")
                       ->orWhere('o.certtype', 'LIKE', "%{$search}%")
                       ->orWhereRaw("CONCAT(c.firstname, ' ', c.lastname) LIKE ?", ["%{$search}%"]);
                });
            }

            $rows = $q->select([
                'o.id',
                'o.userid',
                'o.serviceid',
                'o.remoteid',
                'o.module',
                'o.certtype',
                'o.status',
                'o.configdata',
                'o.completiondate',
                'o.created_at',
                'o.updated_at',
                'h.domain as display_domain',
                Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                'c.id as client_id',
                Capsule::raw("COALESCE(p.name, o.certtype) as product_name"),
                Capsule::raw("'legacy_tblssl' as _source"),
                Capsule::raw("'tblsslorders' as _source_table"),
                Capsule::raw("COALESCE(o.updated_at, o.created_at, o.completiondate) as _sort_date"),
            ])
            ->orderBy('o.id', 'desc')
            ->get()
            ->toArray();

            // Filter out already claimed & enrich
            $result = [];
            foreach ($rows as $r) {
                $key = 'tblsslorders:' . $r->id;
                if (isset($claimedKeys[$key])) continue;

                $slug = self::MODULE_TO_SLUG[$r->module] ?? 'unknown';
                $r->_provider_slug = $slug;
                $r->_is_legacy = true;
                $r->provider_slug = $slug;
                $r->domain = $r->display_domain ?: $this->extractDomainFromConfig($r->configdata);
                $result[] = $r;
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get set of already-claimed legacy keys: "table:id" → true
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
     * Extract domain from JSON configdata
     */
    private function extractDomainFromConfig(?string $configdata): string
    {
        if (empty($configdata)) return '';
        $data = json_decode($configdata, true);
        if (!is_array($data)) return '';

        // Try common keys
        if (!empty($data['domain'])) return $data['domain'];

        // NicSRS: domainInfo[0].domainName
        if (!empty($data['domainInfo'][0]['domainName'])) {
            return $data['domainInfo'][0]['domainName'];
        }

        // GoGetSSL: san_details[0].san_name or domain
        if (!empty($data['san_details'][0]['san_name'])) {
            return $data['san_details'][0]['san_name'];
        }

        return '';
    }

    // ─── Order Detail ──────────────────────────────────────────────

    /**
     * Render order detail page
     *
     * Routing logic:
     *   source=aio    → mod_aio_ssl_orders ONLY
     *   source=tblssl → tblsslorders ONLY
     *   source=nicsrs → nicsrs_sslorders ONLY
     *   source=''     → Try all 3 tables in order: aio → tblssl → nicsrs
     *
     * IMPORTANT: When source is explicitly set, we query ONLY that table.
     * This prevents ID collisions (e.g. tblsslorders.id=3 vs mod_aio_ssl_orders.id=3).
     */
    private function renderDetail(): void
    {
        $id = (int)$this->input('id');
        $source = $this->input('source', '');

        $order = null;
        $sourceTable = '';

        // ── Explicit source routing — no fallthrough ──
        if ($source === 'aio') {
            $order = $this->fetchOrderFromAio($id);
            $sourceTable = 'mod_aio_ssl_orders';
        } elseif ($source === 'tblssl') {
            $order = $this->fetchOrderFromTblssl($id);
            $sourceTable = 'tblsslorders';
        } elseif ($source === 'nicsrs') {
            $order = $this->fetchOrderFromNicsrs($id);
            $sourceTable = 'nicsrs_sslorders';
        } else {
            // No source specified — try all 3 in priority order
            // Priority: aio → tblssl → nicsrs
            $order = $this->fetchOrderFromAio($id);
            if ($order) {
                $sourceTable = 'mod_aio_ssl_orders';
            } else {
                $order = $this->fetchOrderFromTblssl($id);
                if ($order) {
                    $sourceTable = 'tblsslorders';
                } else {
                    $order = $this->fetchOrderFromNicsrs($id);
                    if ($order) {
                        $sourceTable = 'nicsrs_sslorders';
                    }
                }
            }
        }

        if (!$order) {
            echo '<div class="aio-alert aio-alert-danger">'
               . '<i class="fas fa-exclamation-circle"></i> '
               . 'Order #' . $id . ' not found'
               . ($source ? " in source <code>{$source}</code>" : '') . '.'
               . ' <a href="' . ($this->moduleLink ?? '') . '&page=orders">Back to Orders</a>'
               . '</div>';
            return;
        }

        $configdata = json_decode($order->configdata, true) ?: [];

        // Determine if this is a legacy order
        $isLegacy = ($sourceTable !== 'mod_aio_ssl_orders')
                 || !empty($order->legacy_table);

        // Provider slug
        $providerSlug = $order->provider_slug
            ?? $configdata['provider']
            ?? (self::MODULE_TO_SLUG[$order->module] ?? 'unknown');

        // Activity log
        $activities = [];
        try {
            $activities = ActivityLogger::getRecent(20, 'order', (string)$order->id);
        } catch (\Exception $e) {}

        $this->renderTemplate('order_detail.php', [
            'order'        => $order,
            'o'            => $order, // alias for template compatibility
            'configdata'   => $configdata,
            'cfg'          => $configdata,
            'activities'   => $activities,
            'isLegacy'     => $isLegacy,
            'sourceTable'  => $sourceTable,
            'providerSlug' => $providerSlug,
            'providerName' => self::PROVIDER_NAMES[$providerSlug] ?? ucfirst($providerSlug),
        ]);
    }

    // ─── Detail Fetch Helpers ──────────────────────────────────────

    private function fetchOrderFromAio(int $id): ?object
    {
        try {
            $order = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();

            if ($order) {
                // Normalize column names for template compatibility
                $order->module = $order->legacy_module ?: 'aio_ssl';
                $order->serviceid = $order->service_id;
                $order->remoteid = $order->remote_id;
                $order->domain = $order->domain ?: ($order->hosting_domain ?? '');
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchOrderFromTblssl(int $id): ?object
    {
        try {
            $order = Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();

            if ($order) {
                $order->domain = $order->domain ?? $order->hosting_domain ?? '';
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchOrderFromNicsrs(int $id): ?object
    {
        try {
            if (!Capsule::schema()->hasTable('nicsrs_sslorders')) {
                return null;
            }

            $order = Capsule::table('nicsrs_sslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();

            if ($order) {
                $order->module = 'nicsrs_ssl';
                $order->domain = $order->hosting_domain ?? '';
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ─── AJAX Actions ──────────────────────────────────────────────

    private function claimLegacyOrder(): array
    {
        $id = (int)$this->input('id');
        $sourceTable = $this->input('source_table', '');

        $migrationService = new MigrationService();
        return $migrationService->claimOrder($id, $sourceTable);
    }

    private function refreshOrderStatus(): array
    {
        $id = (int)$this->input('id');
        $sourceTable = $this->input('source_table', 'mod_aio_ssl_orders');

        // Find the order
        $order = null;
        try {
            $order = Capsule::table($sourceTable)->find($id);
        } catch (\Exception $e) {}

        if (!$order || empty($order->remoteid ?? $order->remote_id ?? '')) {
            return ['success' => false, 'message' => 'No remote ID found.'];
        }

        $remoteId = $order->remoteid ?? $order->remote_id ?? '';

        // Determine provider
        $slug = '';
        if (!empty($order->provider_slug)) {
            $slug = $order->provider_slug;
        } else {
            $configdata = json_decode($order->configdata ?? '', true) ?: [];
            $slug = $configdata['provider']
                ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        }

        if (empty($slug)) {
            return ['success' => false, 'message' => 'Cannot determine provider.'];
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->getOrderStatus($remoteId);

            if ($result['success'] ?? false) {
                // Update the order in the correct table
                $updateData = ['status' => $result['status'] ?? $order->status];

                if ($sourceTable === 'mod_aio_ssl_orders') {
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                    Capsule::table('mod_aio_ssl_orders')->where('id', $id)->update($updateData);
                }
                // Don't update legacy tables — read-only

                ActivityLogger::log('status_refreshed', 'order', (string)$id,
                    "Refreshed via {$slug}: {$result['status']}");
            }

            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()];
        }
    }

    private function resendDcv(): array
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            return $provider->resendDcvEmail($order->remoteid);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function revokeOrder(): array
    {
        $id = (int)$this->input('id');
        $reason = $this->input('reason', '');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->revokeCertificate($order->remoteid, $reason);
            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $id)->update(['status' => 'Revoked']);
                ActivityLogger::log('order_revoked', 'order', (string)$id, "Order revoked: {$reason}");
            }
            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function cancelOrder(): array
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->cancelOrder($order->remoteid);
            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $id)->update(['status' => 'Cancelled']);
                ActivityLogger::log('order_cancelled', 'order', (string)$id, 'Order cancelled');
            }
            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function bulkRefresh(): array
    {
        $ids = $this->input('ids', '');
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No orders selected.'];
        }

        $idList = array_map('intval', explode(',', $ids));
        $refreshed = 0;
        $failed = 0;

        foreach ($idList as $id) {
            $_REQUEST['id'] = $id;
            $result = $this->refreshOrderStatus();
            if ($result['success'] ?? false) {
                $refreshed++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'message' => "Refreshed: {$refreshed}, Failed: {$failed}",
        ];
    }
}    