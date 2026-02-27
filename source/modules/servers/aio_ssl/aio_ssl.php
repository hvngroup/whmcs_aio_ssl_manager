<?php
/**
 * HVN - AIO SSL Manager — Server Provisioning Module
 *
 * Handles certificate lifecycle: CreateAccount, ClientArea,
 * AdminServicesTab, and custom admin buttons.
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 * @license    Proprietary
 * @version    1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

// 1) Load addon autoloader (for AioSSL\Core\*, AioSSL\Provider\*, AioSSL\Service\*)
$addonAutoload = dirname(dirname(__DIR__)) . '/addons/aio_ssl_admin/lib/autoload.php';
if (file_exists($addonAutoload)) {
    require_once $addonAutoload;
}

// 2) Register server module autoloader (for AioSSL\Server\*)
spl_autoload_register(function ($class) {
    $prefix = 'AioSSL\\Server\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    // Try multiple directory mappings
    $searchPaths = [
        $baseDir . str_replace('\\', '/', $relativeClass) . '.php',
        $baseDir . 'Service/' . $relativeClass . '.php',
        $baseDir . 'Controller/' . $relativeClass . '.php',
        $baseDir . 'Dispatcher/' . $relativeClass . '.php',
    ];

    foreach ($searchPaths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

use WHMCS\Database\Capsule;
use AioSSL\Server\OrderService;
use AioSSL\Server\ProviderBridge;
use AioSSL\Server\ActionDispatcher;
use AioSSL\Server\PageDispatcher;
use AioSSL\Server\ActionController;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ProviderFactory;
use AioSSL\Core\ActivityLogger;

/**
 * Module metadata
 *
 * @return array
 */
function aio_ssl_MetaData()
{
    return [
        'DisplayName'           => 'HVN - AIO SSL Manager',
        'APIVersion'            => '1.1',
        'RequiresServer'        => false,
        'DefaultNonSSLPort'     => '',
        'DefaultSSLPort'        => '',
        'ServiceSingleSignOnLabel' => 'Manage SSL Certificate',
    ];
}

/**
 * Module configuration options — shown in WHMCS product setup
 *
 * @return array
 */
function aio_ssl_ConfigOptions()
{
    // Build product dropdown from canonical product map
    $products = [];
    try {
        $maps = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get();

        foreach ($maps as $map) {
            $products[$map->canonical_id] = "[{$map->vendor}] {$map->canonical_name} ({$map->validation_type})";
        }
    } catch (\Exception $e) {
        $products[''] = '-- Error loading products --';
    }

    // Build provider dropdown
    $providers = ['auto' => 'Auto (Cheapest)'];
    try {
        $rows = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();
        foreach ($rows as $row) {
            $providers[$row->slug] = $row->name;
        }
    } catch (\Exception $e) {
        // Keep just 'auto'
    }

    return [
        'Certificate Product' => [
            'Type'        => 'dropdown',
            'Options'     => $products,
            'Description' => 'Select the SSL certificate product (canonical mapping)',
        ],
        'Preferred Provider' => [
            'Type'        => 'dropdown',
            'Options'     => $providers,
            'Description' => 'Select provider or "Auto" for cheapest available',
        ],
        'Provider Override Token' => [
            'Type'        => 'text',
            'Size'        => 60,
            'Description' => 'Optional: API token override for this product',
        ],
        'Fallback Provider' => [
            'Type'        => 'dropdown',
            'Options'     => array_merge(['' => 'None'], $providers),
            'Description' => 'Fallback provider if primary fails',
        ],
    ];
}

/**
 * Create account — Called when a new service is provisioned
 *
 * @param array $params
 * @return string 'success' or error message
 */
function aio_ssl_CreateAccount(array $params)
{
    try {
        $serviceId = $params['serviceid'];
        $userId    = $params['userid'] ?? $params['clientsdetails']['userid'] ?? 0;

        // ── Step 1: Check for existing orders across ALL tables ──
        $existingOrder = OrderService::findAnyOrderForService($serviceId);

        if ($existingOrder) {
            $status = strtolower($existingOrder->status ?? '');

            // If order is in a terminal state, allow new order
            if (!in_array($status, ['cancelled', 'expired', 'revoked', 'rejected'])) {
                // Active/pending order exists — check if legacy migration
                if ($existingOrder->_source_table !== OrderService::TABLE) {
                    // Legacy order from another module — create AIO shadow record
                    logModuleCall('aio_ssl', 'CreateAccount', [
                        'serviceid' => $serviceId,
                        'legacy_source' => $existingOrder->_source_table,
                        'legacy_id' => $existingOrder->id,
                    ], 'Legacy order detected, creating AIO record');
                } else {
                    // AIO order already exists and is active
                    return 'An active SSL order already exists for this service. '
                         . 'Use "Allow New Certificate" to reset.';
                }
            }
        }

        // ── Step 2: Resolve provider ──
        $providerSlug = '';
        $canonicalId = '';

        // Check configoption2 for provider preference
        $configOption2 = $params['configoption2'] ?? '';
        if (!empty($configOption2) && $configOption2 !== 'auto') {
            $providerSlug = $configOption2;
        }

        // Get canonical product from configoption1
        $configOption1 = $params['configoption1'] ?? '';

        // If no specific provider, try auto-resolve cheapest
        if (empty($providerSlug) || $providerSlug === 'auto') {
            try {
                $bridge = ProviderBridge::resolveProvider($serviceId, $configOption1);
                $providerSlug = $bridge['slug'] ?? '';
            } catch (\Exception $e) {
                // Fallback: use first enabled provider
                $firstProvider = Capsule::table('mod_aio_ssl_providers')
                    ->where('is_enabled', 1)
                    ->orderBy('sort_order')
                    ->first();

                if ($firstProvider) {
                    $providerSlug = $firstProvider->slug;
                }
            }
        }

        if (empty($providerSlug)) {
            return 'No SSL provider configured. Please add and enable a provider in AIO SSL Admin.';
        }

        // ── Step 3: Get domain ──
        $domain = $params['domain'] ?? '';
        if (empty($domain)) {
            $hosting = Capsule::table('tblhosting')->find($serviceId);
            $domain = $hosting ? $hosting->domain : '';
        }

        // ── Step 4: Create record in mod_aio_ssl_orders ──
        $configdata = [
            'provider'    => $providerSlug,
            'canonical'   => $configOption1,
            'created_via' => 'CreateAccount',
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        // If legacy order exists, store reference
        if ($existingOrder && $existingOrder->_source_table !== OrderService::TABLE) {
            $configdata['legacy_source'] = $existingOrder->_source_table;
            $configdata['legacy_id'] = $existingOrder->id;
            $configdata['legacy_module'] = $existingOrder->module ?? '';
        }

        $orderId = OrderService::create([
            'userid'        => $userId,
            'service_id'    => $serviceId,
            'provider_slug' => $providerSlug,
            'canonical_id'  => $configOption1 ?: null,
            'product_code'  => $configOption1 ?: null,
            'domain'        => $domain,
            'certtype'      => $configOption1,
            'status'        => 'Awaiting Configuration',
            'configdata'    => $configdata,
        ]);

        logModuleCall('aio_ssl', 'CreateAccount', [
            'serviceid'    => $serviceId,
            'provider'     => $providerSlug,
            'order_id'     => $orderId,
            'domain'       => $domain,
        ], 'success');

        return 'success';

    } catch (\Exception $e) {
        logModuleCall('aio_ssl', 'CreateAccount', $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend account — Check mod_aio_ssl_orders first, fallback tblsslorders
 *
 * @param array $params
 * @return string
 */
function aio_ssl_SuspendAccount(array $params)
{
    try {
        $serviceId = $params['serviceid'];

        // Try mod_aio_ssl_orders first
        $aioOrder = OrderService::getByServiceId($serviceId);
        if ($aioOrder) {
            OrderService::updateStatus($aioOrder->id, 'Suspended');
            return 'success';
        }

        // Fallback: tblsslorders
        $affected = Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where('module', 'aio_ssl')
            ->update(['status' => 'Suspended']);

        return $affected >= 0 ? 'success' : 'No SSL order found for this service.';

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend account
 *
 * @param array $params
 * @return string
 */
function aio_ssl_UnsuspendAccount(array $params)
{
    try {
        $serviceId = $params['serviceid'];

        // Try mod_aio_ssl_orders first
        $aioOrder = OrderService::getByServiceId($serviceId);
        if ($aioOrder && strtolower($aioOrder->status) === 'suspended') {
            // Restore to Completed (or previous status if stored)
            $configdata = OrderService::decodeConfigdata($aioOrder->configdata);
            $restoreStatus = $configdata['_pre_suspend_status'] ?? 'Completed';
            OrderService::updateStatus($aioOrder->id, $restoreStatus);
            return 'success';
        }

        // Fallback: tblsslorders
        Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->where('module', 'aio_ssl')
            ->where('status', 'Suspended')
            ->update(['status' => 'Completed']);

        return 'success';

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate account — Attempt provider cancel/revoke, then update local status
 *
 * @param array $params
 * @return string
 */
function aio_ssl_TerminateAccount(array $params)
{
    try {
        $serviceId = $params['serviceid'];
        $order = OrderService::findAnyOrderForService($serviceId);

        if (!$order) {
            return 'success'; // No order to terminate
        }

        $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
        $providerSlug = $order->provider_slug ?? $configdata['provider'] ?? '';
        $remoteId = $order->remote_id ?? $order->remoteid ?? '';

        // Try to cancel/revoke on provider if possible
        if (!empty($remoteId) && !empty($providerSlug)) {
            try {
                $provider = \AioSSL\Core\ProviderRegistry::get($providerSlug);
                $caps = $provider->getCapabilities();
                $status = strtolower($order->status ?? '');

                if (in_array($status, ['completed', 'issued', 'active']) && in_array('revoke', $caps)) {
                    $provider->revokeCertificate($remoteId);
                } elseif (in_array($status, ['pending', 'processing']) && in_array('cancel', $caps)) {
                    $provider->cancelOrder($remoteId);
                }
            } catch (\Exception $e) {
                // Log but continue — local termination should still happen
                logModuleCall('aio_ssl', 'TerminateAccount_provider', [
                    'serviceid' => $serviceId,
                    'provider'  => $providerSlug,
                    'remoteid'  => $remoteId,
                ], 'Provider action failed: ' . $e->getMessage());
            }
        }

        // Update local record
        if ($order->_source_table === OrderService::TABLE) {
            OrderService::updateStatus($order->id, 'Cancelled');
        } else {
            // Legacy table — update there too
            try {
                Capsule::table($order->_source_table)
                    ->where('id', $order->id)
                    ->update(['status' => 'Cancelled']);
            } catch (\Exception $e) {
                // Ignore — might be read-only
            }
        }

        return 'success';

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Admin services tab — Extra fields shown in admin service view
 *
 * @param array $params
 * @return array
 */
function aio_ssl_AdminServicesTabFields(array $params)
{
    $fields = [];
    $serviceId = $params['serviceid'];

    try {
        $order = OrderService::findAnyOrderForService($serviceId);

        if (!$order) {
            $fields['SSL Order'] = '<span class="label label-default">No SSL order found</span>';
            return $fields;
        }

        $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
        $sourceTable = $order->_source_table ?? 'unknown';
        $providerSlug = $order->provider_slug ?? $configdata['provider'] ?? '';

        // Legacy module detection
        if (empty($providerSlug) && !empty($order->module)) {
            $legacyMap = [
                'nicsrs_ssl' => 'nicsrs', 'SSLCENTERWHMCS' => 'gogetssl',
                'thesslstore_ssl' => 'thesslstore', 'ssl2buy' => 'ssl2buy',
            ];
            $providerSlug = $legacyMap[$order->module] ?? '';
        }

        // Source badge (AIO vs Legacy)
        $isLegacy = ($sourceTable !== OrderService::TABLE);
        if ($isLegacy) {
            $fields['⚠️ Legacy Order'] = '<span class="label label-warning">'
                . 'From <code>' . htmlspecialchars($sourceTable) . '</code> '
                . '(module: ' . htmlspecialchars($order->module ?? 'N/A') . ')'
                . '</span> '
                . '<a href="addonmodules.php?module=aio_ssl_admin&page=orders" class="btn btn-xs btn-info">'
                . '<i class="fas fa-exchange-alt"></i> Claim to AIO</a>';
        }

        // Provider badge
        $providerColors = [
            'nicsrs' => '#1890ff', 'gogetssl' => '#13c2c2',
            'thesslstore' => '#722ed1', 'ssl2buy' => '#fa8c16',
        ];
        $color = $providerColors[$providerSlug] ?? '#999';
        $fields['Provider'] = '<span class="label" style="background:' . $color . ';color:#fff;">'
            . htmlspecialchars(strtoupper($providerSlug ?: 'Unknown')) . '</span>';

        // Provider tier badge
        try {
            $providerRecord = Capsule::table('mod_aio_ssl_providers')
                ->where('slug', $providerSlug)->first();
            if ($providerRecord && $providerRecord->tier === 'limited') {
                $fields['Provider'] .= ' <span class="label label-warning">LIMITED API</span>';
            }
        } catch (\Exception $e) {}

        // Order info
        $fields['SSL Order ID'] = '#' . $order->id . ' <small>(' . $sourceTable . ')</small>';

        $remoteId = $order->remote_id ?? $order->remoteid ?? '';
        $fields['Remote ID'] = $remoteId ?: '—';

        // Status
        $status = $order->status ?? 'Unknown';
        $statusClass = _aio_ssl_status_class($status);
        $fields['Status'] = '<span class="label label-' . $statusClass . '">'
            . htmlspecialchars($status) . '</span>';

        // Domain
        $domain = $order->domain ?? '';
        if (empty($domain)) {
            $domain = $configdata['domain'] ?? $configdata['domainInfo'][0]['domainName'] ?? '';
        }
        if (!empty($domain)) {
            $fields['Domain'] = '<code>' . htmlspecialchars($domain) . '</code>';
        }

        // Certificate dates
        $beginDate = $order->begin_date ?? $configdata['begin_date']
            ?? $configdata['applyReturn']['beginDate'] ?? '';
        $endDate = $order->end_date ?? $configdata['end_date']
            ?? $configdata['applyReturn']['endDate'] ?? '';

        if (!empty($beginDate) && !empty($endDate)) {
            $fields['Valid Period'] = htmlspecialchars($beginDate) . ' → ' . htmlspecialchars($endDate);

            // Expiry warning
            if (strtotime($endDate) && strtotime($endDate) < strtotime('+30 days')) {
                $daysLeft = max(0, (int)((strtotime($endDate) - time()) / 86400));
                $urgency = $daysLeft <= 7 ? 'danger' : 'warning';
                $fields['⏰ Expiry'] = '<span class="label label-' . $urgency . '">'
                    . $daysLeft . ' days remaining</span>';
            }
        }

        // Admin link to AIO order detail
        $detailSource = ($sourceTable === OrderService::TABLE) ? 'aio'
            : ($sourceTable === 'nicsrs_sslorders' ? 'nicsrs' : 'tblssl');

        $fields[''] = '<a href="addonmodules.php?module=aio_ssl_admin&page=orders'
            . '&action=detail&id=' . $order->id . '&source=' . $detailSource
            . '" class="btn btn-sm btn-primary" target="_blank">'
            . '<i class="fas fa-external-link-alt"></i> View in AIO SSL Manager</a>';

    } catch (\Exception $e) {
        $fields['Error'] = '<span class="label label-danger">' . htmlspecialchars($e->getMessage()) . '</span>';
    }

    return $fields;
}

/**
 * Admin custom buttons
 *
 * @param array $params
 * @return array
 */
function aio_ssl_AdminCustomButtonArray(array $params)
{
    return [
        'Refresh Status'      => 'refreshStatus',
        'Resend DCV Email'    => 'resendDcv',
        'Manage Order'        => 'manageOrder',
        'Allow New Certificate' => 'allowNewCert',
    ];
}

/**
 * Refresh certificate status from provider
 *
 * @param array $params
 * @return string
 */
function aio_ssl_refreshStatus(array $params)
{
    try {
        $order = Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->first();

        if (!$order || empty($order->remoteid)) {
            return 'No active SSL order or remote ID found.';
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $providerSlug = $configdata['provider'] ?? '';

        if (empty($providerSlug) || $providerSlug === 'auto') {
            return 'Provider not resolved for this order.';
        }

        $provider = ProviderRegistry::get($providerSlug);
        $status = $provider->getOrderStatus($order->remoteid);

        // Update order
        $updateData = ['status' => $status['status']];

        if ($status['status'] === 'Completed' && !empty($status['certificate'])) {
            $configdata = array_merge($configdata, [
                'cert'        => $status['certificate']['cert'] ?? '',
                'ca'          => $status['certificate']['ca'] ?? '',
                'private_key' => $status['certificate']['private_key'] ?? '',
            ]);
            $updateData['completiondate'] = date('Y-m-d H:i:s');
        }

        if (!empty($status['begin_date'])) {
            $configdata['begin_date'] = $status['begin_date'];
        }
        if (!empty($status['end_date'])) {
            $configdata['end_date'] = $status['end_date'];
        }
        if (!empty($status['domains'])) {
            $configdata['domains'] = $status['domains'];
        }

        $updateData['configdata'] = json_encode($configdata);
        Capsule::table('tblsslorders')->where('id', $order->id)->update($updateData);

        return 'success';

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Resend DCV email
 *
 * @param array $params
 * @return string
 */
function aio_ssl_resendDcv(array $params)
{
    try {
        $order = Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->first();

        if (!$order || empty($order->remoteid)) {
            return 'No active SSL order found.';
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $providerSlug = $configdata['provider'] ?? '';
        $provider = ProviderRegistry::get($providerSlug);

        $result = $provider->resendDcvEmail($order->remoteid);

        return $result['success'] ? 'success' : $result['message'];

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Manage order — redirect to AIO admin order detail
 *
 * @param array $params
 * @return string
 */
function aio_ssl_manageOrder(array $params)
{
    $order = Capsule::table('tblsslorders')
        ->where('serviceid', $params['serviceid'])
        ->first();

    if ($order) {
        header('Location: addonmodules.php?module=aio_ssl_admin&page=orders&action=detail&id=' . $order->id);
        exit;
    }

    return 'No SSL order found for this service.';
}

/**
 * Allow new certificate — reset order for re-configuration
 *
 * @param array $params
 * @return string
 */
function aio_ssl_allowNewCert(array $params)
{
    try {
        Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->update([
                'status'    => 'Awaiting Configuration',
                'remoteid'  => '',
            ]);
        return 'success';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Client area output — Full dispatcher-based routing
 */
function aio_ssl_ClientArea(array $params)
{
    $serviceId = $params['serviceid'];

    // ═══════════════════════════════════════════════════════════
    // AJAX REQUEST HANDLING
    // ═══════════════════════════════════════════════════════════
    $step = $_GET['step'] ?? $_REQUEST['step'] ?? '';

    // Also check modop=custom&a=xxx pattern
    if (empty($step) && isset($_REQUEST['modop']) && $_REQUEST['modop'] === 'custom' && isset($_REQUEST['a'])) {
        $step = $_REQUEST['a'];
    }

    $isAjax = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($step));

    // Handle AJAX actions
    if (!empty($step) && ActionDispatcher::isValidAction($step) && $isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        try {
            $result = ActionDispatcher::dispatch($step, $params);

            logModuleCall('aio_ssl', 'ClientArea_AJAX', [
                'step'    => $step,
                'service' => $serviceId,
            ], $result);

            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }

    // Handle direct download (GET request, not AJAX)
    if (!empty($step) && in_array($step, ['downloadCert', 'downCert', 'download'])) {
        try {
            ActionDispatcher::dispatch($step, $params);
        } catch (\Exception $e) {
            // Fall through to page rendering
        }
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // PAGE RENDERING
    // ═══════════════════════════════════════════════════════════
    $requestedAction = $_REQUEST['a'] ?? 'index';

    try {
        if ($requestedAction === 'index' || empty($requestedAction) || $requestedAction === $step) {
            $result = PageDispatcher::dispatchByStatus($params);
        } else {
            $result = PageDispatcher::dispatch($requestedAction, $params);
        }

        if (isset($result['templatefile'])) {
            return [
                'tabOverviewReplacementTemplate' => 'view/' . $result['templatefile'] . '.tpl',
                'templateVariables' => $result['vars'] ?? [],
            ];
        }

        return $result;

    } catch (\Exception $e) {
        logModuleCall('aio_ssl', 'ClientArea_Error', $params, $e->getMessage());

        return [
            'tabOverviewReplacementTemplate' => 'view/error.tpl',
            'templateVariables' => [
                'error_message' => $e->getMessage(),
            ],
        ];
    }
}

// ─── Helper Functions ──────────────────────────────────────────

/**
 * Map status to Bootstrap label class
 *
 * @param string $status
 * @return string
 */
function _aio_ssl_status_class(string $status): string
{
    $map = [
        'Awaiting Configuration' => 'default',
        'Draft'       => 'default',
        'Pending'     => 'warning',
        'Processing'  => 'info',
        'Completed'   => 'success',
        'Issued'      => 'success',
        'Active'      => 'success',
        'Suspended'   => 'warning',
        'Cancelled'   => 'danger',
        'Expired'     => 'danger',
        'Revoked'     => 'danger',
        'Rejected'    => 'danger',
    ];
    return $map[$status] ?? 'default';
}