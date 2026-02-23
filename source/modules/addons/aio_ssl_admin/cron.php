<?php

/**
 * Standalone cron endpoint for AIO SSL Manager
 *
 * Usage: php /path/to/whmcs/modules/addons/aio_ssl_admin/cron.php
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('CLI access only.');
}

// Bootstrap WHMCS
$whmcsRoot = dirname(dirname(dirname(__DIR__)));
$initFile = $whmcsRoot . '/init.php';

if (!file_exists($initFile)) {
    echo "Error: WHMCS init.php not found at: {$initFile}\n";
    exit(1);
}

require_once $initFile;
require_once __DIR__ . '/lib/autoload.php';

echo "[" . date('Y-m-d H:i:s') . "] AIO SSL Cron starting...\n";

try {
    $syncService = new \AioSSL\Service\SyncService();

    // Product sync (if interval elapsed)
    echo "  → Syncing products...\n";
    $results = $syncService->syncProducts();
    foreach ($results as $slug => $result) {
        $status = $result['success'] ? "OK ({$result['count']} products)" : "FAIL: {$result['error']}";
        echo "    [{$slug}] {$status}\n";
    }

    // Status sync
    echo "  → Syncing certificate statuses...\n";
    $syncService->syncCertificateStatuses();
    echo "    Done.\n";

    // Expiry warnings
    $notifyExpiry = \WHMCS\Database\Capsule::table('mod_aio_ssl_settings')
        ->where('setting', 'notify_expiry')
        ->value('value');

    if ($notifyExpiry === '1') {
        echo "  → Sending expiry warnings...\n";
        $ns = new \AioSSL\Service\NotificationService();
        $ns->sendExpiryWarnings();
        echo "    Done.\n";
    }

    // Log cleanup
    echo "  → Cleaning old logs (90d)...\n";
    $deleted = \AioSSL\Core\ActivityLogger::cleanup(90);
    echo "    Deleted {$deleted} old entries.\n";

    echo "[" . date('Y-m-d H:i:s') . "] Cron completed successfully.\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}