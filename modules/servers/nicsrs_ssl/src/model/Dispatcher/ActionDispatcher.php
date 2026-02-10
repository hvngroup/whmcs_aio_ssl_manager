<?php
/**
 * NicSRS SSL Module - Action Dispatcher
 * Routes action requests to appropriate controller methods
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use Exception;

class ActionDispatcher
{
    /**
     * Action routes mapping
     */
    private static $routes = [
        // CSR actions
        'generateCSR' => 'generateCSR',
        'decodeCsr' => 'decodeCsr',
        'decodeCSR' => 'decodeCsr', // Alias
        
        // Certificate application
        'submitApply' => 'submitApply',
        'saveDraft' => 'saveDraft',
        
        // Status actions
        'refreshStatus' => 'refreshStatus',
        'refresh' => 'refreshStatus', // Alias
        
        // Download
        'downCert' => 'downCert',
        'download' => 'downCert', // Alias
        'downloadCertificate' => 'downCert', // Alias
        
        // DCV actions
        'batchUpdateDCV' => 'batchUpdateDCV',
        'updateDCV' => 'batchUpdateDCV', // Alias
        'resendDCVEmail' => 'resendDCVEmail',
        'resendDCV' => 'resendDCVEmail', // Alias
        
        // Order management
        'cancelOrder' => 'cancelOrder',
        'cancel' => 'cancelOrder', // Alias
        'revoke' => 'revoke',
        'revokeOrder' => 'revoke', // Alias
        
        // Reissue/Replace
        'submitReissue' => 'submitReissue',
        'submitReplace' => 'submitReissue', // Alias for backward compatibility
        'reissue' => 'submitReissue', // Alias
        
        // Renew
        'renew' => 'renew',
        'renewCertificate' => 'renew', // Alias
    ];

    /**
     * Actions that don't require authentication
     */
    private static $publicActions = [
        'generateCSR',
        'decodeCsr',
    ];

    /**
     * Dispatch action request
     */
    public static function dispatch(string $action, array $params): array
    {
        try {
            // Normalize action name
            $action = trim($action);
            
            // Get controller method
            $method = self::$routes[$action] ?? null;

            if (!$method) {
                return ResponseFormatter::error("Action not found: {$action}");
            }

            // Check if method exists
            if (!method_exists(ActionController::class, $method)) {
                return ResponseFormatter::error("Action method not implemented: {$method}");
            }

            // Validate service ownership for protected actions
            if (!in_array($action, self::$publicActions)) {
                if (!self::validateAccess($params)) {
                    return ResponseFormatter::error('Access denied');
                }
            }

            // Call controller method
            $result = ActionController::$method($params);

            // Log successful action
            logModuleCall('nicsrs_ssl', "Action:{$action}", [
                'serviceid' => $params['serviceid'] ?? null,
            ], $result['success'] ?? false ? 'Success' : ($result['message'] ?? 'Failed'));

            return $result;

        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', "ActionDispatcher::{$action}", $params, $e->getMessage());
            return ResponseFormatter::error($e->getMessage());
        }
    }

    /**
     * Dispatch and output JSON response
     */
    public static function dispatchJson(string $action, array $params): void
    {
        $result = self::dispatch($action, $params);
        
        // Handle file download
        if (isset($result['download']) && $result['download']) {
            self::sendDownload($result);
            return;
        }
        
        // Handle redirect
        if (isset($result['redirect']) && $result['redirect']) {
            header('Location: ' . $result['url']);
            exit;
        }
        
        // JSON response
        ResponseFormatter::json($result);
    }

    /**
     * Validate user access to service
     */
    private static function validateAccess(array $params): bool
    {
        // Admin always has access
        if (defined('ADMINAREA') && ADMINAREA) {
            return true;
        }

        // Check service ownership
        return PageDispatcher::validateServiceOwnership($params);
    }

    /**
     * Send file download response
     */
    private static function sendDownload(array $result): void
    {
        $filename = $result['filename'] ?? 'download.zip';
        $content = $result['content'] ?? '';
        $mimeType = $result['mimeType'] ?? 'application/octet-stream';

        // Decode if base64
        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $content)) {
            $decoded = base64_decode($content, true);
            if ($decoded !== false) {
                $content = $decoded;
            }
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }

    /**
     * Get list of available actions
     */
    public static function getAvailableActions(): array
    {
        return array_keys(self::$routes);
    }

    /**
     * Check if action exists
     */
    public static function actionExists(string $action): bool
    {
        return isset(self::$routes[$action]);
    }
}