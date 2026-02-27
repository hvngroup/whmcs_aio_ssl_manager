<?php

namespace AioSSL\Server;

use WHMCS\Database\Capsule;

/**
 * PageDispatcher — Route client area pages by order status
 *
 * FIXED: Detect AIO orders via _source_table instead of module column
 * (mod_aio_ssl_orders has no 'module' column)
 */
class PageDispatcher
{
    /**
     * Dispatch page based on SSL order status
     */
    public static function dispatchByStatus(array $params): array
    {
        $serviceId = $params['serviceid'];
        $order = ProviderBridge::getOrder($serviceId);

        if (!$order) {
            return [
                'templatefile' => 'error',
                'vars' => ['errorMessage' => 'No SSL certificate order found for this service.'],
            ];
        }

        $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
        $commonVars = self::buildCommonVars($order, $configdata, $params);

        // ── Detect legacy vs AIO order ──
        // FIX: Use _source_table (set by ProviderBridge::getOrder) instead of
        // $order->module, because mod_aio_ssl_orders has no 'module' column.
        $sourceTable = $order->_source_table ?? '';
        $isAioOrder = ($sourceTable === OrderService::TABLE);

        // Legacy order from another module → read-only migrated template
        if (!$isAioOrder) {
            // Double check: also check the module field for tblsslorders records
            $module = $order->module ?? '';
            if ($module !== '' && $module !== 'aio_ssl') {
                return [
                    'templatefile' => 'migrated',
                    'vars' => array_merge($commonVars, [
                        'legacyModule' => $module,
                    ]),
                ];
            }
        }

        // SSL2Buy limited tier → special template
        $providerSlug = $configdata['provider'] ?? $order->provider_slug ?? '';
        if ($providerSlug === 'ssl2buy' && !in_array($order->status, ['Awaiting Configuration'])) {
            return [
                'templatefile' => 'limited_provider',
                'vars' => array_merge($commonVars, [
                    'configLink' => $configdata['config_link'] ?? '',
                    'pin'        => $configdata['config_pin'] ?? '',
                ]),
            ];
        }

        // ── Status-based routing ──
        switch ($order->status) {
            case 'Awaiting Configuration':
            case 'Draft':
                $draft = $configdata['draft'] ?? [];
                return [
                    'templatefile' => 'applycert',
                    'vars' => array_merge($commonVars, [
                        'draft'     => $draft,
                        'hasDraft'  => !empty($draft),
                        'draftStep' => $draft['step'] ?? 1,
                    ]),
                ];

            case 'Pending':
            case 'Processing':
                return [
                    'templatefile' => 'pending',
                    'vars' => array_merge($commonVars, [
                        'dcvStatus' => $configdata['dcv_status'] ?? [],
                        'dcvMethod' => $configdata['dcv_method'] ?? 'email',
                    ]),
                ];

            case 'Completed':
            case 'Issued':
            case 'Active':
                return [
                    'templatefile' => 'complete',
                    'vars' => array_merge($commonVars, [
                        'hasCert'     => !empty($configdata['cert']),
                        'beginDate'   => $configdata['begin_date'] ?? '',
                        'endDate'     => $configdata['end_date'] ?? '',
                        'canReissue'  => self::providerCan($providerSlug, 'reissue'),
                        'canRenew'    => self::providerCan($providerSlug, 'renew'),
                        'canRevoke'   => self::providerCan($providerSlug, 'revoke'),
                        'canDownload' => self::providerCan($providerSlug, 'download'),
                    ]),
                ];

            case 'Cancelled':
            case 'Expired':
            case 'Revoked':
            case 'Rejected':
                return [
                    'templatefile' => 'status',
                    'vars' => $commonVars,
                ];

            default:
                return [
                    'templatefile' => 'status',
                    'vars' => $commonVars,
                ];
        }
    }

    /**
     * Dispatch specific page by action name
     */
    public static function dispatch(string $action, array $params): array
    {
        $order = ProviderBridge::getOrder($params['serviceid']);
        $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
        $commonVars = self::buildCommonVars($order, $configdata, $params);

        switch ($action) {
            case 'reissue':
                return ['templatefile' => 'reissue', 'vars' => $commonVars];
            case 'downloadCert':
            case 'download':
                ActionController::downloadCert($params);
                exit;
            default:
                return self::dispatchByStatus($params);
        }
    }

    /**
     * Build common template variables
     *
     * FIXED: Handle both mod_aio_ssl_orders and tblsslorders field names
     */
    private static function buildCommonVars($order, array $configdata, array $params): array
    {
        $providerSlug = $configdata['provider']
            ?? $order->provider_slug
            ?? '';

        // Fallback: resolve from legacy module name
        $legacyMap = [
            'nicsrs_ssl' => 'nicsrs', 'SSLCENTERWHMCS' => 'gogetssl',
            'thesslstore_ssl' => 'thesslstore', 'ssl2buy' => 'ssl2buy',
        ];
        if (empty($providerSlug) && !empty($order->module)) {
            $providerSlug = $legacyMap[$order->module] ?? '';
        }

        // Get domain — try multiple sources
        $domain = $order->domain ?? '';
        if (empty($domain)) {
            $domain = $configdata['domain']
                ?? $configdata['domainInfo'][0]['domainName']
                ?? $params['domain']
                ?? '';
        }

        // Get product info
        $productCode = $order->certtype ?? $order->product_code
            ?? $configdata['canonical'] ?? $params['configoption1'] ?? '';

        // Determine SSL validation type from product map
        $sslValidationType = 'dv';
        $isMultiDomain = false;
        $maxDomains = 1;

        if (!empty($productCode)) {
            try {
                $map = Capsule::table('mod_aio_ssl_product_map')
                    ->where('canonical_id', $productCode)
                    ->first();
                if ($map) {
                    $sslValidationType = $map->validation_type ?? 'dv';
                    $isMultiDomain = !empty($map->is_multi_domain);
                    $maxDomains = $map->max_domains ?? 1;
                }
            } catch (\Exception $e) {}
        }

        return [
            // Order data
            'serviceid'         => $params['serviceid'],
            'orderStatus'       => $order->status ?? 'Unknown',
            'domain'            => $domain,
            'remoteId'          => $order->remote_id ?? $order->remoteid ?? '',
            'orderId'           => $order->id ?? 0,

            // Provider (internal use only — NOT shown to client)
            'providerSlug'      => $providerSlug,

            // Product
            'productCode'       => $productCode,
            'sslValidationType' => $sslValidationType,
            'isMultiDomain'     => $isMultiDomain,
            'maxDomains'        => $maxDomains,

            // Source info
            'sourceTable'       => $order->_source_table ?? '',
            'isLegacy'          => ($order->_source_table ?? '') !== OrderService::TABLE,
            'legacyModule'      => $order->module ?? '',

            // Dates
            'beginDate'         => $order->begin_date ?? $configdata['begin_date']
                                   ?? $configdata['applyReturn']['beginDate'] ?? '',
            'endDate'           => $order->end_date ?? $configdata['end_date']
                                   ?? $configdata['applyReturn']['endDate'] ?? '',

            // Configdata passthrough
            'configData'        => $configdata,

            // WHMCS module link (for AJAX calls)
            'moduleLink'        => 'clientarea.php?action=productdetails&id=' . $params['serviceid'],

            // Can auto-generate CSR
            'canAutoGenerate'   => function_exists('openssl_pkey_new'),

            // Client details (for pre-filling contact forms)
            'clientsdetails'    => $params['clientsdetails'] ?? [],
        ];
    }

    /**
     * Check if provider supports a capability
     */
    private static function providerCan(string $providerSlug, string $capability): bool
    {
        if (empty($providerSlug)) {
            return false;
        }

        try {
            $provider = \AioSSL\Core\ProviderRegistry::get($providerSlug);
            $caps = $provider->getCapabilities();
            return in_array($capability, $caps);
        } catch (\Exception $e) {
            return false;
        }
    }
}