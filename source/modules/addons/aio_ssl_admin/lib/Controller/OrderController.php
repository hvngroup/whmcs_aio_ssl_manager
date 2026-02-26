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

    /** Status values considered terminal */
    private const TERMINAL_STATUSES = ['cancelled', 'revoked', 'terminated'];

    /** Status values for completed/issued certificates */
    private const COMPLETE_STATUSES = ['complete', 'completed', 'issued', 'active'];

    /** Status values for pending certificates */
    private const PENDING_STATUSES = ['pending', 'processing', 'draft'];

    // ─── Routing ───────────────────────────────────────────────────

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
            case 'refresh_status':  return $this->ajaxRefreshStatus();
            case 'resend_dcv':      return $this->ajaxResendDcv();
            case 'revoke':          return $this->ajaxRevoke();
            case 'cancel':          return $this->ajaxCancel();
            case 'claim':           return $this->claimLegacyOrder();
            case 'bulk_refresh':    return $this->bulkRefresh();
            case 'edit_order':      return $this->ajaxEditOrder();
            case 'download':        return $this->ajaxDownload();
            case 'reissue':         return $this->ajaxReissue();
            case 'renew':           return $this->ajaxRenew();
            case 'change_dcv':      return $this->ajaxChangeDcv();
            case 'delete_order':    return $this->ajaxDeleteOrder();
            case 'config_link':     return $this->ajaxConfigLink();

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
    private function getUnifiedOrders(string $status, string $provider, string $source, string $search): array
    {
        $orders     = [];
        $claimedKeys = $this->getClaimedLegacyKeys();

        if ($source === '' || $source === 'aio') {
            $orders = array_merge($orders, $this->getAioOrders($status, $provider, $search));
        }
        if ($source === '' || $source === 'legacy') {
            if ($provider === '' || $provider === 'nicsrs') {
                $orders = array_merge($orders, $this->getNicsrsLegacyOrders($status, $search, $claimedKeys));
            }
            $orders = array_merge($orders, $this->getTblsslLegacyOrders($status, $provider, $search, $claimedKeys));
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
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        // ── Fetch order from correct table ──
        [$order, $sourceTable] = $this->fetchOrderMultiSource($id, $source);

        if (!$order) {
            echo '<div class="aio-alert aio-alert-danger">'
               . '<i class="fas fa-exclamation-circle"></i> '
               . 'Order #' . $id . ' not found'
               . ($source ? " in source <code>{$source}</code>" : '') . '.'
               . ' <a href="' . $this->moduleLink . '&page=orders">Back to Orders</a>'
               . '</div>';
            return;
        }

        // ── Parse configdata (json_decode → fallback unserialize) ──
        $configdata = $this->parseConfigdata($order->configdata ?? '');

        // ── Determine provider & legacy status ──
        $isLegacy = ($sourceTable !== 'mod_aio_ssl_orders')
                    || !empty($order->legacy_table);

        $providerSlug = $order->provider_slug
            ?? $configdata['provider']
            ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? 'unknown');

        // ── Get provider capabilities ──
        $capabilities = [];
        try {
            if ($providerSlug && $providerSlug !== 'unknown') {
                $provider = ProviderRegistry::get($providerSlug);
                $capabilities = $provider->getCapabilities();
            }
        } catch (\Exception $e) {
            // Provider not configured — show basic UI
        }

        // ── Normalize status for comparison ──
        $statusNorm = strtolower($order->status ?? 'unknown');

        // ── Build capability flags for template ──
        $isComplete    = in_array($statusNorm, self::COMPLETE_STATUSES);
        $isPending     = in_array($statusNorm, self::PENDING_STATUSES);
        $isTerminal    = in_array($statusNorm, self::TERMINAL_STATUSES);
        $isAwaiting    = (stripos($statusNorm, 'awaiting') !== false);

        $canRefresh    = !in_array($statusNorm, ['cancelled']);
        $canDownload   = in_array('download', $capabilities) && $isComplete;
        $canReissue    = in_array('reissue', $capabilities) && $isComplete;
        $canRenew      = in_array('renew', $capabilities) && ($isComplete || $statusNorm === 'expired');
        $canCancel     = in_array('cancel', $capabilities) && !$isTerminal && !$isComplete;
        $canRevoke     = in_array('revoke', $capabilities) && $isComplete;
        $canResendDcv  = (in_array('dcv_email', $capabilities) || in_array('resend_dcv', $capabilities))
                         && $isPending;
        $canChangeDcv  = (in_array('change_dcv', $capabilities) || in_array('dcv_http', $capabilities)
                         || in_array('dcv_cname', $capabilities)) && $isPending;
        $canConfigLink = in_array('config_link', $capabilities);

        // ── Extract certificate data ──
        $applyReturn = $configdata['applyReturn'] ?? [];
        $hasCsr      = !empty($configdata['csr']);
        $hasCert     = !empty($applyReturn['certificate']);
        $hasKey      = !empty($configdata['privateKey']);

        // ── Domain list (multi-source extraction) ──
        $domainList = [];

        // Source 1: configdata.domainInfo (NicSRS native format from original apply)
        if (!empty($configdata['domainInfo'])) {
            foreach ($configdata['domainInfo'] as $d) {
                $domainList[] = [
                    'domainName' => $d['domainName'] ?? $d['domain'] ?? '',
                    'dcvMethod'  => $d['dcvMethod'] ?? $d['method'] ?? '',
                    'isVerified' => !empty($d['isVerified']) || !empty($d['is_verify']),
                ];
            }
        }

        // Source 2: configdata.original.domainInfo (NicSRS migrated/claimed orders)
        if (empty($domainList) && !empty($configdata['original']['domainInfo'])) {
            foreach ($configdata['original']['domainInfo'] as $d) {
                $domainList[] = [
                    'domainName' => $d['domainName'] ?? $d['domain'] ?? '',
                    'dcvMethod'  => $d['dcvMethod'] ?? $d['method'] ?? '',
                    'isVerified' => !empty($d['isVerified']) || !empty($d['is_verify']),
                ];
            }
        }

        // Source 3: applyReturn.dcvList (NicSRS API response format)
        if (empty($domainList) && !empty($applyReturn['dcvList'])) {
            foreach ($applyReturn['dcvList'] as $d) {
                $domainList[] = [
                    'domainName' => $d['domainName'] ?? $d['domain'] ?? '',
                    'dcvMethod'  => $d['dcvMethod'] ?? $d['method'] ?? '',
                    'isVerified' => ($d['is_verify'] ?? '') === 'verified'
                                   || ($applyReturn['dcv']['status'] ?? '') === 'done',
                ];
            }
        }

        // Source 4: configdata.original.applyReturn.dcvList (nested NicSRS)
        if (empty($domainList) && !empty($configdata['original']['applyReturn']['dcvList'])) {
            foreach ($configdata['original']['applyReturn']['dcvList'] as $d) {
                $dcvDone = ($configdata['original']['applyReturn']['dcv']['status'] ?? '') === 'done';
                $domainList[] = [
                    'domainName' => $d['domainName'] ?? $d['domain'] ?? '',
                    'dcvMethod'  => $d['dcvMethod'] ?? $d['method'] ?? '',
                    'isVerified' => ($d['is_verify'] ?? '') === 'verified' || $dcvDone,
                ];
            }
        }

        // Source 5: configdata.domains[] (AIO native / simplified format)
        if (empty($domainList) && !empty($configdata['domains'])) {
            foreach ($configdata['domains'] as $d) {
                if (is_string($d)) {
                    $domainList[] = ['domainName' => $d, 'dcvMethod' => '', 'isVerified' => $isComplete];
                } elseif (is_array($d)) {
                    $domainList[] = [
                        'domainName' => $d['domainName'] ?? $d['DomainName'] ?? $d['domain'] ?? '',
                        'dcvMethod'  => $d['dcvMethod'] ?? $d['DCVMethod'] ?? $d['dcv_method'] ?? '',
                        'isVerified' => !empty($d['IsValidated']) || !empty($d['isVerified']) || $isComplete,
                    ];
                }
            }
        }

        // Source 6: GoGetSSL san_details format
        if (empty($domainList) && !empty($configdata['san_details'])) {
            foreach ($configdata['san_details'] as $san) {
                $domainList[] = [
                    'domainName' => $san['san_name'] ?? $san['domain'] ?? '',
                    'dcvMethod'  => $san['validation_method'] ?? $san['method'] ?? '',
                    'isVerified' => ($san['status'] ?? '') === 'validated',
                ];
            }
        }

        // Source 7: TheSSLStore domains format
        if (empty($domainList) && !empty($configdata['Domains'])) {
            foreach ($configdata['Domains'] as $d) {
                $domainList[] = [
                    'domainName' => $d['DomainName'] ?? $d['domain'] ?? '',
                    'dcvMethod'  => $d['DCVMethod'] ?? '',
                    'isVerified' => !empty($d['IsValidated']) || $isComplete,
                ];
            }
        }

        // Fallback: use primary domain if still empty
        if (empty($domainList) && !empty($primaryDomain)) {
            $dcvMethod = $configdata['dcv_method']
                ?? $configdata['original']['domainInfo'][0]['dcvMethod']
                ?? $applyReturn['dcvList'][0]['dcvMethod']
                ?? '';
            $domainList[] = [
                'domainName' => $primaryDomain,
                'dcvMethod'  => $dcvMethod,
                'isVerified' => $isComplete || ($applyReturn['dcv']['status'] ?? '') === 'done',
            ];
        }

        // Filter empty entries
        $domainList = array_filter($domainList, function($d) {
            return !empty($d['domainName']);
        });

        // ── Calculate validity ──
        $beginDate = $applyReturn['beginDate'] ?? $configdata['begin_date'] ?? '';
        $endDate   = $applyReturn['endDate'] ?? $configdata['end_date'] ?? '';
        $daysLeft  = null;
        $validityPct = 0;
        if ($endDate) {
            $endTs = strtotime($endDate);
            if ($endTs) {
                $daysLeft = max(0, (int)ceil(($endTs - time()) / 86400));
            }
            if ($beginDate) {
                $beginTs = strtotime($beginDate);
                $totalDays = ($endTs - $beginTs) / 86400;
                $elapsed   = (time() - $beginTs) / 86400;
                $validityPct = $totalDays > 0 ? min(100, max(0, round($elapsed / $totalDays * 100))) : 0;
            }
        }
        $renewalDue = '';
        if ($endDate && strtotime($endDate)) {
            $renewalDue = date('Y-m-d', strtotime($endDate) - (30 * 86400));
        }

        // ── Client info (from JOIN) ──
        $clientName = trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? ''));
        if (empty($clientName) && !empty($order->client_name)) {
            $clientName = $order->client_name;
        }

        // ── Activity log ──
        $activities = [];
        try {
            $activities = ActivityLogger::getRecent(50, 'order', (string)$order->id);
        } catch (\Exception $e) {}

        // ── Primary domain ──
        $primaryDomain = $order->domain ?? '';
        if (empty($primaryDomain) && !empty($configdata['domains'][0])) {
            $d0 = $configdata['domains'][0];
            $primaryDomain = is_string($d0) ? $d0 : ($d0['domainName'] ?? '');
        }
        if (empty($primaryDomain) && !empty($configdata['original']['domainInfo'][0]['domainName'])) {
            $primaryDomain = $configdata['original']['domainInfo'][0]['domainName'];
        }
        if (empty($primaryDomain) && !empty($configdata['domainInfo'][0]['domainName'])) {
            $primaryDomain = $configdata['domainInfo'][0]['domainName'];
        }
        if (empty($primaryDomain) && !empty($applyReturn['dcvList'][0]['domainName'])) {
            $primaryDomain = $applyReturn['dcvList'][0]['domainName'];
        }

        // ── DCV data (for instructions display) ──
        // Try multiple sources: applyReturn → original.applyReturn → configdata root
        $origAr = $configdata['original']['applyReturn'] ?? [];
        $dcvData = [
            'DCVfileName'    => $applyReturn['DCVfileName'] ?? $origAr['DCVfileName'] ?? $configdata['dcv_file_name'] ?? '',
            'DCVfileContent' => $applyReturn['DCVfileContent'] ?? $origAr['DCVfileContent'] ?? $configdata['dcv_file_content'] ?? '',
            'DCVfilePath'    => $applyReturn['DCVfilePath'] ?? $origAr['DCVfilePath'] ?? '',
            'DCVdnsHost'     => $applyReturn['DCVdnsHost'] ?? $origAr['DCVdnsHost'] ?? $configdata['dcv_dns_host'] ?? '',
            'DCVdnsValue'    => $applyReturn['DCVdnsValue'] ?? $origAr['DCVdnsValue'] ?? $configdata['dcv_dns_value'] ?? '',
            'DCVdnsType'     => $applyReturn['DCVdnsType'] ?? $origAr['DCVdnsType'] ?? 'CNAME',
        ];

        // ── Render template ──
        $this->renderTemplate('order_detail.php', [
            // Core
            'order'         => $order,
            'o'             => $order,
            'configdata'    => $configdata,
            'cfg'           => $configdata,
            'sourceTable'   => $sourceTable,

            // Provider
            'providerSlug'  => $providerSlug,
            'providerName'  => self::PROVIDER_NAMES[$providerSlug] ?? ucfirst($providerSlug),
            'isLegacy'      => $isLegacy,
            'capabilities'  => $capabilities,
            'isTier2'       => ($providerSlug === 'ssl2buy'),

            // Status flags
            'isComplete'    => $isComplete,
            'isPending'     => $isPending,
            'isTerminal'    => $isTerminal,
            'isAwaiting'    => $isAwaiting,

            // Capability flags
            'canRefresh'    => $canRefresh,
            'canDownload'   => $canDownload,
            'canReissue'    => $canReissue,
            'canRenew'      => $canRenew,
            'canCancel'     => $canCancel,
            'canRevoke'     => $canRevoke,
            'canResendDcv'  => $canResendDcv,
            'canChangeDcv'  => $canChangeDcv,
            'canConfigLink' => $canConfigLink,

            // Certificate data
            'hasCsr'        => $hasCsr,
            'hasCert'       => $hasCert,
            'hasKey'        => $hasKey,
            'applyReturn'   => $applyReturn,
            'domainList'    => $domainList,
            'dcvData'       => $dcvData,

            // Validity
            'beginDate'     => $beginDate,
            'endDate'       => $endDate,
            'daysLeft'      => $daysLeft,
            'validityPct'   => $validityPct,
            'renewalDue'    => $renewalDue,

            // Client & Activity
            'clientName'    => $clientName,
            'primaryDomain' => $primaryDomain,
            'activities'    => $activities,
        ]);
    }

    // ─── Detail Fetch Helpers ──────────────────────────────────────

    /**
     * Fetch order from correct table(s) based on source hint
     *
     * @return array [object|null, string sourceTable]
     */
    private function fetchOrderMultiSource(int $id, string $source = ''): array
    {
        if ($source === 'aio') {
            return [$this->fetchOrderFromAio($id), 'mod_aio_ssl_orders'];
        }
        if ($source === 'tblssl') {
            return [$this->fetchOrderFromTblssl($id), 'tblsslorders'];
        }
        if ($source === 'nicsrs') {
            return [$this->fetchOrderFromNicsrs($id), 'nicsrs_sslorders'];
        }

        // No source — try all 3 in priority order
        $order = $this->fetchOrderFromAio($id);
        if ($order) return [$order, 'mod_aio_ssl_orders'];

        $order = $this->fetchOrderFromTblssl($id);
        if ($order) return [$order, 'tblsslorders'];

        $order = $this->fetchOrderFromNicsrs($id);
        if ($order) return [$order, 'nicsrs_sslorders'];

        return [null, ''];
    }

    private function fetchOrderFromAio(int $id): ?object
    {
        try {
            $order = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    'h.domainstatus as service_status',
                    'h.billingcycle as service_billingcycle',
                    'h.amount as service_amount',
                    'h.nextduedate as service_nextduedate',
                    'h.paymentmethod as service_paymentmethod',
                    'c.firstname', 'c.lastname', 'c.companyname',
                    'c.email as client_email', 'c.phonenumber',
                    'p.name as whmcs_product_name',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();

            if ($order) {
                $order->module    = $order->legacy_module ?? 'aio_ssl';
                $order->serviceid = $order->service_id ?? null;
                $order->remoteid  = $order->remote_id ?? null;
                $order->domain    = $order->domain ?: ($order->hosting_domain ?? '');
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchOrderFromTblssl(int $id): ?object
    {
        try {
            return Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    'h.domainstatus as service_status',
                    'h.billingcycle as service_billingcycle',
                    'h.amount as service_amount',
                    'h.nextduedate as service_nextduedate',
                    'h.paymentmethod as service_paymentmethod',
                    'c.firstname', 'c.lastname', 'c.companyname',
                    'c.email as client_email', 'c.phonenumber',
                    'p.name as whmcs_product_name',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchOrderFromNicsrs(int $id): ?object
    {
        try {
            $order = Capsule::table('nicsrs_sslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->where('o.id', $id)
                ->select([
                    'o.*',
                    'h.domain as hosting_domain',
                    'h.domainstatus as service_status',
                    'h.billingcycle as service_billingcycle',
                    'h.amount as service_amount',
                    'h.nextduedate as service_nextduedate',
                    'h.paymentmethod as service_paymentmethod',
                    'c.firstname', 'c.lastname', 'c.companyname',
                    'c.email as client_email', 'c.phonenumber',
                    'p.name as whmcs_product_name',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->first();

            if ($order) {
                $order->module        = 'nicsrs_ssl';
                $order->provider_slug = 'nicsrs';
            }
            return $order;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseConfigdata(?string $raw): array
    {
        if (empty($raw)) return [];

        // Try JSON first
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;

        // Fallback: unserialize (WHMCS < 7.3 stored serialized)
        if (strpos($raw, 'a:') === 0 || strpos($raw, 's:') === 0) {
            $data = @unserialize($raw);
            if (is_array($data)) return $data;
        }

        return [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  AJAX HANDLERS — Order Detail Actions
    // ═══════════════════════════════════════════════════════════════

    /**
     * Refresh order status from provider API
     *
     * This does a FULL sync — not just status, but also:
     * - Domain list (dcvList → domainInfo)
     * - DCV instructions (file, DNS, email)
     * - Certificate content (cert, CA bundle)
     * - Vendor IDs, dates, serial number
     * - Order domain field (top-level)
     *
     * Reference: NicSRS SyncService::syncSingleCertificate()
     */
    private function ajaxRefreshStatus(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata    = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug  = $order->provider_slug ?? $configdata['provider']
                         ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId      = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID — cannot refresh from provider'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $result   = $provider->getOrderStatus($remoteId);

            // ── Update timestamp ──
            $configdata['lastRefresh'] = date('Y-m-d H:i:s');
            $configdata['apiStatus']   = $result['status'] ?? '';

            // ── Ensure applyReturn exists ──
            if (!isset($configdata['applyReturn'])) {
                $configdata['applyReturn'] = [];
            }

            // ── Merge certificate data ──
            if (!empty($result['certificate'])) {
                $configdata['applyReturn']['certificate'] = $result['certificate'];
            }
            if (!empty($result['ca_bundle'])) {
                $configdata['applyReturn']['caCertificate'] = $result['ca_bundle'];
            }

            // ── Merge dates ──
            if (!empty($result['begin_date'])) {
                $configdata['applyReturn']['beginDate'] = $result['begin_date'];
            }
            if (!empty($result['end_date'])) {
                $configdata['applyReturn']['endDate'] = $result['end_date'];
            }

            // ── Merge IDs ──
            if (!empty($result['serial_number'])) {
                $configdata['applyReturn']['serialNumber'] = $result['serial_number'];
            }
            if (!empty($result['vendor_id'])) {
                $configdata['applyReturn']['vendorId'] = $result['vendor_id'];
            }
            if (!empty($result['vendor_cert_id'])) {
                $configdata['applyReturn']['vendorCertId'] = $result['vendor_cert_id'];
            }

            // ── Merge extra data (provider-specific: DCV, status tracking, etc.) ──
            $extra = $result['extra'] ?? [];
            if (!empty($extra) && is_array($extra)) {

                // DCV instructions
                foreach (['DCVfileName','DCVfileContent','DCVfilePath','DCVdnsHost','DCVdnsValue','DCVdnsType'] as $k) {
                    if (!empty($extra[$k])) {
                        $configdata['applyReturn'][$k] = $extra[$k];
                    }
                }

                // Status tracking (NicSRS: application, dcv, issued sub-objects)
                foreach (['application','dcv','issued'] as $k) {
                    if (!empty($extra[$k])) {
                        $configdata['applyReturn'][$k] = $extra[$k];
                    }
                }

                // Dates from extra
                foreach (['beginDate','endDate','dueDate','applyTime'] as $k) {
                    if (!empty($extra[$k])) {
                        $configdata['applyReturn'][$k] = $extra[$k];
                    }
                }

                // Domain list → update domainInfo + applyReturn.dcvList
                if (!empty($extra['dcvList']) && is_array($extra['dcvList'])) {
                    $configdata['applyReturn']['dcvList'] = $extra['dcvList'];

                    // Also update top-level domainInfo for template compatibility
                    $configdata['domainInfo'] = [];
                    foreach ($extra['dcvList'] as $dcv) {
                        $configdata['domainInfo'][] = [
                            'domainName' => $dcv['domainName'] ?? '',
                            'dcvMethod'  => $dcv['dcvMethod'] ?? '',
                            'dcvEmail'   => $dcv['dcvEmail'] ?? '',
                            'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                            'is_verify'  => $dcv['is_verify'] ?? '',
                        ];
                    }

                    // Also update configdata.domains (simplified format)
                    $configdata['domains'] = [];
                    foreach ($extra['dcvList'] as $dcv) {
                        $configdata['domains'][] = [
                            'domainName' => $dcv['domainName'] ?? '',
                            'dcvMethod'  => $dcv['dcvMethod'] ?? '',
                        ];
                    }
                }
            }

            // ── Map provider status → internal status ──
            $newStatus = $this->mapProviderStatus($result['status'] ?? '');

            // ── Build update data ──
            $updateData = [
                'status'     => $newStatus,
                'configdata' => json_encode($configdata),
            ];

            // Update domain on order record if we got new domain info
            $newDomain = '';
            if (!empty($configdata['domainInfo'][0]['domainName'])) {
                $newDomain = $configdata['domainInfo'][0]['domainName'];
            } elseif (!empty($result['domains'][0])) {
                $newDomain = $result['domains'][0];
            }
            if (!empty($newDomain)) {
                $updateData['domain'] = $newDomain;
            }

            // ── Save to database ──
            $this->updateOrderRecord($table, $id, $updateData);

            // ── Log ──
            ActivityLogger::log('refresh_status', 'order', (string)$id,
                "Status: {$newStatus} (API: " . ($result['status'] ?? 'N/A') . ')'
                . ($newDomain ? ", Domain: {$newDomain}" : ''));

            return [
                'success'     => true,
                'message'     => 'Status refreshed successfully',
                'status'      => $newStatus,
                'domain'      => $newDomain ?: ($order->domain ?? ''),
                'lastRefresh' => $configdata['lastRefresh'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()];
        }
    }

    /**
     * Edit order fields (local update only)
     */
    private function ajaxEditOrder(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        // Only allow editing AIO orders or direct table records
        $updateData = [];
        $changes    = [];

        // Status
        $newStatus = $this->input('status', '');
        if ($newStatus && $newStatus !== ($order->status ?? '')) {
            $changes['status'] = ['old' => $order->status, 'new' => $newStatus];
            $updateData['status'] = $newStatus;
        }

        // Remote ID
        $newRemoteId = $this->input('remote_id', '');
        if ($newRemoteId !== '' && $newRemoteId !== ($order->remoteid ?? '')) {
            $changes['remoteid'] = ['old' => $order->remoteid ?? '', 'new' => $newRemoteId];
            if ($table === 'mod_aio_ssl_orders') {
                $updateData['remote_id'] = $newRemoteId;
            } else {
                $updateData['remoteid'] = $newRemoteId;
            }
        }

        // Service ID
        $newServiceId = $this->input('service_id', '');
        if ($newServiceId !== '' && (int)$newServiceId !== (int)($order->serviceid ?? 0)) {
            // Validate service exists
            $svc = Capsule::table('tblhosting')->find((int)$newServiceId);
            if (!$svc) {
                return ['success' => false, 'message' => "Service #{$newServiceId} not found"];
            }
            $changes['serviceid'] = ['old' => $order->serviceid ?? 0, 'new' => (int)$newServiceId];
            if ($table === 'mod_aio_ssl_orders') {
                $updateData['service_id'] = (int)$newServiceId;
            } else {
                $updateData['serviceid'] = (int)$newServiceId;
            }
        }

        // Domain
        $newDomain = $this->input('domain', '');
        if ($newDomain !== '' && $newDomain !== ($order->domain ?? '')) {
            $changes['domain'] = ['old' => $order->domain ?? '', 'new' => $newDomain];
            $updateData['domain'] = $newDomain;
        }

        if (empty($updateData)) {
            return ['success' => true, 'message' => 'No changes to save'];
        }

        try {
            $this->updateOrderRecord($table, $id, $updateData);

            ActivityLogger::log('edit_order', 'order', (string)$id,
                json_encode(['changes' => $changes]));

            return ['success' => true, 'message' => 'Order updated', 'changes' => $changes];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * Download certificate in specified format
     */
    private function ajaxDownload(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');
        $format = $this->input('format', 'all');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $result   = $provider->downloadCertificate($remoteId);

            if (empty($result['success']) && empty($result['certificate'])) {
                return ['success' => false, 'message' => $result['message'] ?? 'Download failed'];
            }

            // Store certificate in configdata for future downloads
            if (!empty($result['certificate'])) {
                $configdata['applyReturn']['certificate'] = $result['certificate'];
            }
            if (!empty($result['ca_bundle'])) {
                $configdata['applyReturn']['caCertificate'] = $result['ca_bundle'];
            }
            if (!empty($result['intermediate'])) {
                $configdata['applyReturn']['caCertificate'] = $result['intermediate'];
            }
            $this->updateOrderRecord($table, $id, [
                'configdata' => json_encode($configdata),
            ]);

            ActivityLogger::log('download_cert', 'order', (string)$id, "Format: {$format}");

            $domain = $order->domain ?? 'certificate';

            return [
                'success'     => true,
                'cert'        => $result['certificate'] ?? '',
                'ca_bundle'   => $result['ca_bundle'] ?? $result['intermediate'] ?? '',
                'private_key' => $configdata['privateKey'] ?? '',
                'domain'      => $domain,
                'format'      => $format,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Download failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reissue certificate
     */
    private function ajaxReissue(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $caps = $provider->getCapabilities();
            if (!in_array('reissue', $caps)) {
                return ['success' => false, 'message' => 'Provider does not support reissue'];
            }

            $params = [
                'csr'        => $this->rawInput('csr', $configdata['csr'] ?? ''),
                'dcv_method' => $this->input('dcv_method', 'email'),
            ];

            $result = $provider->reissueCertificate($remoteId, $params);

            // Update status
            $configdata['lastRefresh'] = date('Y-m-d H:i:s');
            $this->updateOrderRecord($table, $id, [
                'status'     => 'Pending',
                'configdata' => json_encode($configdata),
            ]);

            ActivityLogger::log('reissue', 'order', (string)$id, 'Reissue request submitted');

            return [
                'success' => true,
                'message' => $result['message'] ?? 'Reissue request submitted',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Reissue failed: ' . $e->getMessage()];
        }
    }

    /**
     * Renew certificate
     * Note: TheSSLStore uses new order for renewal
     */
    private function ajaxRenew(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $caps = $provider->getCapabilities();
            if (!in_array('renew', $caps)) {
                return ['success' => false, 'message' => 'Provider does not support renewal'];
            }

            $params = [
                'csr'          => $configdata['csr'] ?? '',
                'product_code' => $order->certtype ?? $configdata['product_code'] ?? '',
                'period'       => (int)($configdata['period'] ?? 12),
            ];

            $result = $provider->renewCertificate($remoteId, $params);

            ActivityLogger::log('renew', 'order', (string)$id,
                'Renewal submitted. New order: ' . ($result['order_id'] ?? 'N/A'));

            return [
                'success'      => true,
                'message'      => $result['message'] ?? 'Renewal submitted',
                'new_order_id' => $result['order_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Renewal failed: ' . $e->getMessage()];
        }
    }

    /**
     * Resend DCV validation email
     */
    private function ajaxResendDcv(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');
        $domain = $this->input('domain', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $result   = $provider->resendDcvEmail($remoteId, $domain ?: '');

            ActivityLogger::log('resend_dcv', 'order', (string)$id,
                'Resent DCV' . ($domain ? " for {$domain}" : ''));

            return [
                'success' => !empty($result['success']),
                'message' => $result['message'] ?? 'DCV resent',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Resend DCV failed: ' . $e->getMessage()];
        }
    }

    /**
     * Change DCV method for a domain
     */
    private function ajaxChangeDcv(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');
        $domain = $this->input('domain', '');
        $method = $this->input('method', '');
        $email  = $this->input('email', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId) || empty($method)) {
            return ['success' => false, 'message' => 'Remote ID and method are required'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);

            $params = [
                'domain'   => $domain,
                'method'   => $method,
                'email'    => $email,
            ];

            $result = $provider->changeDcvMethod($remoteId, $params);

            // Update configdata with new DCV info if returned
            if (!empty($result['dcv_data'])) {
                $configdata['applyReturn'] = array_merge(
                    $configdata['applyReturn'] ?? [],
                    $result['dcv_data']
                );
                $this->updateOrderRecord($table, $id, [
                    'configdata' => json_encode($configdata),
                ]);
            }

            ActivityLogger::log('change_dcv', 'order', (string)$id,
                "Changed DCV for {$domain} to {$method}");

            return [
                'success' => !empty($result['success']),
                'message' => $result['message'] ?? 'DCV method changed',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Change DCV failed: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel order via provider API
     */
    private function ajaxCancel(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');
        $reason = $this->input('reason', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        // Check terminal status
        if (in_array(strtolower($order->status ?? ''), self::TERMINAL_STATUSES)) {
            return ['success' => false, 'message' => 'Order is already in terminal state'];
        }

        try {
            // Cancel on provider if remote ID exists
            if (!empty($remoteId)) {
                $provider = ProviderRegistry::get($providerSlug);
                $caps = $provider->getCapabilities();
                if (in_array('cancel', $caps)) {
                    $provider->cancelOrder($remoteId);
                }
            }

            // Update local status
            $this->updateOrderRecord($table, $id, ['status' => 'Cancelled']);

            ActivityLogger::log('cancel', 'order', (string)$id,
                'Order cancelled. Reason: ' . ($reason ?: 'N/A'));

            return ['success' => true, 'message' => 'Order cancelled'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Cancel failed: ' . $e->getMessage()];
        }
    }

    /**
     * Revoke certificate via provider API
     */
    private function ajaxRevoke(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');
        $reason = $this->input('reason', 'unspecified');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $provider->revokeCertificate($remoteId, $reason);

            $this->updateOrderRecord($table, $id, ['status' => 'Revoked']);

            ActivityLogger::log('revoke', 'order', (string)$id,
                'Certificate revoked. Reason: ' . $reason);

            return ['success' => true, 'message' => 'Certificate revoked'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Revoke failed: ' . $e->getMessage()];
        }
    }

    /**
     * Delete local order record (does NOT touch provider)
     */
    private function ajaxDeleteOrder(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        try {
            Capsule::table($table)->where('id', $id)->delete();

            ActivityLogger::log('delete_order', 'order', (string)$id,
                json_encode([
                    'table'    => $table,
                    'remoteid' => $order->remoteid ?? '',
                    'domain'   => $order->domain ?? '',
                    'status'   => $order->status ?? '',
                ]));

            return ['success' => true, 'message' => 'Order deleted locally'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get provider portal configuration link (SSL2Buy / limited tier)
     */
    private function ajaxConfigLink(): array
    {
        $id     = (int)$this->input('id');
        $source = $this->input('source', '');

        [$order, $table] = $this->fetchOrderMultiSource($id, $source);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $configdata   = $this->parseConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider']
                        ?? (self::MODULE_TO_SLUG[$order->module ?? ''] ?? '');
        $remoteId     = $order->remoteid ?? '';

        if (empty($remoteId)) {
            return ['success' => false, 'message' => 'No remote ID'];
        }

        try {
            $provider = ProviderRegistry::get($providerSlug);
            $caps = $provider->getCapabilities();

            if (!in_array('config_link', $caps)) {
                return ['success' => false, 'message' => 'Provider does not support config link'];
            }

            $url = $provider->getConfigurationLink($remoteId);

            return ['success' => true, 'url' => $url];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

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

    // ═══════════════════════════════════════════════════════════════
    //  SHARED HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Update record in the correct order table
     */
    private function updateOrderRecord(string $table, int $id, array $data): void
    {
        Capsule::table($table)->where('id', $id)->update($data);
    }

    /**
     * Map provider status string → internal status
     */
    private function mapProviderStatus(string $providerStatus): string
    {
        $map = [
            // NicSRS
            'active' => 'Complete', 'issued' => 'Complete', 'complete' => 'Complete',
            'pending' => 'Pending', 'processing' => 'Pending',
            'cancelled' => 'Cancelled', 'canceled' => 'Cancelled',
            'revoked' => 'Revoked', 'expired' => 'Expired',
            'rejected' => 'Cancelled',
            // GoGetSSL
            'active_ssl' => 'Complete', 'reissue' => 'Pending',
            'new_order' => 'Pending', 'cancelled_ssl' => 'Cancelled',
            // TheSSLStore
            'ACTIVE' => 'Complete', 'PENDING' => 'Pending',
            'CANCELLED' => 'Cancelled', 'REVOKED' => 'Revoked',
            'EXPIRED' => 'Expired', 'INITIAL' => 'Awaiting Configuration',
            // SSL2Buy
            'COMPLETED' => 'Complete', 'IN_PROGRESS' => 'Pending',
        ];

        return $map[$providerStatus] ?? $map[strtolower($providerStatus)] ?? 'Pending';
    }
}    