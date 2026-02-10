<?php
/**
 * NicSRS SSL Module - Certificate Functions
 * Utility functions for certificate operations
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;
use ZipArchive;

class CertificateFunc
{
    /**
     * Cached name-to-code mapping
     * @var array|null
     */
    private static $nameToCodeCache = null;
    
    /**
     * Cached code-to-name mapping  
     * @var array|null
     */
    private static $codeToNameCache = null;

    /**
     * Cached full product data from database
     * @var array|null
     */
    private static $productCache = null;

    // ==========================================
    // Name <-> Code Conversion Functions (NEW)
    // ==========================================

    /**
     * Get product code from product name
     * Searches: 1) Database cache (mod_nicsrs_products), 2) Static CERT_TYPES
     * 
     * @param string|null $name Product name (e.g., "Sectigo PositiveSSL DV")
     * @return string|null Product code (e.g., "sectigo-positivessl-dv") or null
     */
    public static function getCertCodeByName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        // If input already looks like a code (contains dashes, no spaces)
        if (strpos($name, '-') !== false && strpos($name, ' ') === false) {
            return $name;
        }

        // Build cache if needed
        if (self::$nameToCodeCache === null) {
            self::buildNameCodeCache();
        }

        // Exact match
        if (isset(self::$nameToCodeCache[$name])) {
            return self::$nameToCodeCache[$name];
        }

        // Case-insensitive match
        $nameLower = strtolower(trim($name));
        foreach (self::$nameToCodeCache as $cachedName => $code) {
            if (strtolower(trim($cachedName)) === $nameLower) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get product name from product code
     * 
     * @param string|null $code Product code
     * @return string|null Product name or null
     */
    public static function getCertNameByCode(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }

        // Build cache if needed
        if (self::$codeToNameCache === null) {
            self::buildNameCodeCache();
        }

        return self::$codeToNameCache[$code] ?? null;
    }

    /**
     * Normalize certificate identifier - always returns code
     * Accepts either name or code as input
     * 
     * @param string|null $identifier Product name or code
     * @return string Product code (or original if not found)
     */
    public static function normalizeToCode(?string $identifier): string
    {
        if (empty($identifier)) {
            return '';
        }

        $identifier = trim($identifier);

        // If it's already a code (contains dash, no space)
        if (strpos($identifier, '-') !== false && strpos($identifier, ' ') === false) {
            return $identifier;
        }

        // Try to convert from name
        $code = self::getCertCodeByName($identifier);
        return $code ?: $identifier;
    }

    /**
     * Get full product data from database by code or name
     * 
     * @param string $identifier Product code or name
     * @return object|null Product data or null
     */
    public static function getProductFromDatabase(string $identifier): ?object
    {
        if (empty($identifier)) {
            return null;
        }

        try {
            if (!Capsule::schema()->hasTable('mod_nicsrs_products')) {
                return null;
            }

            return Capsule::table('mod_nicsrs_products')
                ->where('product_code', $identifier)
                ->orWhere('product_name', $identifier)
                ->first();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Build name <-> code cache from database and static definitions
     */
    private static function buildNameCodeCache(): void
    {
        self::$nameToCodeCache = [];
        self::$codeToNameCache = [];

        // 1. Load from database (mod_nicsrs_products) - higher priority
        try {
            if (Capsule::schema()->hasTable('mod_nicsrs_products')) {
                $products = Capsule::table('mod_nicsrs_products')
                    ->select('product_code', 'product_name')
                    ->get();

                foreach ($products as $product) {
                    if (!empty($product->product_name) && !empty($product->product_code)) {
                        self::$nameToCodeCache[$product->product_name] = $product->product_code;
                        self::$codeToNameCache[$product->product_code] = $product->product_name;
                    }
                }
            }
        } catch (Exception $e) {
            // Table doesn't exist, continue with static
        }

        // 2. Load from static CERT_TYPES (fallback/supplement)
        if (defined('CERT_TYPES') && is_array(CERT_TYPES)) {
            foreach (CERT_TYPES as $code => $attrs) {
                if (!empty($attrs['name'])) {
                    // Don't override database values
                    if (!isset(self::$nameToCodeCache[$attrs['name']])) {
                        self::$nameToCodeCache[$attrs['name']] = $code;
                    }
                    if (!isset(self::$codeToNameCache[$code])) {
                        self::$codeToNameCache[$code] = $attrs['name'];
                    }
                }
            }
        }
    }

    /**
     * Clear the cache (useful after product sync)
     */
    public static function clearCache(): void
    {
        self::$nameToCodeCache = null;
        self::$codeToNameCache = null;
        self::$productCache = null;
    }

    // ==========================================
    // Certificate Attributes Functions
    // ==========================================

    /**
     * Get certificate type attributes
     * 
     * @param string|null $certType Certificate code (or name - will be normalized)
     * @param string|null $key Specific attribute key to return
     * @return mixed
     */
    public static function getCertAttributes(?string $certType = null, ?string $key = null)
    {
        $types = defined('CERT_TYPES') ? CERT_TYPES : [];

        if ($certType === null) {
            return $types;
        }

        // Normalize to code if name was passed
        $certCode = self::normalizeToCode($certType);

        // Try static CERT_TYPES first
        if (isset($types[$certCode])) {
            if ($key === null) {
                return $types[$certCode];
            }
            return $types[$certCode][$key] ?? null;
        }

        // Fallback: Try to get from database
        $product = self::getProductFromDatabase($certType);
        if ($product) {
            $attrs = [
                'name' => $product->product_name,
                'code' => $product->product_code,
                'vendor' => $product->vendor ?? 'Unknown',
                'sslType' => 'website_ssl',
                'sslValidationType' => $product->validation_type ?? 'dv',
                'isMultiDomain' => (bool) ($product->support_san ?? false),
                'isWildcard' => (bool) ($product->support_wildcard ?? false),
                'supportNormal' => true,
                'supportIp' => false,
                'supportWild' => (bool) ($product->support_wildcard ?? false),
                'supportHttps' => true,
                'maxDomains' => (int) ($product->max_domains ?? 1),
            ];

            if ($key === null) {
                return $attrs;
            }
            return $attrs[$key] ?? null;
        }

        return null;
    }

    /**
     * Get dropdown options string for module config
     * Returns PRODUCT CODES (not names) for proper storage
     * 
     * @return string Comma-separated product codes
     */
    public static function getCertAttributesDropdown(): string
    {
        // Try database first
        try {
            if (Capsule::schema()->hasTable('mod_nicsrs_products')) {
                $codes = Capsule::table('mod_nicsrs_products')
                    ->where('is_active', 1)
                    ->orderBy('vendor')
                    ->orderBy('product_name')
                    ->pluck('product_code')
                    ->toArray();

                if (!empty($codes)) {
                    return implode(',', $codes);
                }
            }
        } catch (Exception $e) {
            // Continue to static fallback
        }

        // Fallback to static CERT_TYPES
        if (defined('CERT_TYPES') && is_array(CERT_TYPES)) {
            return implode(',', array_keys(CERT_TYPES));
        }

        return '';
    }

    // ==========================================
    // CSR Functions
    // ==========================================

    /**
     * Generate CSR and Private Key
     * 
     * @param array $data CSR data (cn/commonName, org, city, state, country, email)
     * @return array ['csr' => string, 'privateKey' => string, 'dn' => array]
     * @throws Exception
     */
    public static function generateCSR(array $data): array
    {
        $dn = [];

        // Common Name (required)
        if (!empty($data['cn'])) {
            $dn['commonName'] = $data['cn'];
        } elseif (!empty($data['commonName'])) {
            $dn['commonName'] = $data['commonName'];
        }

        // Organization
        if (!empty($data['org'])) {
            $dn['organizationName'] = $data['org'];
        } elseif (!empty($data['organization'])) {
            $dn['organizationName'] = $data['organization'];
        }

        // Organizational Unit
        if (!empty($data['ou'])) {
            $dn['organizationalUnitName'] = $data['ou'];
        } elseif (!empty($data['organizationalUnit'])) {
            $dn['organizationalUnitName'] = $data['organizationalUnit'];
        }

        // Locality (City)
        if (!empty($data['city'])) {
            $dn['localityName'] = $data['city'];
        } elseif (!empty($data['locality'])) {
            $dn['localityName'] = $data['locality'];
        }

        // State/Province
        if (!empty($data['state'])) {
            $dn['stateOrProvinceName'] = $data['state'];
        }

        // Country (2-letter code)
        if (!empty($data['country'])) {
            $dn['countryName'] = strtoupper(substr($data['country'], 0, 2));
        }

        // Email
        if (!empty($data['email'])) {
            $dn['emailAddress'] = $data['email'];
        }

        // Remove empty values
        $dn = array_filter($dn, function($v) {
            return !empty($v);
        });

        // Validate required field
        if (empty($dn['commonName'])) {
            throw new Exception('Common Name (domain) is required for CSR generation');
        }

        // Configuration
        $config = defined('DEFAULT_CSR_CONFIG') ? DEFAULT_CSR_CONFIG : [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        // Generate private key
        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new Exception('Failed to generate private key: ' . openssl_error_string());
        }

        // Generate CSR
        $csr = openssl_csr_new($dn, $privateKey, $config);
        if (!$csr) {
            throw new Exception('Failed to generate CSR: ' . openssl_error_string());
        }

        // Export CSR
        openssl_csr_export($csr, $csrOut);

        // Export private key
        openssl_pkey_export($privateKey, $privateKeyOut);

        return [
            'csr' => $csrOut,
            'privateKey' => $privateKeyOut,
            'dn' => $dn,
        ];
    }

    /**
     * Decode and validate CSR
     * 
     * @param string $csr CSR content
     * @return array Decoded CSR data
     * @throws Exception
     */
    public static function decodeCSR(string $csr): array
    {
        $csr = trim($csr);

        // Validate CSR format
        if (strpos($csr, '-----BEGIN CERTIFICATE REQUEST-----') === false &&
            strpos($csr, '-----BEGIN NEW CERTIFICATE REQUEST-----') === false) {
            throw new Exception('Invalid CSR format');
        }

        // Parse CSR
        $csrResource = openssl_csr_get_subject($csr, false);

        if (!$csrResource) {
            throw new Exception('Failed to parse CSR: ' . openssl_error_string());
        }

        // Get public key info
        $pubKeyResource = openssl_csr_get_public_key($csr);
        $pubKeyDetails = openssl_pkey_get_details($pubKeyResource);

        $result = [
            'valid' => true,
            'commonName' => $csrResource['CN'] ?? '',
            'organization' => $csrResource['O'] ?? '',
            'organizationalUnit' => $csrResource['OU'] ?? '',
            'locality' => $csrResource['L'] ?? '',
            'state' => $csrResource['ST'] ?? '',
            'country' => $csrResource['C'] ?? '',
            'email' => $csrResource['emailAddress'] ?? '',
            'keySize' => $pubKeyDetails['bits'] ?? 0,
            'keyType' => self::getKeyTypeName($pubKeyDetails['type'] ?? 0),
        ];

        // Extract domain from common name
        $result['domain'] = $result['commonName'];

        return $result;
    }

    /**
     * Get key type name
     * 
     * @param int $type OpenSSL key type constant
     * @return string Key type name
     */
    private static function getKeyTypeName(int $type): string
    {
        $types = [
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH => 'DH',
            OPENSSL_KEYTYPE_EC => 'EC',
        ];

        return $types[$type] ?? 'Unknown';
    }

    // ==========================================
    // Certificate Download Functions
    // ==========================================

    /**
     * Create certificate download ZIP
     * 
     * @param array $certData Certificate data
     * @param string $format Download format (apache, nginx, iis, tomcat, all)
     * @return string Base64 encoded ZIP content
     * @throws Exception
     */
    public static function createCertificateZip(array $certData, string $format = 'all'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ssl_cert_');
        $zip = new ZipArchive();

        if ($zip->open($tempFile, ZipArchive::CREATE) !== true) {
            throw new Exception('Failed to create ZIP file');
        }

        $domain = $certData['domain'] ?? 'certificate';
        $safeDomain = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain);

        // Get certificate data
        $certificate = $certData['certificate'] ?? '';
        $caCertificate = $certData['caCertificate'] ?? '';
        $privateKey = $certData['privateKey'] ?? '';
        $pkcs12 = $certData['pkcs12'] ?? '';
        $jks = $certData['jks'] ?? '';
        $pkcsPass = $certData['pkcsPass'] ?? '';
        $jksPass = $certData['jksPass'] ?? '';

        switch ($format) {
            case 'apache':
                $zip->addFromString("{$safeDomain}.crt", $certificate);
                $zip->addFromString("{$safeDomain}.ca-bundle", $caCertificate);
                if ($privateKey) {
                    $zip->addFromString("{$safeDomain}.key", $privateKey);
                }
                break;

            case 'nginx':
                $pem = $certificate . "\n" . $caCertificate;
                $zip->addFromString("{$safeDomain}.pem", $pem);
                if ($privateKey) {
                    $zip->addFromString("{$safeDomain}.key", $privateKey);
                }
                break;

            case 'iis':
                if ($pkcs12) {
                    $zip->addFromString("{$safeDomain}.p12", base64_decode($pkcs12));
                    if ($pkcsPass) {
                        $zip->addFromString("password.txt", "PKCS12 Password: {$pkcsPass}");
                    }
                }
                break;

            case 'tomcat':
                if ($jks) {
                    $zip->addFromString("{$safeDomain}.jks", base64_decode($jks));
                    if ($jksPass) {
                        $zip->addFromString("password.txt", "JKS Password: {$jksPass}");
                    }
                }
                break;

            case 'all':
            default:
                // Apache
                $zip->addFromString("apache/{$safeDomain}.crt", $certificate);
                $zip->addFromString("apache/{$safeDomain}.ca-bundle", $caCertificate);
                if ($privateKey) {
                    $zip->addFromString("apache/{$safeDomain}.key", $privateKey);
                }

                // Nginx
                $pem = $certificate . "\n" . $caCertificate;
                $zip->addFromString("nginx/{$safeDomain}.pem", $pem);
                if ($privateKey) {
                    $zip->addFromString("nginx/{$safeDomain}.key", $privateKey);
                }

                // IIS
                if ($pkcs12) {
                    $zip->addFromString("iis/{$safeDomain}.p12", base64_decode($pkcs12));
                }

                // Tomcat
                if ($jks) {
                    $zip->addFromString("tomcat/{$safeDomain}.jks", base64_decode($jks));
                }

                // Passwords
                $passwords = [];
                if ($pkcsPass) {
                    $passwords[] = "PKCS12/IIS Password: {$pkcsPass}";
                }
                if ($jksPass) {
                    $passwords[] = "JKS/Tomcat Password: {$jksPass}";
                }
                if (!empty($passwords)) {
                    $zip->addFromString("passwords.txt", implode("\n", $passwords));
                }

                // Readme
                $readme = self::generateReadme($safeDomain);
                $zip->addFromString("README.txt", $readme);
                break;
        }

        $zip->close();

        // Read and return base64 encoded content
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return base64_encode($content);
    }

    /**
     * Generate README content for certificate ZIP
     * 
     * @param string $domain Domain name
     * @return string README content
     */
    private static function generateReadme(string $domain): string
    {
        $date = date('Y-m-d H:i:s');
        return <<<README
SSL Certificate Installation Guide
===================================

Domain: {$domain}
Generated: {$date}

This archive contains your SSL certificate in multiple formats.

APACHE/STANDARD FORMAT (apache/)
--------------------------------
- {$domain}.crt - Your SSL Certificate
- {$domain}.ca-bundle - CA Bundle (Intermediate Certificates)  
- {$domain}.key - Private Key

Installation:
1. Upload files to your server
2. Update Apache configuration with paths to these files
3. Restart Apache

NGINX FORMAT (nginx/)
---------------------
- {$domain}.pem - Combined certificate (cert + CA bundle)
- {$domain}.key - Private Key

Installation:
1. Upload files to your server
2. Update Nginx configuration with paths to these files
3. Restart Nginx

IIS FORMAT (iis/)
-----------------
- {$domain}.p12 - PKCS#12 file containing certificate and key

Installation:
1. Import .p12 file via IIS Manager or MMC
2. Enter password from passwords.txt when prompted
3. Bind certificate to your website

TOMCAT FORMAT (tomcat/)
-----------------------
- {$domain}.jks - Java KeyStore file

Installation:
1. Copy .jks file to your Tomcat conf directory
2. Update server.xml with keystore path and password
3. Restart Tomcat

IMPORTANT: Keep your private key secure and never share it!

Support: https://hvn.vn
README;
    }

    // ==========================================
    // Domain Validation Functions
    // ==========================================

    /**
     * Validate domain format
     * 
     * @param string $domain Domain to validate
     * @return bool True if valid
     */
    public static function validateDomain(string $domain): bool
    {
        // Remove wildcard prefix if present
        $domain = preg_replace('/^\*\./', '', $domain);

        // Check for valid domain format
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }

        // Check for IP address (for IP-enabled certificates)
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is wildcard
     * 
     * @param string $domain Domain to check
     * @return bool True if wildcard
     */
    public static function isWildcardDomain(string $domain): bool
    {
        return strpos($domain, '*.') === 0;
    }

    /**
     * Get base domain from wildcard
     * 
     * @param string $domain Domain (may include wildcard)
     * @return string Base domain without wildcard
     */
    public static function getBaseDomain(string $domain): string
    {
        return preg_replace('/^\*\./', '', $domain);
    }

    /**
     * Get DCV email options for a domain
     * 
     * @param string $domain Domain name
     * @return array List of valid DCV email addresses
     */
    public static function getDCVEmailOptions(string $domain): array
    {
        $baseDomain = self::getBaseDomain($domain);

        $prefixes = ['admin', 'administrator', 'hostmaster', 'webmaster', 'postmaster'];
        $emails = [];

        foreach ($prefixes as $prefix) {
            $emails[] = "{$prefix}@{$baseDomain}";
        }

        return $emails;
    }

    // ==========================================
    // Date/Status Utility Functions
    // ==========================================

    /**
     * Format date for display
     * 
     * @param string|null $date Date string
     * @param string $format Output format
     * @return string Formatted date or 'N/A'
     */
    public static function formatDate(?string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return 'N/A';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : 'N/A';
    }

    /**
     * Calculate days until expiry
     * 
     * @param string|null $expiryDate Expiry date string
     * @return int|null Days until expiry or null
     */
    public static function getDaysUntilExpiry(?string $expiryDate): ?int
    {
        if (empty($expiryDate)) {
            return null;
        }

        $expiry = strtotime($expiryDate);
        $now = time();

        if (!$expiry) {
            return null;
        }

        return (int) floor(($expiry - $now) / 86400);
    }

    /**
     * Get status badge class
     * 
     * @param string $status Order status
     * @return string CSS class name
     */
    public static function getStatusClass(string $status): string
    {
        $classes = defined('STATUS_CLASSES') ? STATUS_CLASSES : [
            'Awaiting Configuration' => 'warning',
            'Draft' => 'info',
            'Pending' => 'processing',
            'Complete' => 'success',
            'Issued' => 'success',
            'Cancelled' => 'default',
            'Revoked' => 'error',
            'Expired' => 'error',
            'Suspended' => 'warning',
            'Reissue' => 'processing',
        ];

        return $classes[$status] ?? 'default';
    }

    // ==========================================
    // Language Functions
    // ==========================================

    /**
     * Load language file
     * 
     * @param string $language Language name
     * @return array Language strings
     */
    public static function loadLanguage(string $language = 'english'): array
    {
        $langPath = defined('NICSRS_SSL_PATH') 
            ? NICSRS_SSL_PATH . 'lang' . DIRECTORY_SEPARATOR
            : __DIR__ . '/../../lang/';
            
        $langFile = $langPath . strtolower($language) . '.php';

        // Fallback to English
        if (!file_exists($langFile)) {
            $langFile = $langPath . 'english.php';
        }

        if (!file_exists($langFile)) {
            return [];
        }

        $_LANG = [];
        include $langFile;

        return $_LANG;
    }

    /**
     * Get client's preferred language
     * 
     * @param int $userId User ID
     * @return string Language name
     */
    public static function getClientLanguage(int $userId): string
    {
        if (!$userId) {
            return 'english';
        }
        
        try {
            $client = Capsule::table('tblclients')
                ->where('id', $userId)
                ->first();

            return $client->language ?? 'english';
        } catch (Exception $e) {
            return 'english';
        }
    }
    
    // ==========================================
    // Database Functions
    // ==========================================

    /**
     * Create orders table if not exist (backward compatibility)
     */
    public static function createOrdersTableIfNotExist(): void
    {
        if (class_exists('\\nicsrsSSL\\OrderRepository')) {
            OrderRepository::ensureTableExists();
        }
    }
}