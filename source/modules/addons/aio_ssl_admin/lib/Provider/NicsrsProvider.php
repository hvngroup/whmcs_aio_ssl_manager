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

    /** @var string API base URL (sandbox) */
    private const API_URL_SANDBOX = 'https://sandbox.nicsrs.com/ssl';

    /** @var string[] Supported vendor brands */
    private const VENDORS = [
        'Sectigo', 'DigiCert', 'GlobalSign', 'GeoTrust', 'Entrust',
        'sslTrus', 'BaiduTrust', 'RapidSSL', 'Thawte', 'AlphaSSL',
    ];

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string
    {
        return 'nicsrs';
    }

    public function getName(): string
    {
        return 'NicSRS';
    }

    public function getTier(): string
    {
        return 'full';
    }

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

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $response = $this->apiCall('/productList', ['vendor' => 'Sectigo']);

            if ($response['code'] === 200 && !empty($response['decoded'])) {
                return [
                    'success' => true,
                    'message' => 'NicSRS API connection successful.',
                    'balance' => null,
                ];
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

    public function fetchProducts(): array
    {
        $products = [];

        foreach (self::VENDORS as $vendor) {
            try {
                $response = $this->apiCall('/productList', ['vendor' => $vendor]);

                if ($response['code'] !== 200 || !isset($response['decoded']['products'])) {
                    $this->log('warning', "No products for vendor {$vendor}", [
                        'code' => $response['code'],
                    ]);
                    continue;
                }

                foreach ($response['decoded']['products'] as $item) {
                    $products[] = $this->normalizeProduct($item, $vendor);
                }

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
        // Pricing is included in fetchProducts response
        // This method can call product detail if needed
        $response = $this->apiCall('/productDetail', ['product_code' => $productCode]);

        if ($response['code'] !== 200 || !isset($response['decoded']['pricing'])) {
            return [];
        }

        return $this->normalizePricing($response['decoded']['pricing']);
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        try {
            $apiParams = $this->buildOrderParams($params);
            $apiParams['validate_only'] = 1;

            $response = $this->apiCall('/validate', $apiParams);

            if ($response['code'] === 200 && isset($response['decoded']['valid'])) {
                return [
                    'valid'  => (bool)$response['decoded']['valid'],
                    'errors' => $response['decoded']['errors'] ?? [],
                ];
            }

            return ['valid' => false, 'errors' => ['Validation request failed']];

        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function placeOrder(array $params): array
    {
        $apiParams = $this->buildOrderParams($params);
        $response = $this->apiCall('/place', $apiParams);

        if ($response['code'] !== 200) {
            $msg = $response['decoded']['message'] ?? 'Order placement failed';
            throw new \RuntimeException("NicSRS placeOrder: {$msg} (HTTP {$response['code']})");
        }

        $data = $response['decoded'];

        $this->log('info', 'Order placed successfully', [
            'order_id' => $data['order_id'] ?? 'unknown',
            'product'  => $params['product_code'] ?? '',
        ]);

        return [
            'order_id' => (string)($data['order_id'] ?? $data['remoteid'] ?? ''),
            'status'   => $data['status'] ?? 'Pending',
            'extra'    => $data,
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/collect', ['order_id' => $orderId]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException("Failed to get order status for #{$orderId}");
        }

        $data = $response['decoded'];

        return [
            'status'      => $this->normalizeStatus($data['status'] ?? ''),
            'certificate' => isset($data['cert']) ? [
                'cert'        => $data['cert'] ?? '',
                'ca'          => $data['ca'] ?? '',
                'private_key' => $data['private_key'] ?? '',
            ] : null,
            'domains'     => $data['domains'] ?? [],
            'begin_date'  => $data['beginDate'] ?? $data['begin_date'] ?? null,
            'end_date'    => $data['endDate'] ?? $data['end_date'] ?? null,
            'dcv_status'  => $data['dcv_status'] ?? [],
            'raw'         => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        $status = $this->getOrderStatus($orderId);

        if (!$status['certificate']) {
            throw new \RuntimeException('Certificate not yet issued for order #' . $orderId);
        }

        return $status['certificate'];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $apiParams = [
            'order_id'   => $orderId,
            'csr'        => $params['csr'] ?? '',
            'dcv_method' => $params['dcv_method'] ?? 'email',
        ];

        if (isset($params['dcv_email'])) {
            $apiParams['approver_email'] = $params['dcv_email'];
        }

        $response = $this->apiCall('/reissue', $apiParams);

        if ($response['code'] !== 200) {
            $msg = $response['decoded']['message'] ?? 'Reissue failed';
            return ['success' => false, 'message' => $msg];
        }

        $this->log('info', "Certificate reissued for order #{$orderId}");
        return ['success' => true, 'message' => 'Certificate reissue initiated.'];
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        $apiParams = array_merge($this->buildOrderParams($params), [
            'order_id' => $orderId,
        ]);

        $response = $this->apiCall('/renew', $apiParams);

        if ($response['code'] !== 200) {
            throw new \RuntimeException('Renewal failed: ' . ($response['decoded']['message'] ?? 'Unknown error'));
        }

        $data = $response['decoded'];
        $this->log('info', "Certificate renewed for order #{$orderId}");

        return [
            'order_id' => (string)($data['order_id'] ?? $orderId),
            'status'   => $data['status'] ?? 'Pending',
        ];
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $response = $this->apiCall('/revoke', [
            'order_id' => $orderId,
            'reason'   => $reason,
        ]);

        $success = ($response['code'] === 200);
        $msg = $response['decoded']['message'] ?? ($success ? 'Certificate revoked.' : 'Revocation failed.');

        if ($success) {
            $this->log('info', "Certificate revoked for order #{$orderId}");
        }

        return ['success' => $success, 'message' => $msg];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/cancel', ['order_id' => $orderId]);

        $success = ($response['code'] === 200);
        $msg = $response['decoded']['message'] ?? ($success ? 'Order cancelled.' : 'Cancellation failed.');

        if ($success) {
            $this->log('info', "Order #{$orderId} cancelled");
        }

        return ['success' => $success, 'message' => $msg];
    }

    // ─── DCV Management ────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/getDcvEmails', ['domain' => $domain]);

        if ($response['code'] !== 200) {
            return [];
        }

        return $response['decoded']['emails'] ?? $response['decoded'] ?? [];
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $params = ['order_id' => $orderId];
        if (!empty($email)) {
            $params['approver_email'] = $email;
        }

        $response = $this->apiCall('/resendDcv', $params);
        $success = ($response['code'] === 200);

        return [
            'success' => $success,
            'message' => $response['decoded']['message'] ?? ($success ? 'DCV email resent.' : 'Failed to resend.'),
        ];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $apiParams = array_merge($params, [
            'order_id'   => $orderId,
            'dcv_method' => $method,
        ]);

        $response = $this->apiCall('/changeDcv', $apiParams);
        $success = ($response['code'] === 200);

        return [
            'success' => $success,
            'message' => $response['decoded']['message'] ?? ($success ? 'DCV method changed.' : 'Failed to change DCV.'),
        ];
    }

    // ─── Internal Helpers ──────────────────────────────────────────

    /**
     * Make an API call to NicSRS
     *
     * @param string $endpoint
     * @param array  $params
     * @return array
     */
    private function apiCall(string $endpoint, array $params = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        $params['api_token'] = $this->getCredential('api_token');

        return $this->httpPost($url, $params);
    }

    /**
     * Build order parameters for API submission
     *
     * @param array $params
     * @return array
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

        // OV/EV contacts
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
     * @param array  $item
     * @param string $vendor
     * @return NormalizedProduct
     */
    private function normalizeProduct(array $item, string $vendor): NormalizedProduct
    {
        $type = 'ssl';
        $name = $item['product_name'] ?? $item['name'] ?? '';
        $nameLower = strtolower($name);

        if (strpos($nameLower, 'wildcard') !== false) {
            $type = 'wildcard';
        } elseif (strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false || strpos($nameLower, 'san') !== false) {
            $type = 'multi_domain';
        } elseif (strpos($nameLower, 'code sign') !== false) {
            $type = 'code_signing';
        } elseif (strpos($nameLower, 'email') !== false || strpos($nameLower, 's/mime') !== false) {
            $type = 'email';
        }

        $validation = 'dv';
        if (strpos($nameLower, ' ev ') !== false || strpos($nameLower, 'extended') !== false) {
            $validation = 'ev';
        } elseif (strpos($nameLower, ' ov ') !== false || strpos($nameLower, 'organization') !== false) {
            $validation = 'ov';
        }

        return new NormalizedProduct([
            'product_code'     => $item['product_code'] ?? $item['code'] ?? '',
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => (bool)($item['wildcard'] ?? ($type === 'wildcard')),
            'support_san'      => (bool)($item['san'] ?? ($type === 'multi_domain')),
            'max_domains'      => (int)($item['max_domains'] ?? ($type === 'multi_domain' ? 250 : 1)),
            'max_years'        => (int)($item['max_years'] ?? 3),
            'min_years'        => (int)($item['min_years'] ?? 1),
            'price_data'       => $this->normalizePricing($item['pricing'] ?? $item['prices'] ?? []),
            'extra_data'       => [
                'vendor'    => $vendor,
                'raw_code'  => $item['product_code'] ?? $item['code'] ?? '',
                'raw_id'    => $item['id'] ?? null,
            ],
        ]);
    }

    /**
     * Normalize pricing data to standard format
     *
     * @param array $pricing
     * @return array ['base'=>['12'=>float,...], 'san'=>['12'=>float,...], 'wildcard_san'=>['12'=>float,...]]
     */
    private function normalizePricing(array $pricing): array
    {
        $normalized = ['base' => [], 'san' => [], 'wildcard_san' => []];

        // NicSRS returns pricing like: {'1year': 7.95, '2year': 15.90, ...}
        // or {'12': 7.95, '24': 15.90, ...}
        foreach ($pricing as $key => $value) {
            if (is_array($value)) {
                // Nested: {'base': {...}, 'san': {...}}
                if (in_array($key, ['base', 'san', 'wildcard_san'])) {
                    foreach ($value as $period => $price) {
                        $months = $this->periodToMonths($period);
                        if ($months) {
                            $normalized[$key][(string)$months] = (float)$price;
                        }
                    }
                }
            } else {
                $months = $this->periodToMonths($key);
                if ($months) {
                    $normalized['base'][(string)$months] = (float)$value;
                }
            }
        }

        return $normalized;
    }

    /**
     * Convert period key to months
     *
     * @param string|int $period
     * @return int|null
     */
    private function periodToMonths($period): ?int
    {
        $p = strtolower(trim((string)$period));

        $map = [
            '1year' => 12, '2year' => 24, '3year' => 36, '4year' => 48, '5year' => 60,
            '1' => 12, '2' => 24, '3' => 36, '4' => 48, '5' => 60,
            '12' => 12, '24' => 24, '36' => 36, '48' => 48, '60' => 60,
        ];

        return $map[$p] ?? null;
    }

    /**
     * Normalize order status string
     *
     * @param string $status
     * @return string
     */
    private function normalizeStatus(string $status): string
    {
        $map = [
            'active'      => 'Completed',
            'issued'      => 'Completed',
            'completed'   => 'Completed',
            'pending'     => 'Pending',
            'processing'  => 'Processing',
            'cancelled'   => 'Cancelled',
            'expired'     => 'Expired',
            'rejected'    => 'Rejected',
            'revoked'     => 'Revoked',
            'refunded'    => 'Refunded',
        ];

        $lower = strtolower(trim($status));
        return $map[$lower] ?? ucfirst($lower);
    }
}