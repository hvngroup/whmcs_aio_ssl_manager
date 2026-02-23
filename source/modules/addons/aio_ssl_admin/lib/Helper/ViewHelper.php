<?php

namespace AioSSL\Helper;

class ViewHelper
{
    /** @var array Provider colors for badges */
    private static $providerColors = [
        'nicsrs'      => ['bg' => '#1890ff', 'label' => 'NicSRS'],
        'gogetssl'    => ['bg' => '#13c2c2', 'label' => 'GoGetSSL'],
        'thesslstore' => ['bg' => '#722ed1', 'label' => 'TheSSLStore'],
        'ssl2buy'     => ['bg' => '#fa8c16', 'label' => 'SSL2Buy'],
        'aio_ssl'     => ['bg' => '#1890ff', 'label' => 'AIO SSL'],
        // Legacy module names
        'nicsrs_ssl'      => ['bg' => '#1890ff', 'label' => 'NicSRS (Legacy)'],
        'SSLCENTERWHMCS'  => ['bg' => '#13c2c2', 'label' => 'GoGetSSL (Legacy)'],
        'thesslstore_ssl' => ['bg' => '#722ed1', 'label' => 'TheSSLStore (Legacy)'],
        'ssl2buy'         => ['bg' => '#fa8c16', 'label' => 'SSL2Buy (Legacy)'],
    ];

    /** @var array Status → Bootstrap class + icon */
    private static $statusMap = [
        'Completed'              => ['class' => 'success',  'icon' => '✅'],
        'Issued'                 => ['class' => 'success',  'icon' => '✅'],
        'Active'                 => ['class' => 'success',  'icon' => '✅'],
        'Pending'                => ['class' => 'warning',  'icon' => '⏳'],
        'Processing'             => ['class' => 'info',     'icon' => '🔄'],
        'Awaiting Configuration' => ['class' => 'default',  'icon' => '📝'],
        'Cancelled'              => ['class' => 'danger',   'icon' => '❌'],
        'Expired'                => ['class' => 'danger',   'icon' => '⚠️'],
        'Rejected'               => ['class' => 'danger',   'icon' => '🚫'],
        'Revoked'                => ['class' => 'danger',   'icon' => '🔒'],
        'Suspended'              => ['class' => 'warning',  'icon' => '⏸️'],
    ];

    /**
     * Render provider badge HTML
     */
    public static function providerBadge(string $slugOrModule): string
    {
        $info = self::$providerColors[$slugOrModule] ?? ['bg' => '#999', 'label' => ucfirst($slugOrModule)];
        return '<span class="label" style="background:' . $info['bg'] . ';color:#fff;font-size:11px;">'
             . htmlspecialchars($info['label']) . '</span>';
    }

    /**
     * Render status label HTML
     */
    public static function statusLabel(string $status): string
    {
        $info = self::$statusMap[$status] ?? ['class' => 'default', 'icon' => ''];
        return '<span class="label label-' . $info['class'] . '">'
             . $info['icon'] . ' ' . htmlspecialchars($status) . '</span>';
    }

    /**
     * Render tier badge
     */
    public static function tierBadge(string $tier): string
    {
        $color = ($tier === 'full') ? '#52c41a' : '#faad14';
        $label = ($tier === 'full') ? 'Full' : 'Limited';
        return '<span class="label" style="background:' . $color . ';color:#fff;">' . $label . '</span>';
    }

    /**
     * Format date with fallback
     */
    public static function formatDate(?string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '—';
        }
        return date($format, strtotime($date));
    }

    /**
     * Render API test result icon
     */
    public static function testResultIcon(?int $result): string
    {
        if ($result === null) return '<span class="text-muted">—</span>';
        return $result ? '<span style="color:#52c41a;">✅ OK</span>' : '<span style="color:#ff4d4f;">❌ Fail</span>';
    }
}