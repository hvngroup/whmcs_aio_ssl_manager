<?php
/**
 * SSL2Buy Provider — Limited-tier SSL provider
 *
 * API: https://api.ssl2buy.com/1.0/
 * Auth: partner_email + api_key
 * Capabilities: Limited (order, status, config link, approval resend)
 *   - Cannot: reissue, renew, revoke, cancel, download cert, change DCV
 *   - Client manages via configuration link from provider portal
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
    private const API_URL = 'https://api.ssl2buy.com/1.0';

    /** @var array Brand → API path mapping */
    private const BRAND_PATHS = [
        'sectigo'    => 'Sectigo',
        'comodo'     => 'Sectigo',    // Legacy name
        'digicert'   => 'DigiCert',
        'geotrust'   => 'GeoTrust',
        'thawte'     => 'Thawte',
        'rapidssl'   => 'RapidSSL',
        'globalsign' => 'GlobalSign',
        'certera'    => 'Certera',
    ];

    public function getSlug(): string { return 'ssl2buy'; }
    public function getName(): string { return 'SSL2Buy'; }
    public function getTier(): string { return 'limited'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'config_link', 'balance',
            'dcv_email', // approval resend only
        ];
    }

    protected function getBaseUrl(): string
    {
        return self::API_URL;
    }

    // ─── Auth ──────────────────────────────────────────────────────

    private function apiCall(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        $data['partnerEmail'] = $this->getCredential('partner_email');
        $data['apiKey'] = $this->getCredential('api_key');

        if ($method === 'GET') {
            return $this->httpGet($url, $data);
        }
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
        if ($response['code'] === 200 && isset($response['decoded']['balance'])) {
            return ['balance' => (float)$response['decoded']['balance'], 'currency' => 'USD'];
        }
        // Fallback: try to parse from response
        if ($response['code'] === 200 && isset($response['decoded']['result'])) {
            return ['balance' => (float)($response['decoded']['result']['balance'] ?? 0), 'currency' => 'USD'];
        }
        return ['balance' => 0, 'currency' => 'USD'];
    }

    // ─── Products ──────────────────────────────────────────────────

    public function fetchProducts(): array
    {
        $products = [];

        foreach (self::BRAND_PATHS as $slug => $brandPath) {
            try {
                $response = $this->apiCall("/queryservice/{$brandPath}/productlist");

                if ($response['code'] !== 200 || empty($response['decoded'])) {
                    continue;
                }

                $productList = $response['decoded']['products'] ?? $response['decoded'] ?? [];
                if (!is_array($productList)) continue;

                foreach ($productList as $item) {
                    if (!is_array($item)) continue;
                    $products[] = $this->normalizeProduct($item, $brandPath);
                }

                usleep(300000); // 300ms delay between brand calls
            } catch (\Exception $e) {
                $this->log('warning', "SSL2Buy fetchProducts failed for {$brandPath}: " . $e->getMessage());
            }
        }

        return $products;
    }

    public function fetchPricing(string $productCode): array
    {
        // Pricing is included in product list response
        return [];
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        return ['valid' => true, 'errors' => []]; // Basic validation only
    }

    public function placeOrder(array $params): array
    {
        $brand = $params['brand'] ?? 'Sectigo';
        $brandPath = self::BRAND_PATHS[strtolower($brand)] ?? $brand;

        $data = [
            'productCode'   => $params['product_code'] ?? '',
            'csr'           => $params['csr'] ?? '',
            'period'        => $params['period'] ?? 12,
            'serverType'    => $params['server_type'] ?? -1,
            'approverEmail' => $params['dcv_email'] ?? '',
        ];

        if (isset($params['domains'])) {
            $data['domainNames'] = is_array($params['domains'])
                ? implode(',', $params['domains'])
                : $params['domains'];
        }

        $response = $this->apiCall("/orderservice/{$brandPath}/placeorder", $data);

        if ($response['code'] !== 200) {
            $msg = $response['decoded']['message'] ?? 'Order failed';
            throw new \RuntimeException("SSL2Buy placeOrder: {$msg}");
        }

        $result = $response['decoded'];
        $orderId = $result['orderId'] ?? $result['order_id'] ?? '';

        $this->log('info', 'SSL2Buy order placed', ['order_id' => $orderId]);

        return [
            'order_id' => (string)$orderId,
            'status'   => 'Pending',
            'extra'    => array_merge($result, ['brand' => $brandPath]),
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/orderservice/order/getstatus', [
            'orderId' => $orderId,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException("SSL2Buy: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded'];

        return [
            'status'      => $this->normalizeStatus($data['status'] ?? $data['orderStatus'] ?? ''),
            'certificate' => null, // SSL2Buy: cert downloaded via config link
            'domains'     => isset($data['domain']) ? [$data['domain']] : [],
            'begin_date'  => $data['certificateStartDate'] ?? null,
            'end_date'    => $data['certificateEndDate'] ?? null,
            'dcv_status'  => [],
            'raw'         => $data,
        ];
    }

    // ─── Config Link (Primary management method) ───────────────────

    public function getConfigurationLink(string $orderId): array
    {
        $response = $this->apiCall('/orderservice/order/getsslconfigurationlink', [
            'orderId' => $orderId,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException('Failed to get configuration link.');
        }

        $data = $response['decoded'];
        return [
            'url' => $data['configurationLink'] ?? $data['url'] ?? '',
            'pin' => $data['pin'] ?? $data['configurationPin'] ?? null,
        ];
    }

    // ─── DCV (Limited) ─────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        throw new UnsupportedOperationException($this->getName(), 'getDcvEmails');
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        // SSL2Buy: resend approval via brand-specific endpoint
        // We need the brand from the order's configdata
        $response = $this->apiCall('/queryservice/resendapprovalemail', [
            'orderId' => $orderId,
        ]);

        $success = ($response['code'] === 200);
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

    private function normalizeProduct(array $item, string $brand): NormalizedProduct
    {
        $name = $item['productName'] ?? $item['name'] ?? '';
        $nameLower = strtolower($name);

        $type = 'ssl';
        if (strpos($nameLower, 'wildcard') !== false) $type = 'wildcard';
        elseif (strpos($nameLower, 'multi') !== false || strpos($nameLower, 'san') !== false || strpos($nameLower, 'ucc') !== false) $type = 'multi_domain';
        elseif (strpos($nameLower, 'code sign') !== false) $type = 'code_signing';
        elseif (strpos($nameLower, 'email') !== false || strpos($nameLower, 's/mime') !== false) $type = 'email';

        $validation = 'dv';
        if (strpos($nameLower, ' ev') !== false || strpos($nameLower, 'extended') !== false) $validation = 'ev';
        elseif (strpos($nameLower, ' ov') !== false || strpos($nameLower, 'organization') !== false) $validation = 'ov';

        $priceData = ['base' => []];
        if (isset($item['price']) || isset($item['prices'])) {
            $prices = $item['prices'] ?? ['1' => $item['price'] ?? 0];
            foreach ($prices as $period => $price) {
                $months = (int)$period * 12;
                if ($months > 0) {
                    $priceData['base'][(string)$months] = (float)$price;
                }
            }
        }

        $code = $item['productCode'] ?? $item['id'] ?? '';

        return new NormalizedProduct([
            'product_code'     => (string)$code,
            'product_name'     => $name,
            'vendor'           => $brand,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => ($type === 'wildcard'),
            'support_san'      => ($type === 'multi_domain'),
            'max_domains'      => (int)($item['maxDomains'] ?? 1),
            'max_years'        => (int)($item['maxPeriod'] ?? 2),
            'min_years'        => 1,
            'price_data'       => $priceData,
            'extra_data'       => ['brand' => $brand, 'ssl2buy_code' => $code],
        ]);
    }

    private function normalizeStatus(string $status): string
    {
        $map = [
            'active' => 'Completed', 'issued' => 'Completed',
            'pending' => 'Pending', 'processing' => 'Processing',
            'cancelled' => 'Cancelled', 'expired' => 'Expired',
            'rejected' => 'Rejected', 'awaiting_validation' => 'Pending',
            'new' => 'Awaiting Configuration',
        ];
        return $map[strtolower(str_replace(' ', '_', trim($status)))] ?? ucfirst($status);
    }
}