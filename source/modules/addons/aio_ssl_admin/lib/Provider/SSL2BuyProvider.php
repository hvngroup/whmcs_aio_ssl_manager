<?php
/**
 * SSL2Buy Provider — Limited-tier SSL provider
 *
 * API: https://api.ssl2buy.com/1.0/
 * Auth: PartnerEmail + ApiKey in JSON body
 * Content-Type: application/json
 *
 * Capabilities: Limited — order, status, config link, approval resend
 * Cannot: reissue, renew, revoke, cancel, download cert, change DCV
 *
 * CRITICAL: SSL2Buy has NO bulk product list API!
 * Products come from a static catalog (SSL2BuyProducts class).
 * Pricing is fetched per-product via /orderservice/order/getproductprice.
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
     * Each entry: [code, name, brand_name, validation, wildcard, san, max_domains]
     * Source: ref/ssl2buy/lib/SSL2BuyProducts/SSL2BuyProducts.php
     */
    private const PRODUCT_CATALOG = [
        // ── Sectigo (Comodo) DV ──
        ['code' => 351,  'name' => 'Sectigo PositiveSSL',              'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 549,  'name' => 'Sectigo PositiveSSL Wildcard',     'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 352,  'name' => 'Sectigo PositiveSSL Multi-Domain', 'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => true,  'max_domains' => 250],
        ['code' => 360,  'name' => 'Sectigo EssentialSSL',             'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 361,  'name' => 'Sectigo EssentialSSL Wildcard',    'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 550,  'name' => 'Sectigo SSL Certificate',          'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 547,  'name' => 'Sectigo SSL Wildcard',             'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],

        // ── Sectigo (Comodo) OV ──
        ['code' => 362,  'name' => 'Sectigo InstantSSL',               'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 551,  'name' => 'Sectigo InstantSSL Premium',       'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 553,  'name' => 'Sectigo InstantSSL Premium Wildcard', 'brand' => 'Comodo',  'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 355,  'name' => 'Sectigo OV SSL',                   'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 554,  'name' => 'Sectigo OV Wildcard SSL',          'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 356,  'name' => 'Sectigo Multi-Domain SSL',         'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250],
        ['code' => 555,  'name' => 'Sectigo UCC SSL',                  'brand' => 'Comodo',     'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250],

        // ── Sectigo (Comodo) EV ──
        ['code' => 357,  'name' => 'Sectigo EV SSL',                   'brand' => 'Comodo',     'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 358,  'name' => 'Sectigo EV Multi-Domain SSL',      'brand' => 'Comodo',     'validation' => 'ev', 'wildcard' => false, 'san' => true,  'max_domains' => 250],

        // ── GlobalSign ──
        ['code' => 401,  'name' => 'GlobalSign DomainSSL',             'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 402,  'name' => 'GlobalSign DomainSSL Wildcard',    'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 403,  'name' => 'GlobalSign OrganizationSSL',       'brand' => 'GlobalSign', 'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 404,  'name' => 'GlobalSign OrganizationSSL Wildcard', 'brand' => 'GlobalSign', 'validation' => 'ov', 'wildcard' => true, 'san' => false, 'max_domains' => 1],
        ['code' => 405,  'name' => 'GlobalSign ExtendedSSL',           'brand' => 'GlobalSign', 'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],

        // ── AlphaSSL (GlobalSign) ──
        ['code' => 411,  'name' => 'AlphaSSL',                         'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 412,  'name' => 'AlphaSSL Wildcard',                'brand' => 'GlobalSign', 'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],

        // ── DigiCert ──
        ['code' => 501,  'name' => 'DigiCert Standard SSL',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 502,  'name' => 'DigiCert EV SSL',                  'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 503,  'name' => 'DigiCert Secure Site SSL',         'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 504,  'name' => 'DigiCert Secure Site Pro SSL',     'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 505,  'name' => 'DigiCert Secure Site EV SSL',      'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 506,  'name' => 'DigiCert Secure Site Pro EV SSL',  'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 507,  'name' => 'DigiCert Wildcard SSL',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 508,  'name' => 'DigiCert Multi-Domain SSL',        'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => true,  'max_domains' => 250],

        // ── GeoTrust ──
        ['code' => 601,  'name' => 'GeoTrust QuickSSL Premium',        'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 602,  'name' => 'GeoTrust True BusinessID',         'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 603,  'name' => 'GeoTrust True BusinessID Wildcard','brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 604,  'name' => 'GeoTrust True BusinessID EV',      'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],

        // ── RapidSSL ──
        ['code' => 611,  'name' => 'RapidSSL Standard',                'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 612,  'name' => 'RapidSSL Wildcard',                'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],

        // ── Thawte ──
        ['code' => 621,  'name' => 'Thawte SSL 123',                   'brand' => 'Symantec',   'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 622,  'name' => 'Thawte SSL Web Server',            'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 623,  'name' => 'Thawte SSL Web Server Wildcard',   'brand' => 'Symantec',   'validation' => 'ov', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 624,  'name' => 'Thawte SSL Web Server EV',         'brand' => 'Symantec',   'validation' => 'ev', 'wildcard' => false, 'san' => false, 'max_domains' => 1],

        // ── Certera ──
        ['code' => 701,  'name' => 'Certera SSL',                      'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => false, 'max_domains' => 1],
        ['code' => 702,  'name' => 'Certera Wildcard SSL',             'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => true,  'san' => false, 'max_domains' => 1],
        ['code' => 703,  'name' => 'Certera Multi-Domain SSL',         'brand' => 'Comodo',     'validation' => 'dv', 'wildcard' => false, 'san' => true,  'max_domains' => 250],
    ];

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string  { return 'ssl2buy'; }
    public function getName(): string  { return 'SSL2Buy'; }
    public function getTier(): string  { return 'limited'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'config_link', 'balance',
            'dcv_email', // approval resend only
        ];
    }

    protected function getBaseUrl(): string
    {
        // SSL2Buy uses demo URL for test mode
        $testMode = $this->config['test_mode'] ?? false;
        return $testMode ? self::DEMO_API_URL : self::API_URL;
    }

    // ─── Auth (JSON Body) ──────────────────────────────────────────

    /**
     * Make API call to SSL2Buy
     *
     * All requests: POST with JSON body containing PartnerEmail + ApiKey
     */
    private function apiCall(string $endpoint, array $data = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;

        // SSL2Buy auth: PartnerEmail + ApiKey in request body
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
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $products = [];

        foreach (self::PRODUCT_CATALOG as $catalogItem) {
            try {
                // Fetch pricing for multiple periods
                $priceData = $this->fetchProductPricing($catalogItem['code']);

                $products[] = $this->normalizeProduct($catalogItem, $priceData);

                // Rate limit: 200ms between API calls
                usleep(200000);

            } catch (\Exception $e) {
                $this->log('warning', "SSL2Buy: Failed to fetch pricing for product #{$catalogItem['code']}: " . $e->getMessage());

                // Still add product without pricing
                $products[] = $this->normalizeProduct($catalogItem, ['base' => []]);
            }
        }

        $this->log('info', 'SSL2Buy: Fetched ' . count($products) . ' products from static catalog');

        return $products;
    }

    /**
     * Fetch pricing for a specific product across multiple periods
     *
     * @param int $productCode SSL2Buy numeric product code
     * @return array Normalized pricing ['base' => ['12' => float, '24' => float, ...]]
     */
    private function fetchProductPricing(int $productCode): array
    {
        $pricing = ['base' => []];

        // Fetch for 12, 24, 36 month periods
        foreach ([12, 24, 36] as $months) {
            try {
                $response = $this->apiCall('/orderservice/order/getproductprice', [
                    'ProductCode'    => $productCode,
                    'NumberOfMonths' => $months,
                ]);

                if ($response['code'] === 200 && isset($response['decoded'])) {
                    $data = $response['decoded'];
                    $statusCode = $data['StatusCode'] ?? $data['statusCode'] ?? -1;

                    if ($statusCode == 0 && isset($data['ProductPrice'])) {
                        $pricing['base'][(string)$months] = (float)$data['ProductPrice'];
                    }
                }

                usleep(100000); // 100ms between period calls

            } catch (\Exception $e) {
                // Skip this period, continue with others
                $this->log('debug', "SSL2Buy: Pricing failed for #{$productCode}/{$months}mo: " . $e->getMessage());
            }
        }

        return $pricing;
    }

    public function fetchPricing(string $productCode): array
    {
        return $this->fetchProductPricing((int)$productCode);
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
                $statusCode = $data['StatusCode'] ?? -1;
                return [
                    'valid'  => ($statusCode == 0),
                    'errors' => ($statusCode != 0) ? [$data['Message'] ?? 'Validation failed'] : [],
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
            'ApproverEmail'  => $params['dcv_email'] ?? '',
            'WebServerType'  => $params['server_type'] ?? '',
        ];

        if (isset($params['domains'])) {
            $data['DomainNames'] = is_array($params['domains'])
                ? implode(',', $params['domains'])
                : $params['domains'];
        }

        // Admin contact
        if (isset($params['admin_contact'])) {
            $data['AdminContact'] = $params['admin_contact'];
        }

        $response = $this->apiCall('/orderservice/order/placeorder', $data);

        if ($response['code'] !== 200) {
            $msg = $response['decoded']['Message'] ?? 'Order failed';
            throw new \RuntimeException("SSL2Buy placeOrder: {$msg}");
        }

        $result = $response['decoded'];
        $statusCode = $result['StatusCode'] ?? -1;

        if ($statusCode != 0) {
            throw new \RuntimeException("SSL2Buy: " . ($result['Message'] ?? 'Order failed'));
        }

        $orderId = $result['OrderNumber'] ?? $result['orderId'] ?? $result['order_id'] ?? '';

        $this->log('info', 'SSL2Buy order placed', ['order_id' => $orderId]);

        return [
            'order_id' => (string)$orderId,
            'status'   => 'Pending',
            'extra'    => $result,
        ];
    }

    /**
     * Get order status — Brand-specific routing (C8)
     */
    public function getOrderStatus(string $orderId): array
    {
        // Try to determine brand from stored order data
        $brand = $this->config['brand'] ?? 'Comodo';
        $brandRoute = self::BRAND_QUERY_ROUTES[$brand] ?? 'comodo';

        // Prime uses different endpoint
        if ($brandRoute === 'prime') {
            $endpoint = "/queryservice/prime/primesubscriptionorderdetail";
        } else {
            $endpoint = "/queryservice/{$brandRoute}/getorderdetails";
        }

        $response = $this->apiCall($endpoint, [
            'OrderNumber' => $orderId,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException("SSL2Buy: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded'];

        return [
            'status'      => $this->normalizeStatus($data['OrderStatus'] ?? $data['Status'] ?? ''),
            'certificate' => null, // SSL2Buy: cert managed via config link
            'domains'     => isset($data['DomainName']) ? [$data['DomainName']] : [],
            'begin_date'  => $data['CertificateStartDate'] ?? $data['StartDate'] ?? null,
            'end_date'    => $data['CertificateEndDate'] ?? $data['EndDate'] ?? null,
            'extra'       => $data,
        ];
    }

    /**
     * Get SSL2Buy configuration link — primary management method for limited tier
     */
    public function getConfigurationLink(string $orderId): array
    {
        $response = $this->apiCall('/orderservice/order/getsslconfigurationlink', [
            'OrderNumber' => $orderId,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException('SSL2Buy: Failed to get configuration link');
        }

        $data = $response['decoded'];

        return [
            'link' => $data['ConfigurationLink'] ?? $data['configLink'] ?? '',
            'pin'  => $data['Pin'] ?? $data['pin'] ?? '',
        ];
    }

    // ─── DCV (Limited) ─────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        // SSL2Buy does not have a DCV emails endpoint
        return [];
    }

    /**
     * Resend approval email — Brand-routed (C8)
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $brand = $this->config['brand'] ?? 'Comodo';
        $brandRoute = self::BRAND_QUERY_ROUTES[$brand] ?? 'comodo';

        $response = $this->apiCall("/queryservice/{$brandRoute}/resendapprovalemail", [
            'OrderNumber' => $orderId,
        ]);

        $success = ($response['code'] === 200 &&
            ($response['decoded']['StatusCode'] ?? -1) == 0);

        return [
            'success' => $success,
            'message' => $success ? 'Approval email resent.' : 'Failed to resend.',
        ];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        throw new UnsupportedOperationException($this->getName(), 'changeDcvMethod');
    }

    // ─── Unsupported Operations ────────────────────────────────────

    public function downloadCertificate(string $orderId): array
    {
        throw new UnsupportedOperationException($this->getName(), 'downloadCertificate');
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        throw new UnsupportedOperationException($this->getName(), 'reissueCertificate');
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        throw new UnsupportedOperationException($this->getName(), 'renewCertificate');
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        throw new UnsupportedOperationException($this->getName(), 'revokeCertificate');
    }

    public function cancelOrder(string $orderId): array
    {
        throw new UnsupportedOperationException($this->getName(), 'cancelOrder');
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Normalize product from static catalog + fetched pricing
     *
     * @param array $catalogItem Static catalog entry
     * @param array $priceData   Fetched pricing data
     * @return NormalizedProduct
     */
    private function normalizeProduct(array $catalogItem, array $priceData): NormalizedProduct
    {
        $name = $catalogItem['name'];
        $code = (string)$catalogItem['code'];

        // Determine product type
        $type = 'ssl';
        if ($catalogItem['wildcard']) {
            $type = 'wildcard';
        } elseif ($catalogItem['san']) {
            $type = 'multi_domain';
        }

        return new NormalizedProduct([
            'product_code'     => $code,  // SSL2Buy uses numeric code as string
            'product_name'     => $name,
            'vendor'           => $this->mapBrandToVendor($catalogItem['brand']),
            'validation_type'  => $catalogItem['validation'],
            'product_type'     => $type,
            'support_wildcard' => $catalogItem['wildcard'],
            'support_san'      => $catalogItem['san'],
            'max_domains'      => $catalogItem['max_domains'],
            'max_years'        => 3,
            'min_years'        => 1,
            'price_data'       => $priceData,
            'extra_data'       => [
                'ssl2buy_code'  => (int)$catalogItem['code'],
                'brand_name'    => $catalogItem['brand'],
                'brand_route'   => self::BRAND_QUERY_ROUTES[$catalogItem['brand']] ?? 'comodo',
            ],
        ]);
    }

    /**
     * Map SSL2Buy internal brand names to vendor display names
     */
    private function mapBrandToVendor(string $brand): string
    {
        $map = [
            'Comodo'     => 'Sectigo',
            'GlobalSign' => 'GlobalSign',
            'Symantec'   => 'DigiCert',
            'Prime'      => 'PrimeSSL',
        ];
        return $map[$brand] ?? $brand;
    }

    /**
     * Normalize SSL2Buy status strings
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'          => 'Issued',
            'issued'          => 'Issued',
            'completed'       => 'Issued',
            'pending'         => 'Pending',
            'processing'      => 'Processing',
            'initial'         => 'Pending',
            'cancelled'       => 'Cancelled',
            'revoked'         => 'Revoked',
            'expired'         => 'Expired',
            'rejected'        => 'Rejected',
            'waiting_approval'=> 'Awaiting Approval',
        ];

        return $map[strtolower(trim($status))] ?? $status;
    }
}