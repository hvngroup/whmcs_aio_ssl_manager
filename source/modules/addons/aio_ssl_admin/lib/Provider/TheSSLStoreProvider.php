<?php
/**
 * TheSSLStore Provider — Full-tier SSL provider integration
 *
 * API Reference : TheSSLStore REST API Documentation
 * API Base      : https://api.thesslstore.com/rest
 * EU Base       : https://api.thesslstore.eu/rest
 * Sandbox       : https://sandbox-wbapi.thesslstore.com/rest
 * Auth          : PartnerCode + AuthToken in JSON body (AuthRequest object)
 * Content-Type  : application/json; charset=utf-8
 * HTTP Verb     : POST (all endpoints)
 *
 * CRITICAL: Renew = new order with isRenewalOrder=true (no dedicated renew endpoint)
 * CRITICAL: Cancel = refund request (/order/refundrequest)
 * CRITICAL: OrganizationInfo (American spelling, NOT OrganisationInfo)
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ API Coverage (27 methods)                                           │
 * ├──────────────────────────────────────────────────────────────────────┤
 * │ Products   : productQuery, productAgreement                        │
 * │ Orders     : newOrder, inviteOrder, midtermUpgrade,                 │
 * │              validateOrderParameters, purchaseNewSAN                │
 * │ Query      : orderStatus, queryOrder, getModifiedOrdersSummary     │
 * │ Download   : downloadCertificate, downloadCertificateAsZip         │
 * │ Lifecycle  : reissue, refundRequest, refundStatus,                 │
 * │              certificateRevokeRequest                              │
 * │ DCV        : approverList, resend, changeApproverEmail             │
 * │ DigiCert   : digicertOrganizationList, digicertOrganizationInfo,   │
 * │              digicertGetDomainInfo, digicertSetApproverMethod,      │
 * │              digicertCreateNewOrganization                         │
 * │ Settings   : setOrderCallback, setPriceCallback,                   │
 * │              setCancelNotification, setEmailTemplates              │
 * │ User       : newUser, addSubUser, activateSubUser,                 │
 * │              deactivateSubUser, querySubUser, userAccountDetail    │
 * └──────────────────────────────────────────────────────────────────────┘
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
    private const API_URL     = 'https://api.thesslstore.com/rest';
    private const EU_URL      = 'https://api.thesslstore.eu/rest';
    private const SANDBOX_URL = 'https://sandbox-wbapi.thesslstore.com/rest';

    // ═══════════════════════════════════════════════════════════════
    //  IDENTITY
    // ═══════════════════════════════════════════════════════════════

    public function getSlug(): string  { return 'thesslstore'; }
    public function getName(): string  { return 'TheSSLStore'; }
    public function getTier(): string  { return 'full'; }

    public function getCapabilities(): array
    {
        return [
            'order', 'renew', 'reissue', 'revoke', 'cancel',
            'download', 'download_zip',
            'dcv_email', 'dcv_http', 'dcv_cname', 'dcv_https',
            'validate_order', 'get_dcv_emails', 'change_dcv', 'resend_dcv',
            'invite_order', 'midterm_upgrade', 'purchase_san',
            'query_order', 'modified_orders_summary',
            'product_agreement', 'refund_status',
            'balance',  // via /user/accountdetail
            'digicert_organizations', 'digicert_domain_info',
            'digicert_set_approver', 'digicert_create_org',
            'user_account', 'set_callback',
        ];
    }

    protected function getBaseUrl(): string
    {
        if ($this->apiMode === 'sandbox') {
            return self::SANDBOX_URL;
        }
        // Support EU endpoint via config
        if (($this->getCredential('region') ?? '') === 'eu') {
            return self::EU_URL;
        }
        return self::API_URL;
    }

    // ═══════════════════════════════════════════════════════════════
    //  AUTH & HTTP
    // ═══════════════════════════════════════════════════════════════

    /**
     * Build request body with AuthRequest embedded
     *
     * TheSSLStore requires AuthRequest { PartnerCode, AuthToken }
     * in every JSON request body. Supports optional Token system.
     */
    private function buildAuthBody(array $params = []): array
    {
        $auth = [
            'PartnerCode'          => $this->getCredential('partner_code'),
            'AuthToken'            => $this->getCredential('auth_token'),
            'IsUsedForTokenSystem' => false,
        ];

        // Support Token-based auth (AutoInstallSSL Plugin)
        $token = $this->getCredential('token');
        if (!empty($token)) {
            $auth['IsUsedForTokenSystem'] = true;
            $auth['Token'] = $token;
        }

        // UserAgent for tracking
        $auth['UserAgent'] = 'AioSSL-WHMCS/1.0';

        return array_merge(['AuthRequest' => $auth], $params);
    }

    /**
     * Make API call to TheSSLStore
     *
     * All requests are POST with JSON body containing AuthRequest
     */
    private function apiCall(string $endpoint, array $params = []): array
    {
        $url  = $this->getBaseUrl() . $endpoint;
        $body = $this->buildAuthBody($params);

        return $this->httpPostJson($url, $body);
    }

    /**
     * Check if API response contains an error
     *
     * NOTE: Do NOT use for /user/accountdetail — that endpoint 
     * always returns isError:true even on success.
     */
    private function hasError(array $response): bool
    {
        $d = $response['decoded'] ?? [];

        if (!is_array($d)) {
            return false;
        }

        // Direct AuthResponse.isError — strict check
        if (isset($d['AuthResponse']['isError'])) {
            $val = $d['AuthResponse']['isError'];
            return ($val === true || $val === 'true' || $val === 1 || $val === '1');
        }

        // Array response (e.g. product/query)
        if (isset($d[0]) && is_array($d[0])) {
            $auth = $d[0]['AuthResponse'] ?? null;
            if (is_array($auth) && isset($auth['isError'])) {
                $val = $auth['isError'];
                return ($val === true || $val === 'true' || $val === 1 || $val === '1');
            }
        }

        return false;
    }
    
    /**
     * Extract error message from API response
     */
    private function extractError(array $response): string
    {
        $d = $response['decoded'] ?? [];

        if (!is_array($d)) {
            return 'Invalid API response (HTTP ' . ($response['code'] ?? '?') . ')';
        }

        // Direct AuthResponse
        if (isset($d['AuthResponse']['Message'])) {
            $msg = $d['AuthResponse']['Message'];
            if (is_array($msg)) {
                $filtered = array_filter($msg, fn($v) => $v !== null && $v !== '');
                if (!empty($filtered)) {
                    return implode('; ', $filtered);
                }
            } elseif (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        // Array response
        if (isset($d[0]['AuthResponse']['Message'])) {
            $msg = $d[0]['AuthResponse']['Message'];
            if (is_array($msg)) {
                $filtered = array_filter($msg, fn($v) => $v !== null && $v !== '');
                if (!empty($filtered)) {
                    return implode('; ', $filtered);
                }
            } elseif (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return 'Unknown TheSSLStore error (HTTP ' . ($response['code'] ?? '?') . ')';
    }

    // ═══════════════════════════════════════════════════════════════
    //  CONNECTION TEST
    // ═══════════════════════════════════════════════════════════════

    public function testConnection(): array
    {
        try {
            $account = $this->getUserAccountDetail();

            if (!$account['success']) {
                return [
                    'success' => false,
                    'message' => $account['error'] ?: 'Connection failed. Check API credentials.',
                    'balance' => null,
                ];
            }

            $data    = $account['account'];
            $balance = (float)($data['AccountBalance'] ?? 0);
            $currency = $data['CurrencyCode'] ?? $data['Currency'] ?? 'USD';

            return [
                'success' => true,
                'message' => 'Connected to TheSSLStore. Balance: ' . $currency . ' ' . number_format($balance, 2),
                'balance' => $balance,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'balance' => null];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRODUCT CATALOG
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fetch all products via POST /product/query
     *
     * ProductType values: ALL=0, DV=1, EV=2, OV=3, WILDCARD=4,
     *   SCAN=5, SAN_ENABLED=7, CODESIGN=8, DC_SMIME=11, DC_DOCSIGN=12
     */
    public function fetchProducts(): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductCode' => '',
            'ProductType' => 0, // ALL
        ]);

        $decoded = $response['decoded'];

        if ($response['code'] !== 200 || !is_array($decoded)) {
            throw new \RuntimeException('TheSSLStore: Failed to fetch products (HTTP ' . $response['code'] . ')');
        }

        $products = [];
        foreach ($decoded as $item) {
            $item = is_array($item) ? $item : (array)$item;

            // Skip error objects and items without ProductCode
            if (empty($item['ProductCode'])) {
                continue;
            }

            $auth = $item['AuthResponse'] ?? null;
            if ($auth && !empty($auth['isError'])) {
                $this->log('warning', 'TheSSLStore: Auth error in product list — '
                    . (is_array($auth['Message'] ?? null) ? implode('; ', $auth['Message']) : ($auth['Message'] ?? 'unknown')));
                continue;
            }

            $products[] = $this->normalizeProduct($item);
        }

        $this->log('info', 'TheSSLStore: Fetched ' . count($products) . ' products');
        return $products;
    }

    /**
     * Fetch pricing for a specific product
     *
     * @param string $productCode Product code (e.g. 'positivessl')
     * @return array Pricing data indexed by period (months)
     */
    public function fetchPricing(string $productCode): array
    {
        $response = $this->apiCall('/product/query', [
            'ProductCode' => $productCode,
            'ProductType' => 0,
        ]);

        $decoded = $response['decoded'];

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

    /**
     * Get product agreement text
     *
     * POST /order/agreement
     * Returns vendor agreement text that customer should accept before ordering
     */
    public function getProductAgreement(string $productCode, array $params = []): array
    {
        $response = $this->apiCall('/order/agreement', array_merge([
            'ProductCode' => $productCode,
        ], $params));

        if ($response['code'] === 200 && !$this->hasError($response)) {
            $d = $response['decoded'];
            return [
                'success'   => true,
                'agreement' => $d['OrderAgreement'] ?? $d['Agreement'] ?? '',
            ];
        }

        return ['success' => false, 'agreement' => '', 'error' => $this->extractError($response)];
    }

    // ═══════════════════════════════════════════════════════════════
    //  ORDER LIFECYCLE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate order parameters
     *
     * POST /order/validate
     * Validates CSR, product code, server count, validity, web server type
     * Returns decoded CSR info and validation status
     */
    public function validateOrder(array $params): array
    {
        try {
            $response = $this->apiCall('/order/validate', [
                'CSR'            => $params['csr'] ?? '',
                'ProductCode'    => $params['product_code'] ?? '',
                'ServerCount'    => (int)($params['server_count'] ?? -1),
                'ValidityPeriod' => (int)($params['period'] ?? 12),
                'WebServerType'  => $params['server_type'] ?? 'Other',
            ]);

            if ($response['code'] === 200 && !$this->hasError($response)) {
                $d = $response['decoded'];
                return [
                    'valid'  => true,
                    'errors' => [],
                    'csr_info' => [
                        'common_name'       => $d['DomainName'] ?? '',
                        'organization'      => $d['Organization'] ?? '',
                        'organization_unit' => $d['OrganizationUnit'] ?? '',
                        'country'           => $d['Country'] ?? '',
                        'state'             => $d['State'] ?? '',
                        'locality'          => $d['Locality'] ?? '',
                        'email'             => $d['Email'] ?? '',
                        'is_wildcard'       => (bool)($d['isWildcardCSR'] ?? false),
                        'is_valid_domain'   => (bool)($d['isValidDomainName'] ?? true),
                        'has_bad_extensions' => (bool)($d['hasBadExtensions'] ?? false),
                        'md5_hash'          => $d['MD5Hash'] ?? '',
                        'sha1_hash'         => $d['SHA1Hash'] ?? '',
                        'sha256_hash'       => $d['sha256'] ?? '',
                    ],
                ];
            }
            return ['valid' => false, 'errors' => [$this->extractError($response)]];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Place a new order
     *
     * POST /order/neworder
     * Also used for renewals with isRenewalOrder=true
     *
     * @param array $params Order parameters
     * @return array ['order_id', 'vendor_order_id', 'status', 'extra']
     */
    public function placeOrder(array $params): array
    {
        $data = $this->buildNewOrderBody($params);

        $response = $this->apiCall('/order/neworder', $data);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore placeOrder: ' . $this->extractError($response));
        }

        $r = $response['decoded'];

        return [
            'order_id'        => (string)($r['TheSSLStoreOrderID'] ?? ''),
            'vendor_order_id' => (string)($r['VendorOrderID'] ?? ''),
            'custom_order_id' => (string)($r['CustomOrderID'] ?? ''),
            'partner_order_id'=> (string)($r['PartnerOrderID'] ?? ''),
            'status'          => $this->normalizeStatus($r['OrderStatus']['MajorStatus'] ?? 'Pending'),
            'tiny_order_link' => $r['TinyOrderLink'] ?? '',
            'dcv_info'        => $this->extractDcvInfo($r),
            'extra'           => $r,
        ];
    }

    /**
     * Place an invite order (TinyOrder / enrollment link)
     *
     * POST /order/inviteorder
     * Creates an order that sends an enrollment link to the customer
     * Customer completes CSR generation and domain validation via the link
     */
    public function inviteOrder(array $params): array
    {
        $data = [
            'CustomOrderID'             => $params['custom_order_id'] ?? uniqid('INV-'),
            'ProductCode'               => $params['product_code'] ?? '',
            'ExtraProductCodes'         => $params['extra_product_codes'] ?? '',
            'ValidityPeriod'            => (int)($params['period'] ?? 12),
            'ServerCount'               => (int)($params['server_count'] ?? -1),
            'DomainName'                => $params['domain'] ?? '',
            'isCUOrder'                 => (bool)($params['is_cu_order'] ?? false),
            'isRenewalOrder'            => (bool)($params['is_renewal'] ?? false),
            'RelatedTheSSLStoreOrderID' => $params['related_order_id'] ?? '',
            'AdminContact'              => $this->buildContact($params['admin_contact'] ?? []),
            'TechnicalContact'          => $this->buildContact($params['tech_contact'] ?? $params['admin_contact'] ?? []),
            'ApproverEmail'             => $params['approver_email'] ?? '',
            'ReserveSANCount'           => (int)($params['reserve_san_count'] ?? 0),
            'AddInstallationSupport'    => (bool)($params['installation_support'] ?? false),
            'EmailLanguageCode'         => $params['email_language'] ?? 'EN',
            'FileAuthDVIndicator'       => (bool)($params['dcv_file'] ?? false),
            'CNAMEAuthDVIndicator'      => (bool)($params['dcv_cname'] ?? false),
            'HTTPSFileAuthDVIndicator'  => (bool)($params['dcv_https'] ?? false),
            'SignatureHashAlgorithm'    => $params['hash_algorithm'] ?? 'SHA2-256',
        ];

        // SAN domains
        if (!empty($params['dns_names'])) {
            $data['DNSNames'] = is_array($params['dns_names'])
                ? $params['dns_names']
                : explode(',', $params['dns_names']);
        }

        // Organization info for OV/EV
        if (!empty($params['org_info'])) {
            $data['OrganizationInfo'] = $this->buildOrganizationInfo($params['org_info']);
        }

        $response = $this->apiCall('/order/inviteorder', $data);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore inviteOrder: ' . $this->extractError($response));
        }

        $r = $response['decoded'];

        return [
            'order_id'         => (string)($r['TheSSLStoreOrderID'] ?? ''),
            'vendor_order_id'  => (string)($r['VendorOrderID'] ?? ''),
            'tiny_order_link'  => $r['TinyOrderLink'] ?? '',
            'status'           => $this->normalizeStatus($r['OrderStatus']['MajorStatus'] ?? 'Pending'),
            'extra'            => $r,
        ];
    }

    /**
     * Mid-term upgrade order
     *
     * POST /order/midtermupgrade
     * Upgrades an existing order to a higher-tier product mid-term
     * Uses same request body as newOrder
     */
    public function midtermUpgrade(array $params): array
    {
        $data = $this->buildNewOrderBody($params);

        $response = $this->apiCall('/order/midtermupgrade', $data);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore midtermUpgrade: ' . $this->extractError($response));
        }

        $r = $response['decoded'];

        return [
            'order_id'        => (string)($r['TheSSLStoreOrderID'] ?? ''),
            'vendor_order_id' => (string)($r['VendorOrderID'] ?? ''),
            'status'          => $this->normalizeStatus($r['OrderStatus']['MajorStatus'] ?? 'Pending'),
            'extra'           => $r,
        ];
    }

    /**
     * Purchase additional SAN for Multi-Domain certificate
     *
     * POST /order/purchasenewsan (undocumented but present in SDK references)
     */
    public function purchaseNewSAN(string $orderId, array $dnsNames, int $sanCount = 0): array
    {
        $response = $this->apiCall('/order/purchasenewsan', [
            'TheSSLStoreOrderID' => $orderId,
            'DNSNames'           => $dnsNames,
            'ReserveSANCount'    => $sanCount > 0 ? $sanCount : count($dnsNames),
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return [
            'success' => $success,
            'message' => $success ? 'SAN purchased successfully.' : $this->extractError($response),
            'extra'   => $response['decoded'] ?? [],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  ORDER QUERY & STATUS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get order status
     *
     * POST /order/status
     */
    public function getOrderStatus(string $orderId): array
    {
        $response = $this->apiCall('/order/status', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore: Failed to get status — ' . $this->extractError($response));
        }

        $d = $response['decoded'];

        // Extract domains (CommonName + DNSNames)
        $domains = [];
        if (!empty($d['CommonName'])) {
            $domains[] = $d['CommonName'];
        }
        if (!empty($d['DNSNames'])) {
            $sans = is_array($d['DNSNames']) ? $d['DNSNames'] : explode(',', $d['DNSNames']);
            $domains = array_unique(array_merge($domains, array_map('trim', $sans)));
        }

        return [
            'status'            => $this->normalizeStatus($d['OrderStatus']['MajorStatus'] ?? ''),
            'minor_status'      => $d['OrderStatus']['MinorStatus'] ?? '',
            'vendor_order_id'   => $d['VendorOrderID'] ?? '',
            'certificate'       => null, // Use downloadCertificate() for cert content
            'ca_bundle'         => null,
            'domains'           => $domains,
            'common_name'       => $d['CommonName'] ?? '',
            'organization'      => $d['Organization'] ?? '',
            'vendor_name'       => $d['VendorName'] ?? '',
            'product_name'      => $d['ProductName'] ?? '',
            'san_count'         => (int)($d['SANCount'] ?? 0),
            'server_count'      => (int)($d['ServerCount'] ?? 0),
            'validity'          => (int)($d['Validity'] ?? 0),
            'begin_date'        => $d['CertificateStartDateInUTC'] ?? $d['CertificateStartDate'] ?? null,
            'end_date'          => $d['CertificateEndDateInUTC'] ?? $d['CertificateEndDate'] ?? null,
            'purchase_date'     => $d['PurchaseDateInUTC'] ?? $d['PurchaseDate'] ?? null,
            'order_amount'      => $d['OrderAmount'] ?? '',
            'approver_email'    => $d['ApproverEmail'] ?? '',
            'dcv_info'          => $this->extractDcvInfo($d),
            'auth_statuses'     => $d['OrderStatus']['AuthenticationStatuses'] ?? [],
            'serial_number'     => $d['SerialNumber'] ?? '',
            'site_seal_url'     => $d['SiteSealurl'] ?? '',
            'tss_org_id'        => (int)($d['TSSOrganizationId'] ?? 0),
            'extra'             => $d,
        ];
    }

    /**
     * Query orders with pagination
     *
     * POST /order/query
     * Retrieves list of orders matching criteria
     */
    public function queryOrder(array $params = []): array
    {
        $response = $this->apiCall('/order/query', [
            'StartDate'         => $params['start_date'] ?? '',
            'EndDate'           => $params['end_date'] ?? '',
            'SubUserID'         => $params['sub_user_id'] ?? '',
            'ProductCode'       => $params['product_code'] ?? '',
            'DateTimeCulture'   => $params['datetime_culture'] ?? 'en-US',
            'PageNumber'        => (int)($params['page'] ?? 0),
            'PageSize'          => (int)($params['page_size'] ?? 100),
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore queryOrder: ' . $this->extractError($response));
        }

        $d = $response['decoded'];

        // Response is an array of OrderResponse objects
        $orders = [];
        if (is_array($d) && !isset($d['AuthResponse'])) {
            foreach ($d as $item) {
                $item = is_array($item) ? $item : (array)$item;
                if (!empty($item['TheSSLStoreOrderID'])) {
                    $orders[] = [
                        'order_id'       => (string)$item['TheSSLStoreOrderID'],
                        'vendor_order_id'=> (string)($item['VendorOrderID'] ?? ''),
                        'custom_order_id'=> (string)($item['CustomOrderID'] ?? ''),
                        'product_name'   => $item['ProductName'] ?? '',
                        'common_name'    => $item['CommonName'] ?? '',
                        'status'         => $this->normalizeStatus($item['OrderStatus']['MajorStatus'] ?? ''),
                        'order_amount'   => $item['OrderAmount'] ?? '',
                        'purchase_date'  => $item['PurchaseDateInUTC'] ?? $item['PurchaseDate'] ?? '',
                        'end_date'       => $item['CertificateEndDateInUTC'] ?? $item['CertificateEndDate'] ?? '',
                    ];
                }
            }
        }

        return ['orders' => $orders, 'count' => count($orders)];
    }

    /**
     * Get modified orders summary
     *
     * POST /order/getmodifiedorderssummary
     * Returns orders modified since a given date
     */
    public function getModifiedOrdersSummary(string $modifiedSince = '', array $params = []): array
    {
        $response = $this->apiCall('/order/getmodifiedorderssummary', [
            'ModifiedSinceDate' => $modifiedSince,
            'DateTimeCulture'   => $params['datetime_culture'] ?? 'en-US',
            'PageNumber'        => (int)($params['page'] ?? 0),
            'PageSize'          => (int)($params['page_size'] ?? 100),
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['orders' => [], 'error' => $this->extractError($response)];
        }

        return ['orders' => $response['decoded'] ?? [], 'count' => count($response['decoded'] ?? [])];
    }

    // ═══════════════════════════════════════════════════════════════
    //  DOWNLOAD
    // ═══════════════════════════════════════════════════════════════

    /**
     * Download certificate
     *
     * POST /order/download
     * Returns individual certificate files
     */
    public function downloadCertificate(string $orderId, array $params = []): array
    {
        $requestData = [
            'TheSSLStoreOrderID' => $orderId,
            'ReturnPKCS7Cert'    => (bool)($params['return_pkcs7'] ?? false),
            'DateTimeCulture'    => $params['datetime_culture'] ?? 'en-US',
        ];

        // DigiCert-specific options
        if (!empty($params['platform_id'])) {
            $requestData['PlatFormId'] = $params['platform_id'];
        }
        if (!empty($params['format_type'])) {
            $requestData['FormatType'] = $params['format_type'];
        }

        $response = $this->apiCall('/order/download', $requestData);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore: Download failed — ' . $this->extractError($response));
        }

        $d = $response['decoded'];

        // Extract cert from Certificates array or direct fields
        $certificate = '';
        $caBundle    = '';
        $pkcs7       = '';

        if (!empty($d['Certificates']) && is_array($d['Certificates'])) {
            foreach ($d['Certificates'] as $cert) {
                $cert = is_array($cert) ? $cert : (array)$cert;
                $fn   = strtolower($cert['FileName'] ?? '');
                $fc   = $cert['FileContent'] ?? '';

                if (strpos($fn, 'ca') !== false || strpos($fn, 'bundle') !== false || strpos($fn, 'intermediate') !== false) {
                    $caBundle .= $fc . "\n";
                } elseif (strpos($fn, 'pkcs7') !== false || strpos($fn, 'p7b') !== false) {
                    $pkcs7 = $fc;
                } else {
                    $certificate .= $fc . "\n";
                }
            }
        }

        // Fallback: direct fields
        if (empty($certificate)) {
            $certificate = $d['Certificate'] ?? $d['CertificateStatus']['Certificate'] ?? '';
        }
        if (empty($caBundle)) {
            $caBundle = $d['CACertificate'] ?? $d['IntermediateCertificate'] ?? '';
        }

        return [
            'certificate'        => trim($certificate),
            'ca_bundle'          => trim($caBundle),
            'pkcs7'              => $pkcs7,
            'format'             => 'pem',
            'certificate_status' => $d['CertificateStatus'] ?? '',
            'validation_status'  => $d['ValidationStatus'] ?? '',
            'start_date'         => $d['CertificateStartDateInUTC'] ?? $d['CertificateStartDate'] ?? '',
            'end_date'           => $d['CertificateEndDateInUTC'] ?? $d['CertificateEndDate'] ?? '',
            'partner_order_id'   => $d['PartnerOrderID'] ?? '',
        ];
    }

    /**
     * Download certificate as ZIP
     *
     * POST /order/downloadaszip
     * Returns base64-encoded ZIP file containing all certificate files
     */
    public function downloadCertificateAsZip(string $orderId, array $params = []): array
    {
        $requestData = [
            'TheSSLStoreOrderID' => $orderId,
            'ReturnPKCS7Cert'    => (bool)($params['return_pkcs7'] ?? false),
            'DateTimeCulture'    => $params['datetime_culture'] ?? 'en-US',
        ];

        if (!empty($params['platform_id'])) {
            $requestData['PlatFormId'] = $params['platform_id'];
        }
        if (!empty($params['format_type'])) {
            $requestData['FormatType'] = $params['format_type'];
        }

        $response = $this->apiCall('/order/downloadaszip', $requestData);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            throw new \RuntimeException('TheSSLStore: ZIP download failed — ' . $this->extractError($response));
        }

        $d = $response['decoded'];

        return [
            'zip'                => $d['Zip'] ?? '',
            'pkcs7_zip'          => $d['pkcs7zip'] ?? '',
            'certificate_status' => $d['CertificateStatus'] ?? '',
            'validation_status'  => $d['ValidationStatus'] ?? '',
            'start_date'         => $d['CertificateStartDateInUTC'] ?? $d['CertificateStartDate'] ?? '',
            'end_date'           => $d['CertificateEndDateInUTC'] ?? $d['CertificateEndDate'] ?? '',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  REISSUE / RENEW / REVOKE / CANCEL
    // ═══════════════════════════════════════════════════════════════

    /**
     * Reissue certificate
     *
     * POST /order/reissue
     * Supports CSR change, SAN edit (add/delete/edit), DCV method change
     */
    public function reissueCertificate(string $orderId, array $params): array
    {
        $data = [
            'TheSSLStoreOrderID'  => $orderId,
            'CSR'                 => $params['csr'] ?? '',
            'WebServerType'       => $params['server_type'] ?? 'Other',
            'isWildCard'          => (bool)($params['is_wildcard'] ?? false),
            'ReissueEmail'        => $params['reissue_email'] ?? $params['admin_email'] ?? '',
            'PreferEnrollmentLink'=> (bool)($params['prefer_enrollment_link'] ?? false),
            'SignatureHashAlgorithm' => $params['hash_algorithm'] ?? 'SHA2-256',
        ];

        // DCV method flags
        if (isset($params['dcv_file'])) {
            $data['FileAuthDVIndicator'] = (bool)$params['dcv_file'];
        }
        if (isset($params['dcv_https'])) {
            $data['HTTPSFileAuthDVIndicator'] = (bool)$params['dcv_https'];
        }
        if (isset($params['dcv_cname'])) {
            $data['CNAMEAuthDVIndicator'] = (bool)$params['dcv_cname'];
        }

        // Approver email (for DV products and Comodo certs)
        if (!empty($params['approver_email']) || !empty($params['dcv_email'])) {
            $data['ApproverEmails'] = $params['approver_email'] ?? $params['dcv_email'] ?? '';
        }

        // SAN management — Edit, Delete, Add
        if (!empty($params['edit_san'])) {
            $data['EditSAN'] = array_map(fn($s) => [
                'OldValue' => $s['old'] ?? '',
                'NewValue' => $s['new'] ?? '',
            ], $params['edit_san']);
        }
        if (!empty($params['delete_san'])) {
            $data['DeleteSAN'] = array_map(fn($s) => [
                'OldValue' => $s['old'] ?? $s,
                'NewValue' => '',
            ], $params['delete_san']);
        }
        if (!empty($params['add_san'])) {
            $data['AddSAN'] = array_map(fn($s) => [
                'OldValue' => '',
                'NewValue' => is_array($s) ? ($s['new'] ?? $s['domain'] ?? '') : $s,
            ], $params['add_san']);
        }

        // Certificate Transparency
        if (isset($params['cert_transparency'])) {
            $data['CertTransparencyIndicator'] = (bool)$params['cert_transparency'];
        }

        $response = $this->apiCall('/order/reissue', $data);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        $r       = $response['decoded'] ?? [];

        return [
            'success'  => $success,
            'message'  => $success ? 'Reissue initiated.' : $this->extractError($response),
            'dcv_info' => $success ? $this->extractDcvInfo($r) : [],
            'extra'    => $r,
        ];
    }

    /**
     * Renew certificate
     *
     * CRITICAL: TheSSLStore has NO dedicated renew endpoint.
     * Uses /order/neworder with isRenewalOrder=true + RelatedTheSSLStoreOrderID
     */
    public function renewCertificate(string $orderId, array $params): array
    {
        $params['is_renewal']     = true;
        $params['related_order_id'] = $orderId;
        return $this->placeOrder($params);
    }

    /**
     * Revoke certificate
     *
     * POST /order/certificaterevokerequest
     */
    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        $data = ['TheSSLStoreOrderID' => $orderId];
        // Note: TheSSLStore API does not accept a reason parameter for revocation,
        // but we log it for audit purposes
        if ($reason) {
            $this->log('info', "TheSSLStore: Revoking #{$orderId} — reason: {$reason}");
        }

        $response = $this->apiCall('/order/certificaterevokerequest', $data);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return [
            'success' => $success,
            'message' => $success ? 'Certificate revocation requested.' : $this->extractError($response),
        ];
    }

    /**
     * Cancel order / request refund
     *
     * POST /order/refundrequest
     * Note: Refund ≠ immediate cancel. Processed by TheSSLStore team.
     */
    public function cancelOrder(string $orderId): array
    {
        $response = $this->apiCall('/order/refundrequest', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        $r = $response['decoded'] ?? [];

        return [
            'success'           => $success,
            'message'           => $success ? 'Refund requested.' : $this->extractError($response),
            'refund_request_id' => $r['RefundRequestID'] ?? '',
            'is_approved'       => (bool)($r['isRefundApproved'] ?? false),
        ];
    }

    /**
     * Check refund status
     *
     * POST /order/refundstatus
     */
    public function getRefundStatus(string $orderId): array
    {
        $response = $this->apiCall('/order/refundstatus', [
            'TheSSLStoreOrderID' => $orderId,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        $r = $response['decoded'] ?? [];

        return [
            'success'           => true,
            'refund_request_id' => $r['RefundRequestID'] ?? '',
            'is_approved'       => (bool)($r['isRefundApproved'] ?? false),
            'status'            => $this->normalizeStatus($r['OrderStatus']['MajorStatus'] ?? ''),
            'extra'             => $r,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  DCV (Domain Control Validation)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get DCV approver email list for a domain
     *
     * POST /order/approverlist
     */
    public function getDcvEmails(string $domain, string $productCode = ''): array
    {
        $response = $this->apiCall('/order/approverlist', [
            'DomainName'  => $domain,
            'ProductCode' => $productCode,
        ]);

        if ($response['code'] === 200 && !$this->hasError($response)) {
            $d = $response['decoded'];
            return $d['ApproverEmailList'] ?? $d['ApproverEmail'] ?? [];
        }

        return [];
    }

    /**
     * Resend DCV / approval email
     *
     * POST /order/resend
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $data = ['TheSSLStoreOrderID' => $orderId];
        if ($email) {
            $data['ApproverEmail'] = $email;
        }

        $response = $this->apiCall('/order/resend', $data);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return [
            'success' => $success,
            'message' => $success ? 'DCV email resent.' : $this->extractError($response),
        ];
    }

    /**
     * Change approver email / DCV method
     *
     * POST /order/changeapproveremail
     */
    /**
     * Change approver email / DCV method
     *
     * POST /order/changeapproveremail
     *
     * Signature matches ProviderInterface::changeDcvMethod(string $orderId, string $method, array $params = []): array
     *
     * @param string $orderId  TheSSLStoreOrderID
     * @param string $method   DCV method: 'email'|'http'|'https'|'cname'
     * @param array  $params   Additional params: 'approver_email', 'domain', etc.
     * @return array ['success'=>bool, 'message'=>string]
     */
    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        $data = ['TheSSLStoreOrderID' => $orderId];

        // Map method string to TheSSLStore DCV flags
        $m = strtolower(trim($method));

        if ($m === 'email') {
            // Email-based DCV — pass approver email
            if (!empty($params['approver_email'])) {
                $data['ApproverEmail'] = $params['approver_email'];
            }
        } elseif ($m === 'http') {
            $data['FileAuthDVIndicator'] = true;
        } elseif ($m === 'https') {
            $data['HTTPSFileAuthDVIndicator'] = true;
        } elseif ($m === 'cname' || $m === 'dns') {
            $data['CNAMEAuthDVIndicator'] = true;
        }

        // Allow override via params
        if (isset($params['dcv_file'])) {
            $data['FileAuthDVIndicator'] = (bool)$params['dcv_file'];
        }
        if (isset($params['dcv_cname'])) {
            $data['CNAMEAuthDVIndicator'] = (bool)$params['dcv_cname'];
        }
        if (isset($params['dcv_https'])) {
            $data['HTTPSFileAuthDVIndicator'] = (bool)$params['dcv_https'];
        }

        $response = $this->apiCall('/order/changeapproveremail', $data);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return [
            'success' => $success,
            'message' => $success ? 'DCV method changed.' : $this->extractError($response),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  DIGICERT-SPECIFIC ENDPOINTS
    // ═══════════════════════════════════════════════════════════════

    /**
     * List DigiCert organizations
     *
     * POST /digicert/organizationlist
     * Returns organizations pre-approved for OV/EV fast issuance
     */
    public function digicertListOrganizations(): array
    {
        // This endpoint uses AuthRequest directly (no wrapper)
        $response = $this->apiCall('/digicert/organizationlist/', []);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['success' => false, 'organizations' => [], 'error' => $this->extractError($response)];
        }

        $d = $response['decoded'];
        $orgs = $d['OrganizationList'] ?? [];

        return [
            'success'       => true,
            'organizations' => array_map(fn($o) => [
                'vendor_org_id' => $o['VendorOrganizationId'] ?? 0,
                'tss_org_id'    => $o['TSSOrganizationId'] ?? 0,
                'status'        => $o['Status'] ?? '',
                'name'          => $o['Name'] ?? '',
                'display_name'  => $o['display_name'] ?? $o['Name'] ?? '',
                'assumed_name'  => $o['AssumedName'] ?? '',
                'is_active'     => ($o['is_active'] ?? '') === 'active' || ($o['Status'] ?? '') === 'active',
            ], is_array($orgs) ? $orgs : []),
        ];
    }

    /**
     * Get DigiCert organization info
     *
     * POST /digicert/organizationinfo
     */
    public function digicertGetOrganizationInfo(int $tssOrgId): array
    {
        $response = $this->apiCall('/digicert/organizationinfo/', [
            'TSSOrganizationId' => $tssOrgId,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'organization' => $response['decoded'] ?? []];
    }

    /**
     * Get DigiCert domain info
     *
     * POST /digicert/domaininfo
     */
    public function digicertGetDomainInfo(string $orderId, string $domain): array
    {
        $response = $this->apiCall('/digicert/domaininfo/', [
            'TheSSLStoreOrderID' => $orderId,
            'DomainName'         => $domain,
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        $d = $response['decoded'];
        return [
            'success'     => true,
            'dcv_details' => $d['dcvDetails'] ?? [],
            'validations' => $d['validations'] ?? [],
            'org_details' => $d['OrganizationDetails'] ?? [],
        ];
    }

    /**
     * Set DigiCert domain approver method
     *
     * POST /digicert/setapprovermethod
     * Required for DigicertMultiDomain products after placing new order
     */
    public function digicertSetApproverMethod(string $orderId, array $domainApprovers): array
    {
        $response = $this->apiCall('/digicert/setapprovermethod/', [
            'TheSSLStoreOrderID' => $orderId,
            'DomainApproverList' => $domainApprovers,
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return [
            'success' => $success,
            'message' => $success ? 'Approver method set.' : $this->extractError($response),
        ];
    }

    /**
     * Create new DigiCert organization
     *
     * POST /digicert/createneworganization
     */
    public function digicertCreateNewOrganization(array $orgData, array $contact, array $validationTypes): array
    {
        $response = $this->apiCall('/digicert/createneworganization/', [
            'OrganizationName' => $orgData['name'] ?? '',
            'AssumedName'      => $orgData['assumed_name'] ?? '',
            'address1'         => $orgData['address1'] ?? '',
            'address2'         => $orgData['address2'] ?? '',
            'city'             => $orgData['city'] ?? '',
            'state'            => $orgData['state'] ?? '',
            'country'          => $orgData['country'] ?? '',
            'zip'              => $orgData['zip'] ?? '',
            'telephone'        => $orgData['phone'] ?? '',
            'organization_contact' => [
                'first_name'          => $contact['first_name'] ?? '',
                'last_name'           => $contact['last_name'] ?? '',
                'email'               => $contact['email'] ?? '',
                'job_title'           => $contact['job_title'] ?? '',
                'telephone'           => $contact['phone'] ?? '',
                'telephone_extension' => $contact['phone_ext'] ?? '',
            ],
            'validationTypes' => implode(',', $validationTypes), // e.g. ['ov','ev']
        ]);

        if ($response['code'] !== 200 || $this->hasError($response)) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        $d = $response['decoded'];
        return [
            'success'       => true,
            'dc_org_id'     => $d['DCOrganizationID'] ?? '',
            'tss_org_id'    => $d['TSSOrgID'] ?? '',
            'tss_contact_id'=> $d['TSSOrgContactID'] ?? '',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  USER MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get account balance
     *
     * TheSSLStore has NO dedicated /balance endpoint.
     * Balance is extracted from /user/accountdetail response.
     */
    public function getBalance(): array
    {
        $account = $this->getUserAccountDetail();

        if (!$account['success']) {
            throw new \RuntimeException(
                'TheSSLStore getBalance failed: ' . ($account['error'] ?: 'Unknown error')
            );
        }

        $data = $account['account'];
        $balance = (float)($data['AccountBalance'] ?? $data['accountBalance'] ?? 0);
        $currency = $data['CurrencyCode'] ?? $data['Currency'] ?? 'USD';

        return ['balance' => $balance, 'currency' => $currency];
    }

    /**
     * Get user account details
     *
     * POST /user/accountdetail
     *
     * IMPORTANT: This endpoint expects a FLAT auth body (no AuthRequest wrapper):
     *   { "PartnerCode": "x", "AuthToken": "y" }
     * Unlike other endpoints that expect:
     *   { "AuthRequest": { "PartnerCode": "x", ... } }
     */
    public function getUserAccountDetail(): array
    {
        $url = $this->getBaseUrl() . '/user/accountdetail/';

        $body = [
            'PartnerCode' => $this->getCredential('partner_code'),
            'AuthToken'   => $this->getCredential('auth_token'),
            'UserAgent'   => 'AioSSL-WHMCS/1.0',
        ];

        $response = $this->httpPostJson($url, $body);

        $httpCode = $response['code'] ?? 0;
        $decoded  = $response['decoded'] ?? null;

        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            return [
                'success' => false,
                'error'   => 'API request failed (HTTP ' . $httpCode . ')',
            ];
        }

        if (isset($decoded['PartnerCode']) || isset($decoded['AccountBalance'])) {
            return ['success' => true, 'account' => $decoded];
        }

        // No account data —  error
        $error = $this->extractError($response);
        return [
            'success' => false,
            'error'   => $error ?: 'No account data returned',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  SETTINGS / CALLBACKS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Set order callback URL
     *
     * POST /setting/setordercallback
     */
    public function setOrderCallback(string $url): array
    {
        $response = $this->apiCall('/setting/setordercallback/', [
            'url' => $url,
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Callback set.' : $this->extractError($response)];
    }

    /**
     * Set price callback URL
     *
     * POST /setting/setpricecallback
     */
    public function setPriceCallback(string $url): array
    {
        $response = $this->apiCall('/setting/setpricecallback/', [
            'url' => $url,
        ]);

        $success = ($response['code'] === 200 && !$this->hasError($response));
        return ['success' => $success, 'message' => $success ? 'Price callback set.' : $this->extractError($response)];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS (private)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Build the full NewOrder request body
     *
     * Used by placeOrder(), midtermUpgrade(), renewCertificate()
     */
    private function buildNewOrderBody(array $params): array
    {
        $data = [
            'CustomOrderID'             => $params['custom_order_id'] ?? '',
            'ProductCode'               => $params['product_code'] ?? '',
            'ExtraProductCodes'         => $params['extra_product_codes'] ?? '',
            'ValidityPeriod'            => (int)($params['period'] ?? 12),
            'ServerCount'               => (int)($params['server_count'] ?? -1),
            'CSR'                       => $params['csr'] ?? '',
            'DomainName'                => $params['domain'] ?? '',
            'WebServerType'             => $params['server_type'] ?? 'Other',
            'isCUOrder'                 => (bool)($params['is_cu_order'] ?? false),
            'isRenewalOrder'            => (bool)($params['is_renewal'] ?? false),
            'SpecialInstructions'       => $params['special_instructions'] ?? '',
            'RelatedTheSSLStoreOrderID' => $params['related_order_id'] ?? '',
        ];

        // SAN / DNS Names
        if (!empty($params['dns_names'])) {
            $data['DNSNames'] = is_array($params['dns_names'])
                ? $params['dns_names']
                : array_map('trim', explode(',', $params['dns_names']));
        }

        // Organization Info (CRITICAL: American spelling "OrganizationInfo")
        if (!empty($params['org_info'])) {
            $data['OrganizationInfo'] = $this->buildOrganizationInfo($params['org_info']);
        }

        // DigiCert pre-approved org
        if (!empty($params['tss_org_id'])) {
            $data['TSSOrganizationId'] = (int)$params['tss_org_id'];
        }

        // Contacts
        $data['AdminContact']     = $this->buildContact($params['admin_contact'] ?? []);
        $data['TechnicalContact'] = $this->buildContact($params['tech_contact'] ?? $params['admin_contact'] ?? []);

        // Approver email (DV products)
        if (!empty($params['approver_email'])) {
            $data['ApproverEmail'] = $params['approver_email'];
        }

        // DCV method flags
        $data['FileAuthDVIndicator']      = (bool)($params['dcv_file'] ?? false);
        $data['CNAMEAuthDVIndicator']     = (bool)($params['dcv_cname'] ?? false);
        $data['HTTPSFileAuthDVIndicator'] = (bool)($params['dcv_https'] ?? false);

        // Signature & transparency
        $data['SignatureHashAlgorithm']    = $params['hash_algorithm'] ?? 'SHA2-256';
        $data['CertTransparencyIndicator'] = (bool)($params['cert_transparency'] ?? true);

        // SAN reservation
        if (!empty($params['reserve_san_count'])) {
            $data['ReserveSANCount'] = (int)$params['reserve_san_count'];
        }
        if (!empty($params['wildcard_reserve_san_count'])) {
            $data['WildcardReserveSANCount'] = (int)$params['wildcard_reserve_san_count'];
        }

        // Installation support
        $data['AddInstallationSupport'] = (bool)($params['installation_support'] ?? false);
        $data['EmailLanguageCode']      = $params['email_language'] ?? 'EN';

        // Comodo-specific renewal days
        if (!empty($params['renewal_days'])) {
            $data['RenewalDays'] = (int)$params['renewal_days'];
        }

        // Advanced options
        if (!empty($params['datetime_culture'])) {
            $data['DateTimeCulture'] = $params['datetime_culture'];
        }
        if (!empty($params['csr_unique_value'])) {
            $data['CSRUniqueValue'] = $params['csr_unique_value'];
        }

        // Code signing token options
        if (!empty($params['provisioning_method'])) {
            $data['ProvisioningMethod'] = $params['provisioning_method'];
        }
        if (isset($params['cs_token_delivery_method'])) {
            $data['CSTokenDeliveryMethod'] = (int)$params['cs_token_delivery_method'];
        }
        if (!empty($params['cs_token_type'])) {
            $data['CSTokenType'] = $params['cs_token_type'];
        }

        // S/MIME additional params
        if (!empty($params['smime_params'])) {
            $data['SMimeAdditionalParams'] = $params['smime_params'];
        }

        return $data;
    }

    /**
     * Build OrganizationInfo structure
     *
     * CRITICAL: Must use "OrganizationInfo" (American spelling)
     */
    private function buildOrganizationInfo(array $org): array
    {
        $info = [
            'OrganizationName'      => $org['name'] ?? '',
            'DUNS'                  => $org['duns'] ?? '',
            'Division'              => $org['division'] ?? '',
            'IncorporatingAgency'   => $org['incorporating_agency'] ?? '',
            'RegistrationNumber'    => $org['registration_number'] ?? '',
            'JurisdictionCity'      => $org['jurisdiction_city'] ?? '',
            'JurisdictionRegion'    => $org['jurisdiction_region'] ?? '',
            'JurisdictionCountry'   => $org['jurisdiction_country'] ?? '',
            'AssumedName'           => $org['assumed_name'] ?? '',
        ];

        // Organization Address
        $info['OrganizationAddress'] = [
            'AddressLine1' => $org['address1'] ?? $org['address'] ?? '',
            'AddressLine2' => $org['address2'] ?? '',
            'AddressLine3' => $org['address3'] ?? '',
            'City'         => $org['city'] ?? '',
            'Region'       => $org['region'] ?? $org['state'] ?? '',
            'PostalCode'   => $org['postal_code'] ?? $org['zip'] ?? '',
            'Country'      => $org['country'] ?? '',
            'Phone'        => $org['phone'] ?? '',
            'Fax'          => $org['fax'] ?? '',
            'LocalityName' => $org['locality'] ?? '',
        ];

        return $info;
    }

    /**
     * Build contact structure (Admin or Technical)
     */
    private function buildContact(array $c): array
    {
        if (empty($c)) {
            return [];
        }

        return [
            'FirstName'        => $c['first_name'] ?? '',
            'LastName'         => $c['last_name'] ?? '',
            'SubjectFirstName' => $c['subject_first_name'] ?? '',
            'SubjectLastName'  => $c['subject_last_name'] ?? '',
            'Phone'            => $c['phone'] ?? '',
            'Fax'              => $c['fax'] ?? '',
            'Email'            => $c['email'] ?? '',
            'Title'            => $c['title'] ?? '',
            'OrganizationName' => $c['organization'] ?? '',
            'AddressLine1'     => $c['address1'] ?? $c['address'] ?? '',
            'AddressLine2'     => $c['address2'] ?? '',
            'City'             => $c['city'] ?? '',
            'Region'           => $c['region'] ?? $c['state'] ?? '',
            'PostalCode'       => $c['postal_code'] ?? $c['zip'] ?? '',
            'Country'          => $c['country'] ?? '',
        ];
    }

    /**
     * Extract DCV info from order response
     *
     * Parses DomainAuthVettingStatus from OrderStatus
     */
    private function extractDcvInfo(array $data): array
    {
        $dcvStatus = $data['OrderStatus']['DomainAuthVettingStatus'] ?? [];

        if (empty($dcvStatus) || !is_array($dcvStatus)) {
            // Fallback: check for direct DCV fields
            $info = [];
            if (!empty($data['AuthFileName'])) {
                $info['file_name']    = $data['AuthFileName'];
                $info['file_content'] = $data['AuthFileContent'] ?? '';
            }
            if (!empty($data['CNAMEAuthName'])) {
                $info['cname_name']  = $data['CNAMEAuthName'];
                $info['cname_value'] = $data['CNAMEAuthValue'] ?? '';
            }
            return $info;
        }

        return array_map(fn($d) => [
            'domain'          => $d['domain'] ?? '',
            'dcv_method'      => $d['dcvMethod'] ?? '',
            'dcv_status'      => $d['dcvStatus'] ?? '',
            'file_name'       => $d['FileName'] ?? '',
            'file_contents'   => $d['FileContents'] ?? '',
            'dns_name'        => $d['DNSName'] ?? '',
            'dns_entry'       => $d['DNSEntry'] ?? '',
            'poll_status'     => $d['PollStatus'] ?? '',
            'last_poll_date'  => $d['LastPollDate'] ?? '',
            'domain_to_validate' => $d['DomainToValidate'] ?? '',
            'dcv_scope'       => $d['DCVScope'] ?? '',
        ], $dcvStatus);
    }

    /**
     * Normalize product from TheSSLStore API response
     *
     * Maps TheSSLStore product fields to NormalizedProduct.
     * Must match NormalizedProduct constructor keys exactly:
     *   product_code, product_name, vendor, validation_type, product_type,
     *   support_wildcard, support_san, max_domains, max_years, min_years,
     *   price_data, extra_data
     *
     * TheSSLStore /product/query response fields:
     *   ProductCode, ProductName, ProductSlug, ProductDescription,
     *   ProductType (int: 0=ALL,1=DV,2=EV,3=OV,4=WC,5=SCAN,7=SAN,8=CS,11=SMIME,12=DOCSIGN),
     *   VendorName, isDVProduct, isOVProduct, isEVProduct, isGreenBar,
     *   isWildcard, IsSanEnable, isCodeSigning, isScanProduct,
     *   MinSan, MaxSan, PricingInfo[], CurrencyCode,
     *   CanbeReissued, IsCompetitiveUpgradeSupported, IsSupportAutoInstall,
     *   IsNoOfServerFree, isFlexProduct, IssuanceTime, ReissueDays,
     *   SiteSeal, Warranty, isMobileFriendly, isMalwareScan, isSealInSearch,
     *   isVulnerabilityAssessment
     */
    private function normalizeProduct(array $item): NormalizedProduct
    {
        $name      = $item['ProductName'] ?? '';
        $nameLower = strtolower($name);

        // ── Product type: API boolean flags first, then name fallback ──
        $type = 'ssl';
        if (!empty($item['isWildcard']) || !empty($item['isWildcardProduct'])) {
            $type = 'wildcard';
        } elseif (!empty($item['IsSanEnable']) || !empty($item['isMultiDomainProduct'])
            || strpos($nameLower, 'multi') !== false
            || strpos($nameLower, 'ucc') !== false
            || strpos($nameLower, 'san') !== false) {
            $type = 'multi_domain';
        } elseif (!empty($item['isCodeSigning']) || !empty($item['isCodeSigningProduct'])
            || strpos($nameLower, 'code sign') !== false) {
            $type = 'code_signing';
        } elseif (!empty($item['isScanProduct'])) {
            $type = 'scan';
        } elseif (strpos($nameLower, 's/mime') !== false || strpos($nameLower, 'email') !== false) {
            $type = 'email';
        }

        // ── Validation type: API boolean flags first, then string field ──
        $validation = 'dv';
        if (!empty($item['isEVProduct']) || !empty($item['isGreenBar'])) {
            $validation = 'ev';
        } elseif (!empty($item['isOVProduct'])) {
            $validation = 'ov';
        } elseif (!empty($item['isDVProduct'])) {
            $validation = 'dv';
        } elseif (!empty($item['ProductValidationType'])) {
            $vt = strtolower($item['ProductValidationType']);
            if (strpos($vt, 'ev') !== false) $validation = 'ev';
            elseif (strpos($vt, 'ov') !== false) $validation = 'ov';
        }

        $vendor  = $item['VendorName'] ?? $item['BrandName'] ?? 'Unknown';
        $minSan  = (int)($item['MinSan'] ?? 0);
        $maxSan  = (int)($item['MaxSan'] ?? 0);
        $maxDomains = max(1, $maxSan > 0 ? $maxSan : ($type === 'multi_domain' ? 250 : 1));

        // ── Pricing: normalize to standard format ──
        $priceData = $this->extractPricing($item);

        // ── Max years from PricingInfo ──
        $maxYears = 1;
        if (!empty($item['PricingInfo']) && is_array($item['PricingInfo'])) {
            foreach ($item['PricingInfo'] as $pi) {
                $months = (int)($pi['NumberOfMonths'] ?? 0);
                $years  = (int)ceil($months / 12);
                if ($years > $maxYears) $maxYears = $years;
            }
        }

        // ── Build NormalizedProduct with CORRECT field names ──
        // Keys must match NormalizedProduct::__construct() exactly
        return new NormalizedProduct([
            'product_code'     => $item['ProductCode'] ?? '',
            'product_name'     => $name,
            'vendor'           => $vendor,
            'validation_type'  => $validation,           // 'dv','ov','ev'
            'product_type'     => $type,                 // 'ssl','wildcard','multi_domain','code_signing'
            'support_wildcard' => ($type === 'wildcard') || !empty($item['isWildcard']),
            'support_san'      => ($type === 'multi_domain') || !empty($item['IsSanEnable']) || $maxSan > 0,
            'max_domains'      => $maxDomains,
            'max_years'        => $maxYears,
            'min_years'        => 1,
            'price_data'       => $priceData,            // {'base':{'12':5.99},'san':{'12':3.00},'wildcard_san':{'12':49.00}}
            'extra_data'       => [
                'vendor'               => $vendor,
                'product_slug'         => $item['ProductSlug'] ?? '',
                'product_description'  => $item['ProductDescription'] ?? '',
                'product_type_id'      => (int)($item['ProductType'] ?? 0),
                'currency'             => $item['CurrencyCode'] ?? 'USD',
                'min_san'              => $minSan,
                'max_san'              => $maxSan,
                'issuance_time'        => $item['IssuanceTime'] ?? '',
                'reissue_days'         => (int)($item['ReissueDays'] ?? 0),
                'site_seal'            => $item['SiteSeal'] ?? '',
                'warranty'             => $item['Warranty'] ?? '',
                'is_flex'              => !empty($item['isFlexProduct']),
                'can_reissue'          => !empty($item['CanbeReissued']),
                'competitive_upgrade'  => !empty($item['IsCompetitiveUpgradeSupported']),
                'auto_install'         => !empty($item['IsSupportAutoInstall']),
                'free_server_license'  => !empty($item['IsNoOfServerFree']),
                'green_bar'            => !empty($item['isGreenBar']),
                'mobile_friendly'      => !empty($item['isMobileFriendly']),
                'malware_scan'         => !empty($item['isMalwareScan']),
                'seal_in_search'       => !empty($item['isSealInSearch']),
                'vulnerability_scan'   => !empty($item['isVulnerabilityAssessment']),
            ],
        ]);
    }

    /**
     * Extract pricing from TheSSLStore PricingInfo array
     *
     * TheSSLStore PricingInfo format:
     * [
     *   { "NumberOfMonths": 12, "Price": 5.99, "PricePerAdditionalSAN": 3.00,
     *     "PricePerWildcardSAN": 49.00, "WildcardPrice": 199.00, ... },
     *   { "NumberOfMonths": 24, "Price": 10.99, ... }
     * ]
     *
     * Normalized output (must match NicSRS/GoGetSSL format):
     * {
     *   "base":         { "12": 5.99, "24": 10.99, "36": 14.99 },
     *   "san":          { "12": 3.00, "24": 5.00 },
     *   "wildcard_san": { "12": 49.00, "24": 89.00 }
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

            $key = (string)$months;

            // Base price
            $basePrice = (float)($pi['Price'] ?? 0);
            if ($basePrice > 0) {
                $normalized['base'][$key] = $basePrice;
            }

            // SAN price (per additional SAN)
            $sanPrice = (float)($pi['PricePerAdditionalSAN'] ?? 0);
            if ($sanPrice > 0) {
                $normalized['san'][$key] = $sanPrice;
            }

            // Wildcard SAN price
            $wcSanPrice = (float)($pi['PricePerWildcardSAN'] ?? $pi['WildcardPrice'] ?? 0);
            if ($wcSanPrice > 0) {
                $normalized['wildcard_san'][$key] = $wcSanPrice;
            }
        }

        return $normalized;
    }

    /**
     * Normalize TheSSLStore order status strings to standard statuses
     *
     * TheSSLStore uses MajorStatus + MinorStatus:
     * MajorStatus: Initial, Pending, Active, Expired, Cancelled, Revoked, etc.
     */
    private function normalizeStatus(string $status): string
    {
        if (empty($status)) return 'Unknown';

        $map = [
            // Standard statuses
            'active'            => 'Issued',
            'issued'            => 'Issued',
            'initial'           => 'Pending',
            'pending'           => 'Processing',
            'cancelled'         => 'Cancelled',
            'canceled'          => 'Cancelled',
            'revoked'           => 'Revoked',
            'expired'           => 'Expired',
            'rejected'          => 'Rejected',
            'refunded'          => 'Refunded',
            // Specific TheSSLStore statuses
            'new_order'         => 'Pending',
            'reissue'           => 'Reissuing',
            'waiting_approval'  => 'Awaiting Validation',
            'validation'        => 'Awaiting Validation',
            'complete'          => 'Issued',
        ];

        return $map[strtolower(trim($status))] ?? ucfirst($status);
    }

    /**
     * Convert months to TheSSLStore validity period
     *
     * TheSSLStore accepts validity in months directly
     */
    private function monthsToYears(int $months): int
    {
        // TheSSLStore ValidityPeriod is in months, not years
        return $months;
    }
}