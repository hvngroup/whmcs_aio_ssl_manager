<?php
/**
 * Product Service
 * Handles product-related business logic and data operations
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;

class ProductService
{
    /**
     * @var NicsrsApiService API service
     */
    private $apiService;

    /**
     * Constructor
     * 
     * @param NicsrsApiService $apiService API service instance
     */
    public function __construct(NicsrsApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Sync products from NicSRS API
     * 
     * @param string|null $vendor Filter by vendor
     * @return array Result with count of synced products
     * @throws \Exception On error
     */
    public function syncFromApi(?string $vendor = null): array
    {
        $result = $this->apiService->productList($vendor);
        
        if (!isset($result['code']) || $result['code'] != 1) {
            $errorMsg = isset($result['msg']) ? $result['msg'] : 'Unknown API error';
            throw new \Exception("API Error: {$errorMsg}");
        }

        if (!isset($result['data']) || !is_array($result['data'])) {
            return ['count' => 0];
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($result['data'] as $product) {
            if (!isset($product['code'])) {
                continue;
            }

            // Prepare product data
            $productData = [
                'product_code' => $product['code'],
                'product_name' => $product['productName'] ?? $product['code'],
                'vendor' => $vendor ?? ($product['vendor'] ?? 'Unknown'),
                'validation_type' => $this->normalizeValidationType($product['validationType'] ?? 'dv'),
                'support_wildcard' => $this->normalizeBoolean($product['supportWildcard'] ?? 'N'),
                'support_san' => $this->normalizeBoolean($product['supportSan'] ?? 'N'),
                'max_domains' => (int) ($product['maxDomain'] ?? 1),
                'max_years' => (int) ($product['maxYear'] ?? 1),
                'price_data' => json_encode($product['price'] ?? []),
                'last_sync' => $now,
                'updated_at' => $now,
            ];

            // Upsert product
            $exists = Capsule::table('mod_nicsrs_products')
                ->where('product_code', $product['code'])
                ->exists();

            if ($exists) {
                Capsule::table('mod_nicsrs_products')
                    ->where('product_code', $product['code'])
                    ->update($productData);
            } else {
                $productData['created_at'] = $now;
                Capsule::table('mod_nicsrs_products')->insert($productData);
            }

            $count++;
        }

        return ['count' => $count];
    }

    /**
     * Get product by code
     * 
     * @param string $productCode Product code
     * @return object|null Product object or null
     */
    public function getByCode(string $productCode)
    {
        return Capsule::table('mod_nicsrs_products')
            ->where('product_code', $productCode)
            ->first();
    }

    /**
     * Get products by vendor
     * 
     * @param string $vendor Vendor name
     * @return array Products
     */
    public function getByVendor(string $vendor): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->where('vendor', $vendor)
            ->orderBy('product_name')
            ->get()
            ->toArray();
    }

    /**
     * Get products by validation type
     * 
     * @param string $type Validation type (dv, ov, ev)
     * @return array Products
     */
    public function getByValidationType(string $type): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->where('validation_type', strtolower($type))
            ->orderBy('vendor')
            ->orderBy('product_name')
            ->get()
            ->toArray();
    }

    /**
     * Get all unique vendors
     * 
     * @return array Vendor names
     */
    public function getVendors(): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->distinct()
            ->orderBy('vendor')
            ->pluck('vendor')
            ->toArray();
    }

    /**
     * Get product count by vendor
     * 
     * @return array Vendor => count mapping
     */
    public function getCountByVendor(): array
    {
        return Capsule::table('mod_nicsrs_products')
            ->selectRaw('vendor, COUNT(*) as count')
            ->groupBy('vendor')
            ->pluck('count', 'vendor')
            ->toArray();
    }

    /**
     * Get product price
     * 
     * @param string $productCode Product code
     * @param int $years Number of years
     * @param int $sanCount Number of SANs
     * @return float|null Price or null if not found
     */
    public function getPrice(string $productCode, int $years = 1, int $sanCount = 0): ?float
    {
        $product = $this->getByCode($productCode);
        
        if (!$product) {
            return null;
        }

        $priceData = json_decode($product->price_data, true);
        
        if (!$priceData) {
            return null;
        }

        // Calculate period key
        $periodKey = 'price0' . str_pad($years * 12, 2, '0', STR_PAD_LEFT);
        
        // Get base price
        $basePrice = 0;
        if (isset($priceData['basePrice'][$periodKey])) {
            $basePrice = (float) $priceData['basePrice'][$periodKey];
        }
        
        // Add SAN price if applicable
        $sanPrice = 0;
        if ($sanCount > 0 && isset($priceData['sanPrice'][$periodKey])) {
            $sanPrice = (float) $priceData['sanPrice'][$periodKey] * $sanCount;
        }

        return $basePrice + $sanPrice;
    }

    /**
     * Delete outdated products (not synced for X days)
     * 
     * @param int $days Days threshold
     * @return int Number of deleted products
     */
    public function deleteOutdated(int $days = 30): int
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return Capsule::table('mod_nicsrs_products')
            ->where('last_sync', '<', $threshold)
            ->delete();
    }

    /**
     * Normalize validation type to standard format
     * 
     * @param string $type Input type
     * @return string Normalized type (dv, ov, ev)
     */
    private function normalizeValidationType(string $type): string
    {
        $type = strtolower(trim($type));
        
        $mapping = [
            'dv' => 'dv',
            'domain' => 'dv',
            'domain validation' => 'dv',
            'ov' => 'ov',
            'organization' => 'ov',
            'organization validation' => 'ov',
            'ev' => 'ev',
            'extended' => 'ev',
            'extended validation' => 'ev',
        ];
        
        return isset($mapping[$type]) ? $mapping[$type] : 'dv';
    }

    /**
     * Normalize boolean value
     * 
     * @param mixed $value Input value
     * @return int 0 or 1
     */
    private function normalizeBoolean($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        
        if (is_string($value)) {
            $value = strtoupper(trim($value));
            return in_array($value, ['Y', 'YES', 'TRUE', '1']) ? 1 : 0;
        }
        
        return $value ? 1 : 0;
    }
}