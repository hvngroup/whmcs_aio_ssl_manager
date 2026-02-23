<?php
/**
 * GoGetSSL Provider — Full-tier SSL provider integration
 *
 * API: https://my.gogetssl.com/api/
 * Auth: Username/Password → session token
 * Capabilities: Full lifecycle
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
    private const API_URL = 'https://my.gogetssl.com/api';
    private const SANDBOX_URL = 'https://sandbox.gogetssl.com/api';

    /** @var string|null Cached auth token */
    private $authToken = null;

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string { return 'gogetssl'; }
    public function getName(): string { return 'GoGetSSL'; }
    public function getTier(): string { return 'full'; }

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

    // ─── Auth ──────────────────────────────────────────────────────

    /**
     * Authenticate and get session token
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
            $msg = $response['decoded']['message'] ?? $response['decoded']['description'] ?? 'Authentication failed';
            throw new \RuntimeException("GoGetSSL auth failed: {$msg}");
        }

        $this->authToken = $response['decoded']['key'];
        return $this->authToken;
    }

    /**
     * Make authenticated API call
     */
    private function apiCall(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $token = $this->authenticate();
        $url = $this->getBaseUrl() . $endpoint;

        if ($method === 'GET') {
            $params['auth_key'] = $token;
            return $this->httpGet($url, $params);
        }

        $params['auth_key'] = $token;
        return $this->httpPost($url, $params);
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

    public function fetchProducts(): array
    {
        $response = $this->apiCall('/products/');
        if ($response['code'] !== 200 || !is_array($response['decoded'])) {
            throw new \RuntimeException('GoGetSSL: Failed to fetch products');
        }

        $products = [];
        foreach ($response['decoded'] as $item) {
            if (!isset($item['id'])) continue;
            $products[] = $this->normalizeProduct($item);
        }

        return $products;
    }

    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/products/price/' . $productCode . '/');
        if ($response['code'] !== 200 || !is_array($response['decoded'])) {
            return [];
        }
        return $this->normalizePricing($response['decoded']);
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        // GoGetSSL uses CSR decode for validation
        try {
            $response = $this->apiCall('/tools/csr/decode/', [
                'csr' => $params['csr'] ?? '',
            ], 'POST');

            if ($response['code'] === 200 && !empty($response['decoded']['csrResult'])) {
                return ['valid' => true, 'errors' => []];
            }
            return ['valid' => false, 'errors' => ['CSR validation failed']];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function placeOrder(array $params): array
    {
        $apiParams = [
            'product_id'    => $params['product_code'],
            'csr'           => $params['csr'] ?? '',
            'server_count'  => -1,
            'period'        => $this->monthsToYears($params['period'] ?? 12),
            'approver_email'=> $params['dcv_email'] ?? '',
            'dcv_method'    => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
            'webserver_type'=> $params['server_type'] ?? -1,
        ];

        if (isset($params['domains']) && is_array($params['domains'])) {
            $apiParams['dns_names'] = implode(',', $params['domains']);
        }

        // Admin contact for OV/EV
        if (isset($params['admin_contact'])) {
            $c = $params['admin_contact'];
            $apiParams['admin_firstname'] = $c['first_name'] ?? '';
            $apiParams['admin_lastname']  = $c['last_name'] ?? '';
            $apiParams['admin_email']     = $c['email'] ?? '';
            $apiParams['admin_phone']     = $c['phone'] ?? '';
            $apiParams['admin_title']     = $c['title'] ?? '';
        }

        // Organization for OV/EV
        if (isset($params['org_info'])) {
            $o = $params['org_info'];
            $apiParams['org_name']        = $o['name'] ?? '';
            $apiParams['org_division']    = $o['division'] ?? '';
            $apiParams['org_addressline1']= $o['address'] ?? '';
            $apiParams['org_city']        = $o['city'] ?? '';
            $apiParams['org_region']      = $o['state'] ?? '';
            $apiParams['org_postalcode']  = $o['zip'] ?? '';
            $apiParams['org_country']     = $o['country'] ?? '';
            $apiParams['org_phone']       = $o['phone'] ?? '';
        }

        $response = $this->apiCall('/orders/add_ssl_order/', $apiParams, 'POST');

        if ($response['code'] !== 200 || !isset($response['decoded']['order_id'])) {
            $msg = $response['decoded']['message'] ?? $response['decoded']['description'] ?? 'Order failed';
            throw new \RuntimeException("GoGetSSL placeOrder: {$msg}");
        }

        return [
            'order_id' => (string)$response['decoded']['order_id'],
            'status'   => 'Pending',
            'extra'    => $response['decoded'],
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/orders/status/' . $orderId . '/');

        if ($response['code'] !== 200) {
            throw new \RuntimeException("GoGetSSL: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded'];
        $cert = null;
        if (!empty($data['crt_code'])) {
            $cert = [
                'cert'        => $data['crt_code'] ?? '',
                'ca'          => $data['ca_code'] ?? '',
                'private_key' => '',
            ];
        }

        return [
            'status'      => $this->normalizeStatus($data['status'] ?? ''),
            'certificate' => $cert,
            'domains'     => isset($data['san']) ? explode(',', $data['san']) : [$data['domain'] ?? ''],
            'begin_date'  => $data['valid_from'] ?? null,
            'end_date'    => $data['valid_till'] ?? null,
            'dcv_status'  => $data['dcv_status'] ?? [],
            'raw'         => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        $status = $this->getOrderStatus($orderId);
        if (!$status['certificate']) {
            throw new \RuntimeException('Certificate not yet issued.');
        }
        return $status['certificate'];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $response = $this->apiCall('/orders/ssl/reissue/' . $orderId . '/', [
            'csr'        => $params['csr'] ?? '',
            'dcv_method' => $this->mapDcvMethod($params['dcv_method'] ?? 'email'),
        ], 'POST');

        $success = ($response['code'] === 200);
        return [
            'success' => $success,
            'message' => $response['decoded']['message'] ?? ($success ? 'Reissue initiated.' : 'Reissue failed.'),
        ];
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        // GoGetSSL renewal = new order with renewal flag
        $params['renew'] = true;
        return $this->placeOrder($params);
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $response = $this->apiCall('/orders/ssl/revoke/' . $orderId . '/', [
            'reason' => $reason,
        ], 'POST');

        $success = ($response['code'] === 200);
        return [
            'success' => $success,
            'message' => $response['decoded']['message'] ?? ($success ? 'Revoked.' : 'Revocation failed.'),
        ];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/orders/cancel/' . $orderId . '/', [], 'POST');
        $success = ($response['code'] === 200);
        return [
            'success' => $success,
            'message' => $response['decoded']['message'] ?? ($success ? 'Cancelled.' : 'Cancel failed.'),
        ];
    }

    // ─── DCV ───────────────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/tools/domain/emails/', ['domain' => $domain], 'POST');
        if ($response['code'] === 200 && isset($response['decoded'])) {
            // GoGetSSL returns array of emails or object with email list
            $emails = $response['decoded'];
            if (isset($emails['emails'])) $emails = $emails['emails'];
            return is_array($emails) ? $emails : [];
        }
        return [];
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $params = [];
        if ($email) $params['approver_email'] = $email;
        $response = $this->apiCall('/orders/ssl/resend_validation_email/' . $orderId . '/', $params, 'POST');
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

    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name = $item['name'] ?? '';
        $nameLower = strtolower($name);

        $type = 'ssl';
        if (strpos($nameLower, 'wildcard') !== false) $type = 'wildcard';
        elseif (strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false) $type = 'multi_domain';
        elseif (strpos($nameLower, 'code sign') !== false) $type = 'code_signing';

        $validation = 'dv';
        if (strpos($nameLower, ' ev') !== false || strpos($nameLower, 'extended') !== false) $validation = 'ev';
        elseif (strpos($nameLower, ' ov') !== false || strpos($nameLower, 'organization') !== false) $validation = 'ov';

        $vendor = $item['brand'] ?? 'Unknown';

        $priceData = ['base' => []];
        if (isset($item['prices'])) {
            foreach ($item['prices'] as $period => $price) {
                $months = (int)$period * 12;
                if ($months > 0) {
                    $priceData['base'][(string)$months] = (float)$price;
                }
            }
        } elseif (isset($item['price'])) {
            $priceData['base']['12'] = (float)$item['price'];
        }

        return new NormalizedProduct([
            'product_code'     => (string)($item['id'] ?? ''),
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
            'extra_data'       => ['gogetssl_id' => $item['id'] ?? null, 'brand' => $vendor],
        ]);
    }

    private function normalizePricing(array $data): array
    {
        $result = ['base' => [], 'san' => []];
        foreach ($data as $period => $price) {
            $months = (int)$period * 12;
            if ($months > 0) {
                $result['base'][(string)$months] = (float)$price;
            }
        }
        return $result;
    }

    private function normalizeStatus(string $status): string
    {
        $map = [
            'active' => 'Completed', 'issued' => 'Completed', 'processing' => 'Processing',
            'pending' => 'Pending', 'cancelled' => 'Cancelled', 'expired' => 'Expired',
            'rejected' => 'Rejected', 'incomplete' => 'Awaiting Configuration',
            'unpaid' => 'Pending', 'new_order' => 'Pending', 'reissue' => 'Processing',
        ];
        return $map[strtolower(trim($status))] ?? ucfirst($status);
    }

    private function mapDcvMethod(string $method): string
    {
        $map = ['email' => 'EMAIL', 'http' => 'HTTP', 'https' => 'HTTPS', 'cname' => 'CNAME_CSR_HASH'];
        return $map[strtolower($method)] ?? 'EMAIL';
    }

    private function monthsToYears(int $months): int
    {
        return max(1, (int)ceil($months / 12));
    }
}