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

// Ensure admin addon autoloader is loaded
$autoloadPath = dirname(dirname(__DIR__)) . '/addons/aio_ssl_admin/lib/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ProviderFactory;
use AioSSL\Core\ActivityLogger;
use AioSSL\Server\ActionDispatcher;
use AioSSL\Server\PageDispatcher;
use AioSSL\Server\ProviderBridge;

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
        $clientId = $params['userid'];
        $canonicalId = $params['configoption1'] ?? '';
        $preferredProvider = $params['configoption2'] ?? 'auto';

        if (empty($canonicalId)) {
            return 'No certificate product selected in module configuration.';
        }

        // Check if SSL order already exists for this service (vendor migration)
        $existingOrder = Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->first();

        if ($existingOrder) {
            // Vendor migration scenario: order exists from legacy module
            if ($existingOrder->module !== 'aio_ssl') {
                ActivityLogger::log(
                    'create_account_legacy_detected',
                    'order',
                    (string)$existingOrder->id,
                    "Legacy order detected (module: {$existingOrder->module}) for service #{$serviceId}"
                );
                return 'success'; // Allow activation; legacy order will be shown in migrated view
            }
            return 'success'; // Already has AIO order
        }

        // Create new tblsslorders record
        $orderId = Capsule::table('tblsslorders')->insertGetId([
            'userid'         => $clientId,
            'serviceid'      => $serviceId,
            'addon_id'       => 0,
            'remoteid'       => '',
            'module'         => 'aio_ssl',
            'certtype'       => $canonicalId,
            'completiondate' => '0000-00-00 00:00:00',
            'status'         => 'Awaiting Configuration',
            'configdata'     => json_encode([
                'provider'       => $preferredProvider,
                'canonical_id'   => $canonicalId,
                'created_at'     => date('Y-m-d H:i:s'),
                'created_by'     => 'aio_ssl',
            ]),
        ]);

        ActivityLogger::log(
            'order_created',
            'order',
            (string)$orderId,
            "SSL order #{$orderId} created for service #{$serviceId} (product: {$canonicalId})"
        );

        return 'success';

    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend account
 *
 * @param array $params
 * @return string
 */
function aio_ssl_SuspendAccount(array $params)
{
    try {
        Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->update(['status' => 'Suspended']);
        return 'success';
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
        // Restore to previous status (or Completed)
        Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->where('status', 'Suspended')
            ->update(['status' => 'Completed']);
        return 'success';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate account
 *
 * @param array $params
 * @return string
 */
function aio_ssl_TerminateAccount(array $params)
{
    try {
        Capsule::table('tblsslorders')
            ->where('serviceid', $params['serviceid'])
            ->where('module', 'aio_ssl')
            ->update(['status' => 'Cancelled']);
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
        $order = Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$order) {
            $fields['SSL Order'] = '<span class="label label-default">No SSL order found</span>';
            return $fields;
        }

        // Provider badge
        $configdata = json_decode($order->configdata, true) ?: [];
        $providerSlug = $configdata['provider'] ?? 'unknown';
        $providerColors = [
            'nicsrs' => '#1890ff', 'gogetssl' => '#13c2c2',
            'thesslstore' => '#722ed1', 'ssl2buy' => '#fa8c16',
        ];
        $color = $providerColors[$providerSlug] ?? '#999';
        $fields['Provider'] = '<span class="label" style="background:' . $color . ';color:#fff;">'
            . htmlspecialchars(strtoupper($providerSlug)) . '</span>';

        // Order info
        $fields['SSL Order ID'] = '#' . $order->id;
        $fields['Remote ID'] = $order->remoteid ?: '—';
        $fields['Status'] = '<span class="label label-' . _aio_ssl_status_class($order->status) . '">'
            . htmlspecialchars($order->status) . '</span>';
        $fields['Certificate Type'] = htmlspecialchars($order->certtype);

        if ($order->module !== 'aio_ssl') {
            $fields['Migration'] = '<span class="label label-warning">Legacy order (module: '
                . htmlspecialchars($order->module) . ')</span>';
        }

        // Dates
        if ($order->completiondate && $order->completiondate !== '0000-00-00 00:00:00') {
            $fields['Issued'] = $order->completiondate;
        }
        if (isset($configdata['end_date'])) {
            $fields['Expires'] = $configdata['end_date'];
        }

    } catch (\Exception $e) {
        $fields['Error'] = $e->getMessage();
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
                'tabOverviewReplacementTemplate' => 'templates/' . $result['templatefile'] . '.tpl',
                'templateVariables' => $result['vars'] ?? [],
            ];
        }

        return $result;

    } catch (\Exception $e) {
        logModuleCall('aio_ssl', 'ClientArea_Error', $params, $e->getMessage());

        return [
            'tabOverviewReplacementTemplate' => 'templates/error.tpl',
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
        'Completed'              => 'success',
        'Issued'                 => 'success',
        'Active'                 => 'success',
        'Pending'                => 'warning',
        'Processing'             => 'info',
        'Awaiting Configuration' => 'default',
        'Cancelled'              => 'danger',
        'Expired'                => 'danger',
        'Rejected'               => 'danger',
        'Revoked'                => 'danger',
        'Suspended'              => 'warning',
    ];
    return $map[$status] ?? 'default';
}