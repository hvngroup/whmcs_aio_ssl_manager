<?php
/**
 * Sync Service — Product catalog + certificate status sync orchestrator
 *
 * Loops enabled providers, calls fetchProducts(), upserts mod_aio_ssl_products.
 * Handles price normalization, change detection, error tracking, file-based lock.
 *
 * @package    AioSSL\Service
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;

class SyncService
{
    /** @var string Lock file path */
    private $lockFile;

    /** @var int Max sync errors before alert */
    private const MAX_ERROR_COUNT = 3;

    public function __construct()
    {
        $this->lockFile = sys_get_temp_dir() . '/aio_ssl_sync.lock';
    }

    // ─── Product Sync ──────────────────────────────────────────────

    /**
     * Sync product catalogs from providers
     *
     * @param string|null $providerSlug Specific provider or null for all
     * @return array ['synced' => int, 'errors' => array, 'details' => array]
     */
    public function syncProducts(?string $providerSlug = null): array
    {
        if (!$this->acquireLock('product')) {
            return ['synced' => 0, 'errors' => ['Sync already in progress.'], 'details' => []];
        }

        $results = ['synced' => 0, 'errors' => [], 'details' => []];

        try {
            $providers = $providerSlug
                ? [ProviderRegistry::get($providerSlug)]
                : ProviderRegistry::getAllEnabled();

            foreach ($providers as $slug => $provider) {
                if (is_object($provider) && method_exists($provider, 'getSlug')) {
                    $slug = $provider->getSlug();
                }

                try {
                    $products = $provider->fetchProducts();
                    $upserted = $this->upsertProducts($slug, $products);

                    // Update provider last_sync
                    Capsule::table('mod_aio_ssl_providers')
                        ->where('slug', $slug)
                        ->update([
                            'last_sync' => date('Y-m-d H:i:s'),
                            'sync_error_count' => 0,
                        ]);

                    $results['details'][$slug] = [
                        'fetched'  => count($products),
                        'upserted' => $upserted['upserted'],
                        'updated'  => $upserted['updated'],
                        'price_changes' => $upserted['price_changes'],
                    ];
                    $results['synced'] += $upserted['upserted'];

                    ActivityLogger::log('product_sync', 'provider', $slug,
                        "Synced {$upserted['upserted']} products ({$upserted['updated']} updated)");

                } catch (\Exception $e) {
                    $results['errors'][] = "{$slug}: " . $e->getMessage();
                    $this->incrementErrorCount($slug);
                    ActivityLogger::log('sync_error', 'provider', $slug, $e->getMessage());
                }
            }
        } finally {
            $this->releaseLock('product');
        }

        return $results;
    }

    /**
     * Upsert products into mod_aio_ssl_products
     */
    private function upsertProducts(string $slug, array $products): array
    {
        $stats = ['upserted' => 0, 'updated' => 0, 'price_changes' => 0];

        foreach ($products as $p) {
            if (!is_array($p) && !($p instanceof \AioSSL\Core\NormalizedProduct)) {
                continue;
            }

            $data = is_array($p) ? $p : $p->toArray();
            $code = $data['code'] ?? $data['product_code'] ?? '';
            if (empty($code)) continue;

            // Normalize price data to JSON
            $priceJson = is_string($data['price_data'] ?? null)
                ? $data['price_data']
                : json_encode($data['price_data'] ?? []);

            $existing = Capsule::table('mod_aio_ssl_products')
                ->where('provider_slug', $slug)
                ->where('product_code', $code)
                ->first();

            $row = [
                'provider_slug'   => $slug,
                'product_code'    => $code,
                'product_name'    => $data['name'] ?? $data['product_name'] ?? '',
                'vendor'          => $data['vendor'] ?? '',
                'validation_type' => strtolower($data['validation_type'] ?? 'dv'),
                'product_type'    => strtolower($data['type'] ?? $data['product_type'] ?? 'ssl'),
                'support_wildcard'=> (int)($data['wildcard'] ?? $data['support_wildcard'] ?? 0),
                'support_san'     => (int)($data['san'] ?? $data['support_san'] ?? 0),
                'max_domains'     => (int)($data['max_domains'] ?? 1),
                'max_years'       => (int)($data['max_years'] ?? 1),
                'min_years'       => (int)($data['min_years'] ?? 1),
                'price_data'      => $priceJson,
                'last_sync'       => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                // Detect price changes
                if ($existing->price_data !== $priceJson) {
                    $stats['price_changes']++;
                    $this->logPriceChange($slug, $code, $existing->price_data, $priceJson);
                }
                Capsule::table('mod_aio_ssl_products')
                    ->where('id', $existing->id)
                    ->update($row);
                $stats['updated']++;
            } else {
                $row['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_aio_ssl_products')->insert($row);
            }
            $stats['upserted']++;
        }

        return $stats;
    }

    /**
     * Log price change for notification
     */
    private function logPriceChange(string $slug, string $code, ?string $oldJson, string $newJson): void
    {
        ActivityLogger::log('price_change', 'product', "{$slug}:{$code}",
            json_encode(['old' => $oldJson, 'new' => $newJson]));
    }

    // ─── Certificate Status Sync ───────────────────────────────────

    /**
     * Sync certificate statuses for pending/processing orders
     *
     * @param int $batchSize Max orders per run
     * @return array
     */
    public function syncStatuses(int $batchSize = 50): array
    {
        if (!$this->acquireLock('status')) {
            return ['synced' => 0, 'errors' => ['Status sync already in progress.']];
        }

        $results = ['synced' => 0, 'errors' => []];

        try {
            $orders = Capsule::table('mod_aio_ssl_orders')
                ->whereIn('status', ['Pending', 'Processing', 'Awaiting Issuance'])
                ->where('provider_slug', '!=', '')
                ->whereNotNull('remote_id')
                ->where('remote_id', '!=', '')
                ->orderBy('updated_at', 'asc')
                ->limit($batchSize)
                ->get();

            foreach ($orders as $order) {
                try {
                    $provider = ProviderRegistry::get($order->provider_slug);
                    $status = $provider->getOrderStatus($order->remote_id);

                    $update = ['status' => $status['status'], 'updated_at' => date('Y-m-d H:i:s')];

                    // Update configdata with cert info
                    $cfg = json_decode($order->configdata ?? '{}', true) ?: [];
                    if (!empty($status['certificate'])) {
                        $cfg = array_merge($cfg, $status['certificate']);
                        $update['completion_date'] = date('Y-m-d H:i:s');
                    }
                    if (!empty($status['begin_date'])) $cfg['begin_date'] = $status['begin_date'];
                    if (!empty($status['end_date'])) $cfg['end_date'] = $status['end_date'];
                    $update['configdata'] = json_encode($cfg);

                    Capsule::table('mod_aio_ssl_orders')
                        ->where('id', $order->id)
                        ->update($update);

                    $results['synced']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Order #{$order->id}: " . $e->getMessage();
                }
            }
        } finally {
            $this->releaseLock('status');
        }

        return $results;
    }

    // ─── Scheduled Sync (via Cron) ─────────────────────────────────

    /**
     * Run scheduled sync based on configured intervals
     */
    public function runScheduledSync(): void
    {
        $now = time();

        // Product sync
        $productInterval = (int)$this->getSetting('sync_product_interval', 24) * 3600;
        $lastProductSync = $this->getLastSync('product');
        if (($now - $lastProductSync) >= $productInterval) {
            $this->syncProducts();
            $this->setLastSync('product', $now);
        }

        // Status sync
        $statusInterval = (int)$this->getSetting('sync_status_interval', 6) * 3600;
        $lastStatusSync = $this->getLastSync('status');
        if (($now - $lastStatusSync) >= $statusInterval) {
            $batchSize = (int)$this->getSetting('sync_batch_size', 50);
            $this->syncStatuses($batchSize);
            $this->setLastSync('status', $now);
        }
    }

    // ─── Lock Management ───────────────────────────────────────────

    private function acquireLock(string $type): bool
    {
        $file = $this->lockFile . '.' . $type;
        if (file_exists($file)) {
            $lockTime = (int)file_get_contents($file);
            // Stale lock (> 30 min)
            if ((time() - $lockTime) > 1800) {
                @unlink($file);
            } else {
                return false;
            }
        }
        file_put_contents($file, time());
        return true;
    }

    private function releaseLock(string $type): void
    {
        @unlink($this->lockFile . '.' . $type);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function incrementErrorCount(string $slug): void
    {
        Capsule::table('mod_aio_ssl_providers')
            ->where('slug', $slug)
            ->increment('sync_error_count');
    }

    private function getSetting(string $key, $default = null)
    {
        try {
            $row = Capsule::table('mod_aio_ssl_settings')->where('setting', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function getLastSync(string $type): int
    {
        $val = $this->getSetting("last_{$type}_sync", '0');
        return (int)$val;
    }

    private function setLastSync(string $type, int $time): void
    {
        try {
            Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
                ['setting' => "last_{$type}_sync"],
                ['value' => (string)$time]
            );
        } catch (\Exception $e) {
            // Non-critical
        }
    }
}