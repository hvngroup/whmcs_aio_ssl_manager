<?php
/**
 * TheSSLStore Provider — Full-tier SSL provider (REST JSON API)
 *
 * API: https://api.thesslstore.com/rest/
 * Auth: partner_code + auth_token in request headers/body
 * Capabilities: Full lifecycle
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;

class TheSSLStoreProvider extends AbstractProvider
{
    private const API_URL = 'https://api.thesslstore.com/rest';
    private const SANDBOX_URL = 'https://sandbox-wbapi.thesslstore.com/rest';

    public function getSlug(): string { return 'thesslstore'; }
    public function getName(): string { return 'TheSSLStore'; }
    public function getTier(): string { return 'full'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'reissue', 'renew', 'revoke', 'cancel', 'download',
            'dcv_email', 'dcv_http', 'dcv_cname',
            'validate_order', 'get_dcv_emails', 'change_dcv',
        ];
    }

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox') ? self::SANDBOX_URL : self::API_URL;
    }

    // ─── Auth Headers ──────────────────────────────────────────────

    private function authPayload(): array
    {
        return [
            'AuthRequest' => [
                'PartnerCode' => $this->getCredential('partner_code'),
                'AuthToken'   => $this->getCredential('auth_token'),
                'ReplayToken' => uniqid('tss_', true),
                'UserAgent'   => 'AIO-SSL-Manager/' . AIO_SSL_VERSION,
            ],
        ];
    }

    private function apiCall(string $endpoint, array $data = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        $payload = array_merge($this->authPayload(), $data);
        return $this->httpPostJson($url, $payload);
    }

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $response = $this->apiCall('/product/query', [
                'ProductType' => 0,
                'NeedSortedList' => true,
            ]);

            if ($response['code'] === 200 && isset($response['decoded']['AuthResponse'])) {
                $auth = $response['decoded']['AuthResponse'];
                if (isset($auth['isError']) && $auth['isError']) {
                    return ['success' => false, 'message' => $auth['Message'][0] ?? 'Auth error', 'balance' => null];
                }
                return ['success' => true, 'message' => 'TheSSLStore connected successfully.', 'balance' => null];
            }
            return ['success' => false, 'message' => 'Unexpected response.', 'balance' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'balance' => null];
        }
    }

    // ─── Products ──────────────────────────────────────────────────

    public function fetchProducts(): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductType' => 0,
            'NeedSortedList' => true,
        ]);

        if ($response['code'] !== 200 || !isset($response['decoded']['ProductList'])) {
            throw new \RuntimeException('TheSSLStore: Failed to fetch products');
        }

        $products = [];
        foreach ($response['decoded']['ProductList'] as $item) {
            $products[] = $this->normalizeProduct($item);
        }

        return $products;
    }

    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductCode' => $productCode,
            'ProductType' => 0,
        ]);

        if ($response['code'] === 200 && isset($response['decoded']['ProductList'][0])) {
            $item = $response['decoded']['ProductList'][0];
            return $this->extractPricing($item);
        }
        return [];
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        // Validate CSR
        try {
            $response = $this->apiCall('/csr/decode', [
                'CSR' => $params['csr'] ?? '',
            ]);

            if ($response['code'] === 200 && isset($response['decoded']['DomainName'])) {
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
            'ProductCode'      => $params['product_code'] ?? '',
            'ExtraProductCodes'=> '',
            'OrganisationInfo' => [
                'OrganisationName' => $params['org_info']['name'] ?? '',
                'DUNS'             => '',
                'Division'         => $params['org_info']['division'] ?? '',
                'IncorporatingAgency' => '',
                'RegistrationNumber'  => '',
                'JurisdictionCity'    => $params['org_info']['city'] ?? '',
                'JurisdictionRegion'  => $params['org_info']['state'] ?? '',
                'JurisdictionCountry' => $params['org_info']['country'] ?? '',
                'OrganisationAddress' => [
                    'AddressLine1' => $params['org_info']['address'] ?? '',
                    'City'         => $params['org_info']['city'] ?? '',
                    'Region'       => $params['org_info']['state'] ?? '',
                    'PostalCode'   => $params['org_info']['zip'] ?? '',
                    'Country'      => $params['org_info']['country'] ?? '',
                    'Phone'        => $params['org_info']['phone'] ?? '',
                ],
            ],
            'ValidityPeriod'   => $this->monthsToYears($params['period'] ?? 12),
            'ServerCount'      => -1,
            'CSR'              => $params['csr'] ?? '',
            'DomainName'       => $params['domains'][0] ?? '',
            'WebServerType'    => 'Other',
            'ApproverEmail'    => $params['dcv_email'] ?? '',
        ];

        // Admin contact
        if (isset($params['admin_contact'])) {
            $c = $params['admin_contact'];
            $data['AdminContact'] = [
                'FirstName' => $c['first_name'] ?? '',
                'LastName'  => $c['last_name'] ?? '',
                'Phone'     => $c['phone'] ?? '',
                'Email'     => $c['email'] ?? '',
                'Title'     => $c['title'] ?? '',
            ];
        }

        // Tech contact
        if (isset($params['tech_contact'])) {
            $c = $params['tech_contact'];
            $data['TechnicalContact'] = [
                'FirstName' => $c['first_name'] ?? '',
                'LastName'  => $c['last_name'] ?? '',
                'Phone'     => $c['phone'] ?? '',
                'Email'     => $c['email'] ?? '',
            ];
        }

        $response = $this->apiCall('/order/neworder', $data);

        if ($response['code'] !== 200) {
            $msg = $this->extractError($response);
            throw new \RuntimeException("TheSSLStore placeOrder: {$msg}");
        }

        $result = $response['decoded'];
        $orderId = $result['TheSSLStoreOrderID'] ?? $result['VendorOrderID'] ?? '';

        return [
            'order_id' => (string)$orderId,
            'status'   => 'Pending',
            'extra'    => $result,
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/order/query', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200) {
            throw new \RuntimeException("TheSSLStore: Failed to get status for #{$orderId}");
        }

        $data = $response['decoded'];
        $cert = null;
        if (!empty($data['CertificateData']['Certificate'])) {
            $cert = [
                'cert'        => $data['CertificateData']['Certificate'] ?? '',
                'ca'          => $data['CertificateData']['CACertificate'] ?? '',
                'private_key' => '',
            ];
        }

        return [
            'status'      => $this->normalizeStatus($data['OrderStatus']['MajorStatus'] ?? ''),
            'certificate' => $cert,
            'domains'     => [$data['CommonName'] ?? ''],
            'begin_date'  => $data['CertificateStartDate'] ?? null,
            'end_date'    => $data['CertificateEndDate'] ?? null,
            'dcv_status'  => [],
            'raw'         => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        $response = $this->apiCall('/order/download', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200 || empty($response['decoded']['CertificateData'])) {
            throw new \RuntimeException('Certificate download failed.');
        }

        $cd = $response['decoded']['CertificateData'];
        return [
            'cert'        => $cd['Certificate'] ?? '',
            'ca'          => $cd['CACertificate'] ?? '',
            'private_key' => '',
        ];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $response = $this->apiCall('/order/reissue', [
            'TheSSLStoreOrderID' => $orderId,
            'CSR'                => $params['csr'] ?? '',
            'WebServerType'      => 'Other',
            'ApproverEmail'      => $params['dcv_email'] ?? '',
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Reissue initiated.' : $this->extractError($response)];
    }

    public function renewCertificate(string $orderId, array $params): array
    {
        $params['product_code'] = $params['product_code'] ?? '';
        return $this->placeOrder($params); // Renewal = new order
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $response = $this->apiCall('/order/revoke', [
            'TheSSLStoreOrderID' => $orderId,
            'RevokeReason'       => $reason,
        ]);
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Revoked.' : $this->extractError($response)];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/order/cancel', [
            'TheSSLStoreOrderID' => $orderId,
        ]);
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Cancelled.' : $this->extractError($response)];
    }

    // ─── DCV ───────────────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/order/approverlist', [
            'DomainName' => $domain,
            'ProductCode'=> '',
        ]);

        if ($response['code'] === 200 && isset($response['decoded']['ApproverEmailList'])) {
            return $response['decoded']['ApproverEmailList'];
        }
        return [];
    }

    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $params = ['TheSSLStoreOrderID' => $orderId];
        if ($email) $params['ApproverEmail'] = $email;

        $response = $this->apiCall('/order/resend', $params);
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'DCV resent.' : 'Failed.'];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $response = $this->apiCall('/order/changedcv', array_merge([
            'TheSSLStoreOrderID' => $orderId,
            'DCVMethod'          => strtoupper($method),
        ], $params));
        $success = ($response['code'] === 200);
        return ['success' => $success, 'message' => $success ? 'DCV changed.' : 'Failed.'];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name = $item['ProductName'] ?? '';
        $nameLower = strtolower($name);

        $type = 'ssl';
        if (strpos($nameLower, 'wildcard') !== false) $type = 'wildcard';
        elseif (strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false || strpos($nameLower, 'san') !== false) $type = 'multi_domain';
        elseif (strpos($nameLower, 'code sign') !== false) $type = 'code_signing';

        $validation = 'dv';
        if (!empty($item['ProductValidationType'])) {
            $vt = strtolower($item['ProductValidationType']);
            if (strpos($vt, 'ev') !== false) $validation = 'ev';
            elseif (strpos($vt, 'ov') !== false) $validation = 'ov';
        }

        $vendor = $item['VendorName'] ?? $item['BrandName'] ?? 'Unknown';
        $pricing = $this->extractPricing($item);

        return new NormalizedProduct([
            'product_code'     => $item['ProductCode'] ?? '',
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => ($type === 'wildcard'),
            'support_san'      => (bool)($item['isSanEnable'] ?? false),
            'max_domains'      => (int)($item['MaxSan'] ?? 1),
            'max_years'        => (int)($item['MaxPeriod'] ?? 2),
            'min_years'        => 1,
            'price_data'       => $pricing,
            'extra_data'       => ['vendor' => $vendor, 'product_code' => $item['ProductCode'] ?? ''],
        ]);
    }

    private function extractPricing(array $item): array
    {
        $result = ['base' => [], 'san' => []];
        if (isset($item['PricingInfo'])) {
            foreach ($item['PricingInfo'] as $pi) {
                $months = (int)($pi['NumberOfMonths'] ?? 0);
                $price = (float)($pi['Price'] ?? 0);
                if ($months > 0 && $price > 0) {
                    $result['base'][(string)$months] = $price;
                }
            }
        } elseif (isset($item['Price'])) {
            $result['base']['12'] = (float)$item['Price'];
        }
        return $result;
    }

    private function normalizeStatus(string $status): string
    {
        $map = [
            'active' => 'Completed', 'issued' => 'Completed',
            'pending' => 'Pending', 'processing' => 'Processing',
            'cancelled' => 'Cancelled', 'expired' => 'Expired',
            'rejected' => 'Rejected', 'initial' => 'Awaiting Configuration',
        ];
        return $map[strtolower(trim($status))] ?? ucfirst($status);
    }

    private function hasError(array $response): bool
    {
        $auth = $response['decoded']['AuthResponse'] ?? [];
        return !empty($auth['isError']);
    }

    private function extractError(array $response): string
    {
        $auth = $response['decoded']['AuthResponse'] ?? [];
        if (isset($auth['Message']) && is_array($auth['Message'])) {
            return implode('; ', $auth['Message']);
        }
        return $response['decoded']['message'] ?? 'Unknown error (HTTP ' . $response['code'] . ')';
    }

    private function monthsToYears(int $months): int
    {
        return max(1, (int)ceil($months / 12));
    }
}