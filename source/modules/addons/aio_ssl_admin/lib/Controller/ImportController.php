<?php
/**
 * Import Controller — Single Import, Bulk API Import, CSV Import, Legacy Migration
 *
 * Handles:
 *   - Single cert import via provider API (all 4 providers)
 *   - Bulk import via provider order-list APIs (GoGetSSL, TheSSLStore, SSL2Buy)
 *   - CSV upload import (all providers)
 *   - Legacy migration claim (non-destructive)
 *   - Import history tracking
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

class ImportController extends BaseController
{
    /** Module → provider slug mapping */
    private const MODULE_TO_SLUG = [
        'nicsrs_ssl'        => 'nicsrs',
        'SSLCENTERWHMCS'    => 'gogetssl',
        'thesslstore_ssl'   => 'thesslstore',
        'thesslstorefullv2' => 'thesslstore',
        'ssl2buy'           => 'ssl2buy',
    ];

    /** Provider slug → display name */
    private const PROVIDER_NAMES = [
        'nicsrs'      => 'NicSRS',
        'gogetssl'    => 'GoGetSSL',
        'thesslstore' => 'TheSSLStore',
        'ssl2buy'     => 'SSL2Buy',
    ];

    /** Legacy tblsslorders module names */
    private const LEGACY_TBLSSL_MODULES = [
        'SSLCENTERWHMCS',
        'thesslstore_ssl',
        'thesslstorefullv2',
        'thesslstore',
        'ssl2buy',
    ];

    // ─── Routing ───────────────────────────────────────────────────

    public function render(string $action = ''): void
    {
        // Gather data for all tabs
        $enabledProviders = $this->getEnabledProviders();
        $legacyStats      = $this->getLegacyStats();
        $importHistory    = $this->getImportHistory(20);
        $summaryStats     = $this->getSummaryStats();

        $this->renderTemplate('import.php', [
            'enabledProviders' => $enabledProviders,
            'legacyStats'      => $legacyStats,
            'importHistory'    => $importHistory,
            'summary'          => $summaryStats,
        ]);
    }

    /**
     * Handle AJAX requests — MUST override BaseController default
     *
     * BaseController returns ['success'=>false,'message'=>'Not implemented']
     * so this override is REQUIRED for all AJAX actions to work.
     *
     * @param string $action From URL param &action=xxx
     * @return array
     */
    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            // Single Import
            case 'lookup':         return $this->lookupCertificate();
            case 'single':         return $this->importSingle();

            // Bulk API Import
            case 'fetch_orders':   return $this->fetchProviderOrders();
            case 'bulk_api':       return $this->bulkApiImport();

            // CSV Import
            case 'bulk_csv':       return $this->bulkCsvImport();
            case 'csv_template':   return $this->downloadCsvTemplate();

            // Legacy Migration
            case 'claim':          return $this->claimSingle();
            case 'claim_provider': return $this->claimByProvider();
            case 'claim_all':      return $this->claimAll();

            // History
            case 'history':        return $this->getHistoryAjax();

            default:
                return ['success' => false, 'message' => 'Unknown action: ' . $action];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  SINGLE IMPORT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lookup certificate from provider API (preview before import)
     *
     * @return array
     */
    private function lookupCertificate(): array
    {
        $slug     = $this->input('provider', '');
        $remoteId = trim($this->input('remote_id', ''));

        if (empty($slug) || empty($remoteId)) {
            return ['success' => false, 'message' => 'Provider and Remote ID are required.'];
        }

        // Check if already imported in AIO
        // NOTE: mod_aio_ssl_orders uses `remote_id` (with underscore)
        $existing = Capsule::table('mod_aio_ssl_orders')
            ->where('remote_id', $remoteId)
            ->where('provider_slug', $slug)
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => "Already imported as AIO Order #{$existing->id}.",
                'existing_id' => $existing->id,
            ];
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $status   = $provider->getOrderStatus($remoteId);

            return [
                'success'     => true,
                'certificate' => [
                    'remote_id'      => $remoteId,
                    'provider'       => $slug,
                    'provider_name'  => self::PROVIDER_NAMES[$slug] ?? ucfirst($slug),
                    'status'         => $status['status'] ?? 'Unknown',
                    'domains'        => $status['domains'] ?? [],
                    'begin_date'     => $status['begin_date'] ?? null,
                    'end_date'       => $status['end_date'] ?? null,
                    'serial_number'  => $status['serial_number'] ?? null,
                    'has_cert'       => !empty($status['certificate']),
                    'product_type'   => $status['extra']['productType']
                                        ?? $status['extra']['ProductName']
                                        ?? $status['extra']['product_name']
                                        ?? 'Unknown',
                    'extra'          => $status['extra'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Lookup failed: ' . $e->getMessage()];
        }
    }

    /**
     * Import single certificate → create mod_aio_ssl_orders record
     *
     * @return array
     */
    private function importSingle(): array
    {
        $slug      = $this->input('provider', '');
        $remoteId  = trim($this->input('remote_id', ''));
        $serviceId = (int)$this->input('service_id', 0);

        if (empty($slug) || empty($remoteId)) {
            return ['success' => false, 'message' => 'Provider and Remote ID are required.'];
        }

        // Dedup check
        // NOTE: mod_aio_ssl_orders uses `remote_id` (with underscore)
        $existing = Capsule::table('mod_aio_ssl_orders')
            ->where('remote_id', $remoteId)
            ->where('provider_slug', $slug)
            ->first();

        if ($existing) {
            return ['success' => false, 'message' => "Already imported as AIO Order #{$existing->id}."];
        }

        // Validate service_id if provided
        if ($serviceId > 0) {
            $validationResult = $this->validateServiceId($serviceId);
            if (!$validationResult['valid']) {
                return ['success' => false, 'message' => $validationResult['message']];
            }
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $status   = $provider->getOrderStatus($remoteId);
            $orderId  = $this->createAioOrder($slug, $remoteId, $status, $serviceId);

            $this->logImport('api_single', $slug, 1, 1, 0, ['remote_id' => $remoteId]);

            ActivityLogger::log('import_single', 'order', $orderId, json_encode([
                'provider'  => $slug,
                'remote_id' => $remoteId,
                'status'    => $status['status'] ?? 'Unknown',
            ]));

            return [
                'success'  => true,
                'message'  => 'Certificate imported successfully.',
                'order_id' => $orderId,
            ];
        } catch (\Exception $e) {
            $this->logImport('api_single', $slug, 1, 0, 1, [
                'remote_id' => $remoteId,
                'error'     => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  BULK API IMPORT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fetch orders from provider API (paginated)
     *
     * Supported: GoGetSSL (getAllSSLOrders), TheSSLStore (queryOrder), SSL2Buy (getOrderList)
     * NOT supported: NicSRS (no list endpoint)
     *
     * @return array
     */
    private function fetchProviderOrders(): array
    {
        $slug   = $this->input('provider', '');
        $page   = max(1, (int)$this->input('page', 1));
        $status = $this->input('status_filter', '');

        if (empty($slug)) {
            return ['success' => false, 'message' => 'Provider is required.'];
        }

        if ($slug === 'nicsrs') {
            return ['success' => false, 'message' => 'NicSRS API does not support listing orders. Use Single Import or CSV Import.'];
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $result   = $this->fetchOrdersByProvider($provider, $slug, $page, $status);

            // Check which remote IDs are already imported
            $remoteIds = array_column($result['orders'], 'remote_id');
            $imported  = $this->getAlreadyImportedIds($slug, $remoteIds);

            // Mark each order
            foreach ($result['orders'] as &$order) {
                $order['already_imported'] = in_array($order['remote_id'], $imported);
            }
            unset($order);

            return [
                'success'     => true,
                'orders'      => $result['orders'],
                'total'       => $result['total'] ?? count($result['orders']),
                'page'        => $page,
                'total_pages' => $result['total_pages'] ?? 1,
                'provider'    => $slug,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fetch failed: ' . $e->getMessage()];
        }
    }

    /**
     * Dispatch to provider-specific order listing method
     */
    private function fetchOrdersByProvider($provider, string $slug, int $page, string $statusFilter): array
    {
        $pageSize = 50;

        switch ($slug) {
            case 'gogetssl':
                return $this->fetchGoGetSSLOrders($provider, $page, $pageSize);

            case 'thesslstore':
                return $this->fetchTheSSLStoreOrders($provider, $page, $pageSize);

            case 'ssl2buy':
                return $this->fetchSSL2BuyOrders($provider, $page, $pageSize);

            default:
                throw new \RuntimeException("Bulk fetch not supported for provider: {$slug}");
        }
    }

    /**
     * GoGetSSL: GET /orders/ssl/all with limit+offset
     */
    private function fetchGoGetSSLOrders($provider, int $page, int $pageSize): array
    {
        $offset = ($page - 1) * $pageSize;
        $result = $provider->getAllSSLOrders($pageSize, $offset);

        $orders = [];
        foreach ($result['orders'] ?? [] as $o) {
            $orders[] = [
                'remote_id'    => (string)($o['order_id'] ?? $o['id'] ?? ''),
                'domain'       => $o['domain'] ?? $o['common_name'] ?? '',
                'product'      => $o['product_name'] ?? $o['product'] ?? '',
                'status'       => $o['status'] ?? 'unknown',
                'begin_date'   => $o['valid_from'] ?? $o['begin'] ?? '',
                'end_date'     => $o['valid_till'] ?? $o['expires'] ?? $o['end'] ?? '',
                'order_date'   => $o['created'] ?? '',
            ];
        }

        $total = $result['count'] ?? count($orders);

        return [
            'orders'      => $orders,
            'total'       => $total,
            'total_pages' => (int)ceil($total / $pageSize),
        ];
    }

    /**
     * TheSSLStore: POST /order/query with PageNumber+PageSize
     */
    private function fetchTheSSLStoreOrders($provider, int $page, int $pageSize): array
    {
        $result = $provider->queryOrder([
            'page'      => $page - 1, // TheSSLStore uses 0-based page
            'page_size' => $pageSize,
        ]);

        $orders = [];
        foreach ($result as $o) {
            $orders[] = [
                'remote_id'    => (string)($o['order_id'] ?? ''),
                'domain'       => $o['common_name'] ?? '',
                'product'      => $o['product_name'] ?? '',
                'status'       => $o['status'] ?? 'unknown',
                'begin_date'   => $o['begin_date'] ?? '',
                'end_date'     => $o['end_date'] ?? '',
                'order_date'   => $o['purchase_date'] ?? '',
            ];
        }

        return [
            'orders'      => $orders,
            'total'       => count($orders), // TheSSLStore doesn't return total in queryOrder
            'total_pages' => count($orders) < $pageSize ? $page : $page + 1,
        ];
    }

    /**
     * SSL2Buy: POST /getorderlist with PageNo+PageSize (max 50)
     */
    private function fetchSSL2BuyOrders($provider, int $page, int $pageSize): array
    {
        $pageSize = min($pageSize, 50); // API hard limit
        $result   = $provider->getOrderList($page, $pageSize);

        $orders = [];
        foreach ($result['orders'] ?? [] as $o) {
            $orders[] = [
                'remote_id'    => (string)($o['order_number'] ?? ''),
                'domain'       => $o['domain_name'] ?? '',
                'product'      => $o['product_name'] ?? '',
                'status'       => $o['order_status'] ?? 'unknown',
                'begin_date'   => '',
                'end_date'     => $o['expire_on'] ?? '',
                'order_date'   => $o['order_date'] ?? '',
            ];
        }

        $total = $result['total_orders'] ?? count($orders);
        $totalPages = $result['total_pages'] ?? (int)ceil($total / $pageSize);

        return [
            'orders'      => $orders,
            'total'       => $total,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Bulk import selected orders from provider API
     *
     * @return array
     */
    private function bulkApiImport(): array
    {
        $slug      = $this->input('provider', '');
        $remoteIds = $this->input('remote_ids', []);

        if (empty($slug) || empty($remoteIds)) {
            return ['success' => false, 'message' => 'Provider and at least one Remote ID are required.'];
        }

        if (!is_array($remoteIds)) {
            $remoteIds = explode(',', $remoteIds);
        }
        $remoteIds = array_filter(array_map('trim', $remoteIds));

        $imported = 0;
        $failed   = 0;
        $errors   = [];

        try {
            $provider = ProviderRegistry::get($slug);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Provider error: ' . $e->getMessage()];
        }

        // Check already imported
        $alreadyImported = $this->getAlreadyImportedIds($slug, $remoteIds);

        foreach ($remoteIds as $remoteId) {
            if (in_array($remoteId, $alreadyImported)) {
                $errors[] = "#{$remoteId}: Already imported";
                $failed++;
                continue;
            }

            try {
                $status  = $provider->getOrderStatus($remoteId);
                $this->createAioOrder($slug, $remoteId, $status, 0);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "#{$remoteId}: " . $e->getMessage();
                $failed++;
            }
        }

        $total = count($remoteIds);
        $this->logImport('api_bulk', $slug, $total, $imported, $failed, ['errors' => $errors]);

        ActivityLogger::log('import_bulk_api', 'import', null, json_encode([
            'provider' => $slug,
            'total'    => $total,
            'imported' => $imported,
            'failed'   => $failed,
        ]));

        return [
            'success'  => true,
            'message'  => "Bulk import: {$imported}/{$total} imported" . ($failed > 0 ? ", {$failed} failed" : ''),
            'imported' => $imported,
            'failed'   => $failed,
            'total'    => $total,
            'errors'   => $errors,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  CSV IMPORT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Bulk CSV import: parse CSV → fetch from each provider → create records
     *
     * @return array
     */
    private function bulkCsvImport(): array
    {
        if (empty($_FILES['csv_file']['tmp_name'])) {
            return ['success' => false, 'message' => 'No CSV file uploaded.'];
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $rows = $this->parseCsvFile($file);

        if (empty($rows)) {
            return ['success' => false, 'message' => 'CSV file is empty or invalid format.'];
        }

        if (count($rows) > 500) {
            return ['success' => false, 'message' => 'Max 500 rows per batch. Found ' . count($rows) . ' rows.'];
        }

        $imported = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $rowNum    = $i + 2; // +2 for 1-based + header row
            $slug      = trim($row['provider'] ?? '');
            $remoteId  = trim($row['remote_id'] ?? '');
            $serviceId = (int)trim($row['service_id'] ?? 0);

            if (empty($slug) || empty($remoteId)) {
                $errors[] = "Row {$rowNum}: Missing provider or remote_id";
                $failed++;
                continue;
            }

            // Dedup check
            $existing = Capsule::table('mod_aio_ssl_orders')
                ->where('remote_id', $remoteId)
                ->where('provider_slug', $slug)
                ->first();

            if ($existing) {
                $errors[] = "Row {$rowNum}: #{$remoteId} already imported (Order #{$existing->id})";
                $failed++;
                continue;
            }

            try {
                $provider = ProviderRegistry::get($slug);
                $status   = $provider->getOrderStatus($remoteId);
                $this->createAioOrder($slug, $remoteId, $status, $serviceId);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: #{$remoteId} — " . $e->getMessage();
                $failed++;
            }
        }

        $total = count($rows);
        $this->logImport('csv', 'csv_upload', $total, $imported, $failed, ['errors' => $errors]);

        ActivityLogger::log('import_csv', 'import', null, json_encode([
            'total' => $total, 'imported' => $imported, 'failed' => $failed,
        ]));

        return [
            'success'  => $imported > 0,
            'message'  => "CSV import: {$imported}/{$total} imported" . ($failed > 0 ? ", {$failed} failed" : ''),
            'imported' => $imported,
            'failed'   => $failed,
            'total'    => $total,
            'errors'   => array_slice($errors, 0, 20), // Limit error output
        ];
    }

    /**
     * Return CSV template content for download
     *
     * @return array
     */
    private function downloadCsvTemplate(): array
    {
        $csv = "provider,remote_id,service_id\n";
        $csv .= "nicsrs,123456,42\n";
        $csv .= "gogetssl,789012,\n";
        $csv .= "thesslstore,ABC-123,15\n";
        $csv .= "ssl2buy,ORD-456,\n";

        return [
            'success'  => true,
            'csv'      => $csv,
            'filename' => 'aio_ssl_import_template.csv',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  LEGACY MIGRATION (CLAIM)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Claim a single legacy order
     *
     * @return array
     */
    private function claimSingle(): array
    {
        $id          = (int)$this->input('id');
        $sourceTable = $this->input('source_table', '');

        if ($id < 1 || empty($sourceTable)) {
            return ['success' => false, 'message' => 'Order ID and source table required.'];
        }

        $migration = new MigrationService();
        $result = $migration->claimOrder($id, $sourceTable);

        if ($result['success'] ?? false) {
            $this->logImport('migration', $sourceTable, 1, 1, 0, ['legacy_id' => $id]);
        }

        return $result;
    }

    /**
     * Claim all unclaimed orders for a specific provider/module
     *
     * @return array
     */
    private function claimByProvider(): array
    {
        $provider = $this->input('provider', '');
        if (empty($provider)) {
            return ['success' => false, 'message' => 'Provider is required.'];
        }

        $migration = new MigrationService();
        $result    = $this->processClaimBatch($migration, $provider);

        $this->logImport('migration', $provider, $result['total'], $result['claimed'], $result['failed'], []);

        return $result;
    }

    /**
     * Claim ALL unclaimed legacy orders across all modules
     *
     * @return array
     */
    private function claimAll(): array
    {
        $migration   = new MigrationService();
        $totalClaimed = 0;
        $totalFailed  = 0;
        $totalCount   = 0;
        $details      = [];

        // Process NicSRS
        if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
            $r = $this->processClaimBatch($migration, 'nicsrs');
            $totalClaimed += $r['claimed'];
            $totalFailed  += $r['failed'];
            $totalCount   += $r['total'];
            $details['nicsrs'] = $r;
        }

        // Process tblsslorders modules
        foreach (['gogetssl', 'thesslstore', 'ssl2buy'] as $slug) {
            $r = $this->processClaimBatch($migration, $slug);
            $totalClaimed += $r['claimed'];
            $totalFailed  += $r['failed'];
            $totalCount   += $r['total'];
            $details[$slug] = $r;
        }

        $this->logImport('migration_all', 'all', $totalCount, $totalClaimed, $totalFailed, []);

        ActivityLogger::log('import_claim_all', 'import', null, json_encode([
            'claimed' => $totalClaimed, 'failed' => $totalFailed, 'total' => $totalCount,
        ]));

        return [
            'success' => true,
            'message' => "Claimed {$totalClaimed} of {$totalCount} orders" . ($totalFailed > 0 ? " ({$totalFailed} failed)" : ''),
            'claimed' => $totalClaimed,
            'failed'  => $totalFailed,
            'total'   => $totalCount,
            'details' => $details,
        ];
    }

    /**
     * Process claim batch for a specific provider slug
     */
    private function processClaimBatch(MigrationService $migration, string $slug): array
    {
        $claimed = 0;
        $failed  = 0;

        // Get existing claimed IDs to skip
        $claimedKeys = $this->getClaimedLegacyKeys();

        if ($slug === 'nicsrs') {
            if (!Capsule::schema()->hasTable('nicsrs_sslorders')) {
                return ['claimed' => 0, 'failed' => 0, 'total' => 0];
            }

            $orders = Capsule::table('nicsrs_sslorders')->get();
            foreach ($orders as $order) {
                $key = 'nicsrs_sslorders:' . $order->id;
                if (isset($claimedKeys[$key])) continue;

                try {
                    $result = $migration->claimOrder($order->id, 'nicsrs_sslorders');
                    if ($result['success'] ?? false) {
                        $claimed++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }
            }

            return ['claimed' => $claimed, 'failed' => $failed, 'total' => $claimed + $failed];
        }

        // tblsslorders providers
        $moduleNames = [];
        switch ($slug) {
            case 'gogetssl':    $moduleNames = ['SSLCENTERWHMCS']; break;
            case 'thesslstore': $moduleNames = ['thesslstore_ssl', 'thesslstorefullv2', 'thesslstore']; break;
            case 'ssl2buy':     $moduleNames = ['ssl2buy']; break;
        }

        if (empty($moduleNames) || !Capsule::schema()->hasTable('tblsslorders')) {
            return ['claimed' => 0, 'failed' => 0, 'total' => 0];
        }

        $orders = Capsule::table('tblsslorders')
            ->whereIn('module', $moduleNames)
            ->get();

        foreach ($orders as $order) {
            $key = 'tblsslorders:' . $order->id;
            if (isset($claimedKeys[$key])) continue;

            try {
                $result = $migration->claimOrder($order->id, 'tblsslorders');
                if ($result['success'] ?? false) {
                    $claimed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return ['claimed' => $claimed, 'failed' => $failed, 'total' => $claimed + $failed];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HISTORY
    // ═══════════════════════════════════════════════════════════════

    private function getHistoryAjax(): array
    {
        $page  = max(1, (int)$this->input('page', 1));
        $limit = 20;

        $history = $this->getImportHistory($limit, ($page - 1) * $limit);
        $total   = Capsule::table('mod_aio_ssl_import_logs')->count();

        return [
            'success'     => true,
            'history'     => $history,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create a new AIO order record from provider data
     *
     * IMPORTANT: mod_aio_ssl_orders column names use underscores:
     *   remote_id (NOT remoteid), service_id (NOT serviceid)
     *   NO `module` column exists in this table.
     */
    private function createAioOrder(string $slug, string $remoteId, array $status, int $serviceId = 0): int
    {
        // Extract domain from status
        $domains = $status['domains'] ?? [];
        $domain  = !empty($domains) ? $domains[0] : '';

        // Extract cert type
        $certType = $status['extra']['productType']
                    ?? $status['extra']['ProductName']
                    ?? $status['extra']['product_name']
                    ?? 'imported';

        // Normalize status for AIO
        $normalizedStatus = $this->normalizeImportStatus($status['status'] ?? 'Unknown');

        // Build configdata
        $configdata = [
            'provider'       => $slug,
            'imported'       => true,
            'imported_at'    => date('Y-m-d H:i:s'),
            'imported_by'    => $_SESSION['adminid'] ?? 0,
            'import_source'  => 'api',
            'domains'        => $domains,
            'begin_date'     => $status['begin_date'] ?? null,
            'end_date'       => $status['end_date'] ?? null,
            'serial_number'  => $status['serial_number'] ?? null,
            'csr'            => $status['extra']['csr_code'] ?? $status['extra']['csr'] ?? null,
            'cert'           => $status['certificate'] ?? null,
            'ca_bundle'      => $status['ca_bundle'] ?? null,
        ];

        // Resolve userid from service if linked
        $userId = 0;
        if ($serviceId > 0) {
            $service = Capsule::table('tblhosting')->find($serviceId);
            if ($service) {
                $userId = $service->userid ?? 0;
                if (empty($domain) && !empty($service->domain)) {
                    $domain = $service->domain;
                }
            }
        }

        // mod_aio_ssl_orders schema: NO `module` column.
        // Columns: userid, service_id, provider_slug, remote_id, canonical_id,
        //          product_code, domain, certtype, status, configdata,
        //          completiondate, begin_date, end_date,
        //          legacy_table, legacy_order_id, legacy_module,
        //          created_at, updated_at
        $orderId = Capsule::table('mod_aio_ssl_orders')->insertGetId([
            'userid'        => $userId,
            'service_id'    => $serviceId ?: 0,
            'remote_id'     => $remoteId,
            'provider_slug' => $slug,
            'certtype'      => $certType,
            'product_code'  => $certType,
            'domain'        => $domain ?: '',
            'status'        => $normalizedStatus,
            'begin_date'    => $status['begin_date'] ?? null,
            'end_date'      => $status['end_date'] ?? null,
            'configdata'    => json_encode($configdata, JSON_UNESCAPED_UNICODE),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return $orderId;
    }

    /**
     * Normalize status string for AIO orders table
     * Different providers return different status strings
     */
    private function normalizeImportStatus(string $status): string
    {
        $map = [
            // NicSRS
            'complete'                => 'Active',
            'COMPLETE'                => 'Active',
            'issued'                  => 'Active',
            'active'                  => 'Active',
            'Active'                  => 'Active',

            // Pending variants
            'pending'                 => 'Pending',
            'processing'              => 'Processing',
            'Pending'                 => 'Pending',
            'Processing'              => 'Processing',

            // Awaiting
            'awaiting configuration'  => 'Awaiting Configuration',
            'Awaiting Configuration'  => 'Awaiting Configuration',
            'new'                     => 'Awaiting Configuration',

            // Terminal
            'cancelled'               => 'Cancelled',
            'canceled'                => 'Cancelled',
            'Cancelled'               => 'Cancelled',
            'expired'                 => 'Expired',
            'Expired'                 => 'Expired',
            'revoked'                 => 'Revoked',
            'Revoked'                 => 'Revoked',
            'rejected'                => 'Cancelled',
        ];

        return $map[$status] ?? ucfirst(strtolower($status));
    }

    /**
     * Validate that service_id exists and uses servertype=aio_ssl
     */
    private function validateServiceId(int $serviceId): array
    {
        $service = Capsule::table('tblhosting')->find($serviceId);

        if (!$service) {
            return ['valid' => false, 'message' => "Service #{$serviceId} not found."];
        }

        // Check server type
        $product = Capsule::table('tblproducts')->find($service->packageid);
        if ($product && $product->servertype !== 'aio_ssl') {
            return [
                'valid'   => false,
                'message' => "Service #{$serviceId} uses servertype '{$product->servertype}', expected 'aio_ssl'.",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get remote IDs that are already imported for a given provider
     * NOTE: mod_aio_ssl_orders column is `remote_id` (with underscore)
     */
    private function getAlreadyImportedIds(string $slug, array $remoteIds): array
    {
        if (empty($remoteIds)) return [];

        return Capsule::table('mod_aio_ssl_orders')
            ->where('provider_slug', $slug)
            ->whereIn('remote_id', $remoteIds)
            ->pluck('remote_id')
            ->toArray();
    }

    /**
     * Get set of claimed legacy keys: "table:id"
     */
    private function getClaimedLegacyKeys(): array
    {
        $keys = [];
        try {
            $claimed = Capsule::table('mod_aio_ssl_orders')
                ->whereNotNull('legacy_table')
                ->select(['legacy_table', 'legacy_order_id'])
                ->get();

            foreach ($claimed as $row) {
                $keys[$row->legacy_table . ':' . $row->legacy_order_id] = true;
            }
        } catch (\Exception $e) {}

        return $keys;
    }

    /**
     * Get enabled providers for template
     */
    private function getEnabledProviders(): array
    {
        $providers = [];
        try {
            $records = ProviderRegistry::getAllRecords(true);
            foreach ($records as $p) {
                $providers[] = [
                    'slug'       => $p->slug,
                    'name'       => $p->name ?? self::PROVIDER_NAMES[$p->slug] ?? ucfirst($p->slug),
                    'tier'       => $p->tier ?? 'full',
                    'has_list'   => in_array($p->slug, ['gogetssl', 'thesslstore', 'ssl2buy']),
                ];
            }
        } catch (\Exception $e) {}

        return $providers;
    }

    /**
     * Get legacy module statistics for migration tab
     */
    private function getLegacyStats(): array
    {
        $stats = [];
        $claimedKeys = $this->getClaimedLegacyKeys();

        // NicSRS
        if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
            $total = Capsule::table('nicsrs_sslorders')->count();
            $claimed = 0;
            foreach (Capsule::table('nicsrs_sslorders')->select('id')->get() as $o) {
                if (isset($claimedKeys['nicsrs_sslorders:' . $o->id])) $claimed++;
            }
            $stats[] = [
                'module'      => 'nicsrs_ssl',
                'name'        => 'NicSRS SSL',
                'slug'        => 'nicsrs',
                'table'       => 'nicsrs_sslorders',
                'total'       => $total,
                'claimed'     => $claimed,
                'remaining'   => $total - $claimed,
                'detected'    => true,
            ];
        }

        // tblsslorders modules — group by provider slug
        if (Capsule::schema()->hasTable('tblsslorders')) {
            $providerModules = [
                'gogetssl'    => ['modules' => ['SSLCENTERWHMCS'],                                     'name' => 'GoGetSSL (SSLCENTER)'],
                'thesslstore' => ['modules' => ['thesslstore_ssl', 'thesslstorefullv2', 'thesslstore'], 'name' => 'TheSSLStore SSL'],
                'ssl2buy'     => ['modules' => ['ssl2buy'],                                             'name' => 'SSL2Buy'],
            ];

            foreach ($providerModules as $slug => $info) {
                $total = Capsule::table('tblsslorders')
                    ->whereIn('module', $info['modules'])
                    ->count();

                if ($total === 0) continue;

                $claimed = 0;
                foreach (Capsule::table('tblsslorders')->whereIn('module', $info['modules'])->select('id')->get() as $o) {
                    if (isset($claimedKeys['tblsslorders:' . $o->id])) $claimed++;
                }

                $stats[] = [
                    'module'    => implode(', ', $info['modules']),
                    'name'      => $info['name'],
                    'slug'      => $slug,
                    'table'     => 'tblsslorders',
                    'total'     => $total,
                    'claimed'   => $claimed,
                    'remaining' => $total - $claimed,
                    'detected'  => true,
                ];
            }
        }

        return $stats;
    }

    /**
     * Summary stats for page header
     */
    private function getSummaryStats(): array
    {
        $totalImported = Capsule::table('mod_aio_ssl_orders')
            ->whereNotNull('remote_id')
            ->where('remote_id', '!=', '')
            ->count();

        $totalRemaining = 0;
        foreach ($this->getLegacyStats() as $s) {
            $totalRemaining += $s['remaining'];
        }

        return [
            'total_imported'  => $totalImported,
            'total_remaining' => $totalRemaining,
        ];
    }

    /**
     * Get import history from log table
     */
    private function getImportHistory(int $limit = 20, int $offset = 0): array
    {
        try {
            return Capsule::table('mod_aio_ssl_import_logs')
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    $row->details = json_decode($row->details ?? '{}', true) ?: [];
                    return $row;
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Log import operation
     */
    private function logImport(string $type, string $source, int $total, int $success, int $failed, array $details = []): void
    {
        try {
            Capsule::table('mod_aio_ssl_import_logs')->insert([
                'type'       => $type,
                'source'     => $source,
                'total'      => $total,
                'success'    => $success,
                'failed'     => $failed,
                'details'    => json_encode($details, JSON_UNESCAPED_UNICODE),
                'admin_id'   => $_SESSION['adminid'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silent fail — don't break import for logging error
        }
    }

    /**
     * Parse CSV file into array of rows
     */
    private function parseCsvFile(string $filePath): array
    {
        $rows   = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [];
        }

        // Normalize headers
        $header = array_map(function ($h) {
            return strtolower(trim($h));
        }, $header);

        // Validate required columns
        if (!in_array('provider', $header) || !in_array('remote_id', $header)) {
            fclose($handle);
            return [];
        }

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 2) continue;

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}