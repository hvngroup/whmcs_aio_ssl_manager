<?php
/**
 * NicSRS SSL Module - Template Helper
 * Handles template rendering and variable preparation
 * 
 * @package    nicsrs_ssl
 * @version    2.0.1
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;

class TemplateHelper
{
    /**
     * Get base template variables (common to all templates)
     */
    public static function getBaseVars(array $params): array
    {
        // Load language
        $language = self::loadLanguage($params);

        $webRoot = $GLOBALS['CONFIG']['SystemURL'] ?? '';
        if (empty($webRoot)) {
            try {
                $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
            } catch (\Exception $e) {
                try {
                    if (class_exists('\WHMCS\Config\Setting')) {
                        $webRoot = \WHMCS\Config\Setting::getValue('SystemURL') ?? '';
                    }
                } catch (\Exception $e2) {
                    $webRoot = '';
                }
            }
        }
        $webRoot = rtrim($webRoot, '/');

        return [
            '_LANG' => $language,
            '_LANG_JSON' => json_encode($language),
            'WEB_ROOT' => $webRoot,
            'serviceid' => $params['serviceid'] ?? 0,
            'userid' => $params['userid'] ?? 0,
            'domain' => $params['domain'] ?? '',
            'moduleVersion' => '2.1.0',
        ];
    }

    /**
     * Load language file
     */
    private static function loadLanguage(array $params): array
    {
        $lang = '';
        
        // Priority 1: WHMCS module params (most reliable for server modules)
        if (!empty($params['clientsdetails']['language'])) {
            $lang = $params['clientsdetails']['language'];
        }
        
        // Priority 2: Session language (set when user clicks language dropdown)
        if (empty($lang) && !empty($_SESSION['Language'])) {
            $lang = $_SESSION['Language'];
        }
        
        // Priority 3: Lookup from client's profile in database
        if (empty($lang) && !empty($params['userid'])) {
            try {
                $client = Capsule::table('tblclients')
                    ->where('id', $params['userid'])
                    ->value('language');
                
                if (!empty($client)) {
                    $lang = $client;
                }
            } catch (\Exception $e) {
                // Ignore DB errors, will fallback
            }
        }
        
        // Priority 4: WHMCS system default language
        if (empty($lang)) {
            try {
                $lang = \WHMCS\Config\Setting::getValue('Language') ?? '';
            } catch (\Exception $e) {
                $lang = $GLOBALS['CONFIG']['Language'] ?? '';
            }
        }
        
        // Priority 5: Final fallback
        if (empty($lang)) {
            $lang = 'english';
        }
        
        // Normalize language name
        $lang = strtolower(trim($lang));
        
        // Map common language name variants to file names
        $langMap = [
            'vietnamese'  => 'vietnamese',
            'tieng_viet'  => 'vietnamese',
            'vi'          => 'vietnamese',
            'chinese'     => 'chinese',
            'zh'          => 'chinese',
            'zh-cn'       => 'chinese-cn',
            'chinese-cn'  => 'chinese-cn',
            'english'     => 'english',
            'en'          => 'english',
        ];
        
        if (isset($langMap[$lang])) {
            $lang = $langMap[$lang];
        }
        
        // Build language file path
        $langFile = NICSRS_SSL_PATH . 'lang/' . $lang . '.php';
        
        // Fallback to English if language file not found
        if (!file_exists($langFile)) {
            $langFile = NICSRS_SSL_PATH . 'lang/english.php';
        }
        
        $_LANG = [];
        if (file_exists($langFile)) {
            include $langFile;
        }
        
        return $_LANG;
    }
    
    /**
     * Get client information for pre-filling forms
     */
    public static function getClientInfo(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        
        try {
            $client = Capsule::table('tblclients')
                ->where('id', $userId)
                ->first();
            
            if (!$client) {
                return [];
            }
            
            return [
                'firstname' => $client->firstname ?? '',
                'lastname' => $client->lastname ?? '',
                'companyname' => $client->companyname ?? '',
                'email' => $client->email ?? '',
                'phonenumber' => $client->phonenumber ?? '',
                'address1' => $client->address1 ?? '',
                'address2' => $client->address2 ?? '',
                'city' => $client->city ?? '',
                'state' => $client->state ?? '',
                'postcode' => $client->postcode ?? '',
                'country' => $client->country ?? 'VN',
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Load countries from JSON file
     */
    public static function getCountries(): array
    {
        $countryFile = NICSRS_SSL_PATH . 'src/config/country.json';
        
        if (!file_exists($countryFile)) {
            // Return basic fallback
            return [
                ['code' => 'VN', 'name' => 'Vietnam'],
                ['code' => 'US', 'name' => 'United States'],
                ['code' => 'GB', 'name' => 'United Kingdom'],
                ['code' => 'SG', 'name' => 'Singapore'],
                ['code' => 'JP', 'name' => 'Japan'],
                ['code' => 'AU', 'name' => 'Australia'],
                ['code' => 'DE', 'name' => 'Germany'],
                ['code' => 'FR', 'name' => 'France'],
                ['code' => 'CN', 'name' => 'China'],
                ['code' => 'KR', 'name' => 'South Korea'],
            ];
        }
        
        $content = file_get_contents($countryFile);
        $countries = json_decode($content, true);
        
        if (!is_array($countries)) {
            return [];
        }
        
        // Ensure each country has 'code' and 'name'
        $result = [];
        foreach ($countries as $country) {
            if (isset($country['code']) && isset($country['name'])) {
                $result[] = [
                    'code' => $country['code'],
                    'name' => $country['name'],
                ];
            }
        }
        
        return $result;
    }

    /**
     * Render apply certificate page
     * FIXED: Properly handles isRenew/originalfromOthers
     */
    public static function applyCert(array $params, object $order, array $cert): array
    {
        $baseVars = self::getBaseVars($params);
        
        // Decode configdata properly
        $configdata = [];
        if (!empty($order->configdata)) {
            $configdata = json_decode($order->configdata, true);
            if (!is_array($configdata)) {
                $configdata = [];
            }
        }

        // FIXED: Ensure isRenew and originalfromOthers are consistent
        if (isset($configdata['isRenew']) && !isset($configdata['originalfromOthers'])) {
            $configdata['originalfromOthers'] = $configdata['isRenew'];
        } elseif (isset($configdata['originalfromOthers']) && !isset($configdata['isRenew'])) {
            $configdata['isRenew'] = $configdata['originalfromOthers'];
        }

        // DEBUG: Log what we're passing
        logModuleCall('nicsrs_ssl', 'TemplateHelper_applyCert', [
            'order_id' => $order->id ?? 'N/A',
            'order_status' => $order->status ?? 'N/A',
            'configdata_raw_length' => strlen($order->configdata ?? ''),
            'domainCount' => count($configdata['domainInfo'] ?? []),
            'hasAdmin' => !empty($configdata['Administrator']),
            'isRenew' => $configdata['isRenew'] ?? 'not set',
            'originalfromOthers' => $configdata['originalfromOthers'] ?? 'not set',
        ], 'Building template vars');

        // Load countries
        $countries = self::getCountries();

        // Pre-fill client info
        $client = self::getClientInfo($params['userid'] ?? 0);

        // Calculate max domains
        $domainsFromOptions = self::getDomainCountFromOptions($params['serviceid']);
        $maxDomains = ($cert['maxDomains'] ?? 1) + $domainsFromOptions;

        return [
            'templatefile' => 'applycert',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'sslType' => $cert['sslType'] ?? 'website_ssl',
                'sslValidationType' => $cert['sslValidationType'] ?? 'dv',
                'isMultiDomain' => $cert['isMultiDomain'] ?? false,
                'isWildcard' => $cert['isWildcard'] ?? false,
                'maxDomains' => $maxDomains,
                'requiresOrganization' => in_array($cert['sslValidationType'] ?? 'dv', ['ov', 'ev']),
                'supportOptions' => [
                    'supportNormal' => $cert['supportNormal'] ?? true,
                    'supportIp' => $cert['supportIp'] ?? false,
                    'supportWild' => $cert['supportWild'] ?? false,
                    'supportHttps' => $cert['supportHttps'] ?? true,
                ],
                'countries' => $countries,
                'client' => $client,
            ]),
        ];
    }

    /**
     * Get additional domain count from configurable options
     */
    private static function getDomainCountFromOptions(int $serviceId): int
    {
        try {
            $option = Capsule::table('tblhostingconfigoptions as hco')
                ->join('tblproductconfigoptions as pco', 'hco.configid', '=', 'pco.id')
                ->join('tblproductconfigoptionssub as pcosub', 'hco.optionid', '=', 'pcosub.id')
                ->where('hco.relid', $serviceId)
                ->where('pco.optionname', 'LIKE', '%SAN%')
                ->first();

            if ($option && isset($option->optionname)) {
                // Extract number from option name like "5 SANs"
                if (preg_match('/(\d+)/', $option->optionname, $matches)) {
                    return (int) $matches[1];
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        return 0;
    }

    /**
     * Render pending/message page (waiting for validation)
     */
    public static function message(array $params, object $order, array $cert): array
    {
        $baseVars = self::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];
        $applyReturn = $configdata['applyReturn'] ?? [];

        return [
            'templatefile' => 'message',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'sslValidationType' => $cert['sslValidationType'] ?? 'dv',
                'domainInfo' => $configdata['domainInfo'] ?? [],
                'applyReturn' => $applyReturn,
                'certId' => $order->remoteid ?? '',
                'vendorId' => $applyReturn['vendorId'] ?? '',
                // DCV Info
                'dcvFileName' => $applyReturn['DCVfileName'] ?? '',
                'dcvFileContent' => $applyReturn['DCVfileContent'] ?? '',
                'dcvDnsHost' => $applyReturn['DCVdnsHost'] ?? '',
                'dcvDnsValue' => $applyReturn['DCVdnsValue'] ?? '',
                'dcvDnsType' => $applyReturn['DCVdnsType'] ?? '',
            ]),
        ];
    }

    /**
     * Render pending page with DCV status (alias for message)
     */
    public static function pending(array $params, object $order, array $cert, array $collectData = []): array
    {
        $result = self::message($params, $order, $cert);
        
        // Merge collect data if provided
        if (!empty($collectData)) {
            $result['vars']['collectData'] = $collectData;
            $result['vars']['dcvList'] = $collectData['dcvList'] ?? [];
        }
        
        return $result;
    }

    /**
     * Render complete page (certificate issued)
     */
    public static function complete(array $params, object $order, array $cert, array $collectData = []): array
    {
        $baseVars = self::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];
        $applyReturn = $configdata['applyReturn'] ?? [];

        // Merge collect data if provided
        if (!empty($collectData)) {
            $applyReturn = array_merge($applyReturn, $collectData);
        }

        // Check if certificate content is available
        $hasCertificate = !empty($applyReturn['certificate']);
        $hasPrivateKey = !empty($configdata['privateKey']);

        return [
            'templatefile' => 'complete',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'sslValidationType' => $cert['sslValidationType'] ?? 'dv',
                'status' => $order->status,
                'certId' => $order->remoteid ?? '',
                'vendorId' => $applyReturn['vendorId'] ?? '',
                'domainInfo' => $configdata['domainInfo'] ?? [],
                // Certificate data
                'hasCertificate' => $hasCertificate,
                'hasPrivateKey' => $hasPrivateKey,
                'certificate' => $applyReturn['certificate'] ?? '',
                'caCertificate' => $applyReturn['caCertificate'] ?? '',
                'beginDate' => $applyReturn['beginDate'] ?? '',
                'endDate' => $applyReturn['endDate'] ?? '',
                // Download options
                'canDownload' => $hasCertificate,
                'canDownloadKey' => $hasPrivateKey,
            ]),
        ];
    }

    /**
     * Render cancelled/revoked page
     */
    public static function cancelled(array $params, object $order, array $cert): array
    {
        $baseVars = self::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];

        return [
            'templatefile' => 'cancelled',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'status' => $order->status,
                'certId' => $order->remoteid ?? '',
                'canRenew' => true,
            ]),
        ];
    }

    /**
     * Render reissue page
     */
    public static function reissue(array $params, object $order, array $cert): array
    {
        $baseVars = self::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];

        // Load countries
        $countries = self::getCountries();

        // Load client info for pre-filling (FIXED: was missing)
        $clientInfo = self::getClientInfo($params['userid'] ?? 0);

        // Get original domains
        $originalDomains = [];
        if (!empty($configdata['domainInfo'])) {
            foreach ($configdata['domainInfo'] as $domain) {
                $originalDomains[] = $domain['domainName'] ?? '';
            }
        }

        // Calculate max domains
        $domainsFromOptions = self::getDomainCountFromOptions($params['serviceid']);
        $maxDomains = ($cert['maxDomains'] ?? 1) + $domainsFromOptions;

        // Determine Certificate ID with fallbacks (FIXED)
        $certId = '';
        if (!empty($order->remoteid)) {
            $certId = $order->remoteid;
        } elseif (!empty($configdata['applyReturn']['vendorCertId'])) {
            $certId = $configdata['applyReturn']['vendorCertId'];
        } elseif (!empty($configdata['applyReturn']['vendorId'])) {
            $certId = $configdata['applyReturn']['vendorId'];
        }

        return [
            'templatefile' => 'reissue',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'certId' => $certId,
                'sslType' => $cert['sslType'] ?? 'website_ssl',
                'sslValidationType' => $cert['sslValidationType'] ?? 'dv',
                'isMultiDomain' => $cert['isMultiDomain'] ?? false,
                'maxDomains' => $maxDomains,
                'requiresOrganization' => in_array($cert['sslValidationType'] ?? 'dv', ['ov', 'ev']),
                'supportOptions' => [
                    'supportNormal' => $cert['supportNormal'] ?? true,
                    'supportIp' => $cert['supportIp'] ?? false,
                    'supportWild' => $cert['supportWild'] ?? false,
                    'supportHttps' => $cert['supportHttps'] ?? true,
                ],
                'countries' => $countries,
                // Client info for pre-filling (FIXED: was missing)
                'clientsdetails' => $clientInfo,
                // Original domains for reference
                'originalDomains' => $originalDomains,
                'replaceTimes' => $configdata['replaceTimes'] ?? 0,
            ]),
        ];
    }

    /**
     * Render manage page
     */
    public static function manage(array $params, object $order, array $cert): array
    {
        $baseVars = self::getBaseVars($params);
        $configdata = json_decode($order->configdata, true) ?: [];
        $applyReturn = $configdata['applyReturn'] ?? [];

        return [
            'templatefile' => 'manage',
            'vars' => array_merge($baseVars, [
                'order' => $order,
                'configData' => $configdata,
                'cert' => $cert,
                'productCode' => $cert['name'] ?? '',
                'status' => $order->status,
                'certId' => $order->remoteid ?? '',
                'domainInfo' => $configdata['domainInfo'] ?? [],
                'beginDate' => $applyReturn['beginDate'] ?? '',
                'endDate' => $applyReturn['endDate'] ?? '',
            ]),
        ];
    }

    /**
     * Render error page
     */
    public static function error(array $params, string $message): array
    {
        $baseVars = self::getBaseVars($params);

        if (empty($baseVars['WEB_ROOT'])) {
            $baseVars['WEB_ROOT'] = rtrim($GLOBALS['CONFIG']['SystemURL'] 
                ?? \WHMCS\Application\Support\Facades\Config::get('SystemURL') 
                ?? '', '/');
        }

        return [
            'templatefile' => 'error',
            'vars' => array_merge($baseVars, [
                'errorMessage' => $message,
                'errorTimestamp' => date('Y-m-d H:i:s'),
            ]),
        ];
    }

    /**
     * Determine which template to render based on order status
     */
    public static function getTemplateForStatus(array $params, object $order, array $cert): array
    {
        $status = strtolower($order->status ?? '');

        // DEBUG
        logModuleCall('nicsrs_ssl', 'getTemplateForStatus', [
            'status' => $status,
            'order_id' => $order->id ?? 'N/A',
        ], 'Determining template');

        switch ($status) {
            case SSL_STATUS_AWAITING:
            case SSL_STATUS_DRAFT:
            case 'awaiting configuration':
            case 'draft':
                return self::applyCert($params, $order, $cert);

            case SSL_STATUS_PENDING:
            case 'pending':
            case 'processing':
                return self::message($params, $order, $cert);

            case SSL_STATUS_COMPLETE:
            case 'complete':
            case 'active':
            case 'issued':
                return self::complete($params, $order, $cert);

            case SSL_STATUS_REISSUE:
            case 'reissue':
                return self::reissue($params, $order, $cert);

            case SSL_STATUS_CANCELLED:
            case SSL_STATUS_REVOKED:
            case SSL_STATUS_EXPIRED:
            case 'cancelled':
            case 'canceled':
            case 'revoked':
            case 'expired':
                return self::cancelled($params, $order, $cert);

            default:
                // Check if it's a reissue scenario
                $configdata = json_decode($order->configdata, true) ?: [];
                if (!empty($configdata['replaceTimes'])) {
                    return self::reissue($params, $order, $cert);
                }
                // Default to apply cert for unknown status
                return self::applyCert($params, $order, $cert);
        }
    }

    /**
     * Render migrated certificate page (read-only)
     * 
     * Displays certificate information from a previous vendor when the product
     * has been switched to NicSRS but the old cert is still active.
     * Client sees read-only info. Admin must click "Allow New Certificate"
     * to let the client apply for a new NicSRS cert.
     * 
     * @param array  $params      WHMCS module params
     * @param object $vendorOrder Order record from tblsslorders (other vendor)
     * @param array  $cert        Current product's certificate config
     * @return array Template data for rendering
     */
    public static function migrated(array $params, object $vendorOrder, array $cert): array
    {
        $baseVars = self::getBaseVars($params);

        // Parse vendor configdata - different modules store data differently
        $vendorConfig = json_decode($vendorOrder->configdata ?? '{}', true);
        if (!is_array($vendorConfig)) {
            $vendorConfig = [];
        }

        // ---- Extract domains ----
        $domains = self::extractVendorDomains($vendorConfig, $params);

        // ---- Extract dates ----
        $beginDate = self::findInConfig($vendorConfig, [
            'beginDate',
            'applyReturn.beginDate',
            'begin_date',
            'start_date',
            'certificate.beginDate',
            'certificate.start_date',
        ]);

        $endDate = self::findInConfig($vendorConfig, [
            'endDate',
            'applyReturn.endDate',
            'end_date',
            'expires',
            'expiry_date',
            'cert_expiry',
            'certificate.endDate',
            'certificate.end_date',
        ]);

        // ---- Calculate days remaining ----
        $daysRemaining = null;
        if ($endDate && strtotime($endDate)) {
            $daysRemaining = (int) ceil((strtotime($endDate) - time()) / 86400);
        }

        // ---- Vendor display name ----
        $vendorModule = $vendorOrder->module ?? 'Unknown';
        $vendorModuleDisplay = self::getVendorDisplayName($vendorModule);

        // ---- Extract additional info if available ----
        $vendorCertType = $vendorConfig['certtype'] 
                    ?? $vendorConfig['product_type'] 
                    ?? $vendorConfig['productType']
                    ?? $vendorOrder->certtype 
                    ?? '';

        logModuleCall('nicsrs_ssl', 'TemplateHelper::migrated', [
            'serviceid' => $params['serviceid'],
            'vendor_module' => $vendorModule,
            'vendor_remoteid' => $vendorOrder->remoteid ?? '',
            'vendor_status' => $vendorOrder->status ?? '',
            'domains_found' => count($domains),
            'beginDate' => $beginDate ?: 'N/A',
            'endDate' => $endDate ?: 'N/A',
            'daysRemaining' => $daysRemaining,
        ], 'Rendering migrated template');

        return [
            'templatefile' => 'migrated',
            'vars' => array_merge($baseVars, [
                // Vendor order data
                'vendorOrder'         => $vendorOrder,
                'vendorModule'        => $vendorModule,
                'vendorModuleDisplay' => $vendorModuleDisplay,
                'vendorRemoteId'      => $vendorOrder->remoteid ?? '',
                'vendorStatus'        => $vendorOrder->status ?? 'Unknown',
                'vendorCertType'      => $vendorCertType,
                'vendorDomains'       => $domains,
                'vendorBeginDate'     => $beginDate,
                'vendorEndDate'       => $endDate,
                'vendorDaysRemaining' => $daysRemaining,
                'vendorConfigData'    => $vendorConfig,
                // Current product config
                'cert'        => $cert,
                'productCode' => $cert['name'] ?? '',
            ]),
        ];
    }

    /**
     * Extract domain list from vendor configdata
     * 
     * Tries multiple common data structures used by different SSL modules
     * to find the list of domains covered by the certificate.
     * Falls back to tblhosting.domain if nothing found.
     * 
     * @param array $vendorConfig Decoded configdata from vendor order
     * @param array $params       WHMCS module params
     * @return array List of domain strings
     */
    private static function extractVendorDomains(array $vendorConfig, array $params): array
    {
        $domains = [];

        // Pattern 1: domainInfo array (NicSRS, GoGetSSL)
        if (!empty($vendorConfig['domainInfo']) && is_array($vendorConfig['domainInfo'])) {
            foreach ($vendorConfig['domainInfo'] as $d) {
                $name = $d['domainName'] ?? $d['domain'] ?? $d['name'] ?? '';
                if ($name) {
                    $domains[] = $name;
                }
            }
        }

        // Pattern 2: domains array (TheSSLStore)
        if (empty($domains) && !empty($vendorConfig['domains']) && is_array($vendorConfig['domains'])) {
            foreach ($vendorConfig['domains'] as $d) {
                if (is_string($d)) {
                    $domains[] = $d;
                } elseif (is_array($d)) {
                    $name = $d['domain'] ?? $d['domainName'] ?? $d['name'] ?? '';
                    if ($name) {
                        $domains[] = $name;
                    }
                }
            }
        }

        // Pattern 3: Single domain field
        if (empty($domains)) {
            $singleDomain = $vendorConfig['domain'] 
                        ?? $vendorConfig['commonName'] 
                        ?? $vendorConfig['common_name']
                        ?? $vendorConfig['san'] 
                        ?? '';
            if ($singleDomain) {
                $domains[] = $singleDomain;
            }
        }

        // Pattern 4: Nested in applyReturn
        if (empty($domains) && !empty($vendorConfig['applyReturn']['domainInfo'])) {
            foreach ($vendorConfig['applyReturn']['domainInfo'] as $d) {
                $name = $d['domainName'] ?? $d['domain'] ?? '';
                if ($name) {
                    $domains[] = $name;
                }
            }
        }

        // Fallback: tblhosting.domain
        if (empty($domains)) {
            try {
                $service = \WHMCS\Database\Capsule::table('tblhosting')
                    ->where('id', $params['serviceid'])
                    ->first();
                if ($service && !empty($service->domain)) {
                    $domains[] = $service->domain;
                }
            } catch (\Exception $e) {
                // Silently fail
            }
        }

        // Remove empty/duplicate entries
        $domains = array_values(array_unique(array_filter($domains)));

        return $domains;
    }

    /**
     * Find a value in config array using dot-notation paths
     * 
     * Tries multiple possible keys to find a value, since different
     * vendor modules store data in different structures.
     * 
     * @param array $config Config array to search
     * @param array $paths  List of dot-notation paths to try
     * @return string|null First found value or null
     */
    private static function findInConfig(array $config, array $paths): ?string
    {
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $current = $config;
            
            foreach ($parts as $part) {
                if (!is_array($current) || !isset($current[$part])) {
                    $current = null;
                    break;
                }
                $current = $current[$part];
            }
            
            if ($current !== null && is_string($current) && $current !== '') {
                return $current;
            }
        }

        return null;
    }

    /**
     * Get human-readable vendor display name from module identifier
     * 
     * Maps known module names to friendly display names.
     * Falls back to ucfirst + underscore replacement for unknown modules.
     * 
     * @param string $module Module name from tblsslorders
     * @return string Human-readable display name
     */
    private static function getVendorDisplayName(string $module): string
    {
        $map = [
            // Popular WHMCS SSL modules
            'cpanel_ssl'           => 'cPanel SSL',
            'solutelabs_ssl'       => 'SoluteLabs SSL',
            'thesslstore_ssl'      => 'TheSSLStore',
            'thesslstore'          => 'TheSSLStore',
            'gogetssl'             => 'GoGetSSL',
            'resellerclub_ssl'     => 'ResellerClub SSL',
            'namecheap_ssl'        => 'Namecheap SSL',
            'ssls_com'             => 'SSLs.com',
            'certum_ssl'           => 'Certum SSL',
            'sectigo_ssl'          => 'Sectigo SSL',
            'comodo_ssl'           => 'Comodo SSL',
            'digicert_ssl'         => 'DigiCert SSL',
            'globalsign_ssl'       => 'GlobalSign SSL',
            'letsencrypt_ssl'      => "Let's Encrypt SSL",
            'whmcs_ssl'            => 'WHMCS SSL',
            'nicsrs_ssl'           => 'NicSRS SSL',
            // Hosting panel modules
            'plesk'                => 'Plesk SSL',
            'directadmin_ssl'      => 'DirectAdmin SSL',
        ];

        $key = strtolower(trim($module));

        return $map[$key] ?? ucfirst(str_replace(['_', '-'], ' ', $module));
    }
}