<?php

namespace AioSSL\Core;

use WHMCS\Database\Capsule;

/**
 * Activity logger — writes to mod_aio_ssl_activity_log
 */
class ActivityLogger
{
    /**
     * Log an activity
     *
     * @param string      $action     Action identifier
     * @param string|null $entityType 'provider','order','product','sync','settings'
     * @param string|null $entityId
     * @param string      $details    Human-readable description
     * @param array       $context    Additional data (JSON-encoded)
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        string $details = '',
        array $context = []
    ): void {
        try {
            $adminId = null;
            if (defined('ADMINAREA') && isset($_SESSION['adminid'])) {
                $adminId = (int)$_SESSION['adminid'];
            }

            $detailsStr = $details;
            if (!empty($context)) {
                $detailsStr .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }

            Capsule::table('mod_aio_ssl_activity_log')->insert([
                'admin_id'    => $adminId,
                'action'      => substr($action, 0, 100),
                'entity_type' => $entityType ? substr($entityType, 0, 50) : null,
                'entity_id'   => $entityId ? substr($entityId, 0, 100) : null,
                'details'     => $detailsStr,
                'ip_address'  => self::getClientIp(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silent fail — don't break the application for logging failures
            error_log('AIO SSL Logger Error: ' . $e->getMessage());
        }
    }

    /**
     * Get recent log entries
     *
     * @param int         $limit
     * @param string|null $entityType
     * @param string|null $entityId
     * @return array
     */
    public static function getRecent(int $limit = 50, ?string $entityType = null, ?string $entityId = null): array
    {
        $q = Capsule::table('mod_aio_ssl_activity_log')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($entityType) {
            $q->where('entity_type', $entityType);
        }
        if ($entityId) {
            $q->where('entity_id', $entityId);
        }

        return $q->get()->toArray();
    }

    /**
     * Get client IP address
     *
     * @return string|null
     */
    private static function getClientIp(): ?string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    /**
     * Cleanup old log entries
     *
     * @param int $daysOld
     * @return int Number of deleted rows
     */
    public static function cleanup(int $daysOld = 90): int
    {
        return Capsule::table('mod_aio_ssl_activity_log')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$daysOld} days")))
            ->delete();
    }
}