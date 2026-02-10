<?php
/**
 * NicSRS SSL Module - Page Controller
 * Handles page rendering based on certificate status
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use Exception;
use WHMCS\Database\Capsule;

class PageController
{
    /**
     * Route to appropriate page based on order status
     */
    public static function index(array $params): array
    {
        try {
            $order = OrderRepository::getByServiceId($params['serviceid']);

        // === DEBUG START ===
        logModuleCall('nicsrs_ssl', 'DEBUG_index', [
            'serviceid' => $params['serviceid'],
            'has_nicsrs_order' => $order ? 'YES (id=' . $order->id . ')' : 'NO',
        ], 'Step 1: Check nicsrs_sslorders');
        // === DEBUG END ===

        if (!$order) {
            $vendorCert = self::checkVendorMigration($params);
            
            // === DEBUG START ===
            logModuleCall('nicsrs_ssl', 'DEBUG_vendorCheck', [
                'serviceid' => $params['serviceid'],
                'vendor_cert_found' => $vendorCert ? 'YES (id=' . ($vendorCert->id ?? 'N/A') . ', remoteid=' . ($vendorCert->remoteid ?? '') . ')' : 'NO/NULL',
            ], 'Step 2: Check tblsslorders');
            // === DEBUG END ===
            
            if ($vendorCert) {
                $cert = self::getCertConfig($params);
                return TemplateHelper::migrated($params, $vendorCert, $cert);
            }
                // No active vendor cert → create new NicSRS order (normal flow)
                OrderRepository::create([
                    'userid' => $params['userid'],
                    'serviceid' => $params['serviceid'],
                    'certtype' => $params['configoption1'] ?? '',
                    'status' => SSL_STATUS_AWAITING,
                ]);
                $order = OrderRepository::getByServiceId($params['serviceid']);
            }

            $cert = self::getCertConfig($params);

            // Normalize status to handle case-sensitivity
            $status = self::normalizeStatus($order->status);

            // Route based on normalized status
            switch ($status) {
                case 'awaiting':
                case 'draft':
                    return self::renderApplyCert($params, $order, $cert);

                case 'pending':
                case 'processing':
                case 'reissue':
                    return self::renderPending($params, $order, $cert);

                case 'complete':
                case 'issued':
                case 'active':
                    return self::renderComplete($params, $order, $cert);

                case 'cancelled':
                case 'canceled':
                case 'revoked':
                case 'expired':
                case 'suspended':
                case 'terminated':
                    return self::renderCancelled($params, $order, $cert);

                default:
                    // Check if has remoteid (already submitted)
                    if (!empty($order->remoteid)) {
                        return self::renderPending($params, $order, $cert);
                    }
                    return self::renderApplyCert($params, $order, $cert);
            }
        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'PageController::index', $params, $e->getMessage());
            return TemplateHelper::error($params, $e->getMessage());
        }
    }

    /**
     * Check if service has an active certificate from another vendor
     * 
     * Queries WHMCS core `tblsslorders` table to detect certificates
     * issued by other modules (e.g. cPanel SSL, GoGetSSL, TheSSLStore).
     * Returns the vendor order if cert is still active, null otherwise.
     * 
     * When null is returned, the caller should proceed with normal
     * NicSRS order creation flow.
     * 
     * Skip conditions (return null → allow new order):
     *   - tblsslorders table doesn't exist
     *   - No record for this serviceid
     *   - endDate found in configdata and already passed
     * 
     * @param array $params WHMCS module params
     * @return object|null Vendor SSL order from tblsslorders, or null
     */
    private static function checkVendorMigration(array $params): ?object
    {
        try {
            if (!Capsule::schema()->hasTable('tblsslorders')) {
                return null;
            }

            $vendorOrder = Capsule::table('tblsslorders')
                ->where('serviceid', $params['serviceid'])
                ->first();

            // No record → no vendor cert
            if (!$vendorOrder) {
                return null;
            }

            // If status is explicitly inactive → allow new order
            $status = strtolower(trim($vendorOrder->status ?? ''));
            $inactiveStatuses = [
                'cancelled', 'canceled', 'revoked', 'expired',
                'refunded', 'fraud', 'terminated',
            ];
            if ($status && in_array($status, $inactiveStatuses)) {
                logModuleCall('nicsrs_ssl', 'checkVendorMigration', [
                    'serviceid' => $params['serviceid'],
                    'vendor_status' => $status,
                ], 'Vendor cert inactive, allowing new order');
                return null;
            }

            // Check expiry from configdata
            $endDate = self::extractVendorEndDate($vendorOrder);
            if ($endDate && strtotime($endDate) && strtotime($endDate) < time()) {
                logModuleCall('nicsrs_ssl', 'checkVendorMigration', [
                    'serviceid' => $params['serviceid'],
                    'endDate' => $endDate,
                ], 'Vendor cert expired, allowing new order');
                return null;
            }

            // Vendor cert exists and not inactive → block
            logModuleCall('nicsrs_ssl', 'checkVendorMigration', [
                'serviceid' => $params['serviceid'],
                'vendor_order_id' => $vendorOrder->id ?? 'N/A',
                'vendor_module' => $vendorOrder->module ?? '(empty)',
                'vendor_remoteid' => $vendorOrder->remoteid ?? '(empty)',
                'vendor_status' => $vendorOrder->status ?? '(empty)',
            ], 'Vendor cert found - showing migrated template');

            return $vendorOrder;

        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'checkVendorMigration_error', [
                'serviceid' => $params['serviceid'] ?? 0,
            ], $e->getMessage());
            return null;
        }
    }

    /**
     * Extract end date from vendor configdata (static version for PageController)
     */
    private static function extractVendorEndDate(object $vendorOrder): ?string
    {
        $raw = $vendorOrder->configdata ?? '';
        if (empty($raw)) {
            return null;
        }

        $configdata = json_decode($raw, true);
        if (!is_array($configdata)) {
            return null;
        }

        $keys = ['endDate', 'end_date', 'expires', 'expiry_date', 'cert_expiry'];
        foreach ($keys as $key) {
            if (!empty($configdata[$key]) && strtotime($configdata[$key])) {
                return $configdata[$key];
            }
        }

        $nested = [
            ['applyReturn', 'endDate'],
            ['applyReturn', 'end_date'],
            ['certificate', 'endDate'],
            ['certificate', 'end_date'],
        ];
        foreach ($nested as $parts) {
            $val = $configdata;
            foreach ($parts as $p) {
                $val = $val[$p] ?? null;
                if ($val === null) break;
            }
            if ($val && is_string($val) && strtotime($val)) {
                return $val;
            }
        }

        return null;
    }
    /**
     * Normalize status string for comparison
     * Handles case-sensitivity and various formats
     */
    private static function normalizeStatus(?string $status): string
    {
        if (empty($status)) {
            return 'awaiting';
        }

        $status = strtolower(trim($status));

        // Map various status formats to normalized values
        $statusMap = [
            'awaiting configuration' => 'awaiting',
            'awaiting' => 'awaiting',
            'draft' => 'draft',
            'pending' => 'pending',
            'processing' => 'processing',
            'complete' => 'complete',
            'completed' => 'complete',
            'issued' => 'issued',
            'active' => 'active',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'revoked' => 'revoked',
            'expired' => 'expired',
            'suspended' => 'suspended',
            'terminated' => 'terminated',
            'reissue' => 'reissue',
            'reissued' => 'reissue',
        ];

        return $statusMap[$status] ?? $status;
    }

    /**
     * Render apply certificate page
     */
    public static function renderApplyCert(array $params, object $order, array $cert): array
    {
        return TemplateHelper::applyCert($params, $order, $cert);
    }

    /**
     * Render pending page with DCV status
     */
    public static function renderPending(array $params, object $order, array $cert): array
    {
        // Try to refresh status from API
        $collectData = [];
        
        if (!empty($order->remoteid)) {
            try {
                $response = ApiService::collect($params, $order->remoteid);
                $parsed = ApiService::parseResponse($response);
                
                if ($parsed['success'] && $parsed['data']) {
                    $collectData = (array) $parsed['data'];
                    
                    // Update local data
                    $configdata = json_decode($order->configdata, true) ?: [];
                    $configdata['applyReturn'] = array_merge(
                        $configdata['applyReturn'] ?? [],
                        $collectData
                    );
                    $configdata['lastRefresh'] = date('Y-m-d H:i:s');
                    
                    // Check if status changed to complete
                    $apiStatus = strtoupper($parsed['status'] ?? '');
                    if (in_array($apiStatus, ['COMPLETE', 'ISSUED'])) {
                        OrderRepository::update($order->id, [
                            'status' => SSL_STATUS_COMPLETE,
                            'configdata' => json_encode($configdata),
                            'completiondate' => date('Y-m-d H:i:s'),
                        ]);
                        $order->status = SSL_STATUS_COMPLETE;
                        return self::renderComplete($params, $order, $cert, $collectData);
                    }
                    
                    OrderRepository::updateConfigData($order->id, $configdata);
                }
            } catch (Exception $e) {
                logModuleCall('nicsrs_ssl', 'renderPending::collect', $params, $e->getMessage());
            }
        }

        return TemplateHelper::pending($params, $order, $cert, $collectData);
    }

    /**
     * Render complete page with certificate info
     */
    public static function renderComplete(array $params, object $order, array $cert, array $collectData = []): array
    {
        // Refresh if no collect data and we have remote ID
        if (empty($collectData) && !empty($order->remoteid)) {
            try {
                $response = ApiService::collect($params, $order->remoteid);
                $parsed = ApiService::parseResponse($response);
                
                if ($parsed['success'] && $parsed['data']) {
                    $collectData = (array) $parsed['data'];
                    
                    // Update local data
                    $configdata = json_decode($order->configdata, true) ?: [];
                    $configdata['applyReturn'] = array_merge(
                        $configdata['applyReturn'] ?? [],
                        $collectData
                    );
                    $configdata['lastRefresh'] = date('Y-m-d H:i:s');
                    OrderRepository::updateConfigData($order->id, $configdata);
                    
                    // Reload order
                    $order = OrderRepository::getById($order->id);
                }
            } catch (Exception $e) {
                logModuleCall('nicsrs_ssl', 'renderComplete::collect', $params, $e->getMessage());
            }
        }

        return TemplateHelper::complete($params, $order, $cert, $collectData);
    }

    /**
     * Render cancelled/revoked page
     */
    public static function renderCancelled(array $params, object $order, array $cert): array
    {
        $baseVars = TemplateHelper::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];

        // Get WHMCS service regdate
        $service = Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->first();
        
        // Add regdate to order object or pass separately
        $serviceRegdate = $service->regdate ?? null;

        return [
            'templatefile' => 'cancelled',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configdata' => $configdata,
                'serviceRegdate' => $serviceRegdate,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'status' => $order->status,
                'statusClass' => CertificateFunc::getStatusClass($order->status),
                'canRenew' => true,
            ]),
        ];
    }

    /**
     * Render manage page
     */
    public static function manage(array $params): array
    {
        try {
            $order = OrderRepository::getByServiceId($params['serviceid']);
            
            if (!$order) {
                return TemplateHelper::error($params, 'Order not found');
            }

            $cert = self::getCertConfig($params);

            return TemplateHelper::manage($params, $order, $cert);
        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'PageController::manage', $params, $e->getMessage());
            return TemplateHelper::error($params, $e->getMessage());
        }
    }

    /**
     * Render reissue page
     */
    public static function reissue(array $params): array
    {
        try {
            $order = OrderRepository::getByServiceId($params['serviceid']);
            
            if (!$order) {
                return TemplateHelper::error($params, 'Order not found');
            }

            // Allow reissue page for all valid statuses
            // - complete/issued/active: cert is active, can start reissue
            // - reissue: already in reissue process (after submit)
            // - pending/processing: reissue submitted, waiting for CA
            $status = strtolower($order->status ?? '');
            $allowedStatuses = [
                'complete', 'issued', 'active',     // Can initiate reissue
                'reissue',                           // Already in reissue flow
                'pending', 'processing',             // Reissue submitted to CA
            ];

            // Also match constants (case-insensitive)
            $allowedConstants = array_filter([
                defined('SSL_STATUS_COMPLETE') ? strtolower(SSL_STATUS_COMPLETE) : null,
                defined('SSL_STATUS_ISSUED') ? strtolower(SSL_STATUS_ISSUED) : null,
                defined('SSL_STATUS_REISSUE') ? strtolower(SSL_STATUS_REISSUE) : null,
                defined('SSL_STATUS_PENDING') ? strtolower(SSL_STATUS_PENDING) : null,
            ]);

            $allAllowed = array_unique(array_merge($allowedStatuses, $allowedConstants));

            if (!in_array($status, $allAllowed)) {
                // Fallback: check if order has remoteid or replaceTimes
                $configdata = json_decode($order->configdata, true) ?: [];
                if (empty($order->remoteid) && empty($configdata['replaceTimes'])) {
                    return TemplateHelper::error(
                        $params,
                        'Certificate must be issued before it can be reissued.'
                    );
                }
            }

            $cert = self::getCertConfig($params);

            return TemplateHelper::reissue($params, $order, $cert);
        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'PageController::reissue', $params, $e->getMessage());
            return TemplateHelper::error($params, $e->getMessage());
        }
    }

    /**
     * Get certificate configuration from product
     */
    private static function getCertConfig(array $params): array
    {
        $certIdentifier = $params['configoption1'] ?? '';
        
        // Normalize to code (handles both name and code input)
        $certCode = CertificateFunc::normalizeToCode($certIdentifier);
        
        if ($certCode) {
            $cert = CertificateFunc::getCertAttributes($certCode);
            if ($cert) {
                $cert['code'] = $certCode;
                return $cert;
            }
        }

        // Fallback: Try to get from database by name
        if ($certIdentifier) {
            try {
                $product = \WHMCS\Database\Capsule::table('mod_nicsrs_products')
                    ->where('product_name', $certIdentifier)
                    ->orWhere('product_code', $certIdentifier)
                    ->first();

                if ($product) {
                    return [
                        'code' => $product->product_code,
                        'name' => $product->product_name,
                        'vendor' => $product->vendor ?? 'Unknown',
                        'sslType' => 'website_ssl',
                        'sslValidationType' => $product->validation_type ?? 'dv',
                        'isMultiDomain' => (bool) ($product->support_san ?? false),
                        'isWildcard' => (bool) ($product->support_wildcard ?? false),
                        'supportNormal' => true,
                        'supportIp' => false,
                        'supportWild' => (bool) ($product->support_wildcard ?? false),
                        'supportHttps' => true,
                        'maxDomains' => (int) ($product->max_domains ?? 1),
                    ];
                }
            } catch (\Exception $e) {
                // Continue to default
            }
        }

        // Default configuration
        return [
            'code' => 'unknown',
            'name' => $certIdentifier ?: 'SSL Certificate',
            'vendor' => 'Unknown',
            'sslType' => 'website_ssl',
            'sslValidationType' => 'dv',
            'isMultiDomain' => false,
            'isWildcard' => false,
            'supportNormal' => true,
            'supportIp' => false,
            'supportWild' => false,
            'supportHttps' => true,
            'maxDomains' => 1,
        ];
    }
}