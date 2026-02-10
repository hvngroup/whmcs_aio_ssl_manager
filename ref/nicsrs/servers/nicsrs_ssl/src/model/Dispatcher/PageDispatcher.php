<?php
/**
 * NicSRS SSL Module - Page Dispatcher
 * Routes page requests to appropriate controller methods
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use Exception;

class PageDispatcher
{
    /**
     * Page routes mapping
     */
    private static $routes = [
        'index' => 'index',
        'apply' => 'index',
        'manage' => 'manage',
        'reissue' => 'reissue',
        'replace' => 'reissue', // Alias for backward compatibility
    ];

    /**
     * Dispatch page request
     */
    public static function dispatch(string $page, array $params): array
    {
        try {
            // Normalize page name
            $page = strtolower(trim($page));
            
            // Get controller method
            $method = self::$routes[$page] ?? 'index';

            // Check if method exists in PageController
            if (!method_exists(PageController::class, $method)) {
                return self::handleError($params, "Page not found: {$page}");
            }

            // Call controller method
            return PageController::$method($params);

        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'PageDispatcher::dispatch', [
                'page' => $page,
                'params' => $params
            ], $e->getMessage());

            return self::handleError($params, $e->getMessage());
        }
    }

    /**
     * Dispatch based on order status
     */
    public static function dispatchByStatus(array $params): array
    {
        try {
            return PageController::index($params);
        } catch (Exception $e) {
            logModuleCall('nicsrs_ssl', 'PageDispatcher::dispatchByStatus', $params, $e->getMessage());
            return self::handleError($params, $e->getMessage());
        }
    }

    /**
     * Handle error with user-friendly page
     */
    private static function handleError(array $params, string $message): array
    {
        return TemplateHelper::error($params, $message);
    }

    /**
     * Check if service belongs to user
     */
    public static function validateServiceOwnership(array $params): bool
    {
        $serviceId = $params['serviceid'] ?? 0;
        $userId = $params['userid'] ?? 0;

        if (!$serviceId || !$userId) {
            return false;
        }

        try {
            $service = \WHMCS\Database\Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $userId)
                ->first();

            return $service !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if service is active
     */
    public static function isServiceActive(array $params): bool
    {
        $serviceId = $params['serviceid'] ?? 0;

        if (!$serviceId) {
            return false;
        }

        try {
            $service = \WHMCS\Database\Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return false;
            }

            // Check domain status
            $activeStatuses = ['Active', 'Suspended'];
            return in_array($service->domainstatus, $activeStatuses);
        } catch (Exception $e) {
            return false;
        }
    }
}