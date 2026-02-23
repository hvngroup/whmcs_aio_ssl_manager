<?php
/**
 * Product Controller — Product catalog, mapping, sync
 *
 * FIXED: All .tpl references → .php (C1 compliance)
 * ADDED: auto_map, create_canonical AJAX handlers
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;

class ProductController extends BaseController
{
    public function render(string $action = ''): void
    {
        switch ($action) {
            case 'mapping':
                $this->renderMapping();
                break;
            default:
                $this->renderList();
                break;
        }
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'sync':
                return $this->syncProducts();
            case 'save_mapping':
                return $this->saveMapping();
            case 'auto_map':
                return $this->autoMap();
            case 'create_canonical':
                return $this->createCanonical();
            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    // ─── Product List ──────────────────────────────────────────────

    private function renderList(): void
    {
        $page = (int)($this->input('p') ?: 1);
        $providerFilter = $this->input('provider', '');
        $vendorFilter = $this->input('vendor', '');
        $validationFilter = $this->input('validation', '');
        $search = $this->input('search', '');

        $q = Capsule::table('mod_aio_ssl_products');

        if ($providerFilter) $q->where('provider_slug', $providerFilter);
        if ($vendorFilter) $q->where('vendor', $vendorFilter);
        if ($validationFilter) $q->where('validation_type', $validationFilter);
        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('product_name', 'LIKE', "%{$search}%")
                  ->orWhere('product_code', 'LIKE', "%{$search}%");
            });
        }

        $total = (clone $q)->count();
        $pagination = $this->paginate($total, $page);

        $products = (clone $q)
            ->orderBy('provider_slug')
            ->orderBy('vendor')
            ->orderBy('product_name')
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->get()
            ->toArray();

        // Get distinct vendors for filter
        $vendors = Capsule::table('mod_aio_ssl_products')
            ->select('vendor')
            ->distinct()
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->orderBy('vendor')
            ->pluck('vendor')
            ->toArray();

        // Enabled providers for filter/sync buttons
        $providers = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        // Mapping stats
        $mapStats = null;
        if (class_exists('AioSSL\Service\ProductMapService')) {
            try {
                $mapStats = (new \AioSSL\Service\ProductMapService())->getStats();
            } catch (\Exception $e) {}
        }

        // FIX: .tpl → .php (C1 compliance)
        $this->renderTemplate('products.php', [
            'products'   => $products,
            'pagination' => $pagination,
            'vendors'    => $vendors,
            'providers'  => $providers,
            'mapStats'   => $mapStats,
            'filters'    => compact('providerFilter', 'vendorFilter', 'validationFilter', 'search'),
        ]);
    }

    // ─── Product Mapping ───────────────────────────────────────────

    private function renderMapping(): void
    {
        $mappings = Capsule::table('mod_aio_ssl_product_map')
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get()
            ->toArray();

        // Unmapped products
        $unmapped = Capsule::table('mod_aio_ssl_products')
            ->where(function ($q) {
                $q->whereNull('canonical_id')->orWhere('canonical_id', '');
            })
            ->orderBy('provider_slug')
            ->orderBy('product_name')
            ->get()
            ->toArray();

        // Mapping stats
        $mapStats = null;
        if (class_exists('AioSSL\Service\ProductMapService')) {
            try {
                $mapStats = (new \AioSSL\Service\ProductMapService())->getStats();
            } catch (\Exception $e) {}
        }

        // FIX: product_mapping.tpl → product_mapping.php (C1 compliance)
        $this->renderTemplate('product_mapping.php', [
            'mappings' => $mappings,
            'unmapped' => $unmapped,
            'mapStats' => $mapStats,
        ]);
    }

    // ─── AJAX: Sync Products ───────────────────────────────────────

    private function syncProducts(): array
    {
        $providerSlug = $this->input('slug', '') ?: $this->input('provider', '');

        try {
            if (class_exists('AioSSL\Service\SyncService')) {
                $syncService = new \AioSSL\Service\SyncService();
                $result = $syncService->syncProducts($providerSlug ?: null);
                $msg = "Synced {$result['synced']} products.";
                if (!empty($result['errors'])) {
                    $msg .= ' ' . count($result['errors']) . ' error(s).';
                }
                return ['success' => true, 'message' => $msg, 'result' => $result];
            }
            return ['success' => false, 'message' => 'SyncService not available.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    // ─── AJAX: Save Mapping ────────────────────────────────────────

    private function saveMapping(): array
    {
        $canonicalId = $this->input('canonical_id');
        $providerSlug = $this->input('provider_slug');
        $productCode = $this->input('product_code');

        if (empty($canonicalId) || empty($providerSlug)) {
            return ['success' => false, 'message' => 'Missing required fields.'];
        }

        try {
            if (class_exists('AioSSL\Service\ProductMapService')) {
                $service = new \AioSSL\Service\ProductMapService();
                $service->setMapping($canonicalId, $providerSlug, $productCode ?: '');
                return ['success' => true, 'message' => 'Mapping saved.'];
            }

            // Fallback: direct DB update
            $column = $providerSlug . '_code';
            $allowed = ['nicsrs_code', 'gogetssl_code', 'thesslstore_code', 'ssl2buy_code'];
            if (!in_array($column, $allowed)) {
                return ['success' => false, 'message' => 'Invalid provider slug.'];
            }

            Capsule::table('mod_aio_ssl_product_map')
                ->where('canonical_id', $canonicalId)
                ->update([$column => $productCode ?: null]);

            if ($productCode) {
                Capsule::table('mod_aio_ssl_products')
                    ->where('provider_slug', $providerSlug)
                    ->where('product_code', $productCode)
                    ->update(['canonical_id' => $canonicalId]);
            }

            ActivityLogger::log('mapping_updated', 'product', $canonicalId, "Mapped {$providerSlug}: {$productCode}");
            return ['success' => true, 'message' => 'Mapping saved.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── AJAX: Auto-Map ────────────────────────────────────────────

    private function autoMap(): array
    {
        try {
            if (!class_exists('AioSSL\Service\ProductMapService')) {
                return ['success' => false, 'message' => 'ProductMapService not available.'];
            }
            $service = new \AioSSL\Service\ProductMapService();
            $result = $service->autoMap();

            return [
                'success' => true,
                'message' => "Auto-mapped {$result['mapped']} products. {$result['unmapped']} remain unmapped.",
                'mapped'  => $result['mapped'],
                'unmapped'=> $result['unmapped'],
                'details' => $result['details'] ?? [],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Auto-map failed: ' . $e->getMessage()];
        }
    }

    // ─── AJAX: Create Canonical ────────────────────────────────────

    private function createCanonical(): array
    {
        $name = $this->input('name');
        $vendor = $this->input('vendor');

        if (empty($name) || empty($vendor)) {
            return ['success' => false, 'message' => 'Product name and vendor are required.'];
        }

        try {
            if (class_exists('AioSSL\Service\ProductMapService')) {
                $service = new \AioSSL\Service\ProductMapService();
                $id = $service->createCanonical([
                    'canonical_id'   => $this->input('canonical_id') ?: null,
                    'name'           => $name,
                    'vendor'         => $vendor,
                    'validation_type'=> $this->input('validation_type', 'dv'),
                    'product_type'   => $this->input('product_type', 'ssl'),
                ]);
                return ['success' => true, 'message' => "Canonical entry created: {$id}", 'canonical_id' => $id];
            }

            // Fallback
            $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', "{$vendor}-{$name}"));
            Capsule::table('mod_aio_ssl_product_map')->insert([
                'canonical_id'    => $id,
                'canonical_name'  => $name,
                'vendor'          => $vendor,
                'validation_type' => $this->input('validation_type', 'dv'),
                'product_type'    => $this->input('product_type', 'ssl'),
                'is_active'       => 1,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            return ['success' => true, 'message' => "Created: {$id}", 'canonical_id' => $id];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}