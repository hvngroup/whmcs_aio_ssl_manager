<?php

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
            default:
                return parent::handleAjax($action);
        }
    }

    private function renderList(): void
    {
        $page = $this->getCurrentPage();
        $providerFilter = $this->input('provider', '');
        $vendorFilter = $this->input('vendor', '');
        $validationFilter = $this->input('validation', '');
        $search = $this->input('search', '');

        $q = Capsule::table('mod_aio_ssl_products');

        if ($providerFilter) {
            $q->where('provider_slug', $providerFilter);
        }
        if ($vendorFilter) {
            $q->where('vendor', $vendorFilter);
        }
        if ($validationFilter) {
            $q->where('validation_type', $validationFilter);
        }
        if ($search) {
            $q->where(function ($q2) use ($search) {
                $q2->where('product_name', 'LIKE', "%{$search}%")
                   ->orWhere('product_code', 'LIKE', "%{$search}%");
            });
        }

        $total = $q->count();
        $pagination = $this->paginate($total, $page);

        $products = (clone $q)
            ->orderBy('vendor')->orderBy('product_name')
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->get()
            ->toArray();

        // Unique vendors and providers for filters
        $vendors = Capsule::table('mod_aio_ssl_products')
            ->select('vendor')
            ->distinct()
            ->pluck('vendor')
            ->toArray();

        $providers = ProviderRegistry::getAllRecords(true);

        $this->renderTemplate('products.tpl', [
            'products'    => $products,
            'pagination'  => $pagination,
            'vendors'     => $vendors,
            'providers'   => $providers,
            'filters'     => compact('providerFilter', 'vendorFilter', 'validationFilter', 'search'),
        ]);
    }

    private function renderMapping(): void
    {
        $mappings = Capsule::table('mod_aio_ssl_product_map')
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get()
            ->toArray();

        // Unmapped products
        $unmapped = Capsule::table('mod_aio_ssl_products')
            ->whereNull('canonical_id')
            ->orWhere('canonical_id', '')
            ->orderBy('provider_slug')
            ->orderBy('product_name')
            ->get()
            ->toArray();

        $this->renderTemplate('product_mapping.tpl', [
            'mappings' => $mappings,
            'unmapped' => $unmapped,
        ]);
    }

    private function syncProducts(): array
    {
        $providerSlug = $this->input('provider', '');

        try {
            if (class_exists('AioSSL\Service\SyncService')) {
                $syncService = new \AioSSL\Service\SyncService();
                $result = $syncService->syncProducts($providerSlug ?: null);
                return ['success' => true, 'message' => 'Product sync completed.', 'result' => $result];
            }
            return ['success' => false, 'message' => 'SyncService not available.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    private function saveMapping(): array
    {
        $canonicalId = $this->input('canonical_id');
        $providerSlug = $this->input('provider_slug');
        $productCode = $this->input('product_code');

        if (empty($canonicalId) || empty($providerSlug)) {
            return ['success' => false, 'message' => 'Missing required fields.'];
        }

        try {
            $column = $providerSlug . '_code';
            $allowedColumns = ['nicsrs_code', 'gogetssl_code', 'thesslstore_code', 'ssl2buy_code'];

            if (!in_array($column, $allowedColumns)) {
                return ['success' => false, 'message' => 'Invalid provider slug.'];
            }

            Capsule::table('mod_aio_ssl_product_map')
                ->where('canonical_id', $canonicalId)
                ->update([$column => $productCode ?: null]);

            // Update product's canonical_id
            if ($productCode) {
                Capsule::table('mod_aio_ssl_products')
                    ->where('provider_slug', $providerSlug)
                    ->where('product_code', $productCode)
                    ->update(['canonical_id' => $canonicalId]);
            }

            ActivityLogger::log('mapping_updated', 'product', $canonicalId, "Mapped {$providerSlug}: {$productCode}");
            return ['success' => true, 'message' => 'Mapping saved.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Save failed: ' . $e->getMessage()];
        }
    }
}