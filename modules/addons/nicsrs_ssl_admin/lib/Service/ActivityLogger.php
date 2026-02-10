<?php
/**
 * Activity Logger Service
 * Handles audit logging for admin actions
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;

class ActivityLogger
{
    /**
     * @var int Admin ID
     */
    private $adminId;

    /**
     * Constructor
     * 
     * @param int $adminId Admin user ID
     */
    public function __construct(int $adminId)
    {
        $this->adminId = $adminId;
    }

    /**
     * Log an activity
     * 
     * @param string $action Action name
     * @param string|null $entityType Entity type (order, product, settings)
     * @param int|null $entityId Entity ID
     * @param string|null $oldValue Previous value
     * @param string|null $newValue New value
     * @return int Inserted log ID
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $oldValue = null,
        ?string $newValue = null
    ): int {
        return Capsule::table('mod_nicsrs_activity_log')->insertGetId([
            'admin_id' => $this->adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log order action
     * 
     * @param int $orderId Order ID
     * @param string $action Action name
     * @param string|null $details Additional details
     * @return int Log ID
     */
    public function logOrderAction(int $orderId, string $action, ?string $details = null): int
    {
        return $this->log($action, 'order', $orderId, null, $details);
    }

    /**
     * Log settings change
     * 
     * @param string $settingKey Setting key
     * @param string|null $oldValue Old value
     * @param string|null $newValue New value
     * @return int Log ID
     */
    public function logSettingsChange(string $settingKey, ?string $oldValue, ?string $newValue): int
    {
        return $this->log('update_setting', 'settings', null, $oldValue, json_encode([
            'key' => $settingKey,
            'value' => $newValue,
        ]));
    }

    /**
     * Get logs for specific entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $limit Max results
     * @return array Logs
     */
    public function getLogsForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return Capsule::table('mod_nicsrs_activity_log as l')
            ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
            ->select([
                'l.*',
                'a.username',
                'a.firstname as admin_firstname',
                'a.lastname as admin_lastname',
            ])
            ->where('l.entity_type', $entityType)
            ->where('l.entity_id', $entityId)
            ->orderBy('l.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get recent logs
     * 
     * @param int $limit Max results
     * @param string|null $action Filter by action
     * @param string|null $entityType Filter by entity type
     * @return array Logs
     */
    public function getRecentLogs(int $limit = 100, ?string $action = null, ?string $entityType = null): array
    {
        $query = Capsule::table('mod_nicsrs_activity_log as l')
            ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
            ->select([
                'l.*',
                'a.username',
                'a.firstname as admin_firstname',
                'a.lastname as admin_lastname',
            ]);

        if ($action) {
            $query->where('l.action', $action);
        }

        if ($entityType) {
            $query->where('l.entity_type', $entityType);
        }

        return $query
            ->orderBy('l.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get logs by admin
     * 
     * @param int $adminId Admin ID
     * @param int $limit Max results
     * @return array Logs
     */
    public function getLogsByAdmin(int $adminId, int $limit = 100): array
    {
        return Capsule::table('mod_nicsrs_activity_log')
            ->where('admin_id', $adminId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get logs within date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int $limit Max results
     * @return array Logs
     */
    public function getLogsByDateRange(string $startDate, string $endDate, int $limit = 1000): array
    {
        return Capsule::table('mod_nicsrs_activity_log as l')
            ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
            ->select([
                'l.*',
                'a.username',
                'a.firstname as admin_firstname',
                'a.lastname as admin_lastname',
            ])
            ->whereBetween('l.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('l.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Count logs by action
     * 
     * @param int $days Number of days to look back (0 = all time)
     * @return array Action => count mapping
     */
    public function countByAction(int $days = 30): array
    {
        $query = Capsule::table('mod_nicsrs_activity_log')
            ->selectRaw('action, COUNT(*) as count');

        if ($days > 0) {
            $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        }

        return $query
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();
    }

    /**
     * Delete old logs
     * 
     * @param int $days Delete logs older than X days
     * @return int Number of deleted records
     */
    public function deleteOldLogs(int $days): int
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return Capsule::table('mod_nicsrs_activity_log')
            ->where('created_at', '<', $threshold)
            ->delete();
    }

    /**
     * Get client IP address
     * 
     * @return string|null IP address
     */
    private function getClientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR',               // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Get user agent string
     * 
     * @return string|null User agent
     */
    private function getUserAgent(): ?string
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        
        // Truncate if too long
        if ($ua && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }
        
        return $ua;
    }

    /**
     * Get human-readable action description
     * 
     * @param string $action Action code
     * @return string Description
     */
    public static function getActionDescription(string $action): string
    {
        $descriptions = [
            'refresh_status' => 'Refreshed certificate status',
            'cancel' => 'Cancelled certificate order',
            'revoke' => 'Revoked certificate',
            'reissue' => 'Requested certificate reissue',
            'renew' => 'Renewed certificate',
            'resend_dcv' => 'Resent DCV email',
            'sync_products' => 'Synced products from API',
            'sync_all_products' => 'Synced all products',
            'save_settings' => 'Updated module settings',
            'test_api' => 'Tested API connection',
            'clear_logs' => 'Cleared activity logs',
            'export_logs' => 'Exported activity logs',
        ];

        return isset($descriptions[$action]) ? $descriptions[$action] : ucwords(str_replace('_', ' ', $action));
    }
}