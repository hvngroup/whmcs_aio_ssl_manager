<?php
/**
 * NicSRS SSL Module - Backward Compatibility
 * Class aliases for backward compatibility with older code
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

// Service class aliases
if (!class_exists('nicsrsSSL\\nicsrsAPI')) {
    class_alias('nicsrsSSL\\ApiService', 'nicsrsSSL\\nicsrsAPI');
}

if (!class_exists('nicsrsSSL\\nicsrsFunc')) {
    class_alias('nicsrsSSL\\CertificateFunc', 'nicsrsSSL\\nicsrsFunc');
}

if (!class_exists('nicsrsSSL\\nicsrsResponse')) {
    class_alias('nicsrsSSL\\ResponseFormatter', 'nicsrsSSL\\nicsrsResponse');
}

if (!class_exists('nicsrsSSL\\nicsrsSSLSql')) {
    class_alias('nicsrsSSL\\OrderRepository', 'nicsrsSSL\\nicsrsSSLSql');
}

if (!class_exists('nicsrsSSL\\nicsrsTemplate')) {
    class_alias('nicsrsSSL\\TemplateHelper', 'nicsrsSSL\\nicsrsTemplate');
}

/**
 * Legacy function wrapper for API calls
 * @deprecated Use ApiService::call() instead
 */
function nicsrsAPICall($endpoint, $data)
{
    return ApiService::call($endpoint, $data);
}

/**
 * Legacy function wrapper for getting SSL product
 * @deprecated Use OrderRepository::getByServiceId() instead
 */
function GetSSLProduct($serviceId)
{
    return OrderRepository::getByServiceId($serviceId);
}