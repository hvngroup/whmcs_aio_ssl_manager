<?php
/**
 * View Helper — Badge, date, price, HTML formatting utilities
 *
 * @package    AioSSL\Helper
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Helper;

class ViewHelper
{
    /** @var string Date format from settings */
    private $dateFormat = 'Y-m-d';

    public function __construct(string $dateFormat = 'Y-m-d')
    {
        $this->dateFormat = $dateFormat;
    }

    // ─── HTML Escaping ─────────────────────────────────────────────

    public function e(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }

    // ─── Status Badge ──────────────────────────────────────────────

    public function statusBadge(string $status): string
    {
        $map = [
            'Completed' => 'aio-badge-success', 'Issued' => 'aio-badge-success',
            'Active' => 'aio-badge-success',
            'Pending' => 'aio-badge-primary', 'Processing' => 'aio-badge-primary',
            'Expired' => 'aio-badge-danger', 'Cancelled' => 'aio-badge-danger',
            'Revoked' => 'aio-badge-danger',
            'Awaiting Configuration' => 'aio-badge-warning',
        ];
        $class = $map[$status] ?? 'aio-badge-default';
        return '<span class="aio-badge ' . $class . '">' . $this->e($status) . '</span>';
    }

    // ─── Provider Badge ────────────────────────────────────────────

    public function providerBadge(string $slug): string
    {
        $names = ['nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore','ssl2buy'=>'SSL2Buy'];
        $classes = [
            'nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl',
            'thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy',
        ];
        $name = $names[$slug] ?? ucfirst($slug);
        $class = $classes[$slug] ?? 'aio-badge-default';
        return '<span class="aio-provider-badge ' . $class . '">' . $this->e($name) . '</span>';
    }

    // ─── Validation Badge ──────────────────────────────────────────

    public function validationBadge(string $type): string
    {
        $map = ['dv'=>'aio-badge-success','ov'=>'aio-badge-primary','ev'=>'aio-badge-warning'];
        $class = $map[strtolower($type)] ?? 'aio-badge-default';
        return '<span class="aio-badge ' . $class . '">' . strtoupper($type) . '</span>';
    }

    // ─── Tier Badge ────────────────────────────────────────────────

    public function tierBadge(string $tier): string
    {
        $class = $tier === 'full' ? 'aio-tier-full' : 'aio-tier-limited';
        return '<span class="aio-badge ' . $class . '">' . ucfirst($tier) . ' Tier</span>';
    }

    // ─── Source Badge ──────────────────────────────────────────────

    public function sourceBadge(string $source): string
    {
        $isAio = in_array($source, ['aio', 'aio_ssl']);
        $class = $isAio ? 'aio-source-aio' : 'aio-source-legacy';
        return '<span class="aio-badge ' . $class . '">' . ($isAio ? 'AIO' : 'Legacy') . '</span>';
    }

    // ─── Date Formatting ───────────────────────────────────────────

    public function formatDate(?string $date): string
    {
        if (!$date) return '—';
        $ts = strtotime($date);
        return $ts ? date($this->dateFormat, $ts) : $date;
    }

    public function formatDateTime(?string $date): string
    {
        if (!$date) return '—';
        $ts = strtotime($date);
        return $ts ? date($this->dateFormat . ' H:i', $ts) : $date;
    }

    public function timeAgo(?string $date): string
    {
        if (!$date) return '—';
        $ts = strtotime($date);
        if (!$ts) return $date;
        $diff = time() - $ts;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
        return $this->formatDate($date);
    }

    // ─── Price Formatting ──────────────────────────────────────────

    public function formatPrice(?float $amount, string $currency = 'USD'): string
    {
        if ($amount === null) return '—';
        return '$' . number_format($amount, 2);
    }

    // ─── Text Utilities ────────────────────────────────────────────

    public function truncate(string $text, int $len = 50): string
    {
        if (mb_strlen($text) <= $len) return $this->e($text);
        return $this->e(mb_substr($text, 0, $len)) . '…';
    }

    public function boolIcon(bool $val): string
    {
        return $val
            ? '<i class="fas fa-check" style="color:var(--aio-success)"></i>'
            : '<span style="color:var(--aio-text-secondary)">—</span>';
    }

    // ─── Client/Service Links ──────────────────────────────────────

    public function clientLink(?int $userId, ?string $name = null): string
    {
        if (!$userId) return '—';
        $label = $name ?: 'Client #' . $userId;
        return '<a href="clientssummary.php?userid=' . $userId . '" class="aio-link-client">' . $this->e($label) . '</a>';
    }

    public function serviceLink(?int $serviceId): string
    {
        if (!$serviceId) return '—';
        return '<a href="clientsservices.php?id=' . $serviceId . '" class="aio-link-service">#' . $serviceId . '</a>';
    }

    // ─── Pagination HTML ───────────────────────────────────────────

    public function pagination(array $p, string $baseUrl): string
    {
        if (($p['pages'] ?? 1) <= 1) return '';
        $html = '<div class="aio-pagination"><div class="aio-pagination-info">';
        $html .= 'Showing ' . ($p['offset'] + 1) . '–' . min($p['offset'] + $p['limit'], $p['total']);
        $html .= ' of ' . number_format($p['total']) . '</div>';
        $html .= '<div class="aio-pagination-links">';
        $cp = $p['page'];
        $tp = $p['pages'];
        if ($cp > 1) $html .= '<a href="' . $baseUrl . '&p=' . ($cp - 1) . '">‹</a>';
        for ($i = max(1, $cp - 2); $i <= min($tp, $cp + 2); $i++) {
            $html .= $i === $cp
                ? '<span class="current">' . $i . '</span>'
                : '<a href="' . $baseUrl . '&p=' . $i . '">' . $i . '</a>';
        }
        if ($cp < $tp) $html .= '<a href="' . $baseUrl . '&p=' . ($cp + 1) . '">›</a>';
        $html .= '</div></div>';
        return $html;
    }
}