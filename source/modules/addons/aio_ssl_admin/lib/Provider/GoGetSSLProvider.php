<?php
/**
 * GoGetSSL Provider — Full-tier SSL provider integration
 *
 * API Reference : GOGETSSL API (v1) Documentation
 * API Base      : https://my.gogetssl.com/api
 * Sandbox       : https://sandbox.gogetssl.com/api
 * Auth          : POST /auth → session key (expires 365 days, 401 = re-auth)
 * Content-Type  : application/x-www-form-urlencoded
 * Products      : Use NUMERIC IDs (not string codes)
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ API Coverage (31 methods)                                           │
 * ├──────────────────────────────────────────────────────────────────────┤
 * │ Auth       : auth                                                   │
 * │ Products   : getAllProducts, getSslProducts, getProductDetails,      │
 * │              getProductPrice, getAllPrices, getProductAgreement,     │
 * │              getWebServers                                          │
 * │ CSR/Tools  : decodeCSR, validateCSR, generateCSR,                   │
 * │              getDomainEmails, getDomainAlternative                  │
 * │ Account    : getAccountDetails, getAccountBalance, getTotalOrders   │
 * │ Orders     : addSSLOrder, addSSLRenewOrder, reissueSSLOrder,        │
 * │              addSanOrder, cancelOrder                               │
 * │ Query      : getOrderStatus, getOrderStatuses, getAllSSLOrders,     │
 * │              getOrderCommonDetails, getUnpaidOrders                 │
 * │ Download   : downloadCertificate                                    │
 * │ Validation : resendValidationEmail, changeValidationMethod,         │
 * │              changeDomainsValidationMethod, changeValidationEmail,  │
 * │              recheckCAA                                             │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;
use AioSSL\Core\UnsupportedOperationException;

class GoGetSSLProvider extends AbstractProvider
{
    /** @var string Production API base URL */
    private const API_URL = 'https://my.gogetssl.com/api';

    /** @var string Sandbox API base URL */
    private const SANDBOX_URL = 'https://sandbox.gogetssl.com/api';

    /** @var string|null Cached auth token */
    private ?string $authToken = null;

    /** @var int|null Auth timestamp for TTL tracking (365 days) */
    private ?int $authTimestamp = null;

    /** @var int Auth token TTL in seconds (365 days) */
    private const AUTH_TTL = 365 * 24 * 60 * 60;

    // ═══════════════════════════════════════════════════════════════
    //  IDENTITY
    // ═══════════════════════════════════════════════════════════════

    public function getSlug(): string  { return 'gogetssl'; }
    public function getName(): string  { return 'GoGetSSL'; }
    public function getTier(): string  { return 'full'; }

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox') ? self::SANDBOX_URL : self::API_URL;
    }

    public function getCapabilities(): array
    {
        return [
            'order', 'validate', 'status', 'download',
            'reissue', 'renew', 'cancel',
            'dcv_email', 'dcv_http', 'dcv_https', 'dcv_dns',
            'resend_dcv', 'change_dcv', 'change_dcv_batch',
            'get_dcv_emails', 'domain_alternative',
            'balance', 'account_details',
            'csr_decode', 'csr_validate', 'csr_generate',
            'webservers', 'add_san',
            'order_list', 'order_statuses_batch',
            'recheck_caa',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  AUTH
    // ═══════════════════════════════════════════════════════════════

    /**
     * Authenticate with GoGetSSL API
     *
     * API: POST /auth
     * Request: user, pass (form-urlencoded)
     * Response: { "key": "...", "success": true }
     * Token TTL: 365 days
     *
     * @return string Auth key
     * @throws \RuntimeException on failure
     */
    private function authenticate(): string
    {
        $url = $this->getBaseUrl() . '/auth';

        $response = $this->httpPost($url, [
            'user' => $this->getCredential('username'),
            'pass' => $this->getCredential('password'),
        ]);

        $decoded = $response['decoded'] ?? json_decode($response['body'] ?? '', true);

        if (empty($decoded['key'])) {
            $msg = $decoded['description'] ?? $decoded['message'] ?? 'Unknown auth error';
            throw new \RuntimeException('GoGetSSL auth failed: ' . $msg);
        }

        $this->authToken     = $decoded['key'];
        $this->authTimestamp = time();

        return $this->authToken;
    }

    /**
     * Check if current auth token is still valid (TTL-based)
     */
    private function isAuthValid(): bool
    {
        if (empty($this->authToken) || $this->authTimestamp === null) {
            return false;
        }
        return (time() - $this->authTimestamp) < self::AUTH_TTL;
    }

    /**
     * Make authenticated API call with auto-auth and 401 retry
     *
     * GoGetSSL passes auth_key as a query parameter (GET) or POST field.
     * On 401 or "auth_key_not_found" error → re-auth and retry once.
     *
     * @param string $endpoint  API path (e.g. '/products/ssl')
     * @param array  $data      Request parameters
     * @param string $method    HTTP method: 'GET' or 'POST'
     * @return array ['code' => int, 'decoded' => array|null, 'raw' => string]
     */
    protected function apiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        if (!$this->isAuthValid()) {
            $this->authenticate();
        }

        $url = rtrim($this->getBaseUrl(), '/') . $endpoint;

        // Inject auth_key
        $data['auth_key'] = $this->authToken;

        $response = ($method === 'POST')
            ? $this->httpPost($url, $data)
            : $this->httpGet($url, $data);

        $code    = (int)($response['code'] ?? 0);
        $decoded = $response['decoded'] ?? json_decode($response['body'] ?? '', true);

        // 401 or auth error → re-authenticate and retry once
        if ($code === 401
            || (isset($decoded['error']) && $decoded['error'] === 'auth_key_not_found')
        ) {
            $this->authToken = null;
            $this->authenticate();
            $data['auth_key'] = $this->authToken;

            $response = ($method === 'POST')
                ? $this->httpPost($url, $data)
                : $this->httpGet($url, $data);

            $code    = (int)($response['code'] ?? 0);
            $decoded = $response['decoded'] ?? json_decode($response['body'] ?? '', true);
        }

        return [
            'code'    => $code,
            'decoded' => $decoded,
            'raw'     => $response['body'] ?? '',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  CONNECTION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Test API connection
     *
     * Uses getAccountBalance to verify credentials
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance();
            return [
                'success' => true,
                'message' => 'GoGetSSL connected. Balance: $' . number_format($balance['balance'], 2),
                'balance' => $balance['balance'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'balance' => null];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  ACCOUNT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get account balance
     *
     * API: GET /account/balance
     * Response: { "balance": "123.45", "currency": "USD", "success": true }
     */
    public function getBalance(): array
    {
        $r = $this->apiCall('/account/balance');

        if ($r['code'] === 200 && isset($r['decoded']['balance'])) {
            return [
                'balance'  => (float)$r['decoded']['balance'],
                'currency' => $r['decoded']['currency'] ?? 'USD',
            ];
        }

        throw new \RuntimeException(
            'GoGetSSL getBalance failed: ' . ($r['decoded']['description'] ?? 'HTTP ' . $r['code'])
        );
    }

    /**
     * Get account details
     *
     * API: GET /account
     * Response: { "first_name", "last_name", "company_name", "email", "reseller_plan", ... }
     */
    public function getAccountDetails(): array
    {
        $r = $this->apiCall('/account');

        if ($r['code'] === 200 && !empty($r['decoded']['success'])) {
            return $r['decoded'];
        }

        throw new \RuntimeException(
            'GoGetSSL getAccountDetails failed: ' . ($r['decoded']['description'] ?? 'HTTP ' . $r['code'])
        );
    }

    /**
     * Get total active orders count
     *
     * API: GET /account/total_orders
     * Response: { "total_orders": 123, "success": true }
     */
    public function getTotalOrders(): int
    {
        $r = $this->apiCall('/account/total_orders');

        if ($r['code'] === 200 && isset($r['decoded']['total_orders'])) {
            return (int)$r['decoded']['total_orders'];
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRODUCTS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fetch all SSL products (normalized)
     *
     * API: GET /products/ssl
     * Uses getSslProducts for SSL-only, enriches with /products/all_prices
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $r = $this->apiCall('/products/ssl');

        if ($r['code'] !== 200 || !is_array($r['decoded'])) {
            throw new \RuntimeException('GoGetSSL: Failed to fetch products (HTTP ' . $r['code'] . ')');
        }

        $decoded     = $r['decoded'];
        $productList = $decoded['products'] ?? $decoded;

        if (!is_array($productList)) {
            throw new \RuntimeException('GoGetSSL: Invalid products response format');
        }

        $products = [];

        foreach ($productList as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            // CRITICAL: /products/ssl returns 'product' as name field,
            //           /products returns 'name'. Handle both.
            $productName = $item['product'] ?? $item['name'] ?? null;
            if (empty($productName)) {
                continue;
            }

            $vendor = $item['brand'] ?? $item['product_brand'] ?? 'Unknown';

            // ── Validation type: GoGetSSL returns 'domain'/'business'/'extended' ──
            $validationType = $this->normalizeValidationType($item['product_type'] ?? 'domain');

            // ── Product type + wildcard/SAN detection ──
            // /products/ssl uses: wildcard_enabled(1/0), san_enabled(1/0)
            // /products uses:     wildcard(yes/no), is_multidomain(yes/no)
            $isWildcard = $this->toBool($item['wildcard_enabled'] ?? $item['wildcard'] ?? false);
            $isSan      = $this->toBool($item['san_enabled'] ?? $item['is_multidomain'] ?? $item['multi_domain'] ?? false);

            $productType = 'ssl';
            if ($isWildcard && $isSan) {
                $productType = 'multi_domain'; // wildcard multi-domain
            } elseif ($isWildcard) {
                $productType = 'wildcard';
            } elseif ($isSan) {
                $productType = 'multi_domain';
            }

            // ── Pricing ──
            // /products/ssl prices: keys are MONTHS (12, 24, 36, 60)
            //   with nested 'vendor', 'san', 'wildcard_san' sub-arrays
            $priceData = ['base' => [], 'san' => [], 'wildcard_san' => []];

            if (isset($item['prices']) && is_array($item['prices'])) {
                foreach ($item['prices'] as $periodKey => $price) {
                    // Skip nested sub-arrays (vendor, san, wildcard_san)
                    if (!is_numeric($periodKey) || !is_numeric($price)) {
                        continue;
                    }
                    $periodKey = (int)$periodKey;
                    if ($periodKey <= 0) {
                        continue;
                    }
                    // /products/ssl keys are already MONTHS (12,24,36,48,60)
                    $priceData['base'][(string)$periodKey] = (float)$price;
                }

                // SAN prices nested under 'san' key
                if (isset($item['prices']['san']) && is_array($item['prices']['san'])) {
                    foreach ($item['prices']['san'] as $pk => $pr) {
                        if (!is_numeric($pk) || !is_numeric($pr)) continue;
                        $pk = (int)$pk;
                        if ($pk > 0) {
                            $priceData['san'][(string)$pk] = (float)$pr;
                        }
                    }
                }

                // Wildcard SAN prices
                if (isset($item['prices']['wildcard_san']) && is_array($item['prices']['wildcard_san'])) {
                    foreach ($item['prices']['wildcard_san'] as $pk => $pr) {
                        if (!is_numeric($pk) || !is_numeric($pr)) continue;
                        $pk = (int)$pk;
                        if ($pk > 0) {
                            $priceData['wildcard_san'][(string)$pk] = (float)$pr;
                        }
                    }
                }
            }

            // ── Max domains: /products/ssl uses 'san_max' or 'multidomains_maximum' ──
            $maxDomains = (int)($item['san_max']
                ?? $item['multidomains_maximum']
                ?? $item['max_domains']
                ?? 1);

            // ── Build NormalizedProduct with CORRECT field names ──
            $products[] = new NormalizedProduct([
                'product_code'     => (string)$item['id'],
                'product_name'     => $productName,
                'vendor'           => $vendor,
                'validation_type'  => $validationType,      // 'dv','ov','ev'
                'product_type'     => $productType,          // 'ssl','wildcard','multi_domain'
                'support_wildcard' => $isWildcard,            // bool
                'support_san'      => $isSan,                 // bool
                'max_domains'      => $maxDomains,
                'max_years'        => $this->periodToYears((int)($item['max_period'] ?? 24)),
                'min_years'        => $this->periodToYears((int)($item['terms_min'] ?? 12)),
                'price_data'       => $priceData,
                'extra_data'       => [
                    'gogetssl_id'        => (int)$item['id'],
                    'brand'              => $vendor,
                    'wildcard_san'       => $this->toBool($item['wildcard_san_enabled'] ?? $item['product_san_wildcard'] ?? false),
                    'dcv_email'          => $this->toBool($item['dcv_email'] ?? $item['product_dcv_email'] ?? true),
                    'dcv_dns'            => $this->toBool($item['dcv_dns'] ?? $item['product_dcv_dns'] ?? true),
                    'dcv_http'           => $this->toBool($item['dcv_http'] ?? $item['product_dcv_http'] ?? true),
                    'dcv_https'          => $this->toBool($item['dcv_https'] ?? $item['product_dcv_https'] ?? true),
                    'org_required'       => $this->toBool($item['org_required'] ?? false),
                    'unlimited_licencing'=> $this->toBool($item['unlimited_licencing'] ?? false),
                    'site_seal'          => $this->toBool($item['site_seal'] ?? false),
                    'free_reissues'      => $this->toBool($item['free_reissues'] ?? false),
                    'san_included'       => (int)($item['multidomains_included'] ?? $item['single_san_included'] ?? 0),
                    'wildcard_san_included' => (int)($item['wildcard_san_included'] ?? 0),
                ],
            ]);
        }

        // Enrich with detailed pricing from all_prices endpoint
        $this->enrichWithPricing($products);

        $this->log('info', 'GoGetSSL: Fetched ' . count($products) . ' products');

        return $products;
    }

    /**
     * Fetch pricing for a specific product
     *
     * API: GET /products/price/{product_id}
     * Response: { "product_price": [{ "price", "period", "id" }], "success": true }
     *
     * @param string $productCode Product ID (numeric)
     * @param int    $years       Requested years (returns all available)
     * @return array ['base' => [months => price], 'san' => [...]]
     */
    public function fetchPricing(string $productCode, int $years = 1): array
    {
        $r = $this->apiCall('/products/price/' . $productCode);

        if ($r['code'] !== 200 || empty($r['decoded'])) {
            return ['base' => [], 'san' => []];
        }

        $data   = $r['decoded'];
        $result = ['base' => [], 'san' => []];

        // product_price array
        if (isset($data['product_price']) && is_array($data['product_price'])) {
            foreach ($data['product_price'] as $pp) {
                $period = (int)($pp['period'] ?? 0);
                $price  = (float)($pp['price'] ?? 0);
                if ($period > 0) {
                    $result['base'][(string)$period] = $price;
                }
            }
        }

        return $result;
    }

    /**
     * Get detailed product info
     *
     * API: GET /products/details/{product_id}
     * Returns: brand, product type, DCV support, SAN config, prices, etc.
     */
    public function getProductDetails(string $productId): array
    {
        $r = $this->apiCall('/products/details/' . $productId);

        if ($r['code'] === 200 && !empty($r['decoded']['product'])) {
            return $r['decoded']['product'];
        }

        if ($r['code'] === 200 && !empty($r['decoded']['success'])) {
            return $r['decoded'];
        }

        throw new \RuntimeException('GoGetSSL: getProductDetails failed for ID ' . $productId);
    }

    /**
     * Get product agreement text
     *
     * API: GET /products/agreement/{product_id}
     * Response: { "product_id", "product_agreement", "success": true }
     */
    public function getProductAgreement(string $productId): string
    {
        $r = $this->apiCall('/products/agreement/' . $productId);

        if ($r['code'] === 200 && isset($r['decoded']['product_agreement'])) {
            return $r['decoded']['product_agreement'];
        }

        return '';
    }

    /**
     * Get all products (not SSL-only)
     *
     * API: GET /products
     */
    public function getAllProducts(): array
    {
        $r = $this->apiCall('/products');

        if ($r['code'] === 200 && is_array($r['decoded'])) {
            return $r['decoded']['products'] ?? $r['decoded'];
        }

        return [];
    }

    /**
     * Get web server types for a supplier
     *
     * API: GET /products/webservers/{supplier_id}
     *   supplier_id: 1 = Comodo/GGSSL, 2 = Geotrust/Symantec/Thawte/RapidSSL
     *
     * Response: { "webservers": [{ "id": 1, "software": "Apache" }], "success": true }
     *
     * @param int $supplierId 1 or 2
     * @return array [ ['id' => int, 'software' => string], ... ]
     */
    public function getWebServers(int $supplierId = 1): array
    {
        $r = $this->apiCall('/products/webservers/' . $supplierId);

        if ($r['code'] === 200 && isset($r['decoded']['webservers'])) {
            return $r['decoded']['webservers'];
        }

        return [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  CSR TOOLS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Decode CSR — returns parsed CSR data
     *
     * API: POST /tools/csr/decode (no auth required)
     * Request: csr (string, max 4000 chars)
     * Response: { "csrResult": { "CN", "C", "O", "OU", "L", "S", "Key Size", ... }, "success": true }
     */
    public function decodeCSR(string $csr): array
    {
        // This endpoint does NOT require auth_key
        $url = $this->getBaseUrl() . '/tools/csr/decode';

        $response = $this->httpPost($url, ['csr' => $csr]);
        $decoded  = $response['decoded'] ?? json_decode($response['body'] ?? '', true);

        if (!empty($decoded['csrResult'])) {
            return [
                'success' => true,
                'result'  => $decoded['csrResult'],
            ];
        }

        return [
            'success' => false,
            'message' => $decoded['description'] ?? $decoded['message'] ?? 'CSR decode failed',
        ];
    }

    /**
     * Validate CSR data
     *
     * API: POST /tools/csr/validate (no auth required)
     * Request: csr (string, max 4000 chars)
     * Response: { "csrResult": { ... }, "success": true }
     */
    public function validateCSR(string $csr): array
    {
        $url = $this->getBaseUrl() . '/tools/csr/validate';

        $response = $this->httpPost($url, ['csr' => $csr]);
        $decoded  = $response['decoded'] ?? json_decode($response['body'] ?? '', true);

        if (!empty($decoded['csrResult'])) {
            return [
                'valid'   => true,
                'details' => $decoded['csrResult'],
                'errors'  => [],
            ];
        }

        return [
            'valid'  => false,
            'errors' => [$decoded['description'] ?? $decoded['message'] ?? 'CSR validation failed'],
        ];
    }

    /**
     * Generate CSR and private key
     *
     * API: POST /tools/csr/generate (no auth required)
     * Request: csr_commonname, csr_organization, csr_department, csr_city,
     *          csr_state, csr_country, csr_email, signature_hash
     * Response: { "csr_code": "...", "csr_key": "...", "success": true }
     *
     * @param array $params Keys: domain, organization, department, city, state, country, email
     * @return array ['success' => bool, 'csr' => string, 'private_key' => string]
     */
    public function generateCSR(array $params): array
    {
        $url = $this->getBaseUrl() . '/tools/csr/generate';

        $response = $this->httpPost($url, [
            'csr_commonname'   => $params['domain'] ?? $params['csr_commonname'] ?? '',
            'csr_organization' => $params['organization'] ?? $params['csr_organization'] ?? '',
            'csr_department'   => $params['department'] ?? $params['csr_department'] ?? '',
            'csr_city'         => $params['city'] ?? $params['csr_city'] ?? '',
            'csr_state'        => $params['state'] ?? $params['csr_state'] ?? '',
            'csr_country'      => $params['country'] ?? $params['csr_country'] ?? '',
            'csr_email'        => $params['email'] ?? $params['csr_email'] ?? '',
            'signature_hash'   => $params['signature_hash'] ?? 'SHA2',
        ]);

        $decoded = $response['decoded'] ?? json_decode($response['body'] ?? '', true);

        if (!empty($decoded['csr_code']) && !empty($decoded['csr_key'])) {
            return [
                'success'     => true,
                'csr'         => $decoded['csr_code'],
                'private_key' => $decoded['csr_key'],
            ];
        }

        return [
            'success' => false,
            'message' => $decoded['description'] ?? $decoded['message'] ?? 'CSR generation failed',
        ];
    }

    /**
     * Get domain validation email addresses
     *
     * API: POST /tools/domain/emails
     * Request: domain (FQDN)
     * Response: { "ComodoApprovalEmails": [...], "GeotrustApprovalEmails": [...] }
     * Note: Both arrays are identical per docs.
     *
     * @param string $domain
     * @return array List of unique valid email addresses
     */
    public function getDcvEmails(string $domain): array
    {
        $r = $this->apiCall('/tools/domain/emails', ['domain' => $domain], 'POST');

        if ($r['code'] === 200 && isset($r['decoded'])) {
            $emails = [];

            if (!empty($r['decoded']['ComodoApprovalEmails'])) {
                $emails = array_merge($emails, (array)$r['decoded']['ComodoApprovalEmails']);
            }
            if (!empty($r['decoded']['GeotrustApprovalEmails'])) {
                $emails = array_merge($emails, (array)$r['decoded']['GeotrustApprovalEmails']);
            }

            return array_values(array_unique($emails));
        }

        return [];
    }

    /**
     * Get alternative DCV info (HTTP/HTTPS/DNS records)
     *
     * API: POST /tools/domain/alternative/
     * Only for GoGetSSL and Sectigo branded certificates.
     * Does NOT provide Unique Value.
     *
     * Request: csr
     * Response: { "http": { "link", "filename", "content" }, "https": {...}, "dns": { "record" } }
     */
    public function getDomainAlternative(string $csr): array
    {
        $r = $this->apiCall('/tools/domain/alternative/', ['csr' => $csr], 'POST');

        if ($r['code'] === 200 && !empty($r['decoded'])) {
            return $r['decoded'];
        }

        return [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  VALIDATION (ProviderInterface)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate order parameters via CSR validation
     *
     * Uses /tools/csr/validate (not /tools/csr/decode)
     */
    public function validateOrder(array $params): array
    {
        $csr = $params['csr'] ?? '';

        if (empty($csr)) {
            return ['valid' => false, 'errors' => ['CSR is required']];
        }

        return $this->validateCSR($csr);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ORDER LIFECYCLE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Place a new SSL certificate order
     *
     * API: POST /orders/add_ssl_order
     *
     * Required: product_id, period, csr, server_count, dcv_method, webserver_type,
     *           admin_firstname, admin_lastname, admin_phone, admin_email, admin_title
     * Optional: approver_email, dns_names, approver_emails, tech_*, org_*,
     *           signature_hash, unique_code, test
     *
     * Response: { "product_id", "order_id", "invoice_id", "order_status",
     *             "success", "order_amount", "currency", "approver_method", "san" }
     *
     * @param array $params Order parameters
     * @return array ['success' => bool, 'order_id' => string, ...]
     */
    public function placeOrder(array $params): array
    {
        // Resolve webserver_type based on brand
        $brand = strtolower($params['brand'] ?? '');
        $defaultWst = '-1';
        if (in_array($brand, ['geotrust', 'rapidssl', 'digicert', 'thawte', 'symantec'])) {
            $defaultWst = '18';
        }

        // Build order data
        $od = [
            // Required
            'product_id'         => (int)($params['product_code'] ?? $params['product_id'] ?? 0),
            'period'             => (int)($params['period'] ?? 12),
            'csr'                => $params['csr'] ?? '',
            'server_count'       => (int)($params['server_count'] ?? -1),
            'dcv_method'         => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
            'webserver_type'     => $params['webserver_type'] ?? $defaultWst,

            // Admin contact (required)
            'admin_firstname'    => $params['admin_firstname'] ?? '',
            'admin_lastname'     => $params['admin_lastname'] ?? '',
            'admin_phone'        => $params['admin_phone'] ?? '',
            'admin_email'        => $params['admin_email'] ?? '',
            'admin_title'        => $params['admin_title'] ?? 'Mr',
            'admin_organization' => $params['admin_organization'] ?? '',
            'admin_addressline1' => $params['admin_address1'] ?? $params['admin_addressline1'] ?? '',
            'admin_city'         => $params['admin_city'] ?? '',
            'admin_country'      => $params['admin_country'] ?? '',
            'admin_postalcode'   => $params['admin_postcode'] ?? $params['admin_postalcode'] ?? '',
            'admin_region'       => $params['admin_state'] ?? $params['admin_region'] ?? '',
        ];

        // Approver email (for email DCV)
        if (!empty($params['approver_email'])) {
            $od['approver_email'] = $params['approver_email'];
        }

        // Technical contact (optional, needed for OV/EV)
        foreach (['tech_firstname','tech_lastname','tech_phone','tech_email',
                   'tech_title','tech_organization','tech_addressline1',
                   'tech_city','tech_country','tech_postalcode','tech_region'] as $f) {
            if (!empty($params[$f])) {
                $od[$f] = $params[$f];
            }
        }

        // Organization details (required for OV/EV)
        foreach (['org_name','org_division','org_duns','org_addressline1',
                   'org_city','org_country','org_phone','org_postalcode',
                   'org_region','org_lei'] as $f) {
            if (!empty($params[$f])) {
                $od[$f] = $params[$f];
            }
        }

        // SAN domains (comma-separated)
        if (!empty($params['dns_names'])) {
            $od['dns_names'] = is_array($params['dns_names'])
                ? implode(',', array_map('trim', $params['dns_names']))
                : $params['dns_names'];
        } elseif (!empty($params['san_domains'])) {
            $sans = is_array($params['san_domains'])
                ? $params['san_domains']
                : explode("\n", $params['san_domains']);
            $od['dns_names'] = implode(',', array_map('trim', $sans));
        }

        // Per-SAN approver emails (count must match dns_names)
        if (!empty($params['approver_emails'])) {
            $od['approver_emails'] = is_array($params['approver_emails'])
                ? implode(',', $params['approver_emails'])
                : $params['approver_emails'];
        }

        // Optional fields
        if (!empty($params['signature_hash'])) {
            $od['signature_hash'] = $params['signature_hash'];
        }
        if (!empty($params['unique_code'])) {
            $od['unique_code'] = $params['unique_code'];
        }
        if (!empty($params['test'])) {
            $od['test'] = 'Y';
        }

        // API call
        $r = $this->apiCall('/orders/add_ssl_order', $od, 'POST');

        if ($r['code'] === 200 && !empty($r['decoded']['order_id'])) {
            $d = $r['decoded'];
            return [
                'success'         => true,
                'order_id'        => (string)$d['order_id'],
                'remote_id'       => (string)$d['order_id'],
                'invoice_id'      => (string)($d['invoice_id'] ?? ''),
                'order_status'    => $d['order_status'] ?? '',
                'order_amount'    => (float)($d['order_amount'] ?? 0),
                'currency'        => $d['currency'] ?? 'USD',
                'approver_method' => $d['approver_method'] ?? null,
                'san'             => $d['san'] ?? [],
                'message'         => 'Order placed successfully',
            ];
        }

        return [
            'success' => false,
            'message' => $r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Failed to place order',
        ];
    }

    /**
     * Get order status (detailed)
     *
     * API: GET /orders/status/{order_id}
     *
     * Response includes: order_id, partner_order_id, status, domain, valid_from,
     * valid_till, csr_code, ca_code, crt_code, dcv_method, san[], and more.
     */
    public function getOrderStatus(string $remoteId): array
    {
        $r = $this->apiCall('/orders/status/' . $remoteId);

        if ($r['code'] !== 200 || empty($r['decoded'])) {
            return ['status' => 'Unknown', 'raw' => $r['decoded'] ?? []];
        }

        $d = $r['decoded'];

        return [
            'status'                 => $this->normalizeStatus($d['status'] ?? 'unknown'),
            'status_description'     => $d['status_description'] ?? '',
            'order_id'               => (string)($d['order_id'] ?? $remoteId),
            'partner_order_id'       => $d['partner_order_id'] ?? '',
            'ca_order_id'            => $d['ca_order_id'] ?? '',
            'product_id'             => (string)($d['product_id'] ?? ''),
            'domain'                 => $d['domain'] ?? '',
            'total_domains'          => (int)($d['total_domains'] ?? 1),
            'validity_period'        => (int)($d['validity_period'] ?? 0),
            'valid_from'             => $d['valid_from'] ?? $d['begin_date'] ?? '',
            'valid_to'               => $d['valid_till'] ?? $d['end_date'] ?? '',
            'begin_date'             => $d['begin_date'] ?? '',
            'end_date'               => $d['end_date'] ?? '',
            'csr_code'               => $d['csr_code'] ?? '',
            'crt_code'               => $d['crt_code'] ?? '',
            'ca_code'                => $d['ca_code'] ?? '',
            'dcv_method'             => $d['dcv_method'] ?? '',
            'dcv_status'             => $d['dcv_status'] ?? '',
            'approver_email'         => $d['approver_email'] ?? '',
            'approver_method'        => $d['approver_method'] ?? null,
            'san_domains'            => $d['san'] ?? [],
            'reissue'                => (bool)($d['reissue'] ?? false),
            'reissue_now'            => (bool)($d['reissue_now'] ?? false),
            'renew'                  => (bool)($d['renew'] ?? false),
            'manual_check'           => (bool)($d['manual_check'] ?? false),
            'pre_signing'            => (bool)($d['pre_signing'] ?? false),
            'webserver_type'         => $d['webserver_type'] ?? '',
            'validation_description' => $d['validation_description'] ?? '',
            'raw'                    => $d,
        ];
    }

    /**
     * Get batch order statuses
     *
     * API: POST /orders/statuses
     * Request: cids (comma-separated order IDs)
     * Response: { "certificates": [{ "order_id", "status", "expires" }], "success": true }
     *
     * @param array $orderIds Array of order ID strings
     * @return array [ order_id => ['status' => ..., 'expires' => ...], ... ]
     */
    public function getOrderStatuses(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $r = $this->apiCall('/orders/statuses', [
            'cids' => implode(',', $orderIds),
        ], 'POST');

        if ($r['code'] !== 200 || empty($r['decoded']['certificates'])) {
            return [];
        }

        $result = [];
        foreach ($r['decoded']['certificates'] as $cert) {
            $oid = (string)($cert['order_id'] ?? '');
            if ($oid) {
                $result[$oid] = [
                    'status'  => $this->normalizeStatus($cert['status'] ?? 'unknown'),
                    'expires' => $cert['expires'] ?? '',
                ];
            }
        }

        return $result;
    }

    /**
     * Get all SSL orders (paginated)
     *
     * API: GET /orders/ssl/all
     * Params: limit, offset
     *
     * @param int $limit
     * @param int $offset
     * @return array ['orders' => [...], 'count' => int]
     */
    public function getAllSSLOrders(int $limit = 100, int $offset = 0): array
    {
        $r = $this->apiCall('/orders/ssl/all', [
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        if ($r['code'] === 200 && isset($r['decoded']['orders'])) {
            return [
                'orders' => $r['decoded']['orders'],
                'count'  => (int)($r['decoded']['count'] ?? count($r['decoded']['orders'])),
                'limit'  => (int)($r['decoded']['limit'] ?? $limit),
                'offset' => (int)($r['decoded']['offset'] ?? $offset),
            ];
        }

        return ['orders' => [], 'count' => 0];
    }

    /**
     * Get common order details (filterable by status)
     *
     * API: GET /orders?status={status}
     * Status: active, processing, expired, revoked, rejected
     *
     * @param string|null $status Filter status (null = all)
     * @return array
     */
    public function getOrderCommonDetails(?string $status = null): array
    {
        $params = [];
        if ($status !== null) {
            $params['status'] = $status;
        }

        $r = $this->apiCall('/orders', $params);

        if ($r['code'] === 200 && is_array($r['decoded'])) {
            return $r['decoded'];
        }

        return [];
    }

    /**
     * Get unpaid orders
     *
     * API: GET /orders/list/unpaid
     * Response: { "orders": [{ "id", "total_price", "currency", "date" }] }
     */
    public function getUnpaidOrders(): array
    {
        $r = $this->apiCall('/orders/list/unpaid');

        if ($r['code'] === 200 && isset($r['decoded']['orders'])) {
            return $r['decoded']['orders'];
        }

        return [];
    }

    /**
     * Download certificate
     *
     * API: GET /orders/ssl/download/{order_id}
     * Response: { "crt_code", "ca_code", "domain", "success": true }
     */
    public function downloadCertificate(string $remoteId): array
    {
        $r = $this->apiCall('/orders/ssl/download/' . $remoteId);

        if ($r['code'] === 200 && !empty($r['decoded'])) {
            $d = $r['decoded'];

            if (!empty($d['crt_code'])) {
                return [
                    'success'      => true,
                    'certificate'  => $d['crt_code'] ?? '',
                    'ca_bundle'    => $d['ca_code'] ?? '',
                    'intermediate' => $d['ca_code'] ?? '',
                    'domain'       => $d['domain'] ?? '',
                ];
            }
        }

        return ['success' => false, 'message' => 'Failed to download certificate'];
    }

    /**
     * Reissue SSL certificate
     *
     * API: POST /orders/ssl/reissue/{order_id}
     * Note: order_id is a URL parameter, NOT a body parameter.
     *
     * Request: csr, dcv_method, webserver_type, dns_names, approver_emails,
     *          approver_email, signature_hash, unique_code
     * Response: { "order_id", "order_status": "reissue", "validation", "success": true }
     */
    public function reissueCertificate(string $remoteId, array $params): array
    {
        $data = [
            'csr'        => $params['csr'] ?? '',
            'dcv_method' => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ];

        if (isset($params['webserver_type'])) {
            $data['webserver_type'] = $params['webserver_type'];
        }
        if (!empty($params['approver_email'])) {
            $data['approver_email'] = $params['approver_email'];
        }
        if (!empty($params['signature_hash'])) {
            $data['signature_hash'] = $params['signature_hash'];
        }
        if (!empty($params['unique_code'])) {
            $data['unique_code'] = $params['unique_code'];
        }

        // SAN domains
        if (!empty($params['san_domains'])) {
            $sans = is_array($params['san_domains'])
                ? $params['san_domains']
                : explode("\n", $params['san_domains']);
            $data['dns_names'] = implode(',', array_map('trim', $sans));
        }
        if (!empty($params['dns_names'])) {
            $data['dns_names'] = is_array($params['dns_names'])
                ? implode(',', array_map('trim', $params['dns_names']))
                : $params['dns_names'];
        }

        // Per-SAN approver emails
        if (!empty($params['approver_emails'])) {
            $data['approver_emails'] = is_array($params['approver_emails'])
                ? implode(',', $params['approver_emails'])
                : $params['approver_emails'];
        }

        // order_id in URL path (NOT in POST body)
        $r = $this->apiCall('/orders/ssl/reissue/' . $remoteId, $data, 'POST');

        if ($r['code'] === 200 && !empty($r['decoded']['order_id'])) {
            return [
                'success'    => true,
                'order_id'   => (string)$r['decoded']['order_id'],
                'status'     => $r['decoded']['order_status'] ?? 'reissue',
                'validation' => $r['decoded']['validation'] ?? null,
                'message'    => 'Reissue initiated successfully',
            ];
        }

        return [
            'success' => false,
            'message' => $r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Reissue failed',
        ];
    }

    /**
     * Renew SSL certificate
     *
     * API: POST /orders/add_ssl_renew_order
     * This is EXACTLY the same as addSSLOrder — requires ALL the same parameters.
     * Renewal can start max 30 days before expiration. CA adds remaining days.
     *
     * @param string $remoteId Original order ID
     * @param array  $params   Same params as placeOrder()
     */
    public function renewCertificate(string $remoteId, array $params): array
    {
        // Resolve webserver_type
        $brand = strtolower($params['brand'] ?? '');
        $defaultWst = '-1';
        if (in_array($brand, ['geotrust', 'rapidssl', 'digicert', 'thawte', 'symantec'])) {
            $defaultWst = '18';
        }

        $rd = [
            'product_id'         => (int)($params['product_code'] ?? $params['product_id'] ?? 0),
            'period'             => (int)($params['period'] ?? 12),
            'csr'                => $params['csr'] ?? '',
            'server_count'       => (int)($params['server_count'] ?? -1),
            'dcv_method'         => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
            'webserver_type'     => $params['webserver_type'] ?? $defaultWst,

            // Admin contact
            'admin_firstname'    => $params['admin_firstname'] ?? '',
            'admin_lastname'     => $params['admin_lastname'] ?? '',
            'admin_phone'        => $params['admin_phone'] ?? '',
            'admin_email'        => $params['admin_email'] ?? '',
            'admin_title'        => $params['admin_title'] ?? 'Mr',
            'admin_organization' => $params['admin_organization'] ?? '',
            'admin_addressline1' => $params['admin_address1'] ?? $params['admin_addressline1'] ?? '',
            'admin_city'         => $params['admin_city'] ?? '',
            'admin_country'      => $params['admin_country'] ?? '',
            'admin_postalcode'   => $params['admin_postcode'] ?? $params['admin_postalcode'] ?? '',
            'admin_region'       => $params['admin_state'] ?? $params['admin_region'] ?? '',
        ];

        if (!empty($params['approver_email'])) {
            $rd['approver_email'] = $params['approver_email'];
        }

        // Tech contact
        foreach (['tech_firstname','tech_lastname','tech_phone','tech_email',
                   'tech_title','tech_organization','tech_addressline1',
                   'tech_city','tech_country','tech_postalcode','tech_region'] as $f) {
            if (!empty($params[$f])) {
                $rd[$f] = $params[$f];
            }
        }

        // Org details
        foreach (['org_name','org_division','org_duns','org_addressline1',
                   'org_city','org_country','org_phone','org_postalcode',
                   'org_region','org_lei'] as $f) {
            if (!empty($params[$f])) {
                $rd[$f] = $params[$f];
            }
        }

        // SAN domains
        if (!empty($params['dns_names'])) {
            $rd['dns_names'] = is_array($params['dns_names'])
                ? implode(',', array_map('trim', $params['dns_names']))
                : $params['dns_names'];
        }
        if (!empty($params['approver_emails'])) {
            $rd['approver_emails'] = is_array($params['approver_emails'])
                ? implode(',', $params['approver_emails'])
                : $params['approver_emails'];
        }

        // CORRECT endpoint: /orders/add_ssl_renew_order (with _order suffix)
        $r = $this->apiCall('/orders/add_ssl_renew_order', $rd, 'POST');

        if ($r['code'] === 200 && !empty($r['decoded']['order_id'])) {
            return [
                'success'      => true,
                'order_id'     => (string)$r['decoded']['order_id'],
                'remote_id'    => (string)$r['decoded']['order_id'],
                'invoice_id'   => (string)($r['decoded']['invoice_id'] ?? ''),
                'order_status' => $r['decoded']['order_status'] ?? '',
                'order_amount' => (float)($r['decoded']['order_amount'] ?? 0),
                'message'      => 'Renewal order placed',
            ];
        }

        return [
            'success' => false,
            'message' => $r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Renewal failed',
        ];
    }

    /**
     * Add SAN items to an existing order
     *
     * API: POST /orders/add_ssl_san_order
     * Use reissueSSLOrder to reissue SSL with new SAN items after adding.
     *
     * Request: order_id, single_san_count (opt), wildcard_san_count (opt)
     * Response: { "invoice_id", "order_id", "order_status", "order_amount",
     *             "currency", "tax", "tax_rate", "success": true }
     */
    public function addSanOrder(string $remoteId, int $singleSanCount = 0, int $wildcardSanCount = 0): array
    {
        $data = ['order_id' => (int)$remoteId];

        if ($singleSanCount > 0) {
            $data['single_san_count'] = $singleSanCount;
        }
        if ($wildcardSanCount > 0) {
            $data['wildcard_san_count'] = $wildcardSanCount;
        }

        $r = $this->apiCall('/orders/add_ssl_san_order', $data, 'POST');

        if ($r['code'] === 200 && !empty($r['decoded']['success'])) {
            return [
                'success'      => true,
                'order_id'     => (string)($r['decoded']['order_id'] ?? $remoteId),
                'invoice_id'   => (string)($r['decoded']['invoice_id'] ?? ''),
                'order_amount' => (float)($r['decoded']['order_amount'] ?? 0),
                'message'      => 'SAN order added. Use reissue to apply new domains.',
            ];
        }

        return [
            'success' => false,
            'message' => $r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Add SAN failed',
        ];
    }

    /**
     * Cancel/refund an order
     *
     * API: POST /orders/cancel_ssl_order
     * Takes 2-48 hours for review.
     *
     * Request: order_id, reason
     * Response: { "order_id", "success": true }
     *
     * NOTE: GoGetSSL has NO separate revoke endpoint. Cancel is the only option.
     *
     * Signature matches ProviderInterface::cancelOrder(string $orderId): array
     */
    public function cancelOrder(string $orderId): array
    {
        $r = $this->apiCall('/orders/cancel_ssl_order', [
            'order_id' => (int)$orderId,
            'reason'   => 'Cancelled by admin',
        ], 'POST');

        $success = ($r['code'] === 200)
            && (!empty($r['decoded']['success']) || !empty($r['decoded']['order_id']));

        return [
            'success' => $success,
            'message' => $success
                ? 'Cancellation request submitted (review takes 2-48h)'
                : ($r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Cancellation failed'),
        ];
    }

    /**
     * Cancel with custom reason (GoGetSSL-specific convenience method)
     *
     * @param string $orderId
     * @param string $reason
     * @return array
     */
    public function cancelOrderWithReason(string $orderId, string $reason): array
    {
        $r = $this->apiCall('/orders/cancel_ssl_order', [
            'order_id' => (int)$orderId,
            'reason'   => $reason,
        ], 'POST');

        $success = ($r['code'] === 200)
            && (!empty($r['decoded']['success']) || !empty($r['decoded']['order_id']));

        return [
            'success' => $success,
            'message' => $success
                ? 'Cancellation request submitted (review takes 2-48h)'
                : ($r['decoded']['message'] ?? $r['decoded']['description'] ?? 'Cancellation failed'),
        ];
    }

    /**
     * Revoke certificate
     *
     * GoGetSSL does NOT have a dedicated revoke endpoint.
     * This delegates to cancelOrderWithReason as the closest equivalent.
     *
     * Signature matches ProviderInterface::revokeCertificate(string $orderId, string $reason = ''): array
     *
     * @param string $orderId Order ID
     * @param string $reason  Reason text
     * @return array
     */
    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        return $this->cancelOrderWithReason($orderId, $reason ?: 'Revoked by admin');
    }

    // ═══════════════════════════════════════════════════════════════
    //  DCV MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Resend validation email
     *
     * API: GET /orders/ssl/resend_validation_email/{order_id}
     * Note: This is a GET request with order_id in URL path.
     *
     * Response: { "message", "success": true }
     *
     * Signature matches ProviderInterface::resendDcvEmail(string $orderId, string $email = ''): array
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        // GET request — order_id in URL path
        $r = $this->apiCall('/orders/ssl/resend_validation_email/' . $orderId);

        $success = ($r['code'] === 200) && (!empty($r['decoded']['success']));

        return [
            'success' => $success,
            'message' => $r['decoded']['message'] ?? ($success ? 'Validation email resent' : 'Resend failed'),
        ];
    }

    /**
     * Change validation method for a single domain (base domain or SAN)
     *
     * API: POST /orders/ssl/change_validation_method/{order_id}/
     * Request: domain, new_method (email address or HTTP/HTTPS/DNS)
     *
     * Response: { "message", "success": true }
     *
     * Signature matches ProviderInterface::changeDcvMethod(string $orderId, string $method, array $params = []): array
     *
     * @param string $orderId  Remote order ID
     * @param string $method   DCV method: 'email', 'http', 'https', 'dns' (or email address)
     * @param array  $params   Additional params: 'domain' (required), 'approver_email' (opt)
     * @return array
     */
    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $newMethod = $this->mapDcvMethod($method);

        // If method is email and an approver_email is given, use the email as new_method
        if ($method === 'email' && !empty($params['approver_email'])) {
            $newMethod = $params['approver_email'];
        }

        $data = [
            'domain'     => $params['domain'] ?? '',
            'new_method' => $newMethod,
        ];

        // order_id in URL path
        $r = $this->apiCall(
            '/orders/ssl/change_validation_method/' . $orderId . '/',
            $data,
            'POST'
        );

        $success = ($r['code'] === 200) && (!empty($r['decoded']['success']));

        return [
            'success' => $success,
            'message' => $r['decoded']['message'] ?? ($success ? 'Validation method changed' : 'Change failed'),
        ];
    }

    /**
     * Change validation method for multiple domains at once
     *
     * API: POST /orders/ssl/change_domains_validation_method/{order_id}/
     * Request: domains (comma-separated), new_methods (comma-separated)
     * Valid methods: email address, HTTP, HTTPS, DNS (for Comodo/Sectigo/GoGetSSL)
     *
     * Response: { "message", "success": true }
     *
     * @param string $orderId       Order ID
     * @param array  $domainMethods ['domain1.tld' => 'dns', 'domain2.tld' => 'admin@domain2.tld']
     */
    public function changeDomainsValidationMethod(string $orderId, array $domainMethods): array
    {
        $domains = [];
        $methods = [];

        foreach ($domainMethods as $domain => $method) {
            $domains[] = $domain;
            $methods[] = $method;
        }

        $r = $this->apiCall(
            '/orders/ssl/change_domains_validation_method/' . $orderId . '/',
            [
                'domains'     => implode(',', $domains),
                'new_methods' => implode(',', $methods),
            ],
            'POST'
        );

        $success = ($r['code'] === 200) && (!empty($r['decoded']['success']));

        return [
            'success' => $success,
            'message' => $r['decoded']['message'] ?? ($success ? 'Validation methods updated' : 'Update failed'),
        ];
    }

    /**
     * Change validation email or switch DCV method
     *
     * API: POST /orders/ssl/change_validation_email/{order_id}
     * Can be used to: change approver email, or switch DCV method entirely.
     * Supports multi-domain via san_approval array.
     *
     * Request: approver_email (email addr or 'http'/'https'/'dns'),
     *          san_approval[] (optional, for multi-domain)
     *
     * Response: DCV method-specific data (http/https/dns/email)
     */
    public function changeValidationEmail(string $orderId, array $params): array
    {
        $data = [];

        if (!empty($params['approver_email'])) {
            $data['approver_email'] = $params['approver_email'];
        }

        // Multi-domain SAN approval array
        if (!empty($params['san_approval']) && is_array($params['san_approval'])) {
            foreach ($params['san_approval'] as $idx => $san) {
                $data["san_approval[{$idx}][name]"]   = $san['name'] ?? $san['domain'] ?? '';
                $data["san_approval[{$idx}][method]"]  = $san['method'] ?? 'email';
            }
        }

        $r = $this->apiCall(
            '/orders/ssl/change_validation_email/' . $orderId,
            $data,
            'POST'
        );

        $success = ($r['code'] === 200)
            && (!empty($r['decoded']['success']) || !empty($r['decoded']['dcv_method']));

        return [
            'success' => $success,
            'data'    => $r['decoded'] ?? [],
            'message' => $success ? 'Validation email/method changed' : 'Change failed',
        ];
    }

    /**
     * Recheck CAA (Certification Authority Authorization) records
     *
     * API: GET /orders/ssl/recheck-caa/{order_id}
     * Rate limit: 1 request per 10 minutes
     *
     * Use when order status is 'PRE-SIGN FAILED!!!' due to CAA check failure.
     */
    public function recheckCAA(string $orderId): array
    {
        $r = $this->apiCall('/orders/ssl/recheck-caa/' . $orderId);

        $success = ($r['code'] === 200) && (!empty($r['decoded']['success']));

        return [
            'success' => $success,
            'message' => $r['decoded']['message'] ?? ($success ? 'CAA recheck initiated' : 'CAA recheck failed'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRICING ENRICHMENT (internal)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Enrich product list with detailed pricing from /products/all_prices
     *
     * The all_prices endpoint returns flat array:
     * { "product_prices": [{ "id": 71, "period": 12, "price": "5.99" }, ...] }
     * Period values are already in MONTHS.
     *
     * @param NormalizedProduct[] $products
     */
    private function enrichWithPricing(array &$products): void
    {
        try {
            $r = $this->apiCall('/products/all_prices');

            if ($r['code'] !== 200 || empty($r['decoded']['product_prices'])) {
                return;
            }

            // Group all price entries by product ID
            $priceIndex    = [];
            $sanIndex      = [];
            $wildcardIndex = [];

            foreach ($r['decoded']['product_prices'] as $pp) {
                $pid    = (int)($pp['id'] ?? $pp['product_id'] ?? 0);
                $period = (int)($pp['period'] ?? 0);
                $price  = $pp['price'] ?? null;

                if ($pid === 0 || $period === 0 || $price === null) {
                    continue;
                }

                if (!isset($priceIndex[$pid])) {
                    $priceIndex[$pid] = [];
                }
                $priceIndex[$pid][(string)$period] = (float)$price;
            }

            // SAN prices (if present)
            if (!empty($r['decoded']['product_san_prices'])) {
                foreach ($r['decoded']['product_san_prices'] as $sp) {
                    $pid    = (int)($sp['id'] ?? $sp['product_id'] ?? 0);
                    $period = (int)($sp['period'] ?? 0);
                    $price  = $sp['price'] ?? null;

                    if ($pid && $period && $price !== null) {
                        $sanIndex[$pid][(string)$period] = (float)$price;
                    }
                }
            }

            // Wildcard SAN prices
            if (!empty($r['decoded']['product_wildcard_san_prices'])) {
                foreach ($r['decoded']['product_wildcard_san_prices'] as $wp) {
                    $pid    = (int)($wp['id'] ?? $wp['product_id'] ?? 0);
                    $period = (int)($wp['period'] ?? 0);
                    $price  = $wp['price'] ?? null;

                    if ($pid && $period && $price !== null) {
                        $wildcardIndex[$pid][(string)$period] = (float)$price;
                    }
                }
            }

            // Merge into products
            foreach ($products as &$product) {
                if ($product instanceof NormalizedProduct) {
                    $id = $product->extraData['gogetssl_id'] ?? null;

                    if ($id !== null) {
                        if (isset($priceIndex[$id]) && !empty($priceIndex[$id])) {
                            ksort($priceIndex[$id], SORT_NUMERIC);
                            $product->priceData['base'] = $priceIndex[$id];
                        }
                        if (isset($sanIndex[$id]) && !empty($sanIndex[$id])) {
                            ksort($sanIndex[$id], SORT_NUMERIC);
                            $product->priceData['san'] = $sanIndex[$id];
                        }
                        if (isset($wildcardIndex[$id]) && !empty($wildcardIndex[$id])) {
                            ksort($wildcardIndex[$id], SORT_NUMERIC);
                            $product->priceData['wildcard_san'] = $wildcardIndex[$id];
                        }
                    }
                }
            }
            unset($product);

            $this->log('info', 'GoGetSSL: Enriched ' . count($priceIndex) . ' products with all_prices data');

        } catch (\Exception $e) {
            $this->log('warning', 'GoGetSSL: Failed to enrich pricing: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS (private)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Determine product type from API product data
     *
     * /products/ssl uses: wildcard_enabled, san_enabled
     * /products uses:     wildcard, is_multidomain, multi_domain
     */
    private function determineProductType(array $item): string
    {
        if ($this->toBool($item['wildcard_enabled'] ?? $item['wildcard'] ?? false)) {
            return 'wildcard';
        }
        if ($this->toBool($item['san_enabled'] ?? $item['is_multidomain'] ?? $item['multi_domain'] ?? false)) {
            return 'multi_domain';
        }
        return 'ssl';
    }

    /**
     * Map DCV method strings to GoGetSSL API format
     *
     * GoGetSSL accepts LOWERCASE values: email, dns, http, https
     * Per official API documentation examples.
     */
    private function mapDcvMethod(string $method): string
    {
        $map = [
            'email' => 'email',
            'http'  => 'http',
            'https' => 'https',
            'dns'   => 'dns',
            'cname' => 'dns',
        ];

        return $map[strtolower(trim($method))] ?? strtolower(trim($method));
    }

    /**
     * Normalize GoGetSSL product_type to standard validation type
     *
     * GoGetSSL returns: 'domain', 'business', 'extended'
     * We normalize to: 'dv', 'ov', 'ev'
     */
    private function normalizeValidationType(string $type): string
    {
        $map = [
            'domain'     => 'dv',
            'dv'         => 'dv',
            'business'   => 'ov',
            'ov'         => 'ov',
            'organization' => 'ov',
            'extended'   => 'ev',
            'ev'         => 'ev',
        ];

        return $map[strtolower(trim($type))] ?? 'dv';
    }

    /**
     * Normalize GoGetSSL status strings to AIO standard statuses
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'              => 'Issued',
            'issued'              => 'Issued',
            'processing'          => 'Processing',
            'pending'             => 'Pending',
            'new_order'           => 'Pending',
            'cancelled'           => 'Cancelled',
            'canceled'            => 'Cancelled',
            'revoked'             => 'Revoked',
            'expired'             => 'Expired',
            'rejected'            => 'Rejected',
            'incomplete'          => 'Incomplete',
            'unpaid'              => 'Unpaid',
            'reissue'             => 'Reissuing',
            'waiting_validation'  => 'Awaiting Validation',
        ];

        return $map[strtolower(trim($status))] ?? $status;
    }

    /**
     * Convert months to years (for max_years/min_years)
     */
    private function periodToYears(int $months): int
    {
        return max(1, (int)ceil($months / 12));
    }

    /**
     * Convert various boolean representations to PHP bool
     *
     * GoGetSSL API returns mixed formats:
     *   - 1/0 (integer)
     *   - "1"/"0" (string)
     *   - true/false (boolean)
     *   - "true"/"false" (string)
     *   - "yes"/"no" (string)
     *
     * @param mixed $value
     * @return bool
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
        }
        return (bool)$value;
    }

    /**
     * Get API product details (cached from fetchProducts or via getProductDetails)
     * Used internally to determine brand for webserver_type logic.
     *
     * @param string $productId
     * @return array Product detail array
     */
    private function getApiProductDetails(string $productId): array
    {
        try {
            return $this->getProductDetails($productId);
        } catch (\Exception $e) {
            return [];
        }
    }
}