<?php
/**
 * NicSRS SSL Module - API Service
 * Handles all communication with NicSRS API
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;

class ApiService
{
    /**
     * API endpoints mapping
     */
    private static $endpoints = [
        'validate'      => '/validate',
        'place'         => '/place',
        'collect'       => '/collect',
        'cancel'        => '/cancel',
        'revoke'        => '/revoke',
        'reissue'       => '/reissue',
        'renew'         => '/renew',
        'replace'       => '/replace',
        'updateDCV'     => '/updateDCV',
        'batchUpdateDCV'=> '/batchUpdateDCV',
        'DCVemail'      => '/DCVemail',
        'removeMdc'     => '/removeMdcDomain',
        'productList'   => '/productList',
    ];

    /**
     * Get API token with fallback to Admin Addon settings
     * 
     * Priority:
     * 1. Product-level API token (configoption2)
     * 2. Product config via serviceid
     * 3. Admin Addon module settings (tbladdonmodules) <-- ADD THIS
     * 4. Shared settings table (mod_nicsrs_settings)
     * 
     * @param array $params Module parameters
     * @return string API token
     * @throws Exception If no API token configured
     */
    public static function getApiToken(array $params = []): string
    {
        // 1. Check product-level API token first (configoption2)
        if (!empty($params['configoption2'])) {
            return trim($params['configoption2']);
        }

        // 2. Try to get from product config via service
        if (!empty($params['serviceid'])) {
            $service = Capsule::table('tblhosting')
                ->where('id', $params['serviceid'])
                ->first();

            if ($service) {
                $product = Capsule::table('tblproducts')
                    ->where('id', $service->packageid)
                    ->first();

                if ($product && !empty($product->configoption2)) {
                    return trim($product->configoption2);
                }
            }
        }

        // 3. Try Admin Addon module settings (tbladdonmodules)
        try {
            $addonSetting = Capsule::table('tbladdonmodules')
                ->where('module', 'nicsrs_ssl_admin')
                ->where('setting', 'api_token')
                ->first();

            if ($addonSetting && !empty($addonSetting->value)) {
                return trim($addonSetting->value);
            }
        } catch (Exception $e) {
            // Continue to next fallback
        }

        // 4. Fallback to shared settings table (mod_nicsrs_settings)
        $setting = Capsule::table('mod_nicsrs_settings')
            ->where('setting_key', 'api_token')
            ->first();

        if ($setting && !empty($setting->setting_value)) {
            return trim($setting->setting_value);
        }

        throw new Exception('API token not configured. Please configure in product settings or Admin Addon.');
    }

    /**
     * Make API call to NicSRS
     * 
     * @param string $endpoint Endpoint name
     * @param array $data Request data
     * @return object Response object (never null)
     * @throws Exception On API error
     */
    public static function call(string $endpoint, array $data): object
    {
        $url = self::getEndpointUrl($endpoint);
        
        if (empty($url)) {
            throw new Exception("Unknown API endpoint: {$endpoint}");
        }

        // Log the request (without sensitive data)
        $logData = $data;
        if (isset($logData['api_token'])) {
            $logData['api_token'] = substr($logData['api_token'], 0, 8) . '***';
        }
        
        logModuleCall('nicsrs_ssl', $endpoint, $logData, '');

        try {
            $response = self::curlRequest($url, $data);
            
            // Log the response
            logModuleCall('nicsrs_ssl', $endpoint . '_response', $logData, json_encode($response));

            return $response;
        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', $endpoint . '_error', $logData, $e->getMessage());
            throw $e; // Re-throw, don't return null
        }
    }

    /**
     * Get full endpoint URL
     */
    private static function getEndpointUrl(string $endpoint): string
    {
        if (!isset(self::$endpoints[$endpoint])) {
            return '';
        }

        return NICSRS_API_URL . self::$endpoints[$endpoint];
    }

    /**
     * Execute cURL request
     * 
     * @param string $url Request URL
     * @param array $data Request data
     * @return object Response object (never null)
     * @throws Exception On any error
     */
    private static function curlRequest(string $url, array $data): object
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);

        // Handle cURL errors
        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: {$httpCode}");
        }

        // Handle empty response
        if (empty($response)) {
            throw new Exception("Empty response from API");
        }

        // Parse JSON response
        $decoded = json_decode($response);

        // Handle JSON parse errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        // Handle null decoded result
        if ($decoded === null) {
            throw new Exception("API returned null response");
        }

        return $decoded;
    }

    // ==========================================
    // Convenience Methods for Common Operations
    // ==========================================

    /**
     * Validate certificate request
     */
    public static function validate(array $params, string $productCode, string $csr, array $domainInfo): object
    {
        return self::call('validate', [
            'api_token'   => self::getApiToken($params),
            'productCode' => $productCode,
            'csr'         => $csr,
            'domainInfo'  => json_encode($domainInfo),
        ]);
    }

    /**
     * Place certificate order
     */
    public static function place(array $params, string $productCode, int $years, array $requestData): object
    {
        $apiToken = self::getApiToken($params);

        $data = [
            'api_token'   => $apiToken,
            'productCode' => $productCode,
            'years'       => $years,
            'params'      => json_encode($requestData),
        ];

        return self::call('place', $data);
    }

    /**
     * Collect certificate status and details
     */
    public static function collect(array $params, string $certId): object
    {
        return self::call('collect', [
            'api_token' => self::getApiToken($params),
            'certId'    => $certId,
        ]);
    }

    /**
     * Cancel certificate order
     */
    public static function cancel(array $params, string $certId, string $reason = ''): object
    {
        return self::call('cancel', [
            'api_token' => self::getApiToken($params),
            'certId'    => $certId,
            'reason'    => $reason ?: 'Customer requested cancellation',
        ]);
    }

    /**
     * Revoke certificate
     */
    public static function revoke(array $params, string $certId, string $reason = ''): object
    {
        return self::call('revoke', [
            'api_token' => self::getApiToken($params),
            'certId'    => $certId,
            'reason'    => $reason ?: 'Customer requested revocation',
        ]);
    }

    /**
     * Reissue certificate
     */
    public static function reissue(array $params, string $certId, string $csr, array $domainInfo): object
    {
        return self::call('reissue', [
            'api_token'  => self::getApiToken($params),
            'certId'     => $certId,
            'csr'        => $csr,
            'domainInfo' => json_encode($domainInfo),
        ]);
    }

    /**
     * Renew certificate
     */
    public static function renew(array $params, string $certId): object
    {
        $apiToken = self::getApiToken($params);

        return self::call('renew', [
            'api_token' => $apiToken,
            'certId' => $certId,
        ]);
    }

    /**
     * Update DCV method for domains
     */
    public static function batchUpdateDCV(array $params, string $certId, array $domainInfo): object
    {
        $apiToken = self::getApiToken($params);

        return self::call('batchUpdateDCV', [
            'api_token' => $apiToken,
            'certId' => $certId,
            'domainInfo' => json_encode($domainInfo),
        ]);
    }

    /**
     * Resend DCV email
     */
    public static function resendDCVEmail(array $params, string $certId, string $domain, string $email): object
    {
        $apiToken = self::getApiToken($params);

        return self::call('DCVemail', [
            'api_token' => $apiToken,
            'certId' => $certId,
            'domainName' => $domain,
            'dcvEmail' => $email,
        ]);
    }

    /**
     * Remove multi-domain entry
     */
    public static function removeMdcDomain(array $params, string $certId, string $domainName): object
    {
        $apiToken = self::getApiToken($params);

        return self::call('removeMdc', [
            'api_token' => $apiToken,
            'certId' => $certId,
            'domainName' => $domainName,
        ]);
    }

    /**
     * Get product list from API
     */
    public static function getProductList(array $params): object
    {
        return self::call('productList', [
            'api_token' => self::getApiToken($params),
        ]);
    }

    /**
     * Test API connection
     */
    public static function testConnection(array $params): bool
    {
        try {
            $result = self::getProductList($params);
            return isset($result->code) && $result->code == 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Parse API response and check for errors
     * 
     * @param object|null $response API response object
     * @return array Parsed response with success status
     */
    public static function parseResponse($response): array
    {
        // Handle null response
        if ($response === null) {
            return [
                'success' => false,
                'code' => 0,
                'message' => 'No response from API',
                'data' => null,
                'status' => '',
            ];
        }

        $code = $response->code ?? 0;
        $success = ($code == 1 || $code == 2);
        
        // Get message from various possible fields
        $message = $response->msg ?? $response->message ?? $response->error ?? '';
        
        if (!$success && empty($message)) {
            $message = self::getErrorMessage($code);
        }

        return [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $response->data ?? null,
            'status' => $response->status ?? $response->certStatus ?? '',
        ];
    }

    /**
     * Get error message for API code
     */
    private static function getErrorMessage(int $code): string
    {
        $messages = [
            0 => 'Operation failed',
            -1 => 'Parameter validation failed',
            -2 => 'Unknown error',
            -3 => 'Product/price error',
            -4 => 'Insufficient credit',
            -6 => 'CA request failed',
            400 => 'Permission denied - invalid API token',
        ];

        return $messages[$code] ?? "Unknown error (code: {$code})";
    }
}