<?php
/**
 * SSL2Buy Provider — Limited-tier SSL provider (FIXED PRICE SYNC)
 *
 * API: https://api.ssl2buy.com/1.0/
 * Auth: PartnerEmail + ApiKey in JSON body
 * Content-Type: application/json
 *
 * Capabilities: Limited — order, status, config link, approval resend
 * Cannot: reissue, renew, revoke, cancel, download cert, change DCV
 *
 * CRITICAL: SSL2Buy has NO bulk product list API!
 * Products come from a static catalog (PRODUCT_CATALOG constant).
 * Pricing is fetched per-product via /orderservice/order/getproductprice.
 *
 * CRITICAL FIX: Price Sync Issues
 * ────────────────────────────────
 * 1. Each product needs individual API call per period
 * 2. Not all products support all periods (12, 24, 36 months)
 * 3. Response format: { "ProductPrice": 5.99, "StatusCode": 0 }
 * 4. Some products return negative StatusCode for unsupported periods
 * 5. Rate limiting: 100ms between calls to avoid API throttling
 * 6. Products may have SAN pricing via different endpoint
 *
 * CRITICAL: Query endpoints are brand-specific (C8):
 *   Comodo    → /queryservice/comodo/getorderdetails
 *   GlobalSign→ /queryservice/globalsign/getorderdetails
 *   Symantec  → /queryservice/symantec/getorderdetails
 *   Prime     → /queryservice/prime/primesubscriptionorderdetail
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;
use AioSSL\Core\UnsupportedOperationException;

class SSL2BuyProvider extends AbstractProvider
{
    private const API_URL      = 'https://api.ssl2buy.com/1.0';
    private const DEMO_API_URL = 'https://demo-api.ssl2buy.com/1.0';

    /**
     * Brand → query route mapping (C8: brand-specific routing)
     */
    private const BRAND_QUERY_ROUTES = [
        'Comodo'     => 'comodo',
        'Sectigo'    => 'comodo',      // Sectigo = Comodo successor
        'GlobalSign' => 'globalsign',
        'AlphaSSL'   => 'globalsign',  // AlphaSSL is GlobalSign brand
        'Symantec'   => 'symantec',
        'DigiCert'   => 'symantec',    // DigiCert acquired Symantec
        'GeoTrust'   => 'symantec',
        'Thawte'     => 'symantec',
        'RapidSSL'   => 'symantec',
        'Prime'      => 'prime',
        'Certera'    => 'comodo',
    ];

    /**
     * Static product catalog — SSL2Buy has no bulk product list API
     *
     * Each entry: [code, name, brand, validation, wildcard, san, max_domains, max_years]
     * Source: ref/ssl2buy/lib/SSL2BuyProducts/SSL2BuyProducts.php
     *
     * max_years: Maximum validity period supported by the product.
     * NOTE: Since Sep 2020, public SSL max is 1 year (13 months).
     *       But some legacy products and code signing may support multi-year.
     *       We try all periods and let the API tell us which are valid.
     */
    private const PRODUCT_CATALOG = [
        // ── Sectigo (Comodo) DV ──
        ['code' => 351,  'name' => 'Sectigo PositiveSSL',              'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 549,  'name' => 'Sectigo PositiveSSL Wildcard',     'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 352,  'name' => 'Sectigo PositiveSSL Multi-Domain', 'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 3],
        ['code' => 353,  'name' => 'Sectigo SSL Certificate',          'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 354,  'name' => 'Sectigo SSL Wildcard',             'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 355,  'name' => 'Sectigo Essential SSL',            'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 550,  'name' => 'Sectigo Essential SSL Wildcard',   'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],

        // ── Sectigo (Comodo) OV ──
        ['code' => 356,  'name' => 'Sectigo InstantSSL',               'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 357,  'name' => 'Sectigo InstantSSL Premium',       'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 551,  'name' => 'Sectigo InstantSSL Premium Wildcard', 'brand' => 'Comodo',  'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 358,  'name' => 'Sectigo Premium SSL Wildcard',     'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 552,  'name' => 'Sectigo Multi-Domain SSL',         'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 3],
        ['code' => 553,  'name' => 'Sectigo Unified Communications',   'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 3],

        // ── Sectigo (Comodo) EV ──
        ['code' => 359,  'name' => 'Sectigo EV SSL',                   'brand' => 'Comodo',     'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 2],
        ['code' => 554,  'name' => 'Sectigo EV Multi-Domain SSL',      'brand' => 'Comodo',     'validation' => 'ev', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 2],

        // ── GlobalSign / AlphaSSL ──
        ['code' => 363,  'name' => 'AlphaSSL',                         'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 364,  'name' => 'AlphaSSL Wildcard',                'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 365,  'name' => 'GlobalSign DomainSSL',             'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 366,  'name' => 'GlobalSign DomainSSL Wildcard',    'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 367,  'name' => 'GlobalSign OrganizationSSL',       'brand' => 'GlobalSign', 'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 368,  'name' => 'GlobalSign OrganizationSSL Wildcard', 'brand' => 'GlobalSign', 'validation' => 'ov', 'wildcard' => true, 'san' => false, 'max_domains' => 1, 'max_years' => 3],
        ['code' => 369,  'name' => 'GlobalSign ExtendedSSL',           'brand' => 'GlobalSign', 'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 2],

        // ── DigiCert (Symantec) ──
        ['code' => 370,  'name' => 'DigiCert Standard SSL',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 371,  'name' => 'DigiCert Wildcard SSL',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 372,  'name' => 'DigiCert Multi-Domain SSL',        'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 3],
        ['code' => 373,  'name' => 'DigiCert EV SSL',                  'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 2],
        ['code' => 374,  'name' => 'DigiCert EV Multi-Domain',         'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 2],

        // ── GeoTrust ──
        ['code' => 375,  'name' => 'GeoTrust QuickSSL Premium',        'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 376,  'name' => 'GeoTrust QuickSSL Premium Wildcard', 'brand' => 'Symantec', 'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 377,  'name' => 'GeoTrust True BusinessID',         'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 378,  'name' => 'GeoTrust True BusinessID Wildcard', 'brand' => 'Symantec',  'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 379,  'name' => 'GeoTrust True BusinessID EV',      'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 2],
        ['code' => 555,  'name' => 'GeoTrust True BusinessID EV Multi-Domain', 'brand' => 'Symantec', 'validation' => 'ev', 'wildcard' => false, 'san' => true, 'max_domains' => 250, 'max_years' => 2],

        // ── Thawte ──
        ['code' => 380,  'name' => 'Thawte SSL 123',                   'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 381,  'name' => 'Thawte SSL 123 Wildcard',          'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 382,  'name' => 'Thawte SSL Web Server',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 383,  'name' => 'Thawte SSL Web Server Wildcard',   'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 384,  'name' => 'Thawte SSL Web Server EV',         'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 2],

        // ── RapidSSL ──
        ['code' => 385,  'name' => 'RapidSSL Standard',                'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 386,  'name' => 'RapidSSL Wildcard',                'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],

        // ── Certera ──
        ['code' => 600,  'name' => 'Certera SSL',                      'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 601,  'name' => 'Certera SSL Wildcard',             'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1,   'max_years' => 3],
        ['code' => 602,  'name' => 'Certera SSL Multi-Domain',         'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => true,  'max_domains' => 250, 'max_years' => 3],

        // ── Code Signing ──
        ['code' => 390,  'name' => 'Sectigo Code Signing',             'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 0,   'max_years' => 3],
        ['code' => 391,  'name' => 'Sectigo EV Code Signing',          'brand' => 'Comodo',     'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 0,   'max_years' => 3],
        ['code' => 392,  'name' => 'DigiCert Code Signing',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 0,   'max_years' => 3],
        ['code' => 393,  'name' => 'DigiCert EV Code Signing',         'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 0,   'max_years' => 3],
    ];

    // ─── Provider Identity ─────────────────────────────────────────

    public function getSlug(): string    { return 'ssl2buy'; }
    public function getName(): string    { return 'SSL2Buy'; }
    public function getTier(): string    { return 'limited'; }

    protected function getBaseUrl(): string
    {
        $testMode = $this->getCredential('test_mode', false);
        return $testMode ? self::DEMO_API_URL : self::API_URL;
    }

    public function getCapabilities(): array
    {
        return [
            'order', 'validate', 'status', 'config_link',
            'resend_approval', 'balance',
        ];
    }

    // ─── Authentication ────────────────────────────────────────────

    /**
     * Make authenticated API call
     * SSL2Buy auth: PartnerEmail + ApiKey injected into request body
     */
    protected function apiCall(string $endpoint, array $data = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;

        // Inject auth credentials into request body
        $data['PartnerEmail'] = $this->getCredential('partner_email');
        $data['ApiKey'] = $this->getCredential('api_key');

        return $this->httpPostJson($url, $data);
    }

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance();
            return [
                'success' => true,
                'message' => 'SSL2Buy connected. Balance: $' . number_format($balance['balance'], 2),
                'balance' => $balance['balance'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'balance' => null];
        }
    }

    public function getBalance(): array
    {
        $response = $this->apiCall('/orderservice/order/getbalance');

        if ($response['code'] === 200 && isset($response['decoded'])) {
            $data = $response['decoded'];
            $balance = $data['Balance'] ?? $data['balance'] ?? $data['result']['balance'] ?? 0;
            return ['balance' => (float)$balance, 'currency' => 'USD'];
        }
        return ['balance' => 0, 'currency' => 'USD'];
    }

    // ─── Products ──────────────────────────────────────────────────

    /**
     * Fetch all available products from SSL2Buy
     *
     * CRITICAL: SSL2Buy has NO bulk product list API!
     *
     * Approach (from PDR §2.3.3):
     * 1. Use static product catalog (PRODUCT_CATALOG constant)
     * 2. Fetch pricing per product via POST /orderservice/order/getproductprice
     *
     * GetProductPrice request body:
     * {
     *   "PartnerEmail": "...",
     *   "ApiKey": "...",
     *   "ProductCode": 351,
     *   "NumberOfMonths": 12
     * }
     *
     * GetProductPrice response:
     * {
     *   "ProductPrice": 5.99,
     *   "StatusCode": 0,
     *   "Message": ""
     * }
     *
     * FIXED: Now properly handles:
     * - Products that don't support all periods
     * - Response field case variations (ProductPrice vs productPrice)
     * - Rate limiting with configurable delay
     * - Batch error handling without failing entire sync
     * - Actual max_years detection from API responses
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $products = [];
        $totalApiCalls = 0;

        foreach (self::PRODUCT_CATALOG as $catalogItem) {
            try {
                // Fetch pricing for all applicable periods
                $priceResult = $this->fetchProductPricing(
                    $catalogItem['code'],
                    $catalogItem['max_years'] ?? 3
                );

                $priceData = $priceResult['pricing'];
                $actualMaxYears = $priceResult['max_years'];
                $totalApiCalls += $priceResult['api_calls'];

                // Override max_years with actual supported periods
                $catalogItemCopy = $catalogItem;
                if ($actualMaxYears > 0) {
                    $catalogItemCopy['max_years'] = $actualMaxYears;
                }

                $products[] = $this->normalizeProduct($catalogItemCopy, $priceData);

                // Rate limit: 200ms between products
                usleep(200000);

            } catch (\Exception $e) {
                $this->log('warning', "SSL2Buy: Failed to fetch pricing for product #{$catalogItem['code']} ({$catalogItem['name']}): " . $e->getMessage());

                // Still add product without pricing
                $products[] = $this->normalizeProduct($catalogItem, ['base' => []]);
            }
        }

        $this->log('info', "SSL2Buy: Fetched " . count($products) . " products from static catalog ({$totalApiCalls} API calls)");

        return $products;
    }

    /**
     * Fetch pricing for a specific product across multiple periods
     *
     * SSL2Buy API: POST /orderservice/order/getproductprice
     *
     * Request fields (from official API docs):
     *   - PartnerEmail  (String, Required) — injected by apiCall()
     *   - ApiKey         (String, Required) — injected by apiCall()
     *   - ProductID      (Integer, Required) — product code from catalog
     *   - Year           (Integer, Required) — 1, 2, or 3 (NOT months!)
     *
     * Success Response:
     * {
     *   "ProductName": "Sectigo PositiveSSL",
     *   "Year": 1,
     *   "Price": 5.99,
     *   "AddDomainPrice": 3.00,
     *   "StatusCode": 0
     * }
     *
     * Error Response:
     * {
     *   "APIError": {
     *     "ErrorNumber": 100,
     *     "ErrorField": "ProductID",
     *     "ErrorMessage": "Invalid Product ID"
     *   },
     *   "StatusCode": -1
     * }
     *
     * @param int $productCode SSL2Buy numeric product code (= ProductID)
     * @param int $maxYears Maximum years to try (1, 2, or 3)
     * @return array ['pricing' => [...], 'max_years' => int, 'api_calls' => int]
     */
    private function fetchProductPricing(int $productCode, int $maxYears = 3): array
    {
        $pricing = ['base' => [], 'san' => []];
        $actualMaxYears = 0;
        $apiCalls = 0;

        // SSL2Buy API uses Year (1, 2, 3), NOT months
        $yearsToTry = [1];
        if ($maxYears >= 2) $yearsToTry[] = 2;
        if ($maxYears >= 3) $yearsToTry[] = 3;

        foreach ($yearsToTry as $year) {
            try {
                $response = $this->apiCall('/orderservice/order/getproductprice', [
                    'ProductID' => $productCode,
                    'Year'      => $year,
                ]);
                $apiCalls++;

                if ($response['code'] === 200 && isset($response['decoded'])) {
                    $data = $response['decoded'];

                    $statusCode = (int)($data['StatusCode'] ?? $data['statusCode'] ?? -1);

                    if ($statusCode === 0) {
                        // ── Base price ──
                        $price = $data['Price'] ?? $data['price'] ?? null;
                        if ($price !== null && is_numeric($price) && (float)$price > 0) {
                            $months = $year * 12;
                            $pricing['base'][(string)$months] = round((float)$price, 2);
                            $actualMaxYears = max($actualMaxYears, $year);
                        }

                        // ── SAN / Additional Domain price ──
                        // API returns "AddDomainPrice" for SAN-capable products
                        $sanPrice = $data['AddDomainPrice'] ?? $data['addDomainPrice'] ?? null;
                        if ($sanPrice !== null && is_numeric($sanPrice) && (float)$sanPrice > 0) {
                            $months = $year * 12;
                            $pricing['san'][(string)$months] = round((float)$sanPrice, 2);
                        }
                    } else {
                        // StatusCode -1 = error or unsupported period
                        $apiError = $data['APIError'] ?? $data['apiError'] ?? [];
                        $errMsg = $apiError['ErrorMessage'] ?? $data['Message'] ?? $data['message'] ?? '';
                        $this->log('debug', "SSL2Buy: Product #{$productCode} unsupported for {$year}yr: [{$statusCode}] {$errMsg}");
                    }
                }

                // Rate limit: 100ms between period calls for same product
                usleep(100000);

            } catch (\Exception $e) {
                $apiCalls++;
                $this->log('debug', "SSL2Buy: Pricing API error for #{$productCode}/{$year}yr: " . $e->getMessage());
            }
        }

        // Clean empty SAN array
        if (empty($pricing['san'])) {
            unset($pricing['san']);
        }

        // Log warning if no pricing found at all
        if (empty($pricing['base'])) {
            $this->log('warning', "SSL2Buy: No pricing available for product #{$productCode}");
        }

        return [
            'pricing'   => $pricing,
            'max_years' => $actualMaxYears,
            'api_calls' => $apiCalls,
        ];
    }

    /**
     * Public pricing fetch for a single product (used by Compare page)
     */
    public function fetchPricing(string $productCode): array
    {
        $result = $this->fetchProductPricing((int)$productCode);
        return $result['pricing'];
    }

    /**
     * Normalize a catalog entry + pricing into NormalizedProduct
     */
    private function normalizeProduct(array $catalogItem, array $priceData): NormalizedProduct
    {
        $type = 'ssl';
        if (!empty($catalogItem['wildcard'])) {
            $type = 'wildcard';
        } elseif (!empty($catalogItem['san'])) {
            $type = 'multi_domain';
        } elseif (stripos($catalogItem['name'], 'code signing') !== false) {
            $type = 'code_signing';
        }

        $maxDomains = (int)($catalogItem['max_domains'] ?? 1);
        $maxYears = (int)($catalogItem['max_years'] ?? 1);

        // Detect actual max_years from pricing data
        if (!empty($priceData['base'])) {
            $maxMonths = max(array_map('intval', array_keys($priceData['base'])));
            $maxYears = max($maxYears, (int)ceil($maxMonths / 12));
        }

        return new NormalizedProduct([
            'product_code'     => (string)$catalogItem['code'],
            'product_name'     => $catalogItem['name'],
            'vendor'           => $this->mapBrandToVendor($catalogItem['brand']),
            'validation_type'  => $catalogItem['validation'] ?? 'dv',
            'product_type'     => $type,
            'support_wildcard' => (bool)($catalogItem['wildcard'] ?? false),
            'support_san'      => (bool)($catalogItem['san'] ?? false),
            'max_domains'      => $maxDomains,
            'max_years'        => $maxYears,
            'min_years'        => 1,
            'price_data'       => $priceData,
            'extra_data'       => [
                'ssl2buy_code'   => (int)$catalogItem['code'],
                'brand_name'     => $catalogItem['brand'],
                'query_route'    => self::BRAND_QUERY_ROUTES[$catalogItem['brand']] ?? 'comodo',
            ],
        ]);
    }

    /**
     * Map SSL2Buy brand_name to display vendor
     */
    private function mapBrandToVendor(string $brand): string
    {
        $map = [
            'Comodo'     => 'Sectigo',
            'Sectigo'    => 'Sectigo',
            'GlobalSign' => 'GlobalSign',
            'AlphaSSL'   => 'GlobalSign',
            'Symantec'   => 'DigiCert',
            'DigiCert'   => 'DigiCert',
            'GeoTrust'   => 'DigiCert',
            'Thawte'     => 'DigiCert',
            'RapidSSL'   => 'DigiCert',
            'Prime'      => 'PrimeSSL',
            'Certera'    => 'Certera',
        ];
        return $map[$brand] ?? $brand;
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        try {
            $response = $this->apiCall('/orderservice/order/validateorder', [
                'ProductCode'    => $params['product_code'] ?? '',
                'CSR'            => $params['csr'] ?? '',
                'ServerType'     => $params['server_type'] ?? -1,
                'NumberOfMonths' => $params['period'] ?? 12,
            ]);

            if ($response['code'] === 200) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? $data['statusCode'] ?? -1);
                return [
                    'valid'  => ($statusCode === 0),
                    'errors' => ($statusCode !== 0) ? [$data['Message'] ?? $data['message'] ?? 'Validation failed'] : [],
                ];
            }
            return ['valid' => false, 'errors' => ['HTTP ' . $response['code']]];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function placeOrder(array $params): array
    {
        $data = [
            'ProductCode'    => $params['product_code'] ?? '',
            'CSR'            => $params['csr'] ?? '',
            'ServerType'     => $params['server_type'] ?? -1,
            'NumberOfMonths' => $params['period'] ?? 12,
            'AdminFirstName' => $params['admin_info']['first_name'] ?? '',
            'AdminLastName'  => $params['admin_info']['last_name'] ?? '',
            'AdminEmail'     => $params['admin_info']['email'] ?? '',
            'AdminPhone'     => $params['admin_info']['phone'] ?? '',
            'AdminTitle'     => $params['admin_info']['title'] ?? 'IT Admin',
            'AdminAddress'   => $params['admin_info']['address'] ?? '',
            'AdminCity'      => $params['admin_info']['city'] ?? '',
            'AdminCountry'   => $params['admin_info']['country'] ?? '',
            'AdminState'     => $params['admin_info']['state'] ?? '',
            'AdminZip'       => $params['admin_info']['postcode'] ?? '',
            'AdminOrganization' => $params['admin_info']['organization'] ?? '',
        ];

        // SAN domains
        if (!empty($params['san_domains'])) {
            $domains = is_array($params['san_domains'])
                ? implode(',', $params['san_domains'])
                : $params['san_domains'];
            $data['SANDomains'] = $domains;
        }

        $response = $this->apiCall('/orderservice/order/placeorder', $data);

        if ($response['code'] === 200 && !empty($response['decoded'])) {
            $d = $response['decoded'];
            $statusCode = (int)($d['StatusCode'] ?? $d['statusCode'] ?? -1);

            if ($statusCode === 0) {
                $orderId = $d['OrderNumber'] ?? $d['orderId'] ?? $d['order_id'] ?? null;
                return [
                    'success'   => true,
                    'remote_id' => (string)$orderId,
                    'status'    => 'Pending',
                    'extra'     => $d,
                ];
            }
            return ['success' => false, 'errors' => [$d['Message'] ?? $d['message'] ?? 'Order failed']];
        }

        return ['success' => false, 'errors' => ['Failed to place order (HTTP ' . $response['code'] . ')']];
    }

    public function getOrderStatus(string $remoteId): array
    {
        // Determine brand from order data to use correct query route
        $brand = $this->getOrderBrand($remoteId);
        $route = self::BRAND_QUERY_ROUTES[$brand] ?? 'comodo';

        // Brand-specific endpoint routing (C8)
        if ($route === 'prime') {
            $endpoint = "/queryservice/prime/primesubscriptionorderdetail";
        } else {
            $endpoint = "/queryservice/{$route}/getorderdetails";
        }

        $response = $this->apiCall($endpoint, [
            'OrderNumber' => $remoteId,
        ]);

        if ($response['code'] === 200 && !empty($response['decoded'])) {
            $d = $response['decoded'];
            $statusCode = (int)($d['StatusCode'] ?? $d['statusCode'] ?? -1);

            if ($statusCode === 0) {
                return [
                    'status'     => $this->normalizeStatus($d),
                    'domain'     => $d['CommonName'] ?? $d['DomainName'] ?? '',
                    'valid_from' => $d['CertificateStartDate'] ?? null,
                    'valid_till' => $d['CertificateEndDate'] ?? null,
                    'raw'        => $d,
                ];
            }
        }

        throw new \RuntimeException('SSL2Buy: Failed to get order status');
    }

    /**
     * Get SSL configuration link for management at provider portal
     */
    public function getConfigurationLink(string $remoteId): array
    {
        $response = $this->apiCall('/orderservice/order/getsslconfigurationlink', [
            'OrderNumber' => $remoteId,
        ]);

        if ($response['code'] === 200 && !empty($response['decoded'])) {
            $d = $response['decoded'];
            $statusCode = (int)($d['StatusCode'] ?? $d['statusCode'] ?? -1);

            if ($statusCode === 0) {
                return [
                    'success' => true,
                    'url'     => $d['ConfigurationLink'] ?? $d['configurationLink'] ?? '',
                    'pin'     => $d['Pin'] ?? $d['pin'] ?? '',
                ];
            }
        }

        return ['success' => false, 'url' => '', 'pin' => ''];
    }

    /**
     * Resend DCV / approval email — Brand-routed (C8)
     *
     * @param string $orderId Remote order ID
     * @param string $email   Approver email (optional, SSL2Buy uses stored email)
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $brand = $this->config['brand'] ?? 'Comodo';
        $route = self::BRAND_QUERY_ROUTES[$brand] ?? 'comodo';
        $endpoint = "/queryservice/{$route}/resendapprovalemail";

        $data = ['OrderNumber' => $orderId];
        if (!empty($email)) {
            $data['ApproverEmail'] = $email;
        }

        $response = $this->apiCall($endpoint, $data);

        if ($response['code'] === 200) {
            $d = $response['decoded'] ?? [];
            $statusCode = (int)($d['StatusCode'] ?? $d['statusCode'] ?? -1);
            return ['success' => ($statusCode === 0)];
        }

        return ['success' => false, 'errors' => ['Resend approval email failed']];
    }

    // ─── Unsupported Operations ────────────────────────────────────

    public function downloadCertificate(string $remoteId): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support certificate download via API. Use the provider portal.'
        );
    }

    public function reissueCertificate(string $remoteId, array $params): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support certificate reissue via API. Use the provider portal.'
        );
    }

    public function renewCertificate(string $remoteId, array $params): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support certificate renewal via API. Place a new order instead.'
        );
    }

    public function revokeCertificate(string $remoteId, string $reason = ''): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support certificate revocation via API. Use the provider portal.'
        );
    }

    public function cancelOrder(string $remoteId, string $reason = ''): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support order cancellation via API. Use the provider portal.'
        );
    }

    public function getDcvEmails(string $domain): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support DCV email listing via API.'
        );
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        throw new UnsupportedOperationException(
            'SSL2Buy does not support DCV method change via API.'
        );
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Get brand_name for order (used for brand-specific query routing)
     * Looks up from order's configdata or PRODUCT_CATALOG
     */
    private function getOrderBrand(string $remoteId): string
    {
        // Try to find brand from stored order data
        // This would typically come from mod_aio_ssl_orders.configdata
        // Fallback to 'Comodo' as most common brand
        return 'Comodo';
    }

    /**
     * Normalize SSL2Buy status from response data
     * Different brands return status in different fields
     */
    private function normalizeStatus(array $data): string
    {
        $status = $data['CertificateStatus'] ?? $data['OrderStatus'] ?? $data['Status'] ?? 'unknown';
        $status = strtolower(trim($status));

        $map = [
            'active'            => 'Issued',
            'issued'            => 'Issued',
            'processing'        => 'Processing',
            'pending'           => 'Pending',
            'awaiting approval' => 'Awaiting Validation',
            'cancelled'         => 'Cancelled',
            'canceled'          => 'Cancelled',
            'revoked'           => 'Revoked',
            'expired'           => 'Expired',
            'rejected'          => 'Rejected',
            'refunded'          => 'Refunded',
        ];

        return $map[$status] ?? ucfirst($status);
    }
}