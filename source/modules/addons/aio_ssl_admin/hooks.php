<?php
/**
 * WHMCS Hooks for AIO SSL Manager
 *
 * DailyCronJob: Full sync (products + status)
 * AfterCronJob: Quick status sync for pending orders
 * AdminAreaHeaderOutput: Warning banner for sync errors
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) {
    die('Access denied.');
}

use WHMCS\Database\Capsule;

/**
 * Daily Cron Job — Full product and certificate status sync
 */
add_hook('DailyCronJob', 1, function ($vars) {
    try {
        $autoloadPath = __DIR__ . '/lib/autoload.php';
        if (!file_exists($autoloadPath)) {
            return;
        }
        require_once $autoloadPath;

        // Check if sync is enabled
        $syncEnabled = Capsule::table('mod_aio_ssl_settings')
            ->where('setting', 'sync_enabled')
            ->value('value');

        if ($syncEnabled !== '1') {
            return;
        }

        // Acquire lock
        $lockFile = sys_get_temp_dir() . '/aio_ssl_sync.lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 3600) {
            return; // Another sync is running (or stale lock < 1 hour)
        }
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        try {
            // Product catalog sync
            $lastProductSync = Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'last_product_sync')
                ->value('value');
            $productInterval = (int)Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'sync_product_interval')
                ->value('value') ?: 24;

            if (empty($lastProductSync) || (time() - strtotime($lastProductSync)) >= ($productInterval * 3600)) {
                if (class_exists('AioSSL\Service\SyncService')) {
                    $syncService = new \AioSSL\Service\SyncService();
                    $syncService->syncProducts();
                }
                Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
                    ['setting' => 'last_product_sync'],
                    ['value' => date('Y-m-d H:i:s')]
                );
            }

            // Certificate status sync
            $lastStatusSync = Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'last_status_sync')
                ->value('value');
            $statusInterval = (int)Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'sync_status_interval')
                ->value('value') ?: 6;

            if (empty($lastStatusSync) || (time() - strtotime($lastStatusSync)) >= ($statusInterval * 3600)) {
                if (class_exists('AioSSL\Service\SyncService')) {
                    $syncService = new \AioSSL\Service\SyncService();
                    $syncService->syncCertificateStatuses();
                }
                Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
                    ['setting' => 'last_status_sync'],
                    ['value' => date('Y-m-d H:i:s')]
                );
            }

            // Cleanup old activity logs (90 days)
            \AioSSL\Core\ActivityLogger::cleanup(90);

        } finally {
            @unlink($lockFile);
        }

    } catch (\Exception $e) {
        logActivity('AIO SSL Cron Error: ' . $e->getMessage());
    }
});

/**
 * After Cron Job — Quick sync for pending/processing orders
 */
add_hook('AfterCronJob', 1, function ($vars) {
    try {
        $autoloadPath = __DIR__ . '/lib/autoload.php';
        if (!file_exists($autoloadPath)) {
            return;
        }
        require_once $autoloadPath;

        $syncEnabled = Capsule::table('mod_aio_ssl_settings')
            ->where('setting', 'sync_enabled')
            ->value('value');

        if ($syncEnabled !== '1') {
            return;
        }

        // Only sync pending/processing orders (quick check)
        if (class_exists('AioSSL\Service\SyncService')) {
            $syncService = new \AioSSL\Service\SyncService();
            $syncService->syncPendingOrders();
        }

    } catch (\Exception $e) {
        logActivity('AIO SSL AfterCron Error: ' . $e->getMessage());
    }
});

/**
 * Admin Area Header Output — Show sync error banner
 */
add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    try {
        // Check for providers with sync errors
        $errorProviders = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)
            ->where('sync_error_count', '>=', 3)
            ->pluck('name');

        if ($errorProviders->isEmpty()) {
            return '';
        }

        $names = implode(', ', $errorProviders->toArray());
        return '<div class="alert alert-warning" style="margin:10px 15px;">'
             . '<i class="fas fa-exclamation-triangle"></i> '
             . '<strong>AIO SSL Manager:</strong> Sync errors detected for: ' . htmlspecialchars($names)
             . '. <a href="addonmodules.php?module=aio_ssl_admin&page=settings">Check Settings</a>'
             . '</div>';

    } catch (\Exception $e) {
        return '';
    }
});