<?php
/**
 * NicSRS API Service
 * 
 * Handles all communication with NicSRS SSL API
 * Supports shared API token from Addon Module
 * 
 * @package    WHMCS
 * @author     HVN GROUP
 * @version    2.0.0
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;

class nicsrsAPI
{
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://portal.nicsrs.com/ssl';
    
    /**
     * API Timeout in seconds
     */
    const API_TIMEOUT = 60;
    
    /**
     * Cached API token
     */
    private static $cachedAddonToken = null;
    
    /**
     * API endpoint mapping
     */
    private static $endpoints = [
        'validate'      => '/validate',
        'place'         => '/place',
        'collect'       => '/collect',
        'cancel'        => '/cancel',
        'email'         => '/DCVemail',
        'updateDCV'     => '/updateDCV',
        'batchUpdateDCV'=> '/batchUpdateDCV',
        'file'          => '/validatefile',
        'dns'           => '/validatedns',
        'country'       => '/country',
        'reissue'       => '/reissue',
        'revoke'        => '/revoke',
        'replace'       => '/replace',
        'renew'         => '/renew',
        'removeMdc'     => '/removeMdcDomain',
        'productList'   => '/productList',
        'caaCheck'      => '/caaCheck',
        'getCertByRefId'=> '/getCertByRefId',
    ];

    /**
     * Get API token from Addon Module (tbladdonmodules)
     * This is the PRIMARY source for API token
     * 
     * @return string|null
     */
    public static function getAddonApiToken()
    {
        // Return cached token if available
        if (self::$cachedAddonToken !== null) {
            return self::$cachedAddonToken ?: null;
        }
        
        try {
            $result = Capsule::table('tbladdonmodules')
                ->where('module', 'nicsrs_ssl_admin')
                ->where('setting', 'api_token')
                ->first();
            
            if ($result && !empty($result->value)) {
                self::$cachedAddonToken = $result->value;
                return $result->value;
            }
            
            self::$cachedAddonToken = '';
            return null;
            
        } catch (Exception $e) {
            self::$cachedAddonToken = '';
            return null;
        }
    }
    
    /**
     * Get API token with priority handling
     * Priority: 1. Passed token -> 2. Addon Module -> 3. Product config
     * 
     * @param array $params Optional params with configoption2/configoption3
     * @return string|null
     */
    public static function getApiToken($params = [])
    {
        // Priority 1: Explicitly passed token
        if (!empty($params['api_token'])) {
            return $params['api_token'];
        }
        
        // Priority 2: Addon Module token
        $addonToken = self::getAddonApiToken();
        if (!empty($addonToken)) {
            return $addonToken;
        }
        
        // Priority 3: Product-level token (configoption3 in new version)
        if (!empty($params['configoption3'])) {
            return $params['configoption3'];
        }
        
        // Legacy: configoption2 (old version)
        if (!empty($params['configoption2']) && strlen($params['configoption2']) > 20) {
            return $params['configoption2'];
        }
        
        return null;
    }
    
    /**
     * Clear cached token (useful for testing)
     */
    public static function clearTokenCache()
    {
        self::$cachedAddonToken = null;
    }

    /**
     * Make API call
     * 
     * @param string $callable API endpoint name
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function call($callable, $data)
    {
        $url = self::getUrl($callable);
        
        if (empty($url)) {
            throw new Exception("Unknown API endpoint: {$callable}");
        }
        
        // Ensure API token is present
        if (empty($data['api_token'])) {
            $data['api_token'] = self::getApiToken($data);
        }
        
        if (empty($data['api_token'])) {
            throw new Exception("API token is required");
        }
        
        return self::curlRequest($url, $data);
    }

    /**
     * Get full URL for endpoint
     * 
     * @param string $callable Endpoint name
     * @return string Full URL
     */
    private static function getUrl($callable)
    {
        if (!isset(self::$endpoints[$callable])) {
            return '';
        }
        
        return self::API_BASE_URL . self::$endpoints[$callable];
    }

    /**
     * Execute cURL request
     * 
     * @param string $url Request URL
     * @param array|string $data Request data
     * @return object Decoded JSON response
     * @throws Exception
     */
    public static function curlRequest($url, $data)
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => is_array($data) ? http_build_query($data) : $data,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOBODY => false,
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        
        curl_close($curl);
        
        // Log the API call
        self::logApiCall($url, $data, $response, $error);
        
        // Handle cURL errors
        if ($errno) {
            throw new Exception("cURL Error ({$errno}): {$error}");
        }
        
        // Handle HTTP errors
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: {$httpCode}");
        }
        
        // Parse JSON response
        $decoded = json_decode($response);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Log API call to WHMCS module log
     * 
     * @param string $url Request URL
     * @param array $data Request data
     * @param string $response Response body
     * @param string $error Error message if any
     */
    private static function logApiCall($url, $data, $response, $error = '')
    {
        // Mask API token in logs
        $logData = $data;
        if (isset($logData['api_token'])) {
            $logData['api_token'] = substr($logData['api_token'], 0, 8) . '****';
        }
        
        // Extract endpoint from URL
        $endpoint = str_replace(self::API_BASE_URL, '', $url);
        
        logModuleCall(
            'nicsrs_ssl',
            'API' . $endpoint,
            $logData,
            $response,
            $error ?: ''
        );
    }

    // ========================================
    // Specific API Methods
    // ========================================

    /**
     * Validate certificate request
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function validate($data)
    {
        $required = ['productCode', 'csr', 'domainInfo'];
        self::validateRequired($data, $required);
        
        return self::call('validate', $data);
    }

    /**
     * Place certificate order
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function place($data)
    {
        if (empty($data['params'])) {
            throw new Exception("Request params are required");
        }
        
        return self::call('place', $data);
    }

    /**
     * Collect certificate status and details
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function collect($data)
    {
        if (empty($data['certId'])) {
            throw new Exception("certId is required");
        }
        
        return self::call('collect', $data);
    }

    /**
     * Cancel certificate order
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function cancel($data)
    {
        $required = ['certId', 'reason'];
        self::validateRequired($data, $required);
        
        return self::call('cancel', $data);
    }

    /**
     * Revoke certificate
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function revoke($data)
    {
        $required = ['certId', 'reason'];
        self::validateRequired($data, $required);
        
        return self::call('revoke', $data);
    }

    /**
     * Reissue certificate
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function reissue($data)
    {
        $required = ['certId', 'csr', 'domainInfo'];
        self::validateRequired($data, $required);
        
        return self::call('reissue', $data);
    }

    /**
     * Renew certificate
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function renew($data)
    {
        if (empty($data['certId'])) {
            throw new Exception("certId is required");
        }
        
        return self::call('renew', $data);
    }

    /**
     * Replace certificate
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function replace($data)
    {
        if (empty($data['params'])) {
            throw new Exception("Request params are required");
        }
        
        return self::call('replace', $data);
    }

    /**
     * Get DCV email options
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function getDcvEmails($data)
    {
        if (empty($data['domainName'])) {
            throw new Exception("domainName is required");
        }
        
        return self::call('email', $data);
    }

    /**
     * Update DCV method for a domain
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function updateDCV($data)
    {
        $required = ['certId', 'domainName', 'dcvMethod'];
        self::validateRequired($data, $required);
        
        return self::call('updateDCV', $data);
    }

    /**
     * Batch update DCV methods
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function batchUpdateDCV($data)
    {
        if (empty($data['domainInfo'])) {
            throw new Exception("domainInfo is required");
        }
        
        return self::call('batchUpdateDCV', $data);
    }

    /**
     * Remove MDC domain
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function removeMdc($data)
    {
        $required = ['certId', 'domainName'];
        self::validateRequired($data, $required);
        
        return self::call('removeMdc', $data);
    }

    /**
     * Get product list
     * 
     * @param array $data Request data (optional vendor filter)
     * @return object API response
     */
    public static function productList($data = [])
    {
        return self::call('productList', $data);
    }

    /**
     * Check CAA records
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function caaCheck($data)
    {
        if (empty($data['domain'])) {
            throw new Exception("domain is required");
        }
        
        return self::call('caaCheck', $data);
    }

    /**
     * Get certificate by reference ID
     * 
     * @param array $data Request data
     * @return object API response
     * @throws Exception
     */
    public static function getCertByRefId($data)
    {
        if (empty($data['refId'])) {
            throw new Exception("refId is required");
        }
        
        return self::call('getCertByRefId', $data);
    }

    /**
     * Validate required parameters
     * 
     * @param array $data Request data
     * @param array $required Required parameter names
     * @throws Exception
     */
    private static function validateRequired($data, $required)
    {
        foreach ($required as $param) {
            if (empty($data[$param])) {
                throw new Exception("{$param} is required");
            }
        }
    }

    /**
     * Test API connection
     * 
     * @param string|null $apiToken Optional specific token to test
     * @return array ['success' => bool, 'message' => string]
     */
    public static function testConnection($apiToken = null)
    {
        try {
            $token = $apiToken ?: self::getApiToken();
            
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'API token not configured',
                ];
            }
            
            $result = self::productList(['api_token' => $token]);
            
            if (isset($result->code) && $result->code == 1) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'product_count' => isset($result->data) ? count((array)$result->data) : 0,
                ];
            }
            
            return [
                'success' => false,
                'message' => $result->msg ?? 'Unknown error',
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}