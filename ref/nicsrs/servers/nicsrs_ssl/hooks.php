<?php
/**
 * NicSRS SSL Module - WHMCS Hooks
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Daily Cron Job - Auto-refresh pending certificates
 */
add_hook('DailyCronJob', 1, function ($vars) {
    $modulePath = __DIR__ . DIRECTORY_SEPARATOR;
    
    // Load required files if not already loaded
    if (!class_exists('nicsrsSSL\\ApiService')) {
        require_once $modulePath . "src/model/Service/ApiService.php";
        require_once $modulePath . "src/model/Service/OrderRepository.php";
    }
    
    try {
        // Get all pending orders
        $pendingOrders = Capsule::table('nicsrs_sslorders')
            ->whereIn('status', ['Pending', 'Processing'])
            ->get();
        
        foreach ($pendingOrders as $order) {
            try {
                refreshCertificateStatus($order);
            } catch (Exception $e) {
                logModuleCall('nicsrs_ssl', 'DailyCronRefresh', [
                    'orderId' => $order->id,
                    'serviceId' => $order->serviceid
                ], $e->getMessage());
            }
        }
        
        // Check for expiring certificates (30 days)
        checkExpiringCertificates();
        
    } catch (Exception $e) {
        logModuleCall('nicsrs_ssl', 'DailyCronJob', [], $e->getMessage());
    }
});

/**
 * Refresh certificate status from API
 */
function refreshCertificateStatus($order)
{
    if (empty($order->remoteid)) {
        return;
    }
    
    // Get API token
    $apiToken = getApiTokenForOrder($order);
    if (empty($apiToken)) {
        return;
    }
    
    // Call collect API
    $result = \nicsrsSSL\ApiService::call('collect', [
        'api_token' => $apiToken,
        'certId' => $order->remoteid,
    ]);
    
    if (!$result || !isset($result->code)) {
        return;
    }
    
    $configdata = json_decode($order->configdata, true) ?: [];
    
    // Update based on response
    if ($result->code == 1) {
        $status = $result->status ?? $result->certStatus ?? 'Pending';
        
        // Map API status to module status
        $statusMap = [
            'COMPLETE' => 'Complete',
            'ISSUED' => 'Complete',
            'PENDING' => 'Pending',
            'PROCESSING' => 'Pending',
            'CANCELLED' => 'Cancelled',
            'REVOKED' => 'Revoked',
            'EXPIRED' => 'Expired',
        ];
        
        $newStatus = $statusMap[strtoupper($status)] ?? $status;
        
        // Merge response data
        if (isset($result->data)) {
            $configdata['applyReturn'] = array_merge(
                $configdata['applyReturn'] ?? [],
                (array) $result->data
            );
        }
        
        $configdata['lastRefresh'] = date('Y-m-d H:i:s');
        
        // Update order
        $updateData = [
            'status' => $newStatus,
            'configdata' => json_encode($configdata),
        ];
        
        if ($newStatus === 'Complete' && $order->completiondate === '0000-00-00 00:00:00') {
            $updateData['completiondate'] = date('Y-m-d H:i:s');
        }
        
        Capsule::table('nicsrs_sslorders')
            ->where('id', $order->id)
            ->update($updateData);
        
        // Send notification if status changed to Complete
        if ($newStatus === 'Complete' && $order->status !== 'Complete') {
            sendCertificateIssuedNotification($order);
        }
    }
}

/**
 * Get API token for an order
 */
function getApiTokenForOrder($order)
{
    // Get service and product info
    $service = Capsule::table('tblhosting')
        ->where('id', $order->serviceid)
        ->first();
    
    if (!$service) {
        return null;
    }
    
    $product = Capsule::table('tblproducts')
        ->where('id', $service->packageid)
        ->first();
    
    // Try product-level token first (configoption2)
    if ($product && !empty($product->configoption2)) {
        return $product->configoption2;
    }
    
    // Fallback to admin addon shared token
    $setting = Capsule::table('mod_nicsrs_settings')
        ->where('setting_key', 'api_token')
        ->first();
    
    return $setting->setting_value ?? null;
}

/**
 * Check for expiring certificates and send notifications
 */
function checkExpiringCertificates()
{
    $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
    
    // Get all complete certificates
    $orders = Capsule::table('nicsrs_sslorders')
        ->whereIn('status', ['Complete', 'Issued'])
        ->get();
    
    foreach ($orders as $order) {
        $configdata = json_decode($order->configdata, true) ?: [];
        $endDate = $configdata['applyReturn']['endDate'] ?? null;
        
        if (!$endDate) {
            continue;
        }
        
        $expiryDate = date('Y-m-d', strtotime($endDate));
        
        // Check if expiring within 30 days
        if ($expiryDate <= $thirtyDaysFromNow && $expiryDate >= date('Y-m-d')) {
            $daysLeft = (strtotime($expiryDate) - strtotime(date('Y-m-d'))) / 86400;
            
            // Send notification at 30, 14, 7, 3, 1 days
            if (in_array($daysLeft, [30, 14, 7, 3, 1])) {
                sendExpiryNotification($order, $daysLeft);
            }
        }
    }
}

/**
 * Send certificate issued notification
 */
function sendCertificateIssuedNotification($order)
{
    try {
        $service = Capsule::table('tblhosting')
            ->where('id', $order->serviceid)
            ->first();
        
        if (!$service) {
            return;
        }
        
        $configdata = json_decode($order->configdata, true) ?: [];
        $domain = $configdata['domainInfo'][0]['domainName'] ?? 'N/A';
        
        // Send email using WHMCS email template
        $command = 'SendEmail';
        $postData = [
            'messagename' => 'SSL Certificate Issued',
            'id' => $order->serviceid,
            'customvars' => base64_encode(serialize([
                'ssl_domain' => $domain,
                'ssl_cert_id' => $order->remoteid,
                'ssl_expiry' => $configdata['applyReturn']['endDate'] ?? 'N/A',
            ])),
        ];
        
        localAPI($command, $postData);
        
    } catch (Exception $e) {
        logModuleCall('nicsrs_ssl', 'sendCertificateIssuedNotification', $order->id, $e->getMessage());
    }
}

/**
 * Send expiry notification
 */
function sendExpiryNotification($order, $daysLeft)
{
    try {
        $configdata = json_decode($order->configdata, true) ?: [];
        $domain = $configdata['domainInfo'][0]['domainName'] ?? 'N/A';
        
        // Check if notification already sent today
        $notificationKey = 'ssl_expiry_' . $order->id . '_' . $daysLeft;
        $lastSent = Capsule::table('tblconfiguration')
            ->where('setting', $notificationKey)
            ->first();
        
        if ($lastSent && $lastSent->value === date('Y-m-d')) {
            return; // Already sent today
        }
        
        // Send email
        $command = 'SendEmail';
        $postData = [
            'messagename' => 'SSL Certificate Expiry Notice',
            'id' => $order->serviceid,
            'customvars' => base64_encode(serialize([
                'ssl_domain' => $domain,
                'ssl_days_left' => $daysLeft,
                'ssl_expiry' => $configdata['applyReturn']['endDate'] ?? 'N/A',
            ])),
        ];
        
        localAPI($command, $postData);
        
        // Record that notification was sent
        Capsule::table('tblconfiguration')->updateOrInsert(
            ['setting' => $notificationKey],
            ['value' => date('Y-m-d')]
        );
        
    } catch (Exception $e) {
        logModuleCall('nicsrs_ssl', 'sendExpiryNotification', $order->id, $e->getMessage());
    }
}

/**
 * Client area page hook - Add CSS/JS assets
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    // Only add assets on product details page with nicsrs_ssl module
    if (isset($vars['filename']) && $vars['filename'] === 'clientarea' && 
        isset($_GET['action']) && $_GET['action'] === 'productdetails') {
        
        $serviceId = $_GET['id'] ?? 0;
        
        if ($serviceId) {
            $service = Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->where('tblhosting.id', $serviceId)
                ->select('tblproducts.servertype')
                ->first();
            
            if ($service && $service->servertype === 'nicsrs_ssl') {
                return [
                    'extraStyles' => [
                        'modules/servers/nicsrs_ssl/assets/css/ssl-manager.css'
                    ],
                    'extraScripts' => [
                        'modules/servers/nicsrs_ssl/assets/js/ssl-manager.js'
                    ]
                ];
            }
        }
    }
    
    return [];
});

/**
 * Admin area header output - Add admin styles
 */
add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    // Add custom styles for admin service view
    $output = '<style>
        .nicsrs-admin-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .nicsrs-admin-badge.success { background: #d4edda; color: #155724; }
        .nicsrs-admin-badge.warning { background: #fff3cd; color: #856404; }
        .nicsrs-admin-badge.danger { background: #f8d7da; color: #721c24; }
        .nicsrs-admin-badge.info { background: #d1ecf1; color: #0c5460; }
    </style>';
    
    return $output;
});

/**
 * Service delete hook - Clean up SSL order records
 */
add_hook('ServiceDelete', 1, function ($vars) {
    $serviceId = $vars['serviceid'];
    
    try {
        Capsule::table('nicsrs_sslorders')
            ->where('serviceid', $serviceId)
            ->delete();
    } catch (Exception $e) {
        logModuleCall('nicsrs_ssl', 'ServiceDelete', $serviceId, $e->getMessage());
    }
});

/**
 * After module create hook - Initialize SSL order
 */
add_hook('AfterModuleCreate', 1, function ($vars) {
    if ($vars['params']['moduletype'] !== 'nicsrs_ssl') {
        return;
    }
    
    // Order is created in CreateAccount function
    // This hook can be used for additional initialization if needed
});