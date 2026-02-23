<?php

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class SettingsController extends BaseController
{
    /** @var array All configurable settings with metadata */
    private $settingsDef = [
        'sync' => [
            'sync_enabled'          => ['type' => 'bool',   'label' => 'Enable Auto-Sync'],
            'sync_status_interval'  => ['type' => 'select', 'label' => 'Status Sync Interval (hours)',  'options' => [1,2,3,6,12,24]],
            'sync_product_interval' => ['type' => 'select', 'label' => 'Product Sync Interval (hours)', 'options' => [6,12,24,48,72,168]],
            'sync_batch_size'       => ['type' => 'select', 'label' => 'Sync Batch Size',               'options' => [10,25,50,100,200]],
        ],
        'notifications' => [
            'notify_issuance'     => ['type' => 'bool',   'label' => 'Notify on Certificate Issuance'],
            'notify_expiry'       => ['type' => 'bool',   'label' => 'Notify on Expiry Warning'],
            'notify_expiry_days'  => ['type' => 'select', 'label' => 'Expiry Warning Days',  'options' => [7,14,30,60,90]],
            'notify_sync_errors'  => ['type' => 'bool',   'label' => 'Notify on Sync Errors'],
            'notify_price_changes'=> ['type' => 'bool',   'label' => 'Notify on Price Changes'],
            'notify_admin_email'  => ['type' => 'text',   'label' => 'Admin Email Override (blank = default)'],
        ],
        'display' => [
            'items_per_page'  => ['type' => 'select', 'label' => 'Items Per Page', 'options' => [10,25,50,100]],
            'date_format'     => ['type' => 'select', 'label' => 'Date Format', 'options' => ['Y-m-d','d/m/Y','m/d/Y','d-M-Y']],
        ],
        'currency' => [
            'currency_display'      => ['type' => 'select', 'label' => 'Currency Display', 'options' => ['usd','vnd','both']],
            'currency_usd_vnd_rate' => ['type' => 'text',   'label' => 'USD → VND Exchange Rate'],
        ],
    ];

    public function render(string $action = ''): void
    {
        // Load current values
        $settings = [];
        $rows = Capsule::table('mod_aio_ssl_settings')->get();
        foreach ($rows as $row) {
            $settings[$row->setting] = $row->value;
        }

        // Get sync status info
        $syncInfo = [
            'last_product_sync' => $settings['last_product_sync'] ?? 'Never',
            'last_status_sync'  => $settings['last_status_sync'] ?? 'Never',
        ];

        // Provider sync errors
        $errorProviders = Capsule::table('mod_aio_ssl_providers')
            ->where('sync_error_count', '>', 0)
            ->get()
            ->toArray();

        $this->renderTemplate('settings.tpl', [
            'settingsDef'    => $this->settingsDef,
            'settings'       => $settings,
            'syncInfo'       => $syncInfo,
            'errorProviders' => $errorProviders,
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'save':
                return $this->saveSettings();
            case 'manual_sync':
                return $this->triggerManualSync();
            case 'update_rate':
                return $this->updateExchangeRate();
            default:
                return parent::handleAjax($action);
        }
    }

    private function saveSettings(): array
    {
        $updated = 0;

        foreach ($this->settingsDef as $group => $fields) {
            foreach ($fields as $key => $def) {
                if (isset($_POST[$key])) {
                    $val = trim($_POST[$key]);

                    // Validate
                    if ($def['type'] === 'bool') {
                        $val = ($val === '1' || $val === 'on') ? '1' : '0';
                    } elseif ($def['type'] === 'select' && isset($def['options'])) {
                        if (!in_array($val, array_map('strval', $def['options']))) {
                            continue;
                        }
                    }

                    $this->setSetting($key, $val);
                    $updated++;
                }
            }
        }

        ActivityLogger::log('settings_updated', 'settings', null, "{$updated} settings updated");

        return ['success' => true, 'message' => "{$updated} settings saved successfully."];
    }

    private function triggerManualSync(): array
    {
        $type = $this->input('sync_type', 'all'); // 'products', 'status', 'all'

        try {
            if (class_exists('AioSSL\Service\SyncService')) {
                $syncService = new \AioSSL\Service\SyncService();

                if ($type === 'products' || $type === 'all') {
                    $syncService->syncProducts();
                }
                if ($type === 'status' || $type === 'all') {
                    $syncService->syncCertificateStatuses();
                }
            }

            ActivityLogger::log('manual_sync', 'sync', null, "Manual {$type} sync triggered");

            return ['success' => true, 'message' => ucfirst($type) . ' sync completed successfully.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    private function updateExchangeRate(): array
    {
        try {
            // Fetch from exchangerate-api.com
            $ch = curl_init('https://api.exchangerate-api.com/v4/latest/USD');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if (isset($data['rates']['VND'])) {
                $rate = (string)$data['rates']['VND'];
                $this->setSetting('currency_usd_vnd_rate', $rate);
                ActivityLogger::log('exchange_rate_updated', 'settings', null, "USD/VND rate updated: {$rate}");
                return ['success' => true, 'rate' => $rate, 'message' => "Exchange rate updated: 1 USD = {$rate} VND"];
            }

            return ['success' => false, 'message' => 'Could not fetch exchange rate.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}