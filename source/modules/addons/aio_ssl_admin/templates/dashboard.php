<?php
/**
 * Dashboard Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $stats           - array [totalOrders, pending, issued, expiringSoon, byProvider[]]
 *   $recentOrders    - array of recent order rows
 *   $expiringCerts   - array of certs expiring within 30 days
 *   $providerHealth  - array [slug => [success, message, last_test]]
 *   $chartData       - array [statusDistribution, ordersByProvider]
 *   $moduleLink      - string
 *   $moduleVersion   - string
 *   $lang            - array
 *   $csrfToken       - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

$providerNames = [
    'nicsrs_ssl'       => 'NicSRS',
    'SSLCENTERWHMCS'   => 'GoGetSSL',
    'thesslstore_ssl'  => 'TheSSLStore',
    'thesslstore'      => 'TheSSLStore',
    'ssl2buy'          => 'SSL2Buy',
    'aio_ssl'          => 'AIO SSL',
];

$providerColors = [
    'nicsrs'      => '#1890ff',
    'gogetssl'    => '#52c41a',
    'thesslstore' => '#722ed1',
    'ssl2buy'     => '#fa8c16',
    'aio_ssl'     => '#13c2c2',
];

$statusColors = [
    'Completed'              => '#52c41a',
    'Issued'                 => '#52c41a',
    'Active'                 => '#52c41a',
    'Pending'                => '#1890ff',
    'Processing'             => '#1890ff',
    'Awaiting Configuration' => '#8c8c8c',
    'Expired'                => '#ff4d4f',
    'Cancelled'              => '#ff4d4f',
    'Revoked'                => '#ff4d4f',
];
?>

<!-- Stat Cards -->
<div class="aio-stats-grid">
    <div class="aio-stat-card">
        <div class="aio-stat-icon blue"><i class="fas fa-certificate"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['totalOrders'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['total_orders'] ?? 'Total Orders' ?></div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['pending'] ?? 'Pending / Processing' ?></div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['issued'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['issued'] ?? 'Issued / Active' ?></div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($stats['expiringSoon'] ?? 0) ?></div>
            <div class="aio-stat-label"><?= $lang['expiring_soon'] ?? 'Expiring ≤ 30 Days' ?></div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="aio-charts-grid">
    <!-- Orders by Provider (Doughnut) -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-chart-pie"></i> <?= $lang['orders_by_provider'] ?? 'Orders by Provider' ?></span>
        </div>
        <div class="aio-card-body" style="text-align:center;">
            <canvas id="chart-provider" height="220"></canvas>
        </div>
    </div>

    <!-- Status Distribution (Bar) -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-chart-bar"></i> <?= $lang['status_distribution'] ?? 'Certificate Status Distribution' ?></span>
        </div>
        <div class="aio-card-body">
            <?php
            $statusDist = $chartData['statusDistribution'] ?? [];
            $maxStatus = !empty($statusDist) ? max($statusDist) : 1;
            ?>
            <?php if (empty($statusDist)): ?>
                <div class="text-muted text-center" style="padding:20px;">No data available.</div>
            <?php else: ?>
                <?php foreach ($statusDist as $status => $count):
                    $pct = $maxStatus > 0 ? round(($count / $maxStatus) * 100) : 0;
                    $color = $statusColors[$status] ?? '#d9d9d9';
                ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:12px;">
                    <span style="width:130px;font-weight:500;"><?= htmlspecialchars($status) ?></span>
                    <div style="flex:1;height:10px;background:#f0f0f0;border-radius:5px;overflow:hidden;">
                        <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:5px;transition:width 0.5s ease;"></div>
                    </div>
                    <span style="width:40px;text-align:right;font-weight:600;"><?= number_format($count) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- API Health -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-heartbeat"></i> <?= $lang['api_health'] ?? 'API Health Status' ?></span>
            <button class="aio-btn aio-btn-sm" onclick="AioSSL.ajax({page:'dashboard',action:'test_all',loadingMsg:'Testing...',onSuccess:function(){location.reload();}})">
                <i class="fas fa-sync-alt"></i> <?= $lang['test_all'] ?? 'Test All' ?>
            </button>
        </div>
        <div class="aio-card-body">
            <div class="aio-health-grid">
                <?php if (!empty($providerHealth)):
                    foreach ($providerHealth as $slug => $h):
                        $ok = !empty($h['success']);
                ?>
                <div class="aio-health-item">
                    <div class="status-dot <?= $ok ? 'ok' : 'err' ?>"></div>
                    <div>
                        <div class="provider-name"><?= htmlspecialchars($providerNames[$slug] ?? ucfirst($slug)) ?></div>
                        <div class="provider-info">
                            <?php if ($ok): ?>
                                <span style="color:var(--aio-success)">Connected</span>
                            <?php else: ?>
                                <span style="color:var(--aio-danger)"><?= htmlspecialchars($h['message'] ?? 'Error') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="text-muted" style="padding:10px;">No providers configured.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-list"></i> <?= $lang['recent_orders'] ?? 'Recent Orders' ?></span>
        <a href="<?= $moduleLink ?>&page=orders" class="aio-btn aio-btn-sm aio-btn-ghost">
            <?= $lang['view_all'] ?? 'View All' ?> <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($recentOrders)): ?>
            <div class="aio-empty" style="padding:30px;">
                <i class="fas fa-inbox"></i>
                <p><?= $lang['no_orders'] ?? 'No orders found.' ?></p>
            </div>
        <?php else: ?>
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th><?= $lang['order_id'] ?? 'Order ID' ?></th>
                        <th><?= $lang['provider'] ?? 'Provider' ?></th>
                        <th><?= $lang['domain'] ?? 'Domain' ?></th>
                        <th><?= $lang['client'] ?? 'Client' ?></th>
                        <th><?= $lang['status'] ?? 'Status' ?></th>
                        <th><?= $lang['date'] ?? 'Date' ?></th>
                        <th class="text-center"><?= $lang['actions'] ?? 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $o):
                    $cfg = json_decode($o->configdata ?? '{}', true) ?: [];
                    $prov = $cfg['provider'] ?? $providerNames[$o->module] ?? $o->module;
                    $statusClass = 'aio-badge-default';
                    $st = $o->status ?? 'Unknown';
                    if (in_array($st, ['Completed', 'Issued', 'Active'])) $statusClass = 'aio-badge-success';
                    elseif (in_array($st, ['Pending', 'Processing'])) $statusClass = 'aio-badge-primary';
                    elseif (in_array($st, ['Expired', 'Cancelled', 'Revoked'])) $statusClass = 'aio-badge-danger';
                    elseif (stripos($st, 'Awaiting') !== false) $statusClass = 'aio-badge-warning';
                ?>
                <tr>
                    <td>
                        <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>" class="aio-link" style="font-weight:500;">
                            #<?= $o->id ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $pSlug = $cfg['provider'] ?? '';
                        if (!$pSlug) {
                            $mMap = ['nicsrs_ssl'=>'nicsrs','SSLCENTERWHMCS'=>'gogetssl','thesslstore_ssl'=>'thesslstore','ssl2buy'=>'ssl2buy','aio_ssl'=>'aio'];
                            $pSlug = $mMap[$o->module] ?? '';
                        }
                        $pBadge = [
                            'nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl',
                            'thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy'
                        ];
                        ?>
                        <span class="aio-provider-badge <?= $pBadge[$pSlug] ?? 'aio-badge-default' ?>">
                            <?= htmlspecialchars($providerNames[$o->module] ?? ucfirst($pSlug ?: $o->module)) ?>
                        </span>
                    </td>
                    <td class="text-mono"><?= htmlspecialchars($o->domain ?? '—') ?></td>
                    <td>
                        <?php if (!empty($o->userid)): ?>
                        <a href="clientssummary.php?userid=<?= $o->userid ?>" class="aio-link-client">
                            <?= htmlspecialchars($o->client_name ?? 'Client #' . $o->userid) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="aio-badge <?= $statusClass ?>"><?= htmlspecialchars($st) ?></span></td>
                    <td class="text-nowrap" style="color:var(--aio-text-secondary)">
                        <?= !empty($o->created_at) ? date('Y-m-d', strtotime($o->created_at)) : '—' ?>
                    </td>
                    <td class="text-center">
                        <div class="aio-btn-group">
                            <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>" class="aio-btn aio-btn-xs aio-btn-ghost">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="aio-btn aio-btn-xs" onclick="AioSSL.refreshOrder(<?= $o->id ?>)" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Expiring Soon -->
<?php if (!empty($expiringCerts)): ?>
<div class="aio-card">
    <div class="aio-card-header" style="background:var(--aio-warning-bg);">
        <span><i class="fas fa-exclamation-triangle" style="color:var(--aio-warning)"></i> <?= $lang['expiring_certs'] ?? 'Certificates Expiring Soon' ?></span>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Provider</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($expiringCerts as $c):
                    $daysLeft = $c->days_left ?? 0;
                    $urgency = $daysLeft <= 7 ? 'aio-badge-danger' : ($daysLeft <= 14 ? 'aio-badge-warning' : 'aio-badge-primary');
                ?>
                <tr>
                    <td class="text-mono"><?= htmlspecialchars($c->domain ?? '—') ?></td>
                    <td><?= htmlspecialchars($c->provider ?? '—') ?></td>
                    <td><?= htmlspecialchars($c->expiry_date ?? '—') ?></td>
                    <td><span class="aio-badge <?= $urgency ?>"><?= $daysLeft ?> days</span></td>
                    <td class="text-center">
                        <button class="aio-btn aio-btn-xs aio-btn-success">
                            <i class="fas fa-redo"></i> Renew
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js for Provider Doughnut -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    var provData = <?= json_encode($chartData['ordersByProvider'] ?? []) ?>;
    var labels = [], data = [], colors = [];
    var colorMap = <?= json_encode($providerColors) ?>;
    var nameMap = <?= json_encode($providerNames) ?>;

    for (var mod in provData) {
        labels.push(nameMap[mod] || mod);
        data.push(provData[mod]);
        colors.push(colorMap[mod] || '#d9d9d9');
    }

    if (data.length > 0) {
        new Chart(document.getElementById('chart-provider'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } }
                },
                cutout: '60%'
            }
        });
    }
})();
</script>