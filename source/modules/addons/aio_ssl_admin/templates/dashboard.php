<?php

use AioSSL\Helper\ViewHelper;
use AioSSL\Helper\CurrencyHelper;
?>

<!-- Statistics Cards -->
<div class="row">
    <?php
    $cards = [
        ['label' => 'Total Orders',    'value' => $stats['total'],    'icon' => 'fa-box',               'color' => '#1890ff'],
        ['label' => 'Pending',         'value' => $stats['pending'],  'icon' => 'fa-clock',             'color' => '#faad14'],
        ['label' => 'Issued',          'value' => $stats['issued'],   'icon' => 'fa-check-circle',      'color' => '#52c41a'],
        ['label' => 'Expiring (30d)',  'value' => $stats['expiring'], 'icon' => 'fa-exclamation-triangle','color' => '#ff4d4f'],
    ];
    foreach ($cards as $card): ?>
    <div class="col-sm-3">
        <div class="panel panel-default" style="border-left:3px solid <?= $card['color'] ?>;">
            <div class="panel-body text-center">
                <i class="fas <?= $card['icon'] ?> fa-2x" style="color:<?= $card['color'] ?>;margin-bottom:8px;"></i>
                <h3 style="margin:5px 0;"><?= number_format($card['value']) ?></h3>
                <p class="text-muted" style="margin:0;font-size:12px;"><?= $card['label'] ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Provider Health -->
<div class="row">
    <div class="col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-plug"></i> Provider Status</div>
            <table class="table table-condensed table-striped" style="margin:0;">
                <thead><tr><th>Provider</th><th>Tier</th><th>Status</th><th>API</th><th>Last Sync</th></tr></thead>
                <tbody>
                <?php foreach ($providers as $p): ?>
                <tr>
                    <td><?= ViewHelper::providerBadge($p->slug) ?></td>
                    <td><?= ViewHelper::tierBadge($p->tier) ?></td>
                    <td><?= $p->is_enabled ? '<span class="text-success">● On</span>' : '<span class="text-muted">● Off</span>' ?></td>
                    <td><?= ViewHelper::testResultIcon($p->test_result) ?></td>
                    <td style="font-size:11px;"><?= ViewHelper::formatDate($p->last_sync, 'M j, H:i') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-chart-pie"></i> Orders by Status</div>
            <div class="panel-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="panel panel-default">
    <div class="panel-heading"><i class="fas fa-list-alt"></i> Recent Orders</div>
    <table class="table table-condensed table-striped" style="margin:0;">
        <thead><tr><th>#</th><th>Provider</th><th>Domain</th><th>Product</th><th>Client</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): $cd = json_decode($o->configdata, true) ?: []; ?>
        <tr>
            <td><a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>">#<?= $o->id ?></a></td>
            <td><?= ViewHelper::providerBadge($cd['provider'] ?? $o->module) ?></td>
            <td><?= htmlspecialchars($o->domain ?? '') ?></td>
            <td style="font-size:11px;"><?= htmlspecialchars($o->certtype) ?></td>
            <td style="font-size:11px;"><?= htmlspecialchars($o->client_name ?? '') ?></td>
            <td><?= ViewHelper::statusLabel($o->status) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
        <tr><td colspan="6" class="text-center text-muted">No orders found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Footer -->
<div class="text-center" style="padding:15px;color:#999;font-size:11px;">
    HVN — AIO SSL Manager v<?= $moduleVersion ?> · Powered by <a href="https://hvn.vn" target="_blank">HVN GROUP</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
var statusData = <?= json_encode($chartData['statusDistribution'] ?? []) ?>;
var labels = Object.keys(statusData);
var values = Object.values(statusData);
var colors = labels.map(function(s) {
    var m = {Completed:'#52c41a',Pending:'#faad14',Processing:'#1890ff',Cancelled:'#ff4d4f',Expired:'#999',
             'Awaiting Configuration':'#d9d9d9',Rejected:'#ff7a45',Revoked:'#722ed1'};
    return m[s] || '#999';
});
if (document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: colors }] },
        options: { responsive: true, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: {size:11} } } } }
    });
}
</script>