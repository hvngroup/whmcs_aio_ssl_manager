<?php
/**
 * Settings Controller — Load/save settings, trigger manual sync
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;
use AioSSL\Core\ProviderRegistry;

class SettingsController extends BaseController
{
    /** @var array Saveable setting keys (whitelist) */
    private $allowedKeys = [
        'items_per_page', 'date_format',
        'sync_enabled', 'sync_status_interval', 'sync_product_interval', 'sync_batch_size',
        'notify_issuance', 'notify_expiry', 'notify_expiry_days',
        'notify_sync_errors', 'notify_price_changes', 'notify_admin_email',
        'currency_display', 'currency_usd_vnd_rate',
        'exchangerate_api_key', 'exchangerate_auto_enabled', 'exchangerate_update_interval',    
    ];

    public function render(string $action = ''): void
    {
        $settings = $this->loadAllSettings();

        // Build sync status info
        $syncStatus = $this->buildSyncStatus();

        $this->renderTemplate('settings.php', [
            'settings'   => $settings,
            'syncStatus' => $syncStatus,
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'save':
                return $this->saveSettings();
            case 'manual_sync':
                return $this->manualSync();
            case 'test_all':
                return $this->testAllProviders();
            case 'fetch_rate':
                return $this->fetchExchangeRate();
            case 'test_rate_api':
                return $this->testRateApi();
            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    // ─── Load ──────────────────────────────────────────────────────

    private function loadAllSettings(): array
    {
        $settings = [];
        try {
            $rows = Capsule::table('mod_aio_ssl_settings')->get();
            foreach ($rows as $row) {
                $settings[$row->setting] = $row->value;
            }
        } catch (\Exception $e) {
            // Return empty
        }
        return $settings;
    }

    // ─── Save ──────────────────────────────────────────────────────

    private function saveSettings(): array
    {
        $saved = 0;

        // Handle checkbox fields: unchecked = not in POST
        $checkboxKeys = ['sync_enabled', 'notify_issuance', 'notify_expiry', 'notify_sync_errors', 'notify_price_changes', 'exchangerate_auto_enabled',];

        foreach ($this->allowedKeys as $key) {
            $value = $this->input($key);

            // Checkboxes: if not in POST, default to '0'
            if (in_array($key, $checkboxKeys) && $value === null) {
                $value = '0';
            }

            if ($value === null) continue;

            // Validate specific fields
            switch ($key) {
                case 'items_per_page':
                    $value = max(10, min(100, (int)$value));
                    break;
                case 'sync_status_interval':
                    $value = max(1, min(72, (int)$value));
                    break;
                case 'sync_product_interval':
                    $value = max(1, min(168, (int)$value));
                    break;
                case 'sync_batch_size':
                    $value = max(10, min(200, (int)$value));
                    break;
                case 'notify_expiry_days':
                    $value = max(1, min(90, (int)$value));
                    break;
                case 'currency_usd_vnd_rate':
                    $value = max(1, (float)$value);
                    break;
                case 'notify_admin_email':
                    $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                    break;
            }

            try {
                Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
                    ['setting' => $key],
                    ['value' => (string)$value]
                );
                $saved++;
            } catch (\Exception $e) {
                // Skip this key
            }
        }

        ActivityLogger::log('settings_saved', 'system', '', "Saved {$saved} settings");

        return [
            'success' => true,
            'message' => $this->t('settings_saved', "Settings saved successfully. ({$saved} values)"),
        ];
    }

    // ─── Manual Sync ───────────────────────────────────────────────

    private function manualSync(): array
    {
        $type = $this->input('type', 'all');

        try {
            if (!class_exists('AioSSL\Service\SyncService')) {
                return ['success' => false, 'message' => 'SyncService not available.'];
            }

            $syncService = new \AioSSL\Service\SyncService();
            $results = [];

            if ($type === 'products' || $type === 'all') {
                $productResult = $syncService->syncProducts();
                $results['products'] = $productResult;
            }

            if ($type === 'status' || $type === 'all') {
                $batchSize = (int)$this->getSetting('sync_batch_size', 50);
                $statusResult = $syncService->syncStatuses($batchSize);
                $results['status'] = $statusResult;
            }

            // Build summary message
            $messages = [];
            if (isset($results['products'])) {
                $p = $results['products'];
                $messages[] = "Products: {$p['synced']} synced";
                if (!empty($p['errors'])) {
                    $messages[] = count($p['errors']) . ' error(s)';
                }
            }
            if (isset($results['status'])) {
                $s = $results['status'];
                $messages[] = "Statuses: {$s['synced']} updated";
            }

            ActivityLogger::log('manual_sync', 'system', $type, implode('. ', $messages));

            return [
                'success' => true,
                'message' => implode('. ', $messages) . '.',
                'results' => $results,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    // ─── Test All Providers ────────────────────────────────────────

    private function testAllProviders(): array
    {
        $results = [];
        try {
            $providers = ProviderRegistry::getAllEnabled();
            foreach ($providers as $slug => $provider) {
                if (is_object($provider) && method_exists($provider, 'getSlug')) {
                    $slug = $provider->getSlug();
                }
                try {
                    $results[$slug] = $provider->testConnection();
                    // Update DB
                    Capsule::table('mod_aio_ssl_providers')
                        ->where('slug', $slug)
                        ->update([
                            'last_test' => date('Y-m-d H:i:s'),
                            'test_result' => $results[$slug]['success'] ? 1 : 0,
                        ]);
                } catch (\Exception $e) {
                    $results[$slug] = ['success' => false, 'message' => $e->getMessage()];
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'results' => $results];
    }

    // ─── Sync Status Info ──────────────────────────────────────────

    private function buildSyncStatus(): array
    {
        $status = ['providers' => []];

        try {
            $providers = Capsule::table('mod_aio_ssl_providers')
                ->where('is_enabled', 1)
                ->get();

            foreach ($providers as $p) {
                $status['providers'][$p->slug] = [
                    'success'    => $p->sync_error_count < 3,
                    'last_sync'  => $p->last_sync ?? 'Never',
                    'errors'     => $p->sync_error_count,
                ];
            }
        } catch (\Exception $e) {
            // empty
        }

        return $status;
    }
    
    /**
     * Fetch exchange rate from API and save
     */
    private function fetchExchangeRate(): array
    {
        try {
            $helper = new \AioSSL\Helper\CurrencyHelper();
            $result = $helper->updateRateFromApi();

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'rate'    => $result['rate'],
                    'old_rate' => $result['old_rate'] ?? null,
                    'change'  => $result['change'] ?? 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test exchange rate API key without saving
     */
    private function testRateApi(): array
    {
        $apiKey = $this->rawInput('exchangerate_api_key', '');
        if (empty($apiKey)) {
            $apiKey = $this->getSetting('exchangerate_api_key', '');
        }

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Please enter an API key first.'];
        }

        try {
            $helper = new \AioSSL\Helper\CurrencyHelper();
            $result = $helper->fetchRateFromApi($apiKey);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'API key valid! ' . $result['message'],
                    'rate'    => $result['rate'],
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}