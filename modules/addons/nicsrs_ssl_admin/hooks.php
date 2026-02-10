<?php
/**
 * NicSRS SSL Admin - WHMCS Hooks
 * 
 * Handles automatic synchronization via WHMCS cron system.
 * This file registers hooks for DailyCronJob and AfterCronJob events.
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 * @version    1.2.1
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Access Denied');
}

/**
 * Daily Cron Job Hook
 * 
 * Triggers automatic sync based on configured intervals.
 * This hook runs once per day when WHMCS daily cron executes.
 * 
 * For more frequent sync, use AfterCronJob hook instead.
 */
add_hook('DailyCronJob', 1, function ($vars) {
    nicsrs_ssl_admin_run_sync('daily');
});

/**
 * After Cron Job Hook
 * 
 * Runs after every cron execution (typically every 5-15 minutes).
 * This allows for more granular sync intervals.
 */
add_hook('AfterCronJob', 1, function ($vars) {
    nicsrs_ssl_admin_run_sync('after');
});

/**
 * Run synchronization process
 * 
 * @param string $trigger Trigger source ('daily' or 'after')
 * @return void
 */
function nicsrs_ssl_admin_run_sync(string $trigger): void
{
    try {
        // Check if module is active
        if (!nicsrs_ssl_admin_is_module_active()) {
            return;
        }

        // Load required files
        $basePath = __DIR__ . '/lib/Service/';
        
        if (!file_exists($basePath . 'SyncService.php')) {
            logModuleCall(
                'nicsrs_ssl_admin',
                'CronHook',
                ['trigger' => $trigger],
                'SyncService.php not found',
                'ERROR'
            );
            return;
        }

        require_once $basePath . 'SyncService.php';
        
        // Initialize and run sync
        $syncService = new \NicsrsAdmin\Service\SyncService();
        $results = $syncService->runScheduledSync();
        
        // Log results if any sync was performed
        if ($results['status_sync'] !== null || $results['product_sync'] !== null) {
            logModuleCall(
                'nicsrs_ssl_admin',
                'CronSync',
                ['trigger' => $trigger],
                json_encode($results),
                'SUCCESS'
            );
        }
        
    } catch (\Exception $e) {
        logModuleCall(
            'nicsrs_ssl_admin',
            'CronHook',
            ['trigger' => $trigger],
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
}

/**
 * Check if the module is active
 * 
 * @return bool
 */
function nicsrs_ssl_admin_is_module_active(): bool
{
    try {
        $status = Capsule::table('tbladdonmodules')
            ->where('module', 'nicsrs_ssl_admin')
            ->where('setting', 'status')
            ->value('value');
        
        return $status === 'Active';
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Client Area Page Hook (Optional)
 * 
 * Can be used to display sync status in client area if needed.
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    // Reserved for future client-side sync status display
    return [];
});

/**
 * Admin Area Header Output Hook
 * 
 * Displays sync status notification in admin area.
 */
add_hook('AdminAreaHeaderOutput', 1, function ($vars) {
    // Only show on NicSRS SSL Admin pages
    if (!isset($_GET['module']) || $_GET['module'] !== 'nicsrs_ssl_admin') {
        return '';
    }
    
    try {
        // Check for sync errors
        $errorCount = Capsule::table('mod_nicsrs_settings')
            ->where('setting_key', 'sync_error_count')
            ->value('setting_value');
        
        if ($errorCount && (int)$errorCount >= 3) {
            return '<div class="alert alert-warning" style="margin: 10px;">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Sync Warning:</strong> Auto-sync has encountered ' . $errorCount . ' consecutive errors. 
                Please check the module logs and API configuration.
            </div>';
        }
    } catch (\Exception $e) {
        // Silently fail
    }
    
    return '';
});

/**
 * Service Renewal Hook
 * 
 * Optionally trigger certificate renewal when service is renewed.
 */
add_hook('ServiceRenewal', 1, function ($vars) {
    // Reserved for future auto-renewal integration
    // $serviceId = $vars['serviceid'];
});

/**
 * Addon Activation Hook
 * 
 * Initialize default sync settings when addon is activated.
 */
add_hook('AddonActivation', 1, function ($vars) {
    if ($vars['addonModule'] !== 'nicsrs_ssl_admin') {
        return;
    }
    
    try {
        // Ensure sync settings exist
        $syncSettings = [
            'last_status_sync' => '',
            'last_product_sync' => '',
            'sync_batch_size' => '50',
            'sync_error_count' => '0',
        ];
        
        foreach ($syncSettings as $key => $defaultValue) {
            $exists = Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', $key)
                ->exists();
            
            if (!$exists) {
                Capsule::table('mod_nicsrs_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $defaultValue,
                    'setting_type' => 'string',
                ]);
            }
        }
    } catch (\Exception $e) {
        logModuleCall(
            'nicsrs_ssl_admin',
            'AddonActivation',
            [],
            $e->getMessage(),
            'ERROR'
        );
    }
});