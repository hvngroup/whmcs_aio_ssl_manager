<?php
/**
 * NicSRS SSL Utility Functions
 * 
 * Helper functions for the SSL module
 * 
 * @package    WHMCS
 * @author     HVN GROUP
 * @version    2.0.0
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;
use ZipArchive;

class nicsrsFunc
{
    /**
     * Certificate status mapping from API to internal
     */
    const STATUS_MAP = [
        'new' => 'awaiting',
        'awaiting_configuration' => 'awaiting',
        'draft' => 'draft',
        'pending' => 'pending',
        'processing' => 'processing',
        'issued' => 'complete',
        'complete' => 'complete',
        'active' => 'complete',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'revoked' => 'revoked',
        'expired' => 'expired',
        'rejected' => 'rejected',
    ];

    /**
     * Create orders table if not exists
     * 
     * @return bool Success
     */
    public static function createOrdersTableIfNotExist()
    {
        return nicsrsSSLSql::CreateTableIfNotExists();
    }

    /**
     * Load language file
     * 
     * @param string $lang Language code
     * @param int $userId User ID for auto-detection
     * @return array Language strings
     */
    public static function loadLanguage($lang = '', $userId = 0)
    {
        // Try to detect language from user settings
        if (empty($lang) && $userId > 0) {
            try {
                $client = Capsule::table('tblclients')
                    ->where('id', $userId)
                    ->first(['language']);
                
                if ($client && !empty($client->language)) {
                    $lang = $client->language;
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        // Default to English
        if (empty($lang)) {
            $lang = 'english';
        }
        
        // Normalize language name
        $lang = strtolower($lang);
        
        // Map common variants
        $langMap = [
            'vietnamese' => 'vietnamese',
            'tieng_viet' => 'vietnamese',
            'vi' => 'vietnamese',
            'chinese' => 'chinese',
            'zh' => 'chinese',
            'zh-cn' => 'chinese-cn',
            'chinese-cn' => 'chinese-cn',
        ];
        
        if (isset($langMap[$lang])) {
            $lang = $langMap[$lang];
        }
        
        $langFile = LANG_PATH . $lang . '.php';
        
        if (!file_exists($langFile)) {
            $langFile = LANG_PATH . 'english.php';
        }
        
        $_LANG = [];
        if (file_exists($langFile)) {
            include $langFile;
        }
        
        return $_LANG;
    }

    /**
     * Get certificate type options for ConfigOptions
     * First tries to get from Addon's product cache, then falls back to static list
     * 
     * @return string Comma-separated options
     */
    public static function getCertTypeOptions()
    {
        // Try to get from Addon Module's product cache
        try {
            $products = Capsule::table('mod_nicsrs_products')
                ->where('is_active', 1)
                ->orderBy('vendor')
                ->orderBy('product_name')
                ->get();
            
            if ($products->count() > 0) {
                $options = [];
                foreach ($products as $product) {
                    $options[] = $product->product_code;
                }
                return implode(',', $options);
            }
        } catch (Exception $e) {
            // Table might not exist, fall through to static list
        }
        
        // Fallback to static certificate list
        return self::getCertAttributes(null, 'name');
    }

    /**
     * Get certificate attributes (legacy method)
     * 
     * @param string|null $certKey Certificate key
     * @param string $type Type of return (name, code, all)
     * @return mixed
     */
    public static function getCertAttributes($certKey = null, $type = 'all')
    {
        // Static certificate type definitions
        $certTypes = [
            'sectigo-positivessl-dv' => [
                'name' => 'Sectigo PositiveSSL DV',
                'vendor' => 'Sectigo',
                'validation' => 'dv',
            ],
            'sectigo-positivessl-wildcard-dv' => [
                'name' => 'Sectigo PositiveSSL Wildcard DV',
                'vendor' => 'Sectigo',
                'validation' => 'dv',
            ],
            'sectigo-essentialssl-dv' => [
                'name' => 'Sectigo EssentialSSL DV',
                'vendor' => 'Sectigo',
                'validation' => 'dv',
            ],
            'sectigo-instantssl-ov' => [
                'name' => 'Sectigo InstantSSL OV',
                'vendor' => 'Sectigo',
                'validation' => 'ov',
            ],
            'sectigo-premiumssl-wildcard-ov' => [
                'name' => 'Sectigo PremiumSSL Wildcard OV',
                'vendor' => 'Sectigo',
                'validation' => 'ov',
            ],
            'sectigo-ev' => [
                'name' => 'Sectigo EV SSL',
                'vendor' => 'Sectigo',
                'validation' => 'ev',
            ],
            'sectigo-ev-mdc' => [
                'name' => 'Sectigo EV MDC SSL',
                'vendor' => 'Sectigo',
                'validation' => 'ev',
            ],
            'geotrust-quickssl-premium-dv' => [
                'name' => 'GeoTrust QuickSSL Premium DV',
                'vendor' => 'GeoTrust',
                'validation' => 'dv',
            ],
            'geotrust-truebusiness-id-ov' => [
                'name' => 'GeoTrust TrueBusiness ID OV',
                'vendor' => 'GeoTrust',
                'validation' => 'ov',
            ],
            'geotrust-truebusiness-id-ev' => [
                'name' => 'GeoTrust TrueBusiness ID EV',
                'vendor' => 'GeoTrust',
                'validation' => 'ev',
            ],
            'digicert-standard-ssl' => [
                'name' => 'DigiCert Standard SSL',
                'vendor' => 'DigiCert',
                'validation' => 'ov',
            ],
            'digicert-ev-ssl' => [
                'name' => 'DigiCert EV SSL',
                'vendor' => 'DigiCert',
                'validation' => 'ev',
            ],
            'digicert-wildcard-ssl' => [
                'name' => 'DigiCert Wildcard SSL',
                'vendor' => 'DigiCert',
                'validation' => 'ov',
            ],
            'rapidssl-standard-dv' => [
                'name' => 'RapidSSL Standard DV',
                'vendor' => 'RapidSSL',
                'validation' => 'dv',
            ],
            'rapidssl-wildcard-dv' => [
                'name' => 'RapidSSL Wildcard DV',
                'vendor' => 'RapidSSL',
                'validation' => 'dv',
            ],
            'thawte-ssl-webserver-ov' => [
                'name' => 'Thawte SSL WebServer OV',
                'vendor' => 'Thawte',
                'validation' => 'ov',
            ],
            'thawte-ssl-webserver-ev' => [
                'name' => 'Thawte SSL WebServer EV',
                'vendor' => 'Thawte',
                'validation' => 'ev',
            ],
            'globalsign-domainssl-dv' => [
                'name' => 'GlobalSign DomainSSL DV',
                'vendor' => 'GlobalSign',
                'validation' => 'dv',
            ],
            'globalsign-organizationssl-ov' => [
                'name' => 'GlobalSign OrganizationSSL OV',
                'vendor' => 'GlobalSign',
                'validation' => 'ov',
            ],
            'globalsign-extendedssl-ev' => [
                'name' => 'GlobalSign ExtendedSSL EV',
                'vendor' => 'GlobalSign',
                'validation' => 'ev',
            ],
            'gogetssl-domain-ssl' => [
                'name' => 'GoGetSSL Domain SSL',
                'vendor' => 'GoGetSSL',
                'validation' => 'dv',
            ],
        ];
        
        // Return specific certificate
        if ($certKey !== null) {
            if (isset($certTypes[$certKey])) {
                return $type === 'all' ? $certTypes[$certKey] : ($certTypes[$certKey][$type] ?? null);
            }
            return null;
        }
        
        // Return all certificates
        if ($type === 'name') {
            $names = [];
            foreach ($certTypes as $code => $info) {
                $names[] = $code;
            }
            return implode(',', $names);
        }
        
        return $certTypes;
    }

    /**
     * Map API status to internal order status
     * 
     * @param object $apiData API response data
     * @return string|null Internal status
     */
    public static function mapApiStatusToOrder($apiData)
    {
        $apiDataArray = (array) $apiData;
        
        // Check if certificate is issued
        if (!empty($apiDataArray['certificate'])) {
            return 'complete';
        }
        
        // Check status fields
        $statuses = ['application', 'dcv', 'issued'];
        $allDone = true;
        
        foreach ($statuses as $status) {
            if (isset($apiDataArray[$status])) {
                $statusData = (array) $apiDataArray[$status];
                if (($statusData['status'] ?? '') !== 'done') {
                    $allDone = false;
                    break;
                }
            }
        }
        
        if ($allDone && !empty($apiDataArray['issued'])) {
            return 'complete';
        }
        
        // Check for specific status field
        if (!empty($apiDataArray['status'])) {
            $status = strtolower($apiDataArray['status']);
            return self::STATUS_MAP[$status] ?? 'pending';
        }
        
        return 'pending';
    }

    /**
     * Check if string is an email address
     * 
     * @param string $str String to check
     * @return bool
     */
    public static function checkEmail($str)
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate CSR format
     * 
     * @param string $csr CSR content
     * @return bool
     */
    public static function validateCsr($csr)
    {
        $csr = trim($csr);
        
        // Check for PEM format
        if (strpos($csr, '-----BEGIN CERTIFICATE REQUEST-----') === false) {
            return false;
        }
        
        if (strpos($csr, '-----END CERTIFICATE REQUEST-----') === false) {
            return false;
        }
        
        // Try to parse with OpenSSL
        $parsed = openssl_csr_get_subject($csr);
        
        return $parsed !== false;
    }

    /**
     * Parse CSR to extract information
     * 
     * @param string $csr CSR content
     * @return array|false Parsed data or false
     */
    public static function parseCsr($csr)
    {
        $csr = trim($csr);
        
        $subject = openssl_csr_get_subject($csr);
        
        if (!$subject) {
            return false;
        }
        
        return [
            'commonName' => $subject['CN'] ?? '',
            'organization' => $subject['O'] ?? '',
            'organizationalUnit' => $subject['OU'] ?? '',
            'locality' => $subject['L'] ?? '',
            'state' => $subject['ST'] ?? '',
            'country' => $subject['C'] ?? '',
            'email' => $subject['emailAddress'] ?? '',
        ];
    }

    /**
     * Generate ZIP file with certificate files
     * 
     * @param object $certData Certificate data from API
     * @param string $domain Primary domain
     * @return array Result with status and data/error
     */
    public static function zipCert($certData, $domain)
    {
        try {
            $certDataArray = (array) $certData->data;
            
            if (empty($certDataArray['certificate']) || empty($certDataArray['caCertificate'])) {
                return [
                    'status' => 0,
                    'error' => 'Certificate data incomplete',
                ];
            }
            
            // Sanitize domain for filename
            $safeFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $domain);
            $tempDir = sys_get_temp_dir() . '/nicsrs_cert/';
            
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $zipFile = $tempDir . $safeFileName . '_' . time() . '.zip';
            
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
                return [
                    'status' => 0,
                    'error' => 'Failed to create ZIP file',
                ];
            }
            
            // Add certificate file
            $zip->addFromString("{$safeFileName}.crt", $certDataArray['certificate']);
            
            // Add CA bundle
            $zip->addFromString("{$safeFileName}.ca-bundle", $certDataArray['caCertificate']);
            
            // Add full chain (cert + CA)
            $fullChain = $certDataArray['certificate'] . "\n" . $certDataArray['caCertificate'];
            $zip->addFromString("{$safeFileName}.fullchain.crt", $fullChain);
            
            // Add private key if available
            if (!empty($certDataArray['privateKey'])) {
                $zip->addFromString("{$safeFileName}.key", $certDataArray['privateKey']);
            }
            
            // Add PFX/PKCS12 if available
            if (!empty($certDataArray['pkcs12'])) {
                $zip->addFromString("{$safeFileName}.pfx", base64_decode($certDataArray['pkcs12']));
            }
            
            // Add JKS if available
            if (!empty($certDataArray['jks'])) {
                $zip->addFromString("{$safeFileName}.jks", base64_decode($certDataArray['jks']));
            }
            
            // Add README file
            $readme = self::generateCertReadme($domain, $certDataArray);
            $zip->addFromString("README.txt", $readme);
            
            $zip->close();
            
            return [
                'status' => 1,
                'data' => [
                    'file' => $zipFile,
                    'filename' => basename($zipFile),
                ],
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate README file for certificate ZIP
     * 
     * @param string $domain Domain name
     * @param array $certData Certificate data
     * @return string README content
     */
    private static function generateCertReadme($domain, $certData)
    {
        $passwords = [];
        
        if (!empty($certData['pkcsPass'])) {
            $passwords[] = "PFX Password: {$certData['pkcsPass']}";
        }
        
        if (!empty($certData['jksPass'])) {
            $passwords[] = "JKS Password: {$certData['jksPass']}";
        }
        
        $passwordSection = !empty($passwords) 
            ? "PASSWORDS:\n" . implode("\n", $passwords) . "\n\n"
            : "";
        
        $validity = '';
        if (!empty($certData['beginDate']) && !empty($certData['endDate'])) {
            $validity = "VALIDITY:\nFrom: {$certData['beginDate']}\nTo: {$certData['endDate']}\n\n";
        }
        
        return <<<README
SSL CERTIFICATE FOR: {$domain}
Generated: {$this->formatDate(date('Y-m-d H:i:s'))}

FILES INCLUDED:
- {$domain}.crt         : Primary certificate
- {$domain}.ca-bundle   : CA bundle (intermediate certificates)
- {$domain}.fullchain.crt : Full chain (certificate + CA bundle)
- {$domain}.key         : Private key (if generated by system)
- {$domain}.pfx         : PKCS#12 format (for IIS/Windows)
- {$domain}.jks         : Java KeyStore format (for Tomcat/Java)

{$validity}{$passwordSection}INSTALLATION GUIDES:

APACHE:
  SSLCertificateFile /path/to/{$domain}.crt
  SSLCertificateKeyFile /path/to/{$domain}.key
  SSLCertificateChainFile /path/to/{$domain}.ca-bundle

NGINX:
  ssl_certificate /path/to/{$domain}.fullchain.crt;
  ssl_certificate_key /path/to/{$domain}.key;

IIS:
  Import the .pfx file using IIS Manager or MMC

TOMCAT:
  Configure server.xml to use the .jks file

---
Powered by HVN GROUP - SSL Admin
https://hvn.vn
README;
    }

    /**
     * Format date for display
     * 
     * @param string $date Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public static function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        try {
            $dt = new \DateTime($date);
            return $dt->format($format);
        } catch (Exception $e) {
            return $date;
        }
    }

    /**
     * Get country list from JSON file
     * 
     * @return array Countries
     */
    public static function getCountryList()
    {
        $countryFile = CONF_PATH . 'country.json';
        
        if (!file_exists($countryFile)) {
            return [];
        }
        
        $content = file_get_contents($countryFile);
        $countries = json_decode($content, true);
        
        return is_array($countries) ? $countries : [];
    }

    /**
     * Get DCV method display name
     * 
     * @param string $method DCV method code
     * @return string Display name
     */
    public static function getDcvMethodName($method)
    {
        $methods = [
            'EMAIL' => 'Email Validation',
            'HTTP_CSR_HASH' => 'HTTP File Validation',
            'HTTPS_CSR_HASH' => 'HTTPS File Validation',
            'CNAME_CSR_HASH' => 'DNS CNAME Validation',
            'DNS_CSR_HASH' => 'DNS TXT Validation',
        ];
        
        return $methods[$method] ?? $method;
    }

    /**
     * Sanitize filename
     * 
     * @param string $filename Filename
     * @return string Safe filename
     */
    public static function sanitizeFilename($filename)
    {
        // Remove any path components
        $filename = basename($filename);
        
        // Replace special characters
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        
        return $filename;
    }

    /**
     * Get validation type display name
     * 
     * @param string $type Validation type code
     * @return string Display name
     */
    public static function getValidationTypeName($type)
    {
        $types = [
            'dv' => 'Domain Validation (DV)',
            'ov' => 'Organization Validation (OV)',
            'ev' => 'Extended Validation (EV)',
        ];
        
        return $types[strtolower($type)] ?? $type;
    }

    /**
     * Check if certificate is expiring soon
     * 
     * @param string $endDate Expiry date
     * @param int $days Days threshold
     * @return bool
     */
    public static function isExpiringSoon($endDate, $days = 30)
    {
        if (empty($endDate)) {
            return false;
        }
        
        try {
            $expiry = new \DateTime($endDate);
            $now = new \DateTime();
            $threshold = new \DateTime("+{$days} days");
            
            return $expiry <= $threshold && $expiry > $now;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if certificate is expired
     * 
     * @param string $endDate Expiry date
     * @return bool
     */
    public static function isExpired($endDate)
    {
        if (empty($endDate)) {
            return false;
        }
        
        try {
            $expiry = new \DateTime($endDate);
            $now = new \DateTime();
            
            return $expiry < $now;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Calculate days until expiry
     * 
     * @param string $endDate Expiry date
     * @return int|null Days or null if invalid
     */
    public static function daysUntilExpiry($endDate)
    {
        if (empty($endDate)) {
            return null;
        }
        
        try {
            $expiry = new \DateTime($endDate);
            $now = new \DateTime();
            $diff = $now->diff($expiry);
            
            return $diff->invert ? -$diff->days : $diff->days;
        } catch (Exception $e) {
            return null;
        }
    }
}