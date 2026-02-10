<?php
/**
 * NicSRS SSL Module - Constants Definition
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * API Base URL
 */
define('NICSRS_API_URL', 'https://portal.nicsrs.com/ssl');

/**
 * Order Status Constants
 */
define('SSL_STATUS_AWAITING', 'Awaiting Configuration');
define('SSL_STATUS_DRAFT', 'Draft');
define('SSL_STATUS_PENDING', 'Pending');
define('SSL_STATUS_PROCESSING', 'Processing');
define('SSL_STATUS_COMPLETE', 'Complete');
define('SSL_STATUS_ISSUED', 'Issued');
define('SSL_STATUS_CANCELLED', 'Cancelled');
define('SSL_STATUS_REVOKED', 'Revoked');
define('SSL_STATUS_EXPIRED', 'Expired');
define('SSL_STATUS_REISSUE', 'Reissue');
define('SSL_STATUS_SUSPENDED', 'Suspended');
define('SSL_STATUS_TERMINATED', 'Terminated');

/**
 * DCV (Domain Control Validation) Methods
 */
define('DCV_METHODS', [
    'HTTP_CSR_HASH' => [
        'name' => 'HTTP File',
        'type' => 'http',
        'description' => 'Upload a file to your web server at /.well-known/pki-validation/',
        'icon' => 'file-text'
    ],
    'HTTPS_CSR_HASH' => [
        'name' => 'HTTPS File',
        'type' => 'https',
        'description' => 'Upload a file to your HTTPS server at /.well-known/pki-validation/',
        'icon' => 'lock'
    ],
    'CNAME_CSR_HASH' => [
        'name' => 'DNS CNAME',
        'type' => 'dns',
        'description' => 'Add a CNAME record to your DNS',
        'icon' => 'globe'
    ],
    'DNS_CSR_HASH' => [
        'name' => 'DNS TXT',
        'type' => 'dns',
        'description' => 'Add a TXT record to your DNS',
        'icon' => 'list'
    ],
    'EMAIL' => [
        'name' => 'Email',
        'type' => 'email',
        'description' => 'Verify via email sent to domain admin',
        'icon' => 'mail'
    ],
]);

/**
 * Certificate Types Configuration
 * Maps certificate codes to their attributes
 */
define('CERT_TYPES', [
    // Sectigo DV
    'sectigo_ov_ssl' => [
        'name' => 'Sectigo OV SSL',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'sectigo_ov_wildcard' => [
        'name' => 'Sectigo OV Wildcard',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => true,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => true,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'sectigo_ev_ssl' => [
        'name' => 'Sectigo EV SSL',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ev',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'sectigo_ev_multidomain' => [
        'name' => 'Sectigo EV Multi-Domain',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ev',
        'isMultiDomain' => true,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 250,
    ],
    'positivessl' => [
        'name' => 'PositiveSSL',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'positivessl_wildcard' => [
        'name' => 'PositiveSSL Wildcard',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => false,
        'isWildcard' => true,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => true,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'positivessl_multidomain' => [
        'name' => 'PositiveSSL Multi-Domain',
        'vendor' => 'Sectigo',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => true,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => true,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 250,
    ],
    // DigiCert
    'digicert_standard_ssl' => [
        'name' => 'DigiCert Standard SSL',
        'vendor' => 'DigiCert',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'digicert_ev_ssl' => [
        'name' => 'DigiCert EV SSL',
        'vendor' => 'DigiCert',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ev',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'digicert_wildcard' => [
        'name' => 'DigiCert Wildcard SSL',
        'vendor' => 'DigiCert',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => true,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => true,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    // GlobalSign
    'globalsign_dv_ssl' => [
        'name' => 'GlobalSign DV SSL',
        'vendor' => 'GlobalSign',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'globalsign_ov_ssl' => [
        'name' => 'GlobalSign OV SSL',
        'vendor' => 'GlobalSign',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'globalsign_ev_ssl' => [
        'name' => 'GlobalSign EV SSL',
        'vendor' => 'GlobalSign',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ev',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    // GeoTrust
    'geotrust_quickssl_premium' => [
        'name' => 'GeoTrust QuickSSL Premium',
        'vendor' => 'GeoTrust',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    'geotrust_truebusiness_id' => [
        'name' => 'GeoTrust True BusinessID',
        'vendor' => 'GeoTrust',
        'sslType' => 'website_ssl',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => true,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => true,
        'maxDomains' => 1,
    ],
    // Code Signing
    'sectigo_code_signing' => [
        'name' => 'Sectigo Code Signing',
        'vendor' => 'Sectigo',
        'sslType' => 'code_signing',
        'sslValidationType' => 'ov',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => false,
        'maxDomains' => 0,
    ],
    'sectigo_ev_code_signing' => [
        'name' => 'Sectigo EV Code Signing',
        'vendor' => 'Sectigo',
        'sslType' => 'code_signing',
        'sslValidationType' => 'ev',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => false,
        'maxDomains' => 0,
    ],
    // S/MIME Email
    'sectigo_personal_auth' => [
        'name' => 'Sectigo Personal Authentication',
        'vendor' => 'Sectigo',
        'sslType' => 'email_ssl',
        'sslValidationType' => 'dv',
        'isMultiDomain' => false,
        'isWildcard' => false,
        'supportNormal' => false,
        'supportIp' => false,
        'supportWild' => false,
        'supportHttps' => false,
        'maxDomains' => 0,
    ],
]);

/**
 * Server Types for Certificate
 */
define('SERVER_TYPES', [
    'other' => 'Other',
    'apache' => 'Apache',
    'nginx' => 'Nginx',
    'iis' => 'Microsoft IIS',
    'tomcat' => 'Tomcat',
    'cpanel' => 'cPanel',
    'plesk' => 'Plesk',
]);

/**
 * Download Format Types
 */
define('DOWNLOAD_FORMATS', [
    'all' => [
        'name' => 'All Formats (ZIP)',
        'description' => 'Contains all certificate formats',
        'extension' => 'zip'
    ],
    'apache' => [
        'name' => 'Apache',
        'description' => '.crt + .ca-bundle + .key',
        'extension' => 'zip'
    ],
    'nginx' => [
        'name' => 'Nginx',
        'description' => 'Combined .pem file',
        'extension' => 'pem'
    ],
    'iis' => [
        'name' => 'IIS (PKCS#12)',
        'description' => '.p12 / .pfx file',
        'extension' => 'p12'
    ],
    'tomcat' => [
        'name' => 'Tomcat (JKS)',
        'description' => 'Java KeyStore file',
        'extension' => 'jks'
    ],
]);

/**
 * API Response Codes
 */
define('API_CODE_SUCCESS', 1);
define('API_CODE_PROCESSING', 2);
define('API_CODE_VALIDATION_ERROR', -1);
define('API_CODE_UNKNOWN_ERROR', -2);
define('API_CODE_PRODUCT_ERROR', -3);
define('API_CODE_INSUFFICIENT_CREDIT', -4);
define('API_CODE_CA_ERROR', -6);
define('API_CODE_PERMISSION_DENIED', 400);

/**
 * Status to CSS class mapping
 */
define('STATUS_CLASSES', [
    'Awaiting Configuration' => 'default',
    'Draft' => 'default',
    'Pending' => 'warning',
    'Processing' => 'warning',
    'Complete' => 'success',
    'Issued' => 'success',
    'Cancelled' => 'danger',
    'Revoked' => 'danger',
    'Expired' => 'danger',
    'Suspended' => 'warning',
    'Terminated' => 'danger',
    'Reissue' => 'info',
]);

/**
 * Validation Types
 */
define('VALIDATION_TYPES', [
    'dv' => [
        'name' => 'Domain Validation',
        'requiresOrganization' => false,
        'requiresContacts' => false,
    ],
    'ov' => [
        'name' => 'Organization Validation',
        'requiresOrganization' => true,
        'requiresContacts' => true,
    ],
    'ev' => [
        'name' => 'Extended Validation',
        'requiresOrganization' => true,
        'requiresContacts' => true,
    ],
]);

/**
 * Default CSR Configuration
 */
define('DEFAULT_CSR_CONFIG', [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'digest_alg' => 'sha256',
]);