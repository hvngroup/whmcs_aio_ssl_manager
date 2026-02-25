<?php

namespace AioSSL\Provider;

use AioSSL\Core\NormalizedProduct;

/**
 * GoGetSSL Provider Implementation
 *
 * API Base: https://my.gogetssl.com/api/
 * Auth: POST /auth/ → session key (401 = re-auth)
 * Products use NUMERIC IDs (not string codes)
 * Has sandbox: https://sandbox.gogetssl.com/api
 *
 * @package AioSSL\Provider
 * @author  HVN GROUP
 */
class GoGetSSLProvider extends AbstractProvider
{
    protected string $slug    = 'gogetssl';
    protected string $name    = 'GoGetSSL';
    protected string $tier    = 'full';
    protected string $baseUrl = 'https://my.gogetssl.com/api';

    /** @var string|null Cached auth token */
    private ?string $authToken = null;

    // ─── Auth ──────────────────────────────────────────────────────

    /**
     * Authenticate with GoGetSSL API
     * POST /auth/ → { "key": "...", "success": true }
     */
    private function authenticate(): string
    {
        $response = $this->httpPost($this->baseUrl . '/auth/', [
            'user' => $this->getCredential('username'),
            'pass' => $this->getCredential('password'),
        ]);

        $decoded = json_decode($response['body'] ?? '', true);

        if (empty($decoded['key'])) {
            throw new \RuntimeException('GoGetSSL: Authentication failed');
        }

        $this->authToken = $decoded['key'];
        return $this->authToken;
    }

    /**
     * Make an API call with auto-authentication and 401 retry
     */
    protected function apiCall(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        if (!$this->authToken) {
            $this->authenticate();
        }

        $data['auth_key'] = $this->authToken;
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $response = ($method === 'POST')
            ? $this->httpPost($url, $data)
            : $this->httpGet($url, $data);

        $code    = (int)($response['code'] ?? 0);
        $decoded = json_decode($response['body'] ?? '', true);

        // 401 = session expired → re-authenticate and retry once
        if ($code === 401 || (isset($decoded['error']) && $decoded['error'] === 'auth_key_not_found')) {
            $this->authToken = null;
            $this->authenticate();
            $data['auth_key'] = $this->authToken;

            $response = ($method === 'POST')
                ? $this->httpPost($url, $data)
                : $this->httpGet($url, $data);

            $code    = (int)($response['code'] ?? 0);
            $decoded = json_decode($response['body'] ?? '', true);
        }

        return ['code' => $code, 'decoded' => $decoded, 'raw' => $response['body'] ?? ''];
    }

    // ─── Connection ────────────────────────────────────────────────

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

    // ─── Capabilities ──────────────────────────────────────────────

    public function getCapabilities(): array
    {
        return [
            'order', 'validate', 'status', 'download', 'reissue',
            'renew', 'revoke', 'cancel', 'dcv_emails', 'resend_dcv',
            'change_dcv', 'balance', 'csr_decode',
        ];
    }

    // ─── Products ──────────────────────────────────────────────────

    /**
     * Fetch all available SSL products from GoGetSSL
     *
     * GoGetSSL API:
     *   GET /products/ssl/  → SSL-only products
     *   GET /products/      → All products
     *
     * Response format from /products/ssl/:
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
     *       "prices": { "1": "5.99", "2": "10.99", "3": "14.99" },  ← Keys = YEARS
     *       ...
     *     }
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

        $decoded     = $response['decoded'];
        $productList = $decoded['products'] ?? $decoded;

        if (!is_array($productList)) {
            throw new \RuntimeException('GoGetSSL: Unexpected response format');
        }

        $products = [];
        foreach ($productList as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $products[] = $this->normalizeProduct($item);
        }

        $this->log('info', 'GoGetSSL: Fetched ' . count($products) . ' SSL products');

        // Enrich with detailed pricing from /products/all_prices/
        $this->enrichWithPricing($products);

        return $products;
    }

    /**
     * Fetch pricing for a specific product
     *
     * GET /products/price/{product_id}/
     *
     * Response format:
     * {
     *   "product_id": 71,
     *   "prices": { "1": "5.99", "2": "10.99", "3": "14.99" },  ← Keys = YEARS
     *   "san_prices": { "1": "3.00", "2": "5.00" },              ← Keys = YEARS
     *   "success": true
     * }
     */
    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/products/price/' . $productCode . '/');
        if ($response['code'] !== 200 || !is_array($response['decoded'])) {
            return [];
        }
        // /products/price/{id}/ uses YEAR-keyed format (same as product list)
        return $this->normalizePricingFromYearKeys($response['decoded']);
    }

    /**
     * Enrich products with detailed pricing from /products/all_prices/
     *
     * CRITICAL FIX: The /products/all_prices/ endpoint returns a DIFFERENT format
     * than /products/ssl/ and /products/price/{id}/
     *
     * /products/all_prices/ response:
     * {
     *   "product_prices": [
     *     { "id": 71, "period": 12, "price": "5.99" },   ← period in MONTHS
     *     { "id": 71, "period": 24, "price": "10.99" },
     *     { "id": 71, "period": 36, "price": "14.99" },
     *     { "id": 72, "period": 12, "price": "9.99" },
     *     ...
     *   ],
     *   "success": true
     * }
     *
     * BUG FIX #1: Previous code overwrote entries — $priceIndex[$pid] = $pp;
     *             Only the last period per product survived.
     * BUG FIX #2: Previous code called normalizePricingFromDetail() which expected
     *             year-keyed 'prices' hash — but all_prices has flat {period, price}.
     */
    private function enrichWithPricing(array &$products): void
    {
        try {
            $response = $this->apiCall('/products/all_prices/');

            if ($response['code'] !== 200 || empty($response['decoded']['product_prices'])) {
                return;
            }

            // ── FIX #1: Group ALL price entries by product ID ──
            // Build: [ productId => [ monthPeriod => price, ... ], ... ]
            $priceIndex = [];
            foreach ($response['decoded']['product_prices'] as $pp) {
                $pid    = $pp['id'] ?? $pp['product_id'] ?? null;
                $period = $pp['period'] ?? null;   // Already in MONTHS (12, 24, 36, ...)
                $price  = $pp['price']  ?? null;

                if ($pid === null || $period === null || $price === null) {
                    continue;
                }

                $pid    = (int)$pid;
                $period = (int)$period;

                if (!isset($priceIndex[$pid])) {
                    $priceIndex[$pid] = [];
                }

                // Period from all_prices is already in months — store directly
                if ($period > 0) {
                    $priceIndex[$pid][(string)$period] = (float)$price;
                }
            }

            // ── FIX #2: Merge grouped pricing into products (no format conversion needed) ──
            foreach ($products as &$product) {
                if ($product instanceof NormalizedProduct) {
                    $id = $product->extraData['gogetssl_id'] ?? null;
                    if ($id !== null && isset($priceIndex[$id])) {
                        $detailedBase = $priceIndex[$id];
                        if (!empty($detailedBase)) {
                            // Sort by period for consistency
                            ksort($detailedBase, SORT_NUMERIC);
                            $product->priceData['base'] = $detailedBase;
                        }
                    }
                }
            }
            unset($product);

            $this->log('info', 'GoGetSSL: Enriched ' . count($priceIndex) . ' products with all_prices data');

        } catch (\Exception $e) {
            // Non-critical: products already have basic pricing from /products/ssl/
            $this->log('warning', 'GoGetSSL: Failed to enrich pricing: ' . $e->getMessage());
        }
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    /**
     * Validate order parameters (CSR decode)
     */
    public function validateOrder(array $params): array
    {
        try {
            $response = $this->apiCall('/tools/csr/decode/', [
                'csr' => $params['csr'] ?? '',
            ], 'POST');

            if ($response['code'] === 200 && !empty($response['decoded']['csrResult'])) {
                return [
                    'valid'   => true,
                    'details' => $response['decoded']['csrResult'],
                    'errors'  => [],
                ];
            }

            return [
                'valid'  => false,
                'errors' => [$response['decoded']['message'] ?? 'CSR validation failed'],
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Place a new SSL certificate order
     *
     * POST /orders/add_ssl_order/
     */
    public function placeOrder(array $params): array
    {
        $apiProduct = $this->getApiProductDetails($params['product_code'] ?? '');
        $brand = strtolower($apiProduct['brand'] ?? '');

        // Brand-specific webserver_type override (from reference module)
        $webserverType = '-1';
        if (in_array($brand, ['geotrust', 'rapidssl', 'digicert', 'thawte'])) {
            $webserverType = '18';
        }

        $orderData = [
            'product_id'         => (int)$params['product_code'],
            'period'             => $this->monthsToPeriod($params['period'] ?? 12),
            'csr'                => $params['csr'] ?? '',
            'server_count'       => -1,
            'approver_email'     => $params['approver_email'] ?? '',
            'webserver_type'     => $params['webserver_type'] ?? $webserverType,
            'dcv_method'         => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
            'admin_firstname'    => $params['admin_firstname'] ?? '',
            'admin_lastname'     => $params['admin_lastname'] ?? '',
            'admin_organization' => $params['admin_organization'] ?? '',
            'admin_title'        => $params['admin_title'] ?? '',
            'admin_addressline1' => $params['admin_address1'] ?? '',
            'admin_phone'        => $params['admin_phone'] ?? '',
            'admin_email'        => $params['admin_email'] ?? '',
            'admin_city'         => $params['admin_city'] ?? '',
            'admin_country'      => $params['admin_country'] ?? '',
            'admin_postalcode'   => $params['admin_postcode'] ?? '',
            'admin_region'       => $params['admin_state'] ?? '',
            'tech_firstname'     => $params['tech_firstname'] ?? $params['admin_firstname'] ?? '',
            'tech_lastname'      => $params['tech_lastname'] ?? $params['admin_lastname'] ?? '',
            'tech_organization'  => $params['tech_organization'] ?? $params['admin_organization'] ?? '',
            'tech_title'         => $params['tech_title'] ?? $params['admin_title'] ?? '',
            'tech_addressline1'  => $params['tech_address1'] ?? $params['admin_address1'] ?? '',
            'tech_phone'         => $params['tech_phone'] ?? $params['admin_phone'] ?? '',
            'tech_email'         => $params['tech_email'] ?? $params['admin_email'] ?? '',
            'tech_city'          => $params['tech_city'] ?? $params['admin_city'] ?? '',
            'tech_country'       => $params['tech_country'] ?? $params['admin_country'] ?? '',
            'tech_postalcode'    => $params['tech_postcode'] ?? $params['admin_postcode'] ?? '',
            'tech_region'        => $params['tech_state'] ?? $params['admin_state'] ?? '',
        ];

        // Multi-domain: add SAN domains
        if (!empty($params['san_domains'])) {
            $sanDomains = is_array($params['san_domains'])
                ? $params['san_domains']
                : explode("\n", $params['san_domains']);
            $orderData['dns_names'] = implode(',', array_map('trim', $sanDomains));
        }

        $response = $this->apiCall('/orders/add_ssl_order/', $orderData, 'POST');

        if ($response['code'] === 200 && !empty($response['decoded']['order_id'])) {
            return [
                'success'    => true,
                'order_id'   => (string)$response['decoded']['order_id'],
                'remote_id'  => (string)$response['decoded']['order_id'],
                'status'     => 'Pending',
                'message'    => 'Order placed successfully',
            ];
        }

        $errorMsg = $response['decoded']['message']
            ?? $response['decoded']['description']
            ?? 'Failed to place order';

        return ['success' => false, 'message' => $errorMsg];
    }

    /**
     * Get order status
     *
     * GET /orders/status/{order_id}/
     */
    public function getOrderStatus(string $remoteId): array
    {
        $response = $this->apiCall('/orders/status/' . $remoteId . '/');

        if ($response['code'] !== 200 || empty($response['decoded'])) {
            return ['status' => 'Unknown', 'raw' => $response['decoded'] ?? []];
        }

        $data = $response['decoded'];

        return [
            'status'         => $this->normalizeStatus($data['status'] ?? 'unknown'),
            'order_id'       => (string)($data['order_id'] ?? $remoteId),
            'domain'         => $data['domain'] ?? '',
            'valid_from'     => $data['valid_from'] ?? $data['begin_date'] ?? '',
            'valid_to'       => $data['valid_till'] ?? $data['end_date'] ?? '',
            'partner_order'  => $data['partner_order_id'] ?? '',
            'ca_order_id'    => $data['ca_order_id'] ?? '',
            'approver_email' => $data['approver_email'] ?? '',
            'dcv_method'     => $data['dcv_method'] ?? '',
            'san_domains'    => $data['san'] ?? [],
            'raw'            => $data,
        ];
    }

    /**
     * Download certificate
     *
     * GET /orders/download/{order_id}/
     */
    public function downloadCertificate(string $remoteId): array
    {
        $response = $this->apiCall('/orders/download/' . $remoteId . '/');

        if ($response['code'] !== 200 || empty($response['decoded'])) {
            return ['success' => false, 'message' => 'Failed to download certificate'];
        }

        $data = $response['decoded'];

        return [
            'success'       => true,
            'certificate'   => $data['crt_code'] ?? '',
            'ca_bundle'     => $data['ca_code'] ?? '',
            'intermediate'  => $data['ca_code'] ?? '',
            'domain'        => $data['domain'] ?? '',
        ];
    }

    /**
     * Reissue certificate
     *
     * POST /orders/ssl/reissue/
     */
    public function reissueCertificate(string $remoteId, array $params): array
    {
        $reissueData = [
            'order_id'    => (int)$remoteId,
            'csr'         => $params['csr'] ?? '',
            'dcv_method'  => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ];

        // Brand-specific webserver_type
        if (isset($params['webserver_type'])) {
            $reissueData['webserver_type'] = $params['webserver_type'];
        }

        if (!empty($params['approver_email'])) {
            $reissueData['approver_email'] = $params['approver_email'];
        }

        if (!empty($params['san_domains'])) {
            $sanDomains = is_array($params['san_domains'])
                ? $params['san_domains']
                : explode("\n", $params['san_domains']);
            $reissueData['dns_names'] = implode(',', array_map('trim', $sanDomains));
        }

        $response = $this->apiCall('/orders/ssl/reissue/', $reissueData, 'POST');

        if ($response['code'] === 200 && !empty($response['decoded']['order_id'])) {
            return [
                'success'  => true,
                'order_id' => (string)$response['decoded']['order_id'],
                'message'  => 'Reissue initiated successfully',
            ];
        }

        return [
            'success' => false,
            'message' => $response['decoded']['message'] ?? 'Reissue failed',
        ];
    }

    /**
     * Renew certificate
     *
     * POST /orders/add_ssl_renew/
     */
    public function renewCertificate(string $remoteId, array $params): array
    {
        $renewData = [
            'order_id'   => (int)$remoteId,
            'product_id' => (int)($params['product_code'] ?? 0),
            'period'     => $this->monthsToPeriod($params['period'] ?? 12),
            'csr'        => $params['csr'] ?? '',
        ];

        $response = $this->apiCall('/orders/add_ssl_renew/', $renewData, 'POST');

        if ($response['code'] === 200 && !empty($response['decoded']['order_id'])) {
            return [
                'success'     => true,
                'order_id'    => (string)$response['decoded']['order_id'],
                'remote_id'   => (string)$response['decoded']['order_id'],
                'message'     => 'Renewal order placed',
            ];
        }

        return [
            'success' => false,
            'message' => $response['decoded']['message'] ?? 'Renewal failed',
        ];
    }

    /**
     * Revoke certificate
     *
     * POST /orders/cancel_ssl_order/
     */
    public function revokeCertificate(string $remoteId, array $params = []): array
    {
        $response = $this->apiCall('/orders/cancel_ssl_order/', [
            'order_id' => (int)$remoteId,
            'reason'   => $params['reason'] ?? 'Revoked by admin',
        ], 'POST');

        return [
            'success' => ($response['code'] === 200 && ($response['decoded']['success'] ?? false)),
            'message' => $response['decoded']['message'] ?? 'Revocation submitted',
        ];
    }

    /**
     * Cancel order
     *
     * POST /orders/cancel_ssl_order/
     */
    public function cancelOrder(string $remoteId): array
    {
        $response = $this->apiCall('/orders/cancel_ssl_order/', [
            'order_id' => (int)$remoteId,
            'reason'   => 'Cancelled by admin',
        ], 'POST');

        return [
            'success' => ($response['code'] === 200 && ($response['decoded']['success'] ?? false)),
            'message' => $response['decoded']['message'] ?? 'Cancellation submitted',
        ];
    }

    /**
     * Get available DCV (Domain Control Validation) emails
     *
     * POST /tools/domain/emails/
     */
    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/tools/domain/emails/', [
            'domain' => $domain,
        ], 'POST');

        if ($response['code'] === 200 && isset($response['decoded'])) {
            $data = $response['decoded'];
            // Merge Comodo and GeoTrust email lists
            $emails = [];
            if (!empty($data['ComodoApprovalEmails'])) {
                $emails = array_merge($emails, (array)$data['ComodoApprovalEmails']);
            }
            if (!empty($data['GeotrustApprovalEmails'])) {
                $emails = array_merge($emails, (array)$data['GeotrustApprovalEmails']);
            }
            return array_unique($emails);
        }

        return [];
    }

    /**
     * Resend DCV email
     *
     * POST /orders/ssl/resend_validation_email/
     */
    public function resendDcvEmail(string $remoteId, array $params = []): array
    {
        $response = $this->apiCall('/orders/ssl/resend_validation_email/', [
            'order_id' => (int)$remoteId,
        ], 'POST');

        return [
            'success' => ($response['code'] === 200 && ($response['decoded']['success'] ?? false)),
            'message' => $response['decoded']['message'] ?? 'DCV email resent',
        ];
    }

    /**
     * Change DCV method for an order
     *
     * POST /orders/ssl/change_validation_method/
     */
    public function changeDcvMethod(string $remoteId, array $params): array
    {
        $response = $this->apiCall('/orders/ssl/change_validation_method/', [
            'order_id'       => (int)$remoteId,
            'dcv_method'     => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
            'approver_email' => $params['approver_email'] ?? '',
        ], 'POST');

        return [
            'success' => ($response['code'] === 200 && ($response['decoded']['success'] ?? false)),
            'message' => $response['decoded']['message'] ?? 'DCV method changed',
        ];
    }

    /**
     * Get webserver types list
     *
     * GET /tools/webservers/
     */
    public function getWebservers(): array
    {
        $response = $this->apiCall('/tools/webservers/');
        if ($response['code'] === 200 && isset($response['decoded']['webservers'])) {
            return $response['decoded']['webservers'];
        }
        return [];
    }

    // ─── Private Helpers ───────────────────────────────────────────

    /**
     * Get product details from API
     */
    private function getApiProductDetails(string $productId): array
    {
        $response = $this->apiCall('/products/ssl/' . $productId);
        if ($response['code'] === 200 && is_array($response['decoded'])) {
            return $response['decoded'];
        }
        return [];
    }

    /**
     * Normalize a single product from /products/ssl/ response
     *
     * IMPORTANT: The 'prices' key from /products/ssl/ uses YEAR-based keys:
     * { "1": "5.99", "2": "10.99", "3": "14.99" }
     * We convert these to month-based keys for NormalizedProduct.
     */
    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name = $item['name'] ?? 'Unknown Product';

        // Detect type
        $type = 'ssl';
        if (!empty($item['wildcard']) || stripos($name, 'wildcard') !== false) {
            $type = 'wildcard';
        } elseif (!empty($item['multi_domain']) || !empty($item['is_multidomain'])) {
            $type = 'multi_domain';
        }

        // Detect validation level
        $validation = 'dv';
        $nameLower  = strtolower($name);
        if (strpos($nameLower, ' ev ') !== false || strpos($nameLower, 'extended') !== false) {
            $validation = 'ev';
        } elseif (strpos($nameLower, ' ov ') !== false || strpos($nameLower, 'organization') !== false) {
            $validation = 'ov';
        }

        // Brand mapping
        $vendor = $item['brand'] ?? 'Unknown';

        // ── Pricing from /products/ssl/ — keys are YEARS ──
        $priceData = ['base' => [], 'san' => []];

        if (isset($item['prices']) && is_array($item['prices'])) {
            foreach ($item['prices'] as $years => $price) {
                $years = (int)$years;
                if ($years > 0) {
                    $months = $years * 12;
                    $priceData['base'][(string)$months] = (float)$price;
                }
            }
        } elseif (isset($item['price'])) {
            // Fallback: single 'price' field = 1-year price
            $priceData['base']['12'] = (float)$item['price'];
        }

        // SAN pricing from /products/ssl/ — also YEAR-keyed
        if (isset($item['san_prices']) && is_array($item['san_prices'])) {
            foreach ($item['san_prices'] as $years => $price) {
                $years = (int)$years;
                if ($years > 0) {
                    $months = $years * 12;
                    $priceData['san'][(string)$months] = (float)$price;
                }
            }
        }

        return new NormalizedProduct([
            'product_code'     => (string)$item['id'],
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
                'gogetssl_id'  => (int)$item['id'],
                'brand'        => $vendor,
                'wildcard_san' => (bool)($item['wildcard_san_enabled'] ?? false),
            ],
        ]);
    }

    /**
     * Normalize pricing from YEAR-keyed format
     *
     * Used by fetchPricing() for /products/price/{id}/ endpoint
     * which returns: { "prices": { "1": "5.99", "2": "10.99" }, "san_prices": {...} }
     */
    private function normalizePricingFromYearKeys(array $data): array
    {
        $normalized = ['base' => [], 'san' => []];

        if (isset($data['prices']) && is_array($data['prices'])) {
            foreach ($data['prices'] as $years => $price) {
                $years = (int)$years;
                if ($years > 0) {
                    $months = $years * 12;
                    $normalized['base'][(string)$months] = (float)$price;
                }
            }
        }

        // SAN pricing
        if (isset($data['san_prices']) && is_array($data['san_prices'])) {
            foreach ($data['san_prices'] as $years => $price) {
                $years = (int)$years;
                if ($years > 0) {
                    $months = $years * 12;
                    $normalized['san'][(string)$months] = (float)$price;
                }
            }
        }

        return $normalized;
    }

    /**
     * Convert months to GoGetSSL period (years) for ordering
     *
     * GoGetSSL addSSLOrder expects 'period' in MONTHS (12, 24, 36...)
     * But some older API versions expect years — handle both.
     */
    private function monthsToPeriod(int $months): int
    {
        // GoGetSSL order API uses months directly
        return max(12, $months);
    }

    /**
     * Map DCV method to GoGetSSL format
     */
    private function mapDcvMethod(string $method): string
    {
        $map = [
            'email'  => 'EMAIL',
            'http'   => 'HTTP',
            'https'  => 'HTTPS',
            'cname'  => 'CNAME_CSR_HASH',
            'dns'    => 'CNAME_CSR_HASH',
        ];
        return $map[strtolower($method)] ?? strtoupper($method);
    }

    /**
     * Map GoGetSSL status strings to normalized status
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'             => 'Issued',
            'issued'             => 'Issued',
            'processing'         => 'Processing',
            'pending'            => 'Pending',
            'new_order'          => 'Pending',
            'cancelled'          => 'Cancelled',
            'canceled'           => 'Cancelled',
            'revoked'            => 'Revoked',
            'expired'            => 'Expired',
            'rejected'           => 'Rejected',
            'incomplete'         => 'Incomplete',
            'waiting_validation' => 'Awaiting Validation',
        ];

        return $map[strtolower(trim($status))] ?? $status;
    }
}