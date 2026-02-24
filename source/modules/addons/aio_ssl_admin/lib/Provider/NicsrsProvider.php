<?php
/**
 * NicSRS Provider — Full-tier SSL provider integration
 *
 * API: https://portal.nicsrs.com
 * Auth: POST with api_token field
 * Capabilities: Full lifecycle (order, reissue, renew, revoke, cancel, download, DCV)
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;

class NicsrsProvider extends AbstractProvider
{
    /** @var string API base URL (live) */
    private const API_URL_LIVE = 'https://portal.nicsrs.com/ssl';

    /** @var string API base URL (sandbox) — NicSRS has no sandbox */
    private const API_URL_SANDBOX = 'https://portal.nicsrs.com/ssl';

    /** @var string[] Supported vendor brands */
    private const VENDORS = [
        'Sectigo', 'DigiCert', 'GlobalSign', 'GeoTrust', 'Entrust', 'Positive',
        'sslTrus', 'BaiduTrust', 'RapidSSL', 'Thawte', 'AlphaSSL',
    ];

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string  { return 'nicsrs'; }
    public function getName(): string  { return 'NicSRS'; }
    public function getTier(): string  { return 'full'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'reissue', 'renew', 'revoke', 'cancel', 'download',
            'dcv_email', 'dcv_http', 'dcv_cname', 'dcv_https',
            'validate_order', 'get_dcv_emails', 'change_dcv',
        ];
    }

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox') ? self::API_URL_SANDBOX : self::API_URL_LIVE;
    }

    // ─── Internal API Call ─────────────────────────────────────────

    /**
     * Make authenticated API call to NicSRS
     *
     * NicSRS uses POST with api_token as form field
     * Response format: { "code": 1, "msg": "...", "data": [...] }
     *
     * @param string $endpoint  API endpoint path (e.g. '/productList')
     * @param array  $params    Additional POST parameters
     * @return array ['code' => int, 'body' => string, 'decoded' => array]
     */
    private function apiCall(string $endpoint, array $params = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;

        // NicSRS auth: api_token as POST form field
        $params['api_token'] = $this->getCredential('api_token');

        return $this->httpPost($url, $params);
    }

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $response = $this->apiCall('/productList', ['vendor' => 'Sectigo']);

            // NicSRS API returns: { "code": 1, "msg": "success", "data": [...] }
            if ($response['code'] === 200 && !empty($response['decoded'])) {
                $apiCode = $response['decoded']['code'] ?? null;

                if ($apiCode == 1) {
                    $count = isset($response['decoded']['data'])
                        ? count($response['decoded']['data'])
                        : 0;
                    return [
                        'success' => true,
                        'message' => "NicSRS API connected. {$count} Sectigo products found.",
                        'balance' => null,
                    ];
                }

                // Auth or API error
                $msg = $response['decoded']['msg'] ?? 'Unknown error';
                return ['success' => false, 'message' => "NicSRS API error: {$msg}", 'balance' => null];
            }

            return [
                'success' => false,
                'message' => 'NicSRS API returned unexpected response: HTTP ' . $response['code'],
                'balance' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'NicSRS connection failed: ' . $e->getMessage(),
                'balance' => null,
            ];
        }
    }

    // ─── Product Catalog ───────────────────────────────────────────

    /**
     * Fetch all available products from NicSRS
     *
     * NicSRS API: POST /productList with vendor parameter
     * Response:
     * {
     *   "code": 1,
     *   "msg": "success",
     *   "data": [
     *     {
     *       "code": "positivessl",
     *       "productName": "Sectigo PositiveSSL",
     *       "supportWildcard": "N",
     *       "supportSan": "Y",
     *       "validationType": "dv",
     *       "maxDomain": 1,
     *       "maxYear": 3,
     *       "price": {
     *         "basePrice": { "price012": 5.99, "price024": 10.99, "price036": 14.99 },
     *         "sanPrice":  { "price012": 3.00, "price024": 5.00 }
     *       }
     *     },
     *     ...
     *   ]
     * }
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $products = [];

        foreach (self::VENDORS as $vendor) {
            try {
                $response = $this->apiCall('/productList', ['vendor' => $vendor]);

                // ── FIX: Check NicSRS-specific response structure ──
                // HTTP status must be 200
                if ($response['code'] !== 200) {
                    $this->log('warning', "NicSRS: HTTP {$response['code']} for vendor {$vendor}");
                    continue;
                }

                $decoded = $response['decoded'];

                // NicSRS API code: 1 = success
                $apiCode = $decoded['code'] ?? null;
                if ($apiCode != 1) {
                    $msg = $decoded['msg'] ?? 'Unknown error';
                    $this->log('warning', "NicSRS API error for vendor {$vendor}: {$msg}");
                    continue;
                }

                // ── FIX: Products are in 'data' key, NOT 'products' ──
                $productList = $decoded['data'] ?? [];
                if (!is_array($productList) || empty($productList)) {
                    $this->log('info', "No products returned for vendor {$vendor}");
                    continue;
                }

                foreach ($productList as $item) {
                    // ── FIX: Product code field is 'code', not 'product_code' ──
                    if (empty($item['code'])) {
                        continue;
                    }
                    $products[] = $this->normalizeProduct($item, $vendor);
                }

                $this->log('info', "NicSRS: Fetched " . count($productList) . " products for {$vendor}");

                // Rate limit: 500ms between vendor calls
                usleep(500000);

            } catch (\Exception $e) {
                $this->log('error', "fetchProducts failed for vendor {$vendor}: " . $e->getMessage());
            }
        }

        return $products;
    }

    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/productDetail', ['product_code' => $productCode]);

        if ($response['code'] !== 200) {
            return [];
        }

        $decoded = $response['decoded'];
        if (($decoded['code'] ?? null) != 1 || empty($decoded['data'])) {
            return [];
        }

        // Extract pricing from product detail data
        $data = is_array($decoded['data']) ? $decoded['data'] : [];
        $pricing = $data['price'] ?? $data['pricing'] ?? [];

        return $this->normalizePricing($pricing);
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        try {
            $apiParams = $this->buildOrderParams($params);
            $apiParams['validate_only'] = 1;

            $response = $this->apiCall('/validate', $apiParams);

            if ($response['code'] === 200 && isset($response['decoded']['code'])) {
                $valid = ($response['decoded']['code'] == 1);
                return [
                    'valid'  => $valid,
                    'errors' => $valid ? [] : [$response['decoded']['msg'] ?? 'Validation failed'],
                ];
            }
            return ['valid' => false, 'errors' => ['Unexpected response from NicSRS']];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function placeOrder(array $params): array
    {
        $apiParams = $this->buildOrderParams($params);
        $response = $this->apiCall('/place', $apiParams);

        if ($response['code'] !== 200 || ($response['decoded']['code'] ?? null) != 1) {
            $msg = $response['decoded']['msg'] ?? 'Order failed';
            throw new \RuntimeException("NicSRS placeOrder: {$msg}");
        }

        $data = $response['decoded']['data'] ?? $response['decoded'];

        return [
            'order_id' => (string)($data['certId'] ?? $data['cert_id'] ?? ''),
            'status'   => 'Pending',
            'extra'    => $data,
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/collect', ['cert_id' => $orderId]);

        if ($response['code'] !== 200 || ($response['decoded']['code'] ?? null) != 1) {
            throw new \RuntimeException("NicSRS: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded']['data'] ?? [];

        return [
            'status'      => $this->normalizeStatus($data['status'] ?? ''),
            'certificate' => $data['certificate'] ?? null,
            'ca_bundle'   => $data['ca_bundle'] ?? $data['caBundle'] ?? null,
            'domains'     => isset($data['domain']) ? [$data['domain']] : [],
            'begin_date'  => $data['begin_date'] ?? $data['beginDate'] ?? null,
            'end_date'    => $data['end_date'] ?? $data['endDate'] ?? null,
            'extra'       => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        // NicSRS includes cert in /collect response
        $status = $this->getOrderStatus($orderId);

        if (empty($status['certificate'])) {
            throw new \RuntimeException('Certificate not yet available.');
        }

        return [
            'certificate' => $status['certificate'],
            'ca_bundle'   => $status['ca_bundle'] ?? '',
            'format'      => 'pem',
        ];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $apiParams = $this->buildOrderParams($params);
        $apiParams['cert_id'] = $orderId;

        $response = $this->apiCall('/reissue', $apiParams);

        if ($response['code'] !== 200 || ($response['decoded']['code'] ?? null) != 1) {
            $msg = $response['decoded']['msg'] ?? 'Reissue failed';
            throw new \RuntimeException("NicSRS reissue: {$msg}");
        }

        return ['success' => true, 'message' => 'Certificate reissue initiated.'];
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        $apiParams = $this->buildOrderParams($params);
        $apiParams['cert_id'] = $orderId;

        $response = $this->apiCall('/renew', $apiParams);

        if ($response['code'] !== 200 || ($response['decoded']['code'] ?? null) != 1) {
            $msg = $response['decoded']['msg'] ?? 'Renew failed';
            throw new \RuntimeException("NicSRS renew: {$msg}");
        }

        $data = $response['decoded']['data'] ?? $response['decoded'];

        return [
            'order_id' => (string)($data['certId'] ?? $data['cert_id'] ?? ''),
            'status'   => 'Pending',
            'extra'    => $data,
        ];
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $response = $this->apiCall('/revoke', [
            'cert_id' => $orderId,
            'reason'  => $reason,
        ]);

        $success = ($response['code'] === 200 && ($response['decoded']['code'] ?? null) == 1);
        return [
            'success' => $success,
            'message' => $success ? 'Certificate revoked.' : ($response['decoded']['msg'] ?? 'Revoke failed'),
        ];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/cancel', ['cert_id' => $orderId]);

        $success = ($response['code'] === 200 && ($response['decoded']['code'] ?? null) == 1);
        return [
            'success' => $success,
            'message' => $success ? 'Order cancelled.' : ($response['decoded']['msg'] ?? 'Cancel failed'),
        ];
    }

    // ─── DCV Management ────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/DCVemail', ['domain' => $domain]);

        if ($response['code'] === 200 && ($response['decoded']['code'] ?? null) == 1) {
            return $response['decoded']['data'] ?? $response['decoded']['emails'] ?? [];
        }
        return [];
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $params = ['cert_id' => $orderId];
        if ($email) {
            $params['approver_email'] = $email;
        }

        $response = $this->apiCall('/DCVemail', $params);
        $success = ($response['code'] === 200 && ($response['decoded']['code'] ?? null) == 1);
        return ['success' => $success, 'message' => $success ? 'DCV email resent.' : 'Failed.'];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $data = array_merge([
            'cert_id'    => $orderId,
            'dcv_method' => $method,
        ], $params);

        $response = $this->apiCall('/updateDCV', $data);
        $success = ($response['code'] === 200 && ($response['decoded']['code'] ?? null) == 1);
        return ['success' => $success, 'message' => $success ? 'DCV method changed.' : 'Failed.'];
    }

    public function getBalance(): array
    {
        // NicSRS does not have a balance check endpoint
        return ['balance' => 0, 'currency' => 'USD'];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Build order parameters for NicSRS API
     */
    private function buildOrderParams(array $params): array
    {
        $apiParams = [
            'product_code' => $params['product_code'] ?? '',
            'period'       => $params['period'] ?? 12,
            'csr'          => $params['csr'] ?? '',
            'server_type'  => $params['server_type'] ?? -1,
            'dcv_method'   => $params['dcv_method'] ?? 'email',
        ];

        if (isset($params['domains'])) {
            $apiParams['domains'] = is_array($params['domains'])
                ? implode(',', $params['domains'])
                : $params['domains'];
        }

        if (isset($params['dcv_email'])) {
            $apiParams['approver_email'] = $params['dcv_email'];
        }

        // OV/EV contact fields
        foreach (['admin_contact', 'tech_contact', 'org_info'] as $section) {
            if (isset($params[$section]) && is_array($params[$section])) {
                foreach ($params[$section] as $k => $v) {
                    $apiParams[$section . '_' . $k] = $v;
                }
            }
        }

        return $apiParams;
    }

    /**
     * Normalize a product from NicSRS API response
     *
     * NicSRS product fields:
     * - code: "positivessl" (product code)
     * - productName: "Sectigo PositiveSSL"
     * - supportWildcard: "Y" or "N"
     * - supportSan: "Y" or "N"
     * - validationType: "dv", "ov", "ev"
     * - maxDomain: int
     * - maxYear: int
     * - price: { basePrice: { price012: float }, sanPrice: { price012: float } }
     */
    private function normalizeProduct(array $item, string $vendor): NormalizedProduct
    {
        $name = $item['productName'] ?? $item['product_name'] ?? $item['name'] ?? '';
        $nameLower = strtolower($name);

        // Determine product type
        $type = 'ssl';
        if (strpos($nameLower, 'wildcard') !== false) {
            $type = 'wildcard';
        } elseif (strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false || strpos($nameLower, 'san') !== false) {
            $type = 'multi_domain';
        } elseif (strpos($nameLower, 'code sign') !== false) {
            $type = 'code_signing';
        } elseif (strpos($nameLower, 'email') !== false || strpos($nameLower, 's/mime') !== false) {
            $type = 'email';
        }

        // Determine validation type
        $validation = strtolower($item['validationType'] ?? 'dv');
        if (!in_array($validation, ['dv', 'ov', 'ev'])) {
            $validation = 'dv';
            if (strpos($nameLower, ' ev ') !== false || strpos($nameLower, 'extended') !== false) {
                $validation = 'ev';
            } elseif (strpos($nameLower, ' ov ') !== false || strpos($nameLower, 'organization') !== false) {
                $validation = 'ov';
            }
        }

        // Support flags: NicSRS returns "Y"/"N" strings
        $supportWildcard = strtoupper($item['supportWildcard'] ?? 'N') === 'Y';
        $supportSan = strtoupper($item['supportSan'] ?? 'N') === 'Y';

        // Normalize pricing from NicSRS format
        $priceData = $this->normalizePricing($item['price'] ?? []);

        return new NormalizedProduct([
            'product_code'     => $item['code'],  // ← NicSRS uses 'code' field
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => $supportWildcard || ($type === 'wildcard'),
            'support_san'      => $supportSan || ($type === 'multi_domain'),
            'max_domains'      => (int)($item['maxDomain'] ?? ($type === 'multi_domain' ? 250 : 1)),
            'max_years'        => (int)($item['maxYear'] ?? 3),
            'min_years'        => 1,
            'price_data'       => $priceData,
            'extra_data'       => [
                'vendor'   => $vendor,
                'raw_code' => $item['code'],
            ],
        ]);
    }

    /**
     * Normalize pricing data from NicSRS format
     *
     * NicSRS price structure:
     * {
     *   "basePrice": { "price012": 5.99, "price024": 10.99, "price036": 14.99 },
     *   "sanPrice":  { "price012": 3.00, "price024": 5.00 },
     *   "wildcardSanPrice": { "price012": 49.00 }
     * }
     *
     * Normalized output:
     * {
     *   "base":         { "12": 5.99, "24": 10.99, "36": 14.99 },
     *   "san":          { "12": 3.00, "24": 5.00 },
     *   "wildcard_san": { "12": 49.00 }
     * }
     */
    private function normalizePricing(array $pricing): array
    {
        $normalized = ['base' => [], 'san' => [], 'wildcard_san' => []];

        // Map NicSRS keys → normalized keys
        $keyMap = [
            'basePrice'        => 'base',
            'sanPrice'         => 'san',
            'wildcardSanPrice' => 'wildcard_san',
            // Fallback for alternative structures
            'base'             => 'base',
            'san'              => 'san',
            'wildcard_san'     => 'wildcard_san',
        ];

        foreach ($pricing as $priceKey => $priceSet) {
            $normalizedKey = $keyMap[$priceKey] ?? null;

            if ($normalizedKey && is_array($priceSet)) {
                foreach ($priceSet as $periodKey => $price) {
                    $months = $this->extractMonthsFromKey($periodKey);
                    if ($months) {
                        $normalized[$normalizedKey][(string)$months] = (float)$price;
                    }
                }
            } elseif (!is_array($priceSet)) {
                // Flat pricing: key is period, value is price
                $months = $this->extractMonthsFromKey($priceKey);
                if ($months) {
                    $normalized['base'][(string)$months] = (float)$priceSet;
                }
            }
        }

        return $normalized;
    }

    /**
     * Extract months from NicSRS price key
     *
     * Handles: "price012" → 12, "price024" → 24, "12" → 12, "1year" → 12, etc.
     */
    private function extractMonthsFromKey($key): ?int
    {
        $k = strtolower(trim((string)$key));

        // NicSRS format: "price012", "price024", "price036"
        if (preg_match('/^price0?(\d+)$/', $k, $m)) {
            return (int)$m[1];
        }

        // Direct month values
        $monthMap = [
            '12' => 12, '24' => 24, '36' => 36, '48' => 48, '60' => 60,
            '1' => 12, '2' => 24, '3' => 36, '4' => 48, '5' => 60,
            '1year' => 12, '2year' => 24, '3year' => 36, '4year' => 48, '5year' => 60,
        ];

        return $monthMap[$k] ?? null;
    }

    /**
     * Map NicSRS status strings to normalized status
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'          => 'Issued',
            'issued'          => 'Issued',
            'completed'       => 'Issued',
            'pending'         => 'Pending',
            'processing'      => 'Processing',
            'waiting_approve' => 'Awaiting Approval',
            'cancelled'       => 'Cancelled',
            'revoked'         => 'Revoked',
            'expired'         => 'Expired',
            'rejected'        => 'Rejected',
            'new_order'       => 'Pending',
        ];

        return $map[strtolower(trim($status))] ?? $status;
    }
}