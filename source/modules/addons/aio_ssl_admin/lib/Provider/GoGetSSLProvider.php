<?php
/**
 * GoGetSSL Provider — Full-tier SSL provider integration
 *
 * API: https://my.gogetssl.com/api/
 * Auth: Username/Password → session token (POST /auth/)
 * Token: Passed as auth_key query parameter. Must refresh on 401.
 * Capabilities: Full lifecycle
 *
 * CRITICAL: Products use NUMERIC IDs (not string codes)
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;

class GoGetSSLProvider extends AbstractProvider
{
    private const API_URL = 'https://my.gogetssl.com/api';
    private const SANDBOX_URL = 'https://sandbox.gogetssl.com/api';

    /** @var string|null Cached auth token */
    private $authToken = null;

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string  { return 'gogetssl'; }
    public function getName(): string  { return 'GoGetSSL'; }
    public function getTier(): string  { return 'full'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'reissue', 'renew', 'revoke', 'cancel', 'download',
            'dcv_email', 'dcv_http', 'dcv_cname', 'dcv_https',
            'validate_order', 'get_dcv_emails', 'change_dcv', 'balance',
        ];
    }

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox') ? self::SANDBOX_URL : self::API_URL;
    }

    // ─── Auth (Session-based) ──────────────────────────────────────

    /**
     * Authenticate and get session token
     *
     * GoGetSSL: POST /auth/ with user + pass → { "key": "xxx" }
     * Token is cached and refreshed on 401
     *
     * @return string Auth token
     * @throws \RuntimeException
     */
    private function authenticate(): string
    {
        if ($this->authToken !== null) {
            return $this->authToken;
        }

        $response = $this->httpPost($this->getBaseUrl() . '/auth/', [
            'user' => $this->getCredential('username'),
            'pass' => $this->getCredential('password'),
        ]);

        if ($response['code'] !== 200 || empty($response['decoded']['key'])) {
            $msg = $response['decoded']['message']
                ?? $response['decoded']['description']
                ?? $response['decoded']['error']
                ?? 'Authentication failed';
            throw new \RuntimeException("GoGetSSL auth failed: {$msg}");
        }

        $this->authToken = $response['decoded']['key'];
        return $this->authToken;
    }

    /**
     * Invalidate cached token (called on 401)
     */
    private function invalidateToken(): void
    {
        $this->authToken = null;
    }

    /**
     * Make authenticated API call with auto-retry on 401
     *
     * GoGetSSL passes auth_key as query parameter for GET,
     * or as form field for POST requests.
     *
     * @param string $endpoint  API path (e.g. '/products/ssl/')
     * @param array  $params    Request parameters
     * @param string $method    HTTP method ('GET' or 'POST')
     * @return array ['code' => int, 'body' => string, 'decoded' => array]
     */
    private function apiCall(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $maxAttempts = 2; // 1 normal + 1 retry after re-auth
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $token = $this->authenticate();
            $url = $this->getBaseUrl() . $endpoint;

            if ($method === 'GET') {
                $params['auth_key'] = $token;
                $lastResponse = $this->httpGet($url, $params);
            } else {
                $params['auth_key'] = $token;
                $lastResponse = $this->httpPost($url, $params);
            }

            // ── FIX: Handle 401 by re-authenticating (C6) ──
            if ($lastResponse['code'] === 401 && $attempt < $maxAttempts) {
                $this->invalidateToken();
                $this->log('info', 'GoGetSSL: Token expired, re-authenticating...');
                continue;
            }

            return $lastResponse;
        }

        return $lastResponse;
    }

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $this->authenticate();
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

    public function getBalance(): array
    {
        $response = $this->apiCall('/account/balance/');
        if ($response['code'] === 200 && isset($response['decoded']['balance'])) {
            return [
                'balance'  => (float)$response['decoded']['balance'],
                'currency' => 'USD',
            ];
        }
        return ['balance' => 0, 'currency' => 'USD'];
    }

    // ─── Products ──────────────────────────────────────────────────

    /**
     * Fetch all available SSL products from GoGetSSL
     *
     * GoGetSSL API:
     *   GET /products/ssl/  → SSL-only products
     *   GET /products/      → All products
     *
     * Response format:
     * {
     *   "products": [
     *     {
     *       "id": 71,
     *       "name": "Sectigo PositiveSSL DV",
     *       "brand": "Sectigo",
     *       "price": "5.99",
     *       "max_period": 3,
     *       "multi_domain": false,
     *       "wildcard": false,
     *       "max_domains": 1,
     *       "prices": { "1": "5.99", "2": "10.99", "3": "14.99" },
     *       ...
     *     },
     *     ...
     *   ]
     * }
     *
     * NOTE: Product IDs are NUMERIC
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $response = $this->apiCall('/products/ssl/');

        if ($response['code'] !== 200 || !is_array($response['decoded'])) {
            throw new \RuntimeException('GoGetSSL: Failed to fetch products (HTTP ' . $response['code'] . ')');
        }

        $decoded = $response['decoded'];

        // The reference module: $apiProducts['products']
        $productList = $decoded['products'] ?? $decoded;

        if (!is_array($productList)) {
            throw new \RuntimeException('GoGetSSL: Unexpected response format');
        }

        $products = [];
        foreach ($productList as $item) {
            if (!is_array($item)) continue;

            // GoGetSSL uses numeric 'id' as product identifier
            if (!isset($item['id'])) continue;

            $products[] = $this->normalizeProduct($item);
        }

        $this->log('info', 'GoGetSSL: Fetched ' . count($products) . ' SSL products');

        // Optionally fetch detailed pricing for all products
        $this->enrichWithPricing($products);

        return $products;
    }

    /**
     * Fetch pricing for a specific product
     *
     * GET /products/price/{product_id}/
     */
    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/products/price/' . $productCode . '/');
        if ($response['code'] !== 200 || !is_array($response['decoded'])) {
            return [];
        }
        return $this->normalizePricingFromDetail($response['decoded']);
    }

    /**
     * Enrich products with detailed pricing from /products/all_prices/
     *
     * The reference module uses this endpoint for bulk price fetching
     */
    private function enrichWithPricing(array &$products): void
    {
        try {
            $response = $this->apiCall('/products/all_prices/');

            if ($response['code'] !== 200 || empty($response['decoded']['product_prices'])) {
                return;
            }

            // Index prices by product ID
            $priceIndex = [];
            foreach ($response['decoded']['product_prices'] as $pp) {
                $pid = $pp['id'] ?? $pp['product_id'] ?? null;
                if ($pid !== null) {
                    $priceIndex[(int)$pid] = $pp;
                }
            }

            // Merge pricing into products
            foreach ($products as &$product) {
                if ($product instanceof NormalizedProduct) {
                    $id = $product->extra_data['gogetssl_id'] ?? null;
                    if ($id !== null && isset($priceIndex[$id])) {
                        $detailedPricing = $this->normalizePricingFromDetail($priceIndex[$id]);
                        if (!empty($detailedPricing['base'])) {
                            $product->price_data = $detailedPricing;
                        }
                    }
                }
            }
            unset($product);

        } catch (\Exception $e) {
            // Non-critical: products already have basic pricing
            $this->log('warning', 'GoGetSSL: Failed to enrich pricing: ' . $e->getMessage());
        }
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        try {
            $response = $this->apiCall('/tools/csr/decode/', [
                'csr' => $params['csr'] ?? '',
            ], 'POST');

            if ($response['code'] === 200 && isset($response['decoded']['csrResult'])) {
                return ['valid' => true, 'errors' => []];
            }
            return ['valid' => false, 'errors' => ['CSR validation failed']];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function placeOrder(array $params): array
    {
        $data = [
            'product_id'    => $params['product_code'] ?? '',  // GoGetSSL uses numeric product_id
            'period'        => $this->monthsToPeriod($params['period'] ?? 12),
            'csr'           => $params['csr'] ?? '',
            'server_count'  => -1,
            'dcv_method'    => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ];

        if (isset($params['domains'])) {
            $data['dns_names'] = is_array($params['domains'])
                ? implode(',', $params['domains'])
                : $params['domains'];
        }

        if (isset($params['dcv_email'])) {
            $data['approver_email'] = $params['dcv_email'];
        }

        // Admin/tech contacts for OV/EV
        if (isset($params['admin_contact'])) {
            foreach ($params['admin_contact'] as $k => $v) {
                $data['admin_' . $k] = $v;
            }
        }
        if (isset($params['org_info'])) {
            foreach ($params['org_info'] as $k => $v) {
                $data['org_' . $k] = $v;
            }
        }

        $response = $this->apiCall('/orders/add_ssl_order/', $data, 'POST');

        if ($response['code'] !== 200 || !empty($response['decoded']['error'])) {
            $msg = $response['decoded']['message'] ?? $response['decoded']['description'] ?? 'Order failed';
            throw new \RuntimeException("GoGetSSL placeOrder: {$msg}");
        }

        $result = $response['decoded'];

        return [
            'order_id' => (string)($result['order_id'] ?? ''),
            'status'   => 'Pending',
            'extra'    => $result,
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/orders/status/' . $orderId);

        if ($response['code'] !== 200) {
            throw new \RuntimeException("GoGetSSL: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded'];

        return [
            'status'      => $this->normalizeStatus($data['status'] ?? ''),
            'certificate' => $data['crt_code'] ?? null,
            'ca_bundle'   => $data['ca_code'] ?? null,
            'domains'     => isset($data['domain']) ? [$data['domain']] : [],
            'begin_date'  => $data['valid_from'] ?? null,
            'end_date'    => $data['valid_till'] ?? null,
            'extra'       => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        $response = $this->apiCall('/orders/ssl/download/' . $orderId . '/');

        if ($response['code'] !== 200 || empty($response['decoded'])) {
            throw new \RuntimeException('GoGetSSL: Certificate not available.');
        }

        $data = $response['decoded'];
        return [
            'certificate' => $data['crt_code'] ?? '',
            'ca_bundle'   => $data['ca_code'] ?? '',
            'format'      => 'pem',
        ];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $data = [
            'csr'          => $params['csr'] ?? '',
            'dcv_method'   => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ];

        if (isset($params['dcv_email'])) {
            $data['approver_email'] = $params['dcv_email'];
        }

        $response = $this->apiCall('/orders/ssl/reissue/' . $orderId . '/', $data, 'POST');

        $success = ($response['code'] === 200 && empty($response['decoded']['error']));
        return [
            'success' => $success,
            'message' => $success ? 'Reissue initiated.' : ($response['decoded']['message'] ?? 'Failed'),
        ];
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        $data = [
            'product_id'   => $params['product_code'] ?? '',
            'period'       => $this->monthsToPeriod($params['period'] ?? 12),
            'csr'          => $params['csr'] ?? '',
            'server_count' => -1,
            'dcv_method'   => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ];

        if (isset($params['dcv_email'])) {
            $data['approver_email'] = $params['dcv_email'];
        }

        $response = $this->apiCall('/orders/add_ssl_renew_order/', $data, 'POST');

        if ($response['code'] !== 200 || !empty($response['decoded']['error'])) {
            $msg = $response['decoded']['message'] ?? 'Renew failed';
            throw new \RuntimeException("GoGetSSL renew: {$msg}");
        }

        $result = $response['decoded'];
        return [
            'order_id' => (string)($result['order_id'] ?? ''),
            'status'   => 'Pending',
            'extra'    => $result,
        ];
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $params = [];
        if ($reason) $params['reason'] = $reason;

        $response = $this->apiCall('/orders/ssl/revoke/' . $orderId . '/', $params, 'POST');
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'Revoked.' : 'Failed.'];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/orders/cancel_ssl_order/' . $orderId . '/', [], 'POST');
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'Cancelled.' : 'Failed.'];
    }

    // ─── DCV ───────────────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/tools/domain/emails/', ['domain' => $domain], 'POST');
        if ($response['code'] === 200 && isset($response['decoded'])) {
            // GoGetSSL returns { "GeoTrust": [...], "Comodo": [...], ... }
            // or flat array of emails
            $emails = [];
            $decoded = $response['decoded'];
            if (isset($decoded['emails'])) {
                $emails = $decoded['emails'];
            } else {
                foreach ($decoded as $brand => $brandEmails) {
                    if (is_array($brandEmails)) {
                        $emails = array_merge($emails, $brandEmails);
                    }
                }
            }
            return array_unique($emails);
        }
        return [];
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $response = $this->apiCall('/orders/ssl/resend_validation_email/' . $orderId . '/', [], 'POST');
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'DCV email resent.' : 'Failed.'];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $response = $this->apiCall('/orders/ssl/change_dcv_method/' . $orderId . '/', [
            'dcv_method' => $this->mapDcvMethod($method),
        ], 'POST');
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'DCV method changed.' : 'Failed.'];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Normalize product from GoGetSSL API response
     *
     * GoGetSSL product fields:
     * - id: 71 (numeric)
     * - name: "Sectigo PositiveSSL DV"
     * - brand: "Sectigo"
     * - price: "5.99" (base price)
     * - max_period: 3 (years)
     * - multi_domain: bool
     * - wildcard: bool
     * - max_domains: int
     * - prices: { "1": "5.99", "2": "10.99", "3": "14.99" } (price per YEAR)
     */
    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name = $item['name'] ?? $item['product'] ?? '';
        $nameLower = strtolower($name);

        $type = 'ssl';
        if (!empty($item['wildcard']) || strpos($nameLower, 'wildcard') !== false) {
            $type = 'wildcard';
        } elseif (!empty($item['multi_domain']) || strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false) {
            $type = 'multi_domain';
        } elseif (strpos($nameLower, 'code sign') !== false) {
            $type = 'code_signing';
        }

        $validation = 'dv';
        if (strpos($nameLower, ' ev') !== false || strpos($nameLower, 'extended') !== false) {
            $validation = 'ev';
        } elseif (strpos($nameLower, ' ov') !== false || strpos($nameLower, 'organization') !== false) {
            $validation = 'ov';
        }

        $vendor = $item['brand'] ?? 'Unknown';

        // ── FIX: Correct price parsing ──
        // GoGetSSL 'prices' key: { "1": "5.99", "2": "10.99" } where key = years
        $priceData = ['base' => [], 'san' => []];

        if (isset($item['prices']) && is_array($item['prices'])) {
            foreach ($item['prices'] as $years => $price) {
                $months = (int)$years * 12;
                if ($months > 0) {
                    $priceData['base'][(string)$months] = (float)$price;
                }
            }
        } elseif (isset($item['price'])) {
            // Fallback: single price
            $priceData['base']['12'] = (float)$item['price'];
        }

        // SAN pricing if available
        if (isset($item['san_prices']) && is_array($item['san_prices'])) {
            foreach ($item['san_prices'] as $years => $price) {
                $months = (int)$years * 12;
                if ($months > 0) {
                    $priceData['san'][(string)$months] = (float)$price;
                }
            }
        }

        return new NormalizedProduct([
            'product_code'     => (string)$item['id'],  // ← GoGetSSL uses numeric ID
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => ($type === 'wildcard'),
            'support_san'      => (bool)($item['multi_domain'] ?? ($type === 'multi_domain')),
            'max_domains'      => (int)($item['max_domains'] ?? 1),
            'max_years'        => (int)($item['max_period'] ?? 2),
            'min_years'        => 1,
            'price_data'       => $priceData,
            'extra_data'       => [
                'gogetssl_id'       => (int)$item['id'],
                'brand'             => $vendor,
                'wildcard_san'      => (bool)($item['wildcard_san_enabled'] ?? false),
            ],
        ]);
    }

    /**
     * Normalize pricing from detailed price response
     * Used by fetchPricing() and enrichWithPricing()
     */
    private function normalizePricingFromDetail(array $data): array
    {
        $normalized = ['base' => [], 'san' => []];

        // From /products/price/{id}/ or /products/all_prices/
        if (isset($data['prices']) && is_array($data['prices'])) {
            foreach ($data['prices'] as $years => $price) {
                $months = (int)$years * 12;
                if ($months > 0) {
                    $normalized['base'][(string)$months] = (float)$price;
                }
            }
        }

        return $normalized;
    }

    /**
     * Convert months to GoGetSSL period (years)
     */
    private function monthsToPeriod(int $months): int
    {
        return max(1, (int)ceil($months / 12));
    }

    /**
     * Map DCV method to GoGetSSL format
     */
    private function mapDcvMethod(string $method): string
    {
        $map = [
            'email'    => 'EMAIL',
            'http'     => 'HTTP',
            'https'    => 'HTTPS',
            'cname'    => 'CNAME_CSR_HASH',
            'dns'      => 'CNAME_CSR_HASH',
        ];
        return $map[strtolower($method)] ?? strtoupper($method);
    }

    /**
     * Map GoGetSSL status strings to normalized status
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
            'waiting_validation'  => 'Awaiting Validation',
        ];

        return $map[strtolower(trim($status))] ?? $status;
    }
}