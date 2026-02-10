<?php
/**
 * NicSRS API Service
 * Handles all API communication with NicSRS portal
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Service;

class NicsrsApiService
{
    /**
     * @var string API base URL
     */
    private const API_BASE = 'https://portal.nicsrs.com/ssl';
    
    /**
     * @var string API token
     */
    private $apiToken;
    
    /**
     * @var int Request timeout in seconds
     */
    private $timeout = 30;

    /**
     * Constructor
     * 
     * @param string $apiToken API token
     */
    public function __construct(string $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    /**
     * Set request timeout
     * 
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws \Exception On error
     */
    private function request(string $endpoint, array $data = []): array
    {
        // Add API token to all requests
        $data['api_token'] = $this->apiToken;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_BASE . $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);

        // Log the API call
        $logData = $data;
        // Mask sensitive data in logs
        if (isset($logData['api_token'])) {
            $logData['api_token'] = '***MASKED***';
        }
        
        logModuleCall(
            'nicsrs_ssl_admin',
            $endpoint,
            $logData,
            $response,
            ['http_code' => $httpCode]
        );

        // Handle cURL errors
        if ($errno) {
            throw new \Exception("cURL Error ({$errno}): {$error}");
        }

        // Handle HTTP errors
        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error: {$httpCode}");
        }

        // Parse response
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Test API connection
     * 
     * @return bool Connection successful
     */
    public function testConnection(): bool
    {
        if (empty($this->apiToken)) {
            return false;
        }
        
        try {
            $result = $this->productList('Sectigo');
            return isset($result['code']) && ($result['code'] == 1 || $result['code'] == 2);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get product list from API
     * 
     * @param string|null $vendor Filter by vendor
     * @return array API response
     */
    public function productList(?string $vendor = null): array
    {
        $data = [];
        if ($vendor) {
            $data['vendor'] = $vendor;
        }
        return $this->request('/productList', $data);
    }

    /**
     * Collect certificate status and details
     * 
     * @param string $certId Certificate ID
     * @return array API response
     */
    public function collect(string $certId): array
    {
        return $this->request('/collect', [
            'certId' => $certId,
        ]);
    }

    /**
     * Cancel certificate order
     * 
     * @param string $certId Certificate ID
     * @param string $reason Cancellation reason (Required by API)
     * @return array API response
     */
    public function cancel(string $certId, string $reason = ''): array
    {
        if (empty($reason)) {
            $reason = 'Cancelled by administrator';
        }
        
        return $this->request('/cancel', [
            'certId' => $certId,
            'reason' => $reason,
        ]);
    }

    /**
     * Revoke certificate
     * 
     * @param string $certId Certificate ID
     * @param string $reason Revocation reason (Required by API)
     * @return array API response
     */
    public function revoke(string $certId, string $reason = ''): array
    {
        if (empty($reason)) {
            $reason = 'Revoked by administrator';
        }
        
        return $this->request('/revoke', [
            'certId' => $certId,
            'reason' => $reason,
        ]);
    }

    /**
     * Reissue certificate
     * 
     * @param string $certId Certificate ID
     * @param array $data Reissue data (csr, domainInfo)
     * @return array API response
     */
    public function reissue(string $certId, array $data = []): array
    {
        $requestData = ['certId' => $certId];
        
        if (isset($data['csr'])) {
            $requestData['csr'] = $data['csr'];
        }
        
        if (isset($data['domainInfo'])) {
            $requestData['domainInfo'] = is_array($data['domainInfo']) 
                ? json_encode($data['domainInfo']) 
                : $data['domainInfo'];
        }
        
        return $this->request('/reissue', $requestData);
    }

    /**
     * Renew certificate
     * 
     * @param string $certId Certificate ID
     * @return array API response
     */
    public function renew(string $certId): array
    {
        return $this->request('/renew', [
            'certId' => $certId,
        ]);
    }

    /**
     * Get certificate by reference ID
     * 
     * @param string $refId Reference ID
     * @return array API response
     */
    public function getCertByRefId(string $refId): array
    {
        return $this->request('/getCertByRefId', [
            'refId' => $refId,
        ]);
    }

    /**
     * Check CAA records for domain
     * 
     * @param string $domain Domain name
     * @return array API response
     */
    public function caaCheck(string $domain): array
    {
        return $this->request('/caaCheck', [
            'domain' => $domain,
        ]);
    }

    /**
     * Resend DCV verification email
     * 
     * @param string $certId Certificate ID
     * @param string $domain Domain name
     * @return array API response
     */
    public function resendDcv(string $certId, string $domain): array
    {
        return $this->request('/DCVemail', [
            'certId' => $certId,
            'domain' => $domain,
        ]);
    }

    /**
     * Update DCV method for domain
     * 
     * @param string $certId Certificate ID
     * @param array $domainInfo Domain validation info
     * @return array API response
     */
    public function updateDcv(string $certId, array $domainInfo): array
    {
        return $this->request('/updateDCV', [
            'certId' => $certId,
            'domainInfo' => json_encode($domainInfo),
        ]);
    }

    /**
     * Batch update DCV methods
     * 
     * @param string $certId Certificate ID
     * @param array $domainInfoList List of domain validation info
     * @return array API response
     */
    public function batchUpdateDcv(string $certId, array $domainInfoList): array
    {
        return $this->request('/batchUpdateDCV', [
            'certId' => $certId,
            'domainInfo' => json_encode($domainInfoList),
        ]);
    }

    /**
     * Get DCV email options for domain
     * 
     * @param string $domain Domain name
     * @return array API response
     */
    public function getDcvEmails(string $domain): array
    {
        return $this->request('/DCVemail', [
            'domain' => $domain,
        ]);
    }

    /**
     * Validate certificate request
     * 
     * @param string $productCode Product code
     * @param string $csr CSR content
     * @param array $domainInfo Domain info
     * @return array API response
     */
    public function validate(string $productCode, string $csr, array $domainInfo): array
    {
        return $this->request('/validate', [
            'productCode' => $productCode,
            'csr' => $csr,
            'domainInfo' => json_encode($domainInfo),
        ]);
    }

    /**
     * Place certificate order
     * 
     * @param array $orderData Order data
     * @return array API response
     */
    public function place(array $orderData): array
    {
        // Ensure domainInfo is JSON encoded
        if (isset($orderData['domainInfo']) && is_array($orderData['domainInfo'])) {
            $orderData['domainInfo'] = json_encode($orderData['domainInfo']);
        }
        
        // Ensure Administrator is JSON encoded
        if (isset($orderData['Administrator']) && is_array($orderData['Administrator'])) {
            $orderData['Administrator'] = json_encode($orderData['Administrator']);
        }
        
        // Ensure organizationInfo is JSON encoded
        if (isset($orderData['organizationInfo']) && is_array($orderData['organizationInfo'])) {
            $orderData['organizationInfo'] = json_encode($orderData['organizationInfo']);
        }
        
        return $this->request('/place', $orderData);
    }

    /**
     * Get API response code description
     * 
     * @param int $code Response code
     * @return string Description
     */
    public static function getResponseCodeDescription(int $code): string
    {
        $codes = [
            1 => 'Success',
            2 => 'Certificate being issued, retry later',
            -1 => 'Parameter validation failed',
            -2 => 'Unknown error',
            -3 => 'Product/price error',
            -4 => 'Insufficient credit',
            -6 => 'CA request failed',
            400 => 'Permission denied',
        ];
        
        return isset($codes[$code]) ? $codes[$code] : 'Unknown code';
    }
}