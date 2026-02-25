<?php
/**
 * Dashboard Template — Unified across 3 tables
 *
 * Variables from DashboardController:
 *   $stats          - [total, active, pending, expiring, byProvider[], byStatus[]]
 *   $providers      - array of mod_aio_ssl_providers rows
 *   $expiringCerts  - array of certs expiring ≤ 30 days (all 3 tables)
 *   $recentOrders   - array of recent orders (all 3 tables)
 *   $chartData      - [statusDistribution, ordersByProvider, monthlyOrders]
 *   $moduleLink, $lang, $csrfToken
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$providerNames = [
    'nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore',
    'ssl2buy'=>'SSL2Buy','aio'=>'AIO SSL',
];
$providerColors = [
    'NicSRS'=>'#1890ff','GoGetSSL'=>'#52c41a','TheSSLStore'=>'#722ed1',
    'SSL2Buy'=>'#fa8c16','AIO SSL'=>'#13c2c2',
];
$statusColors = [
    'Active'=>'#52c41a','Pending'=>'#1890ff','Awaiting'=>'#8c8c8c',
    'Draft'=>'#91d5ff','Expired'=>'#ff4d4f','Cancelled'=>'#ff7875',
    'Revoked'=>'#cf1322','Other'=>'#d9d9d9',
];
$providerBadgeMap = [
    'nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl',
    'thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy',
    'aio'=>'aio-provider-aio',
];
$sourceMap = [
    'mod_aio_ssl_orders'=>'aio','nicsrs_sslorders'=>'nicsrs','tblsslorders'=>'tblssl',
];
?>

<!-- ═══ Stat Cards ═══ -->
<div class="aio-stats-grid">
    <div class="aio-stat-card">
        <div class="aio-stat-icon blue"><i class="fas fa-certificate"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['total_orders'] ?? 'Total Orders' ?></div>
            <div class="aio-stat-sub">
                <?php foreach (($stats['byProvider'] ?? []) as $slug => $cnt): ?>
                <span style="font-size:10px;color:var(--aio-text-secondary);"><?= $providerNames[$slug] ?? $slug ?>: <?= $cnt ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['active'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['issued_certs'] ?? 'Active / Issued' ?></div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['pending_orders'] ?? 'Pending / Processing' ?></div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['expiring'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['expiring_soon'] ?? 'Expiring ≤ 30 Days' ?></div>
        </div>
    </div>
</div>

<!-- ═══ Charts Row ═══ -->
<div class="aio-charts-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    <!-- Orders by Provider (Doughnut) -->
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-chart-pie"></i> <?= $lang['orders_by_provider'] ?? 'Orders by Provider' ?></span></div>
        <div class="aio-card-body" style="text-align:center;min-height:260px;">
            <?php if (empty($chartData['ordersByProvider'] ?? [])): ?>
                <div style="padding:60px;color:var(--aio-text-secondary);">No data</div>
            <?php else: ?>
                <canvas id="chart-provider" height="240"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Distribution (Doughnut) -->
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-chart-bar"></i> <?= $lang['status_distribution'] ?? 'Status Distribution' ?></span></div>
        <div class="aio-card-body" style="text-align:center;min-height:260px;">
            <?php if (empty($chartData['statusDistribution'] ?? [])): ?>
                <div style="padding:60px;color:var(--aio-text-secondary);">No data</div>
            <?php else: ?>
                <canvas id="chart-status" height="240"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly Trend (Line) -->
<div class="aio-card" style="margin-bottom:16px;">
    <div class="aio-card-header"><span><i class="fas fa-chart-line"></i> Monthly Order Trend</span></div>
    <div class="aio-card-body" style="min-height:200px;">
        <?php if (empty($chartData['monthlyOrders'] ?? [])): ?>
            <div style="padding:40px;text-align:center;color:var(--aio-text-secondary);">No data</div>
        <?php else: ?>
            <canvas id="chart-monthly" height="160"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ Two-Column: API Health + Expiring Certs ═══ -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:16px;">

    <!-- API Health -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-heartbeat"></i> <?= $lang['api_health'] ?? 'Provider Status' ?></span>
            <button class="aio-btn aio-btn-xs" onclick="AioSSL.ajax({page:'dashboard',action:'test_all',loadingMsg:'Testing...',onSuccess:function(){location.reload();}})">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div class="aio-card-body" style="padding:0;">
            <?php if (empty($providers)): ?>
                <div style="padding:20px;text-align:center;color:var(--aio-text-secondary);font-size:12px;">
                    No providers configured. <a href="<?= $moduleLink ?>&page=providers&action=add">Add one</a>.
                </div>
            <?php else: ?>
            <?php foreach ($providers as $p): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--aio-border,#f0f0f0);font-size:12px;">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= $p->test_result ? '#52c41a' : ($p->test_result === null ? '#d9d9d9' : '#ff4d4f') ?>;flex-shrink:0;"></span>
                <span class="aio-provider-badge <?= $providerBadgeMap[$p->slug] ?? '' ?>" style="font-size:11px;"><?= htmlspecialchars($p->name) ?></span>
                <span style="margin-left:auto;color:var(--aio-text-secondary);font-size:10px;">
                    <?= $p->last_test ? date('M j H:i', strtotime($p->last_test)) : 'Never tested' ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expiring Certificates -->
    <div class="aio-card">
        <div class="aio-card-header" style="background:var(--aio-danger-bg,#fff2f0);">
            <span><i class="fas fa-calendar-times" style="color:var(--aio-danger)"></i> <?= $lang['expiring_certs'] ?? 'Expiring Certificates' ?>
                <?php if (!empty($expiringCerts)): ?>
                <span class="aio-badge aio-badge-danger" style="margin-left:6px;"><?= count($expiringCerts) ?></span>
                <?php endif; ?>
            </span>
            <a href="<?= $moduleLink ?>&page=orders&status=active" class="aio-btn aio-btn-xs aio-btn-ghost">View All</a>
        </div>
        <div class="aio-card-body" style="padding:0;">
            <?php if (empty($expiringCerts)): ?>
                <div style="padding:30px;text-align:center;color:var(--aio-text-secondary);font-size:12px;">
                    <i class="fas fa-check-circle" style="color:var(--aio-success);font-size:20px;"></i><br>
                    No certificates expiring within 30 days.
                </div>
            <?php else: ?>
            <table class="aio-table" style="font-size:12px;">
                <thead><tr>
                    <th>Domain</th>
                    <th>Provider</th>
                    <th>Product / Service</th>
                    <th>Client</th>
                    <th>Expires</th>
                    <th>Days Left</th>
                </tr></thead>
                <tbody>
                <?php foreach ($expiringCerts as $c):
                    $daysLeft = $c->days_left ?? 0;
                    $urgClass = $daysLeft <= 7 ? 'aio-badge-danger' : 'aio-badge-warning';
                    $slug = $c->_provider_slug ?? $c->provider_slug ?? '';
                    $st = $sourceMap[$c->_source_table ?? ''] ?? '';
                    $sid = $c->serviceid ?? 0;
                    $cid = $c->client_id ?? $c->userid ?? 0;
                ?>
                <tr>
                    <td style="font-family:monospace;font-size:11px;">
                        <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $c->id ?>&source=<?= $st ?>" class="aio-link">
                            <?= htmlspecialchars(mb_strimwidth($c->domain ?? '—', 0, 30, '...')) ?>
                        </a>
                    </td>
                    <td><span class="aio-provider-badge <?= $providerBadgeMap[$slug] ?? '' ?>" style="font-size:10px;"><?= $providerNames[$slug] ?? $slug ?></span></td>
                    <td>
                        <?php if ($sid > 0): ?>
                        <a href="clientsservices.php?userid=<?= $cid ?>&id=<?= $sid ?>" class="aio-link" style="font-size:11px;">
                            <?= htmlspecialchars(mb_strimwidth($c->product_name ?? '—', 0, 25, '...')) ?>
                        </a>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--aio-text-secondary);"><?= htmlspecialchars($c->product_name ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cid > 0): ?>
                        <a href="clientssummary.php?userid=<?= $cid ?>" class="aio-link" style="font-size:11px;"><?= htmlspecialchars($c->client_name ?? '—') ?></a>
                        <?php else: ?>
                        <span style="font-size:11px;"><?= htmlspecialchars($c->client_name ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap" style="color:var(--aio-danger);font-weight:600;font-size:11px;">
                        <?= date('Y-m-d', strtotime($c->end_date)) ?>
                    </td>
                    <td><span class="aio-badge <?= $urgClass ?>"><?= $daysLeft ?>d</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══ Recent Orders ═══ -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-history"></i> <?= $lang['recent_orders'] ?? 'Recent Orders' ?></span>
        <a href="<?= $moduleLink ?>&page=orders" class="aio-btn aio-btn-xs aio-btn-ghost">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($recentOrders)): ?>
            <div style="padding:30px;text-align:center;color:var(--aio-text-secondary);font-size:12px;">
                <i class="fas fa-inbox" style="font-size:20px;"></i><br>No orders yet.
            </div>
        <?php else: ?>
        <table class="aio-table" style="font-size:12px;">
            <thead><tr>
                <th>ID</th>
                <th>Provider</th>
                <th>Domain</th>
                <th>Product / Service</th>
                <th>Client</th>
                <th>Status</th>
                <th>Date</th>
            </tr></thead>
            <tbody>
            <?php
            $statusNormMap = [
                'active'=>['Active','aio-status-active','fa-check-circle'],
                'complete'=>['Active','aio-status-active','fa-check-circle'],
                'completed'=>['Active','aio-status-active','fa-check-circle'],
                'configuration submitted'=>['Active','aio-status-active','fa-check-circle'],
                'issued'=>['Active','aio-status-active','fa-check-circle'],
                'pending'=>['Pending','aio-status-pending','fa-clock'],
                'processing'=>['Processing','aio-status-pending','fa-spinner'],
                'awaiting configuration'=>['Awaiting','aio-status-awaiting','fa-hourglass-half'],
                'awaiting'=>['Awaiting','aio-status-awaiting','fa-hourglass-half'],
                'expired'=>['Expired','aio-status-expired','fa-calendar-times'],
                'cancelled'=>['Cancelled','aio-status-cancelled','fa-ban'],
                'canceled'=>['Cancelled','aio-status-cancelled','fa-ban'],
                'revoked'=>['Revoked','aio-status-revoked','fa-times-circle'],
                'draft'=>['Draft','aio-status-awaiting','fa-pencil-alt'],
            ];
            foreach ($recentOrders as $o):
                $slug = $o->_provider_slug ?? $o->provider_slug ?? '';
                $st = $sourceMap[$o->_source_table ?? ''] ?? '';
                $rawStatus = strtolower(trim($o->status ?? ''));
                $si = $statusNormMap[$rawStatus] ?? [ucfirst($o->status ?? 'Unknown'), '', 'fa-question-circle'];
                $sid = $o->serviceid ?? 0;
                $cid = $o->client_id ?? $o->userid ?? 0;
                $dateStr = $o->_sort_date ?? $o->created_at ?? '';
            ?>
            <tr>
                <td>
                    <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>&source=<?= $st ?>" class="aio-link" style="font-weight:600;">#<?= $o->id ?></a>
                </td>
                <td><span class="aio-provider-badge <?= $providerBadgeMap[$slug] ?? '' ?>" style="font-size:10px;"><?= $providerNames[$slug] ?? $slug ?></span></td>
                <td style="font-family:monospace;font-size:11px;"><?= htmlspecialchars(mb_strimwidth($o->domain ?? '—', 0, 30, '...')) ?></td>
                <td>
                    <?php if ($sid > 0): ?>
                    <a href="clientsservices.php?userid=<?= $cid ?>&id=<?= $sid ?>" class="aio-link" style="font-size:11px;">
                        <?= htmlspecialchars(mb_strimwidth($o->product_name ?? '—', 0, 28, '...')) ?>
                    </a>
                    <?php else: ?>
                    <span style="font-size:11px;color:var(--aio-text-secondary);"><?= htmlspecialchars($o->product_name ?? '—') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($cid > 0): ?>
                    <a href="clientssummary.php?userid=<?= $cid ?>" class="aio-link" style="font-size:11px;"><?= htmlspecialchars($o->client_name ?? '—') ?></a>
                    <?php else: ?>
                    <?= htmlspecialchars($o->client_name ?? '—') ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="aio-badge <?= $si[1] ?>" title="<?= htmlspecialchars($o->status ?? '') ?>">
                        <i class="fas <?= $si[2] ?>" style="font-size:10px;margin-right:2px;"></i> <?= $si[0] ?>
                    </span>
                </td>
                <td class="text-nowrap" style="font-size:11px;color:var(--aio-text-secondary);">
                    <?= (!empty($dateStr) && $dateStr !== '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($dateStr)) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ Chart.js (CDN loaded by WHMCS or aio assets) ═══ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Provider Doughnut ──
    var provData = <?= json_encode($chartData['ordersByProvider'] ?? new \stdClass()) ?>;
    var provLabels = Object.keys(provData);
    var provValues = Object.values(provData);
    var provColors = <?= json_encode($providerColors) ?>;
    var provBg = provLabels.map(function(l) { return provColors[l] || '#d9d9d9'; });

    if (provLabels.length > 0 && document.getElementById('chart-provider')) {
        new Chart(document.getElementById('chart-provider'), {
            type: 'doughnut',
            data: {
                labels: provLabels,
                datasets: [{ data: provValues, backgroundColor: provBg, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } }
            }
        });
    }

    // ── Status Doughnut ──
    var statusData = <?= json_encode($chartData['statusDistribution'] ?? new \stdClass()) ?>;
    var statusLabels = Object.keys(statusData);
    var statusValues = Object.values(statusData);
    var sColors = <?= json_encode($statusColors) ?>;
    var statusBg = statusLabels.map(function(l) { return sColors[l] || '#d9d9d9'; });

    if (statusLabels.length > 0 && document.getElementById('chart-status')) {
        new Chart(document.getElementById('chart-status'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{ data: statusValues, backgroundColor: statusBg, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } }
            }
        });
    }

    // ── Monthly Trend Line ──
    var monthlyData = <?= json_encode($chartData['monthlyOrders'] ?? []) ?>;
    var mLabels = monthlyData.map(function(m) { return m.short || m.month; });
    var mValues = monthlyData.map(function(m) { return m.count || 0; });

    if (mLabels.length > 0 && document.getElementById('chart-monthly')) {
        new Chart(document.getElementById('chart-monthly'), {
            type: 'line',
            data: {
                labels: mLabels,
                datasets: [{
                    label: 'Orders',
                    data: mValues,
                    borderColor: '#1890ff',
                    backgroundColor: 'rgba(24,144,255,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#1890ff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } } },
                    x: { ticks: { font: { size: 11 } } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

});
</script>