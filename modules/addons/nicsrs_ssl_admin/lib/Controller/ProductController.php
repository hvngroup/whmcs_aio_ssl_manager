<?php
/**
 * Product Controller
 * Handles product list display and synchronization
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\NicsrsApiService;
use NicsrsAdmin\Service\ProductService;
use NicsrsAdmin\Helper\Pagination;

class ProductController extends BaseController
{
    /**
     * @var NicsrsApiService API service
     */
    private $apiService;
    
    /**
     * @var ProductService Product service
     */
    private $productService;

    /**
     * @var array Available vendors
     */
    private $vendors = [
        'Sectigo', 
        'Positive',
        'DigiCert', 
        'GlobalSign', 
        'GeoTrust', 
        'Thawte',
        'RapidSSL',
        'sslTrus', 
        'Entrust',
        'BaiduTrust',
    ];

    /**
     * Constructor
     * 
     * @param array $vars Module variables
     */
    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->apiService = new NicsrsApiService($this->getApiToken());
        $this->productService = new ProductService($this->apiService);
    }

    /**
     * Render products page
     * 
     * @param string $action Current action
     * @return void
     */
    public function render(string $action): void
    {
        $page = $this->getCurrentPage();
        $perPage = $this->getItemsPerPage();
        $vendor = isset($_GET['vendor']) ? $this->sanitize($_GET['vendor']) : '';
        $type = isset($_GET['type']) ? $this->sanitize($_GET['type']) : '';
        $search = isset($_GET['search']) ? $this->sanitize($_GET['search']) : '';
        $linkedFilter = isset($_GET['linked']) ? $_GET['linked'] : '';

        // Build query
        $query = Capsule::table('mod_nicsrs_products');
        
        if ($vendor) {
            $query->where('vendor', $vendor);
        }
        if ($type) {
            $query->where('validation_type', $type);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        // Filter by linked status
        if ($linkedFilter !== '') {
            $linkedCodes = $this->getLinkedProductCodes();
            if ($linkedFilter === '1') {
                $query->whereIn('product_code', $linkedCodes);
            } else {
                $query->whereNotIn('product_code', $linkedCodes);
            }
        }

        // Get total count
        $total = $query->count();

        // Get products
        $products = $query
            ->orderBy('vendor')
            ->orderBy('product_name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Get linked WHMCS products (keyed by product_code)
        $linkedProducts = $this->getLinkedWhmcsProducts();

        // Process products
        $processedProducts = [];
        foreach ($products as $product) {
            $priceData = json_decode($product->price_data, true) ?: [];
            
            // Check if linked to WHMCS product
            $linkedWhmcs = isset($linkedProducts[$product->product_code]) 
                ? $linkedProducts[$product->product_code] 
                : null;
            
            $processedProducts[] = [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'vendor' => $product->vendor,
                'validation_type' => $product->validation_type,
                'support_wildcard' => $product->support_wildcard,
                'support_san' => $product->support_san,
                'max_domains' => $product->max_domains,
                'max_years' => $product->max_years,
                'price_1y' => isset($priceData['basePrice']['price012']) ? $priceData['basePrice']['price012'] : null,
                'price_2y' => isset($priceData['basePrice']['price024']) ? $priceData['basePrice']['price024'] : null,
                'san_price' => isset($priceData['sanPrice']['price012']) ? $priceData['sanPrice']['price012'] : null,
                'last_sync' => $product->last_sync,
                // NEW: Linked WHMCS product info
                'linked_whmcs' => $linkedWhmcs,
                'is_linked' => !empty($linkedWhmcs),
            ];
        }

        // Get unique vendors from database
        $availableVendors = Capsule::table('mod_nicsrs_products')
            ->distinct()
            ->orderBy('vendor')
            ->pluck('vendor')
            ->toArray();

        // Create pagination with linked filter
        $paginationParams = [];
        if ($vendor) $paginationParams['vendor'] = $vendor;
        if ($type) $paginationParams['type'] = $type;
        if ($search) $paginationParams['search'] = $search;
        if ($linkedFilter !== '') $paginationParams['linked'] = $linkedFilter;
        
        $pagination = new Pagination(
            $total, 
            $perPage, 
            $page, 
            $this->modulelink . '&action=products',
            $paginationParams
        );

        // Get last sync time
        $lastSync = Capsule::table('mod_nicsrs_products')
            ->max('last_sync');

        // Get linked stats
        $linkedStats = $this->getLinkedStats();

        $data = [
            'products' => $processedProducts,
            'vendors' => $this->vendors,
            'availableVendors' => $availableVendors,
            'currentVendor' => $vendor,
            'currentType' => $type,
            'search' => $search,
            'linkedFilter' => $linkedFilter,
            'linkedStats' => $linkedStats,
            'pagination' => $pagination,
            'total' => $total,
            'lastSync' => $lastSync,
        ];

        $this->includeTemplate('products/list', $data);
    }

    /**
     * Get all WHMCS products using nicsrs_ssl server module
     * Returns array keyed by product_code (configoption1)
     * 
     * @return array
     */
    private function getLinkedWhmcsProducts(): array
    {
        $products = Capsule::table('tblproducts')
            ->select([
                'id',
                'name',
                'configoption1 as product_code',
                'hidden',
                'retired',
            ])
            ->where('servertype', 'nicsrs_ssl')
            ->whereNotNull('configoption1')
            ->where('configoption1', '!=', '')
            ->get();

        $result = [];
        foreach ($products as $product) {
            $result[$product->product_code] = $product;
        }
        
        return $result;
    }

    /**
     * Get array of product codes that are linked to WHMCS products
     * 
     * @return array
     */
    private function getLinkedProductCodes(): array
    {
        $configValues = Capsule::table('tblproducts')
            ->where('servertype', 'nicsrs_ssl')
            ->whereNotNull('configoption1')
            ->where('configoption1', '!=', '')
            ->pluck('configoption1')
            ->toArray();

        $productCodes = [];
        
        foreach ($configValues as $value) {
            // Check if it's a code (has dash, no space)
            if (strpos($value, '-') !== false && strpos($value, ' ') === false) {
                $productCodes[] = $value;
            } else {
                // It's a name, try to find the code
                $product = Capsule::table('mod_nicsrs_products')
                    ->where('product_name', $value)
                    ->first();
                
                if ($product) {
                    $productCodes[] = $product->product_code;
                }
            }
        }

        return array_unique($productCodes);
    }


    /**
     * Get linked/not-linked statistics
     * 
     * @return array
     */
    private function getLinkedStats(): array
    {
        $totalNicsrs = Capsule::table('mod_nicsrs_products')->count();
        $linkedCodes = $this->getLinkedProductCodes();
        
        $linkedCount = Capsule::table('mod_nicsrs_products')
            ->whereIn('product_code', $linkedCodes)
            ->count();
        
        return [
            'total' => $totalNicsrs,
            'linked' => $linkedCount,
            'not_linked' => $totalNicsrs - $linkedCount,
        ];
    }

    /**
     * Handle AJAX requests
     * 
     * @param array $post POST data
     * @return string JSON response
     */
    public function handleAjax(array $post): string
    {
        $action = isset($post['ajax_action']) ? $post['ajax_action'] : '';

        switch ($action) {
            case 'sync_vendor':
                $vendor = isset($post['vendor']) ? $this->sanitize($post['vendor']) : '';
                return $this->syncVendorProducts($vendor);

            case 'sync_all':
                return $this->syncAllProducts();

            case 'get_product_detail':
                $productId = isset($post['product_id']) ? (int) $post['product_id'] : 0;
                return $this->getProductDetail($productId);

            default:
                return $this->jsonError('Unknown action');
        }
    }

    /**
     * Sync products from specific vendor
     * 
     * @param string $vendor Vendor name
     * @return string JSON response
     */
    private function syncVendorProducts(string $vendor): string
    {
        if (empty($vendor)) {
            return $this->jsonError('Vendor is required');
        }

        try {
            $result = $this->productService->syncFromApi($vendor);
            
            // Log activity
            $this->logger->log('sync_products', 'product', null, null, json_encode([
                'vendor' => $vendor,
                'count' => $result['count'],
            ]));
            
            return $this->jsonSuccess(
                "Synced {$result['count']} products from {$vendor}",
                ['count' => $result['count']]
            );
        } catch (\Exception $e) {
            return $this->jsonError('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync products from all vendors
     * 
     * @return string JSON response
     */
    private function syncAllProducts(): string
    {
        $totalCount = 0;
        $errors = [];
        $vendorResults = [];

        foreach ($this->vendors as $vendor) {
            try {
                $result = $this->productService->syncFromApi($vendor);
                $totalCount += $result['count'];
                $vendorResults[$vendor] = $result['count'];
            } catch (\Exception $e) {
                $errors[] = "{$vendor}: {$e->getMessage()}";
                $vendorResults[$vendor] = 0;
            }
        }

        // Log activity
        $this->logger->log('sync_all_products', 'product', null, null, json_encode([
            'total' => $totalCount,
            'vendors' => $vendorResults,
            'errors' => $errors,
        ]));

        if (empty($errors)) {
            return $this->jsonSuccess(
                "Successfully synced {$totalCount} products from all vendors",
                ['total' => $totalCount, 'vendors' => $vendorResults]
            );
        }

        return $this->jsonResponse([
            'success' => $totalCount > 0,
            'message' => "Synced {$totalCount} products with some errors",
            'total' => $totalCount,
            'vendors' => $vendorResults,
            'errors' => $errors,
        ]);
    }

    /**
     * Get product detail
     * 
     * @param int $productId Product ID
     * @return string JSON response
     */
    private function getProductDetail(int $productId): string
    {
        $product = Capsule::table('mod_nicsrs_products')
            ->where('id', $productId)
            ->first();

        if (!$product) {
            return $this->jsonError('Product not found');
        }

        // Get linked WHMCS product if any
        $linkedWhmcs = Capsule::table('tblproducts')
            ->select(['id', 'name', 'hidden', 'retired'])
            ->where('servertype', 'nicsrs_ssl')
            ->where('configoption1', $product->product_code)
            ->first();

        $product->price_data = json_decode($product->price_data, true);
        $product->linked_whmcs = $linkedWhmcs;

        return $this->jsonSuccess('Product found', ['product' => $product]);
    }
}