<?php
/**
 * TheSSLStore Provider — Full-tier SSL provider integration
 *
 * API: https://api.thesslstore.com/rest
 * Auth: PartnerCode + AuthToken in JSON body (AuthRequest object)
 * Content-Type: application/json; charset=utf-8
 *
 * CRITICAL: Renew = new order with isRenewalOrder=true (C7)
 * CRITICAL: Sandbox URL = sandbox-wbapi.thesslstore.com
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

    // ─── Identity ──────────────────────────────────────────────────

    public function getSlug(): string  { return 'thesslstore'; }
    public function getName(): string  { return 'TheSSLStore'; }
    public function getTier(): string  { return 'full'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'reissue', 'revoke', 'cancel', 'download',
            'dcv_email', 'dcv_http', 'dcv_cname',
            'validate_order', 'get_dcv_emails', 'change_dcv',
            'invite_order', 'midterm_upgrade',
        ];
    }

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox') ? self::SANDBOX_URL : self::API_URL;
    }

    // ─── Auth (JSON Body) ──────────────────────────────────────────

    /**
     * Build request body with AuthRequest embedded
     *
     * TheSSLStore requires AuthRequest { PartnerCode, AuthToken }
     * in every JSON request body
     */
    private function buildAuthBody(array $params = []): array
    {
        return array_merge([
            'AuthRequest' => [
                'PartnerCode' => $this->getCredential('partner_code'),
                'AuthToken'   => $this->getCredential('auth_token'),
            ],
        ], $params);
    }

    /**
     * Make API call to TheSSLStore
     *
     * All requests are POST with JSON body containing AuthRequest
     */
    private function apiCall(string $endpoint, array $params = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        $body = $this->buildAuthBody($params);

        return $this->httpPostJson($url, $body);
    }

    /**
     * Check if API response contains an error
     */
    private function hasError(array $response): bool
    {
        $decoded = $response['decoded'] ?? [];

        // Error in AuthResponse
        if (isset($decoded['AuthResponse']['isError']) && $decoded['AuthResponse']['isError']) {
            return true;
        }

        // Error in first array element's AuthResponse
        if (is_array($decoded) && isset($decoded[0])) {
            $first = is_array($decoded[0]) ? $decoded[0] : (array)$decoded[0];
            $auth = $first['AuthResponse'] ?? null;
            if ($auth && !empty($auth['isError'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract error message from response
     */
    private function extractError(array $response): string
    {
        $decoded = $response['decoded'] ?? [];

        // Check AuthResponse.Message
        $auth = $decoded['AuthResponse'] ?? null;
        if ($auth && isset($auth['Message'])) {
            return is_array($auth['Message']) ? implode('; ', $auth['Message']) : $auth['Message'];
        }

        // Check first element
        if (is_array($decoded) && isset($decoded[0])) {
            $first = is_array($decoded[0]) ? $decoded[0] : (array)$decoded[0];
            $auth = $first['AuthResponse'] ?? null;
            if ($auth && isset($auth['Message'])) {
                return is_array($auth['Message']) ? implode('; ', $auth['Message']) : $auth['Message'];
            }
        }

        return 'Unknown error (HTTP ' . ($response['code'] ?? '?') . ')';
    }

    // ─── Connection ────────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            // Use /product/query with minimal params as connection test
            $response = $this->apiCall('/product/query', [
                'ProductType'    => 0,
                'NeedSortedList' => true,
            ]);

            $httpCode = $response['code'];
            $decoded = $response['decoded'];

            if ($httpCode !== 200 || empty($decoded)) {
                return [
                    'success' => false,
                    'message' => 'TheSSLStore: HTTP ' . $httpCode . ' — empty response.',
                    'balance' => null,
                ];
            }

            // API returns flat array: [{product}, {product}, ...]
            // If auth fails, first element contains AuthResponse.isError
            if (is_array($decoded) && isset($decoded[0])) {
                $first = is_array($decoded[0]) ? $decoded[0] : (array)$decoded[0];

                // Check auth error
                $auth = $first['AuthResponse'] ?? null;
                if ($auth && !empty($auth['isError'])) {
                    return [
                        'success' => false,
                        'message' => $this->extractError($response),
                        'balance' => null,
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'TheSSLStore connected. ' . count($decoded) . ' products available.',
                    'balance' => null,
                ];
            }

            // Single object error response
            if ($this->hasError($response)) {
                return ['success' => false, 'message' => $this->extractError($response), 'balance' => null];
            }

            return ['success' => false, 'message' => 'Unexpected response format.', 'balance' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage(), 'balance' => null];
        }
    }

    public function getBalance(): array
    {
        // TheSSLStore does not have a balance endpoint
        return ['balance' => 0, 'currency' => 'USD'];
    }

    // ─── Products ──────────────────────────────────────────────────

    /**
     * Fetch all available products from TheSSLStore
     *
     * TheSSLStore API: POST /product/query
     * Body: { AuthRequest: {...}, ProductType: 0, NeedSortedList: true }
     *
     * Response: FLAT ARRAY of product objects (not nested under a key!)
     * [
     *   {
     *     "AuthResponse": { "isError": false, ... },
     *     "ProductCode": "positivessl",
     *     "ProductName": "Sectigo Positive SSL",
     *     "ProductType": "1",
     *     "VendorName": "Sectigo",
     *     "BrandName": "Sectigo",
     *     "ProductValidationType": "DV",
     *     "MinSan": 0,
     *     "MaxSan": 0,
     *     "isWildcardProduct": false,
     *     "isMultiDomainProduct": false,
     *     "isCodeSigningProduct": false,
     *     "PricingInfo": [
     *       {
     *         "NumberOfMonths": 12,
     *         "Price": 5.99,
     *         "PricePerAdditionalSAN": 3.00,
     *         "PricePerWildcardSAN": 0,
     *         "WildcardPrice": 0
     *       },
     *       ...
     *     ]
     *   },
     *   ...
     * ]
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductType'    => 0,
            'NeedSortedList' => true,
        ]);

        $decoded = $response['decoded'];

        if ($response['code'] !== 200 || !is_array($decoded) || empty($decoded)) {
            throw new \RuntimeException('TheSSLStore: Failed to fetch products (HTTP ' . $response['code'] . ')');
        }

        $products = [];
        foreach ($decoded as $item) {
            $item = is_array($item) ? $item : (array)$item;

            // ── FIX: Skip error objects and items without ProductCode ──
            if (empty($item['ProductCode'])) {
                continue;
            }

            // Skip items that are only AuthResponse error objects
            $auth = $item['AuthResponse'] ?? null;
            if ($auth && !empty($auth['isError'])) {
                $this->log('warning', 'TheSSLStore: Auth error in product list — ' . ($auth['Message'] ?? 'unknown'));
                continue;
            }

            $products[] = $this->normalizeProduct($item);
        }

        $this->log('info', 'TheSSLStore: Fetched ' . count($products) . ' products');

        return $products;
    }

    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductCode' => $productCode,
            'ProductType' => 0,
        ]);

        $decoded = $response['decoded'];

        // Response is a flat array; find the matching product
        if ($response['code'] === 200 && is_array($decoded)) {
            foreach ($decoded as $item) {
                $item = is_array($item) ? $item : (array)$item;
                if (($item['ProductCode'] ?? '') === $productCode) {
                    return $this->extractPricing($item);
                }
            }
        }

        return [];
    }

    // ─── Order Lifecycle ───────────────────────────────────────────

    public function validateOrder(array $params): array
    {
        try {
            $response = $this->apiCall('/order/validate', [
                'CSR'            => $params['csr'] ?? '',
                'ProductCode'    => $params['product_code'] ?? '',
                'ServerCount'    => -1,
                'ValidityPeriod' => $this->monthsToYears($params['period'] ?? 12),
                'WebServerType'  => $params['server_type'] ?? '',
            ]);

            if ($response['code'] === 200 && !$this->hasError($response)) {
                return ['valid' => true, 'errors' => []];
            }
            return ['valid' => false, 'errors' => [$this->extractError($response)]];
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
            'WebServerType'    => $params['server_type'] ?? '',
            'ApproverEmail'    => $params['dcv_email'] ?? '',
        ];

        // SAN domains
        if (isset($params['domains']) && count($params['domains']) > 1) {
            $data['DNSNames'] = implode(',', array_slice($params['domains'], 1));
        }

        // Admin contact
        if (isset($params['admin_contact'])) {
            $data['AdminContact'] = $this->buildContact($params['admin_contact']);
        }

        // Technical contact
        if (isset($params['tech_contact'])) {
            $data['TechnicalContact'] = $this->buildContact($params['tech_contact']);
        }

        // Renewal flags (C7)
        if (!empty($params['isRenewalOrder'])) {
            $data['isRenewalOrder'] = true;
            $data['RelatedTheSSLStoreOrderID'] = $params['RelatedTheSSLStoreOrderID'] ?? '';
        }

        $response = $this->apiCall('/order/neworder', $data);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore placeOrder: ' . $this->extractError($response));
        }

        $result = $response['decoded'];

        return [
            'order_id' => (string)($result['TheSSLStoreOrderID'] ?? $result['CustomOrderID'] ?? ''),
            'status'   => 'Pending',
            'extra'    => $result,
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/order/status', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore: Failed to get status — ' . $this->extractError($response));
        }

        $data = $response['decoded'];

        return [
            'status'      => $this->normalizeStatus($data['OrderStatus']['MajorStatus'] ?? $data['OrderStatus'] ?? ''),
            'certificate' => $data['Certificate'] ?? null,
            'ca_bundle'   => null,
            'domains'     => isset($data['CommonName']) ? [$data['CommonName']] : [],
            'begin_date'  => $data['CertificateStartDateInUTC'] ?? null,
            'end_date'    => $data['CertificateEndDateInUTC'] ?? null,
            'extra'       => $data,
        ];
    }

    public function downloadCertificate(string $orderId): array
    {
        $response = $this->apiCall('/order/download', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore: Download failed — ' . $this->extractError($response));
        }

        $data = $response['decoded'];
        return [
            'certificate' => $data['CertificateStatus']['Certificates'][0]['Certificate'] ?? $data['Certificate'] ?? '',
            'ca_bundle'   => $data['CertificateStatus']['Certificates'][0]['CACertificate'] ?? '',
            'format'      => 'pem',
        ];
    }

    public function reissueCertificate(string $orderId, array $params): array
    {
        $data = [
            'TheSSLStoreOrderID' => $orderId,
            'CSR'                => $params['csr'] ?? '',
            'WebServerType'      => $params['server_type'] ?? '',
        ];

        if (isset($params['dcv_email'])) {
            $data['ApproverEmail'] = $params['dcv_email'];
        }

        $response = $this->apiCall('/order/reissue', $data);
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Reissue initiated.' : $this->extractError($response)];
    }

    /**
     * Renew certificate (C7: TheSSLStore uses new order with renewal flag)
     */
    public function renewCertificate(string $orderId, array $params): array
    {
        $params['isRenewalOrder'] = true;
        $params['RelatedTheSSLStoreOrderID'] = $orderId;
        return $this->placeOrder($params);
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $response = $this->apiCall('/order/certificaterevokerequest', [
            'TheSSLStoreOrderID' => $orderId,
        ]);
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Revoked.' : $this->extractError($response)];
    }

    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/order/refundrequest', [
            'TheSSLStoreOrderID' => $orderId,
        ]);
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Refund requested.' : $this->extractError($response)];
    }

    // ─── DCV ───────────────────────────────────────────────────────

    public function getDcvEmails(string $domain): array
    {
        $response = $this->apiCall('/order/approverlist', [
            'DomainName'  => $domain,
            'ProductCode' => '',
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
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'DCV resent.' : 'Failed.'];
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $response = $this->apiCall('/order/changeapproveremail', array_merge([
            'TheSSLStoreOrderID' => $orderId,
        ], $params));
        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'DCV changed.' : 'Failed.'];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Normalize product from TheSSLStore API response
     *
     * TheSSLStore product fields:
     * - ProductCode: "positivessl"
     * - ProductName: "Sectigo Positive SSL"
     * - ProductType: "1"
     * - VendorName: "Sectigo"
     * - ProductValidationType: "DV" / "OV" / "EV"
     * - isWildcardProduct: bool
     * - isMultiDomainProduct: bool
     * - isCodeSigningProduct: bool
     * - MinSan, MaxSan: int
     * - PricingInfo: [ { NumberOfMonths, Price, PricePerAdditionalSAN, ... } ]
     */
    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name = $item['ProductName'] ?? '';
        $nameLower = strtolower($name);

        // Product type — use API flags first, then name fallback
        $type = 'ssl';
        if (!empty($item['isWildcardProduct'])) {
            $type = 'wildcard';
        } elseif (!empty($item['isMultiDomainProduct']) || strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false || strpos($nameLower, 'san') !== false) {
            $type = 'multi_domain';
        } elseif (!empty($item['isCodeSigningProduct']) || strpos($nameLower, 'code sign') !== false) {
            $type = 'code_signing';
        }

        // Validation type — use ProductValidationType field
        $validation = 'dv';
        if (!empty($item['ProductValidationType'])) {
            $vt = strtolower($item['ProductValidationType']);
            if (strpos($vt, 'ev') !== false) $validation = 'ev';
            elseif (strpos($vt, 'ov') !== false) $validation = 'ov';
        }

        $vendor = $item['VendorName'] ?? $item['BrandName'] ?? 'Unknown';

        // ── FIX: Correctly extract pricing from PricingInfo array ──
        $pricing = $this->extractPricing($item);

        // Max SAN count
        $maxSan = (int)($item['MaxSan'] ?? 0);
        $maxDomains = max(1, $maxSan > 0 ? $maxSan : ($type === 'multi_domain' ? 250 : 1));

        // Max years from PricingInfo
        $maxYears = 1;
        if (!empty($item['PricingInfo']) && is_array($item['PricingInfo'])) {
            foreach ($item['PricingInfo'] as $pi) {
                $months = (int)($pi['NumberOfMonths'] ?? 0);
                $years = (int)ceil($months / 12);
                $maxYears = max($maxYears, $years);
            }
        }

        return new NormalizedProduct([
            'product_code'     => $item['ProductCode'],
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,
            'product_type'     => $type,
            'support_wildcard' => ($type === 'wildcard'),
            'support_san'      => ($type === 'multi_domain' || $maxSan > 0),
            'max_domains'      => $maxDomains,
            'max_years'        => $maxYears,
            'min_years'        => 1,
            'price_data'       => $pricing,
            'extra_data'       => [
                'vendor'             => $vendor,
                'product_type_raw'   => $item['ProductType'] ?? '',
                'is_flex'            => $item['isFlexProduct'] ?? false,
            ],
        ]);
    }

    /**
     * Extract pricing from TheSSLStore PricingInfo array
     *
     * TheSSLStore PricingInfo format:
     * [
     *   {
     *     "NumberOfMonths": 12,
     *     "Price": 5.99,
     *     "PricePerAdditionalSAN": 3.00,
     *     "PricePerWildcardSAN": 49.00,
     *     "WildcardPrice": 199.00
     *   },
     *   {
     *     "NumberOfMonths": 24,
     *     "Price": 10.99,
     *     ...
     *   }
     * ]
     *
     * Normalized output:
     * {
     *   "base":         { "12": 5.99, "24": 10.99 },
     *   "san":          { "12": 3.00, "24": ... },
     *   "wildcard_san": { "12": 49.00, "24": ... }
     * }
     */
    private function extractPricing(array $item): array
    {
        $normalized = ['base' => [], 'san' => [], 'wildcard_san' => []];

        $pricingInfo = $item['PricingInfo'] ?? [];

        if (!is_array($pricingInfo)) {
            return $normalized;
        }

        foreach ($pricingInfo as $pi) {
            if (!is_array($pi)) {
                $pi = (array)$pi;
            }

            $months = (int)($pi['NumberOfMonths'] ?? 0);
            if ($months <= 0) continue;

            $monthsKey = (string)$months;

            // Base price
            if (isset($pi['Price'])) {
                $normalized['base'][$monthsKey] = (float)$pi['Price'];
            }

            // SAN price per additional domain
            if (isset($pi['PricePerAdditionalSAN']) && (float)$pi['PricePerAdditionalSAN'] > 0) {
                $normalized['san'][$monthsKey] = (float)$pi['PricePerAdditionalSAN'];
            }

            // Wildcard SAN price
            if (isset($pi['PricePerWildcardSAN']) && (float)$pi['PricePerWildcardSAN'] > 0) {
                $normalized['wildcard_san'][$monthsKey] = (float)$pi['PricePerWildcardSAN'];
            }
        }

        return $normalized;
    }

    /**
     * Build contact object for TheSSLStore order
     */
    private function buildContact(array $contact): array
    {
        return [
            'FirstName'       => $contact['first_name'] ?? '',
            'LastName'        => $contact['last_name'] ?? '',
            'Phone'           => $contact['phone'] ?? '',
            'Fax'             => $contact['fax'] ?? '',
            'Email'           => $contact['email'] ?? '',
            'Title'           => $contact['title'] ?? '',
            'OrganizationName'=> $contact['organization'] ?? '',
            'AddressLine1'    => $contact['address'] ?? '',
            'City'            => $contact['city'] ?? '',
            'Region'          => $contact['state'] ?? '',
            'PostalCode'      => $contact['zip'] ?? '',
            'Country'         => $contact['country'] ?? '',
        ];
    }

    /**
     * Convert months to years for TheSSLStore ValidityPeriod
     */
    private function monthsToYears(int $months): int
    {
        return max(1, (int)ceil($months / 12));
    }

    /**
     * Map TheSSLStore status strings
     */
    private function normalizeStatus($status): string
    {
        if (is_array($status)) {
            $status = $status['MajorStatus'] ?? '';
        }

        $map = [
            'active'              => 'Issued',
            'issued'              => 'Issued',
            'processing'          => 'Processing',
            'pending'             => 'Pending',
            'initial'             => 'Pending',
            'cancelled'           => 'Cancelled',
            'revoked'             => 'Revoked',
            'expired'             => 'Expired',
            'rejected'            => 'Rejected',
            'waiting_validation'  => 'Awaiting Validation',
        ];

        return $map[strtolower(trim((string)$status))] ?? (string)$status;
    }
}