<?php
/**
 * Reports Template — Admin Addon (PHP Template)
 *
 * Variables: $reportType, $reportData, $period, $moduleLink, $lang, $csrfToken
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$reportType = $reportType ?? ($_GET['type'] ?? 'revenue');
$period = $period ?? ($_GET['period'] ?? '30');
$providerNames = ['nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore','ssl2buy'=>'SSL2Buy'];
$providerBadge = ['nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl','thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy'];
$data = $reportData ?? [];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-chart-bar"></i> <?= $lang['reports_title'] ?? 'Reports' ?>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn" onclick="exportReport()">
            <i class="fas fa-file-csv"></i> <?= $lang['export_csv'] ?? 'Export CSV' ?>
        </button>
    </div>
</div>

<!-- Report Tabs + Period Filter -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div class="aio-sub-tabs" style="margin-bottom:0;border:none;">
        <?php
        $reports = [
            'revenue' => ['icon' => 'fa-dollar-sign', 'label' => $lang['revenue_by_provider'] ?? 'Revenue by Provider'],
            'products' => ['icon' => 'fa-cube', 'label' => $lang['product_performance'] ?? 'Product Performance'],
            'expiry'  => ['icon' => 'fa-exclamation-triangle', 'label' => $lang['expiry_forecast'] ?? 'Expiry Forecast'],
        ];
        foreach ($reports as $k => $r):
        ?>
        <button class="<?= $reportType === $k ? 'active' : '' ?>"
                onclick="location.href='<?= $moduleLink ?>&page=reports&type=<?= $k ?>&period=<?= $period ?>'">
            <i class="fas <?= $r['icon'] ?>"></i> <?= $r['label'] ?>
        </button>
        <?php endforeach; ?>
    </div>

    <form method="get" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="module" value="aio_ssl_admin" />
        <input type="hidden" name="page" value="reports" />
        <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>" />
        <select name="period" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 Days</option>
            <option value="365" <?= $period == '365' ? 'selected' : '' ?>>This Year</option>
            <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All Time</option>
        </select>
    </form>
</div>

<?php if ($reportType === 'revenue'): ?>
<!-- ══════════ REVENUE BY PROVIDER ══════════ -->
<?php
$totalOrders = 0; $totalRevenue = 0; $totalCost = 0;
foreach ($data as $d) {
    $totalOrders  += $d['orders'] ?? 0;
    $totalRevenue += $d['revenue'] ?? 0;
    $totalCost    += $d['cost'] ?? 0;
}
$totalProfit = $totalRevenue - $totalCost;
?>

<!-- Summary Cards -->
<div class="aio-stats-grid">
    <div class="aio-stat-card">
        <div class="aio-stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($totalOrders) ?></div>
            <div class="aio-stat-label">Total Orders</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon green"><i class="fas fa-dollar-sign"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value">$<?= number_format($totalRevenue, 2) ?></div>
            <div class="aio-stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon orange"><i class="fas fa-receipt"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value">$<?= number_format($totalCost, 2) ?></div>
            <div class="aio-stat-label">Total Cost</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon <?= $totalProfit >= 0 ? 'green' : 'red' ?>"><i class="fas fa-chart-line"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value">$<?= number_format($totalProfit, 2) ?></div>
            <div class="aio-stat-label">Net Profit</div>
        </div>
    </div>
</div>

<!-- Revenue Table -->
<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-table"></i> Revenue Breakdown by Provider</span></div>
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($data)): ?>
        <div class="aio-empty" style="padding:30px;"><i class="fas fa-chart-bar"></i><p>No revenue data for this period.</p></div>
        <?php else: ?>
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th class="text-right">Orders</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Profit</th>
                        <th class="text-right">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $d):
                    $profit = ($d['revenue'] ?? 0) - ($d['cost'] ?? 0);
                    $margin = ($d['revenue'] ?? 0) > 0 ? ($profit / $d['revenue'] * 100) : 0;
                ?>
                <tr>
                    <td>
                        <span class="aio-provider-badge <?= $providerBadge[$d['slug'] ?? ''] ?? 'aio-badge-default' ?>">
                            <?= htmlspecialchars($d['name'] ?? $providerNames[$d['slug'] ?? ''] ?? '—') ?>
                        </span>
                    </td>
                    <td class="text-right" style="font-weight:600;"><?= number_format($d['orders'] ?? 0) ?></td>
                    <td class="text-right" style="font-weight:600;">$<?= number_format($d['revenue'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format($d['cost'] ?? 0, 2) ?></td>
                    <td class="text-right <?= $profit >= 0 ? 'aio-margin-positive' : 'aio-margin-negative' ?>" style="font-weight:600;">
                        <?= $profit >= 0 ? '+' : '' ?>$<?= number_format($profit, 2) ?>
                    </td>
                    <td class="text-right">
                        <span class="aio-badge <?= $margin >= 50 ? 'aio-badge-success' : ($margin >= 20 ? 'aio-badge-primary' : 'aio-badge-warning') ?>">
                            <?= number_format($margin, 1) ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot style="font-weight:700;background:var(--aio-bg-light);">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-right"><?= number_format($totalOrders) ?></td>
                        <td class="text-right">$<?= number_format($totalRevenue, 2) ?></td>
                        <td class="text-right">$<?= number_format($totalCost, 2) ?></td>
                        <td class="text-right <?= $totalProfit >= 0 ? 'aio-margin-positive' : 'aio-margin-negative' ?>">
                            <?= $totalProfit >= 0 ? '+' : '' ?>$<?= number_format($totalProfit, 2) ?>
                        </td>
                        <td class="text-right">
                            <?= $totalRevenue > 0 ? number_format(($totalProfit / $totalRevenue) * 100, 1) : 0 ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($reportType === 'expiry'): ?>
<!-- ══════════ EXPIRY FORECAST ══════════ -->
<?php
$buckets = [
    ['label' => 'Next 7 days', 'key' => '7', 'color' => 'red', 'icon' => 'fa-exclamation-circle'],
    ['label' => '8–30 days',   'key' => '30', 'color' => 'orange', 'icon' => 'fa-exclamation-triangle'],
    ['label' => '31–60 days',  'key' => '60', 'color' => 'blue', 'icon' => 'fa-info-circle'],
    ['label' => '61–90 days',  'key' => '90', 'color' => 'green', 'icon' => 'fa-calendar'],
];
?>

<div class="aio-stats-grid">
    <?php foreach ($buckets as $b):
        $count = $data[$b['key']] ?? 0;
    ?>
    <div class="aio-stat-card">
        <div class="aio-stat-icon <?= $b['color'] ?>"><i class="fas <?= $b['icon'] ?>"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= number_format($count) ?></div>
            <div class="aio-stat-label"><?= $b['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($data['details'])): ?>
<div class="aio-card">
    <div class="aio-card-header" style="background:var(--aio-warning-bg);">
        <span><i class="fas fa-clock" style="color:var(--aio-warning)"></i> Certificates Expiring Soon</span>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead><tr><th>Domain</th><th>Provider</th><th>Client</th><th>Expiry Date</th><th>Days Left</th><th class="text-center">Action</th></tr></thead>
                <tbody>
                <?php foreach ($data['details'] as $cert):
                    $days = $cert['days_left'] ?? 0;
                    $urg = $days <= 7 ? 'aio-badge-danger' : ($days <= 30 ? 'aio-badge-warning' : 'aio-badge-primary');
                ?>
                <tr>
                    <td class="text-mono"><?= htmlspecialchars($cert['domain'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($providerNames[$cert['provider'] ?? ''] ?? $cert['provider'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($cert['client'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= htmlspecialchars($cert['expiry'] ?? '—') ?></td>
                    <td><span class="aio-badge <?= $urg ?>"><?= $days ?> days</span></td>
                    <td class="text-center">
                        <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $cert['order_id'] ?? '' ?>" class="aio-btn aio-btn-xs aio-btn-ghost">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($reportType === 'products'): ?>
<!-- ══════════ PRODUCT PERFORMANCE ══════════ -->
<?php if (!empty($data)): ?>
<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-trophy"></i> Top Products by Orders</span></div>
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead><tr><th>#</th><th>Product</th><th>Provider</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($data as $i => $p): ?>
                <tr>
                    <td style="font-weight:600;color:var(--aio-text-secondary);"><?= $i + 1 ?></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($p['product_name'] ?? '—') ?></td>
                    <td>
                        <span class="aio-provider-badge <?= $providerBadge[$p['provider'] ?? ''] ?? '' ?>">
                            <?= $providerNames[$p['provider'] ?? ''] ?? '—' ?>
                        </span>
                    </td>
                    <td class="text-right" style="font-weight:600;"><?= number_format($p['orders'] ?? 0) ?></td>
                    <td class="text-right">$<?= number_format($p['revenue'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="aio-card"><div class="aio-card-body"><div class="aio-empty"><i class="fas fa-chart-bar"></i><p>No product performance data for this period.</p></div></div></div>
<?php endif; ?>

<?php endif; ?>

<script>
function exportReport() {
    AioSSL.ajax({
        page: 'reports',
        action: 'export',
        data: { type: '<?= $reportType ?>', period: '<?= $period ?>' },
        loadingMsg: 'Generating report...',
        successMessage: false,
        onSuccess: function(r) {
            if (r.csv) {
                var blob = new Blob([r.csv], { type: 'text/csv' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = r.filename || 'report.csv';
                a.click();
                AioSSL.toast('Report exported.', 'success');
            }
        }
    });
}
</script>