<?php
/**
 * NicSRS SSL Module - Response Formatter
 * Standardized response formatting for AJAX and API calls
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

class ResponseFormatter
{
    /**
     * Success response
     */
    public static function success($message = 'Success', $data = null): array
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Error response
     */
    public static function error($message = 'An error occurred', $code = null, $data = null): array
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Validation error response
     */
    public static function validationError(array $errors): array
    {
        return [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ];
    }

    /**
     * Processing response (for async operations)
     */
    public static function processing($message = 'Request is being processed', $data = null): array
    {
        $response = [
            'success' => true,
            'processing' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Redirect response
     */
    public static function redirect(string $url, string $message = ''): array
    {
        return [
            'success' => true,
            'redirect' => true,
            'url' => $url,
            'message' => $message,
        ];
    }

    /**
     * File download response
     */
    public static function download(string $filename, string $content, string $mimeType = 'application/octet-stream'): array
    {
        return [
            'success' => true,
            'download' => true,
            'filename' => $filename,
            'content' => $content,
            'mimeType' => $mimeType,
        ];
    }

    /**
     * Parse API response into standardized format
     */
    public static function fromApiResponse(object $apiResponse): array
    {
        $code = $apiResponse->code ?? -2;
        $message = $apiResponse->msg ?? 'Unknown error';
        $status = $apiResponse->status ?? '';
        $data = $apiResponse->data ?? null;

        if ($code == API_CODE_SUCCESS) {
            return self::success($message, [
                'status' => $status,
                'data' => $data,
            ]);
        }

        if ($code == API_CODE_PROCESSING) {
            return self::processing($message, [
                'status' => $status,
                'data' => $data,
            ]);
        }

        // Error responses
        $errorMessage = self::getApiErrorMessage($code, $message);
        return self::error($errorMessage, $code, $data);
    }

    /**
     * Get human-readable API error message
     */
    private static function getApiErrorMessage(int $code, string $defaultMessage): string
    {
        $messages = [
            API_CODE_VALIDATION_ERROR => 'Validation error: ' . $defaultMessage,
            API_CODE_UNKNOWN_ERROR => 'An unknown error occurred. Please try again.',
            API_CODE_PRODUCT_ERROR => 'Product configuration error. Please contact support.',
            API_CODE_INSUFFICIENT_CREDIT => 'Insufficient credit. Please contact your provider.',
            API_CODE_CA_ERROR => 'Certificate Authority error. Please try again later.',
            API_CODE_PERMISSION_DENIED => 'Permission denied. Please check your API credentials.',
        ];

        return $messages[$code] ?? $defaultMessage;
    }

    /**
     * JSON encode and output response
     */
    public static function json(array $response): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Create WHMCS module return array
     */
    public static function moduleReturn(string $templateFile, array $vars = []): array
    {
        return [
            'templatefile' => $templateFile,
            'vars' => $vars,
        ];
    }

    /**
     * Create WHMCS module error return
     */
    public static function moduleError(string $message): string
    {
        return $message;
    }

    /**
     * Format order data for display
     */
    public static function formatOrderForDisplay(object $order): array
    {
        $configdata = json_decode($order->configdata, true) ?: [];
        $applyReturn = $configdata['applyReturn'] ?? [];
        $domainInfo = $configdata['domainInfo'] ?? [];

        // Primary domain
        $primaryDomain = '';
        if (!empty($domainInfo)) {
            $primaryDomain = $domainInfo[0]['domainName'] ?? '';
        }

        // Days until expiry
        $daysLeft = null;
        $expiryDate = $applyReturn['endDate'] ?? null;
        if ($expiryDate) {
            $daysLeft = CertificateFunc::getDaysUntilExpiry($expiryDate);
        }

        return [
            'id' => $order->id,
            'serviceid' => $order->serviceid,
            'userid' => $order->userid,
            'remoteid' => $order->remoteid,
            'certtype' => $order->certtype,
            'status' => $order->status,
            'statusClass' => CertificateFunc::getStatusClass($order->status),
            'domain' => $primaryDomain,
            'allDomains' => array_column($domainInfo, 'domainName'),
            'issuedDate' => CertificateFunc::formatDate($applyReturn['beginDate'] ?? null),
            'expiryDate' => CertificateFunc::formatDate($expiryDate),
            'daysLeft' => $daysLeft,
            'provisionDate' => CertificateFunc::formatDate($order->provisiondate),
            'completionDate' => CertificateFunc::formatDate($order->completiondate),
            'lastRefresh' => $configdata['lastRefresh'] ?? null,
            'vendorId' => $applyReturn['vendorId'] ?? null,
            'vendorCertId' => $applyReturn['vendorCertId'] ?? null,
            'configdata' => $configdata,
        ];
    }

    /**
     * Format domain validation status for display
     */
    public static function formatDCVStatus(array $domainInfo, array $dcvList = []): array
    {
        $result = [];

        // Create lookup from dcvList
        $dcvLookup = [];
        foreach ($dcvList as $dcv) {
            $domain = $dcv['domainName'] ?? '';
            if ($domain) {
                $dcvLookup[$domain] = $dcv;
            }
        }

        foreach ($domainInfo as $domain) {
            $domainName = $domain['domainName'] ?? '';
            $dcvData = $dcvLookup[$domainName] ?? [];

            $isVerified = false;
            if (isset($domain['isVerified'])) {
                $isVerified = $domain['isVerified'];
            } elseif (isset($domain['is_verify'])) {
                $isVerified = $domain['is_verify'] === 'verified';
            } elseif (isset($dcvData['is_verify'])) {
                $isVerified = $dcvData['is_verify'] === 'verified';
            }

            $result[] = [
                'domain' => $domainName,
                'method' => $domain['dcvMethod'] ?? $dcvData['dcvMethod'] ?? '',
                'methodName' => DCV_METHODS[$domain['dcvMethod'] ?? '']['name'] ?? 'Unknown',
                'isVerified' => $isVerified,
                'statusText' => $isVerified ? 'Verified' : 'Pending',
                'statusClass' => $isVerified ? 'success' : 'warning',
                'email' => $domain['dcvEmail'] ?? '',
            ];
        }

        return $result;
    }
}