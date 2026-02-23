<?php

namespace AioSSL\Server;

use WHMCS\Database\Capsule;

/**
 * PageDispatcher — Route client area pages by order status
 */
class PageDispatcher
{
    /**
     * Dispatch page based on SSL order status
     *
     * @param array $params WHMCS service params
     * @return array ['templatefile' => string, 'vars' => array]
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

        $configdata = json_decode($order->configdata, true) ?: [];
        $commonVars = self::buildCommonVars($order, $configdata, $params);

        // Legacy order → read-only migrated template
        if ($order->module !== 'aio_ssl') {
            return [
                'templatefile' => 'migrated',
                'vars' => $commonVars,
            ];
        }

        // SSL2Buy limited tier → special template
        $providerSlug = $configdata['provider'] ?? '';
        if ($providerSlug === 'ssl2buy' && !in_array($order->status, ['Awaiting Configuration'])) {
            return [
                'templatefile' => 'limited_provider',
                'vars' => array_merge($commonVars, [
                    'configLink' => $configdata['config_link'] ?? '',
                    'pin'        => $configdata['config_pin'] ?? '',
                ]),
            ];
        }

        // Status-based routing
        switch ($order->status) {
            case 'Awaiting Configuration':
                // Check for draft data
                $draft = $configdata['draft'] ?? [];
                return [
                    'templatefile' => 'applycert',
                    'vars' => array_merge($commonVars, [
                        'draft'       => $draft,
                        'hasDraft'    => !empty($draft),
                        'draftStep'   => $draft['step'] ?? 1,
                    ]),
                ];

            case 'Pending':
            case 'Processing':
                return [
                    'templatefile' => 'pending',
                    'vars' => array_merge($commonVars, [
                        'dcvStatus'  => $configdata['dcv_status'] ?? [],
                        'dcvMethod'  => $configdata['dcv_method'] ?? 'email',
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
     *
     * @param string $action  'reissue', 'manage', etc.
     * @param array  $params
     * @return array
     */
    public static function dispatch(string $action, array $params): array
    {
        $order = ProviderBridge::getOrder($params['serviceid']);
        $configdata = json_decode($order->configdata ?? '{}', true) ?: [];
        $commonVars = self::buildCommonVars($order, $configdata, $params);

        switch ($action) {
            case 'reissue':
                return [
                    'templatefile' => 'reissue',
                    'vars' => $commonVars,
                ];
            case 'downloadCert':
            case 'download':
                // Trigger download action directly
                ActionController::downloadCert($params);
                exit;
            default:
                return self::dispatchByStatus($params);
        }
    }

    /**
     * Build common template variables
     */
    private static function buildCommonVars($order, array $configdata, array $params): array
    {
        $providerSlug = $configdata['provider'] ?? ($order ? $order->module : 'unknown');
        $providerLabels = [
            'nicsrs' => 'NicSRS', 'gogetssl' => 'GoGetSSL',
            'thesslstore' => 'TheSSLStore', 'ssl2buy' => 'SSL2Buy',
            'aio_ssl' => 'AIO SSL',
            'nicsrs_ssl' => 'NicSRS (Legacy)', 'SSLCENTERWHMCS' => 'GoGetSSL (Legacy)',
            'thesslstore_ssl' => 'TheSSLStore (Legacy)',
        ];

        $hosting = Capsule::table('tblhosting')->find($params['serviceid']);
        $domain = $hosting ? $hosting->domain : '';

        return [
            'serviceId'     => $params['serviceid'],
            'orderId'       => $order ? $order->id : 0,
            'order'         => $order,
            'configdata'    => $configdata,
            'status'        => $order ? $order->status : 'Unknown',
            'certType'      => $order ? $order->certtype : '',
            'remoteid'      => $order ? $order->remoteid : '',
            'domains'       => $configdata['domains'] ?? ($domain ? [$domain] : []),
            'domain'        => $domain,
            'provider'      => $providerSlug,
            'providerLabel' => $providerLabels[$providerSlug] ?? ucfirst($providerSlug),
            'beginDate'     => $configdata['begin_date'] ?? '',
            'endDate'       => $configdata['end_date'] ?? '',
            'hasCert'       => !empty($configdata['cert']),
            'moduleVersion' => defined('AIO_SSL_VERSION') ? AIO_SSL_VERSION : '1.0.0',
        ];
    }

    /**
     * Check if provider supports a capability
     */
    private static function providerCan(string $slug, string $capability): bool
    {
        try {
            $provider = \AioSSL\Core\ProviderRegistry::get($slug);
            return in_array($capability, $provider->getCapabilities());
        } catch (\Exception $e) {
            return false;
        }
    }
}