<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;

class SyncService
{
    /**
     * Sync products from all (or one) provider(s)
     *
     * @param string|null $providerSlug Specific provider, or null for all
     * @return array Results per provider
     */
    public function syncProducts(?string $providerSlug = null): array
    {
        $results = [];

        if ($providerSlug) {
            $providers = [$providerSlug => ProviderRegistry::get($providerSlug)];
        } else {
            $providers = ProviderRegistry::getAllEnabled();
        }

        foreach ($providers as $slug => $provider) {
            try {
                $products = $provider->fetchProducts();
                $upserted = 0;

                foreach ($products as $product) {
                    $row = $product->toDbRow($slug);

                    // Detect price changes
                    $existing = Capsule::table('mod_aio_ssl_products')
                        ->where('provider_slug', $slug)
                        ->where('product_code', $row['product_code'])
                        ->first();

                    $priceChanged = false;
                    if ($existing && $existing->price_data !== $row['price_data']) {
                        $priceChanged = true;
                    }

                    // Upsert
                    Capsule::table('mod_aio_ssl_products')->updateOrInsert(
                        ['provider_slug' => $slug, 'product_code' => $row['product_code']],
                        $row
                    );

                    if ($priceChanged) {
                        ActivityLogger::log('price_changed', 'product', $row['product_code'],
                            "Price changed for {$row['product_name']} ({$slug})");
                    }

                    $upserted++;
                }

                // Reset sync error count on success
                Capsule::table('mod_aio_ssl_providers')
                    ->where('slug', $slug)
                    ->update([
                        'last_sync'        => date('Y-m-d H:i:s'),
                        'sync_error_count' => 0,
                    ]);

                ActivityLogger::log('product_sync_ok', 'provider', $slug, "Synced {$upserted} products");
                $results[$slug] = ['success' => true, 'count' => $upserted];

            } catch (\Exception $e) {
                Capsule::table('mod_aio_ssl_providers')
                    ->where('slug', $slug)
                    ->increment('sync_error_count');

                ActivityLogger::log('product_sync_fail', 'provider', $slug, $e->getMessage());
                $results[$slug] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Sync certificate statuses for all pending/processing orders
     */
    public function syncCertificateStatuses(): void
    {
        $batchSize = (int)(Capsule::table('mod_aio_ssl_settings')
            ->where('setting', 'sync_batch_size')
            ->value('value') ?: 50);

        $orders = Capsule::table('tblsslorders')
            ->where('module', 'aio_ssl')
            ->whereIn('status', ['Pending', 'Processing'])
            ->where('remoteid', '!=', '')
            ->limit($batchSize)
            ->get();

        foreach ($orders as $order) {
            try {
                $configdata = json_decode($order->configdata, true) ?: [];
                $slug = $configdata['provider'] ?? '';
                if (empty($slug) || $slug === 'auto') continue;

                $provider = ProviderRegistry::get($slug);
                $status = $provider->getOrderStatus($order->remoteid);

                $update = ['status' => $status['status']];

                if ($status['status'] === 'Completed' && !empty($status['certificate'])) {
                    $configdata = array_merge($configdata, $status['certificate']);
                    $update['completiondate'] = date('Y-m-d H:i:s');
                }

                if (!empty($status['end_date'])) $configdata['end_date'] = $status['end_date'];
                if (!empty($status['begin_date'])) $configdata['begin_date'] = $status['begin_date'];
                if (!empty($status['domains'])) $configdata['domains'] = $status['domains'];

                $update['configdata'] = json_encode($configdata);
                Capsule::table('tblsslorders')->where('id', $order->id)->update($update);

                // Trigger notification if newly completed
                if ($status['status'] === 'Completed' && $order->status !== 'Completed') {
                    $this->notifyIssuance($order, $configdata);
                }

            } catch (\Exception $e) {
                ActivityLogger::log('status_sync_error', 'order', (string)$order->id, $e->getMessage());
            }
        }
    }

    /**
     * Quick sync for pending orders only (AfterCronJob)
     */
    public function syncPendingOrders(): void
    {
        $this->syncCertificateStatuses();
    }

    private function notifyIssuance($order, array $configdata): void
    {
        try {
            $notifyEnabled = Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'notify_issuance')
                ->value('value');

            if ($notifyEnabled === '1') {
                $ns = new NotificationService();
                $ns->notifyIssuance($order, $configdata);
            }
        } catch (\Exception $e) {
            // Silent
        }
    }
}