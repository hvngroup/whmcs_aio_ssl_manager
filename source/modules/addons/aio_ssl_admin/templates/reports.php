<?php
/**
 * Reports Template — 6 report types with Chart.js + currency display mode
 *
 * Variables injected by ReportController via BaseController::renderTemplate():
 *   $reportType, $reportData, $chartData, $period, $filters,
 *   $currencyInfo, $providerNames, $providerBadge,
 *   $quickStats, $moduleLink, $lang, $csrfToken, $currency
 *
 * $currency = CurrencyHelper instance (injected by BaseController)
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$reportType    = $reportType ?? 'index';
$period        = $period ?? '30';
$data          = $reportData ?? [];
$cInfo         = $currencyInfo ?? [];
$provNames     = $providerNames ?? [];
$provBadge     = $providerBadge ?? [];
$displayMode   = $cInfo['display_mode'] ?? 'vnd';
$rate          = $cInfo['rate'] ?? 25000;
$rateFormatted = $cInfo['rate_formatted'] ?? '';
$vatFormatted  = $cInfo['vat_rate_formatted'] ?? '10%';

// ═══════════════════════════════════════════════════════════════
// Currency formatting helpers
//
// Revenue gốc = VND (có VAT) → hiển thị VND trực tiếp khi mode=vnd
// Cost/Profit gốc = USD → convert sang VND khi mode=vnd
// ═══════════════════════════════════════════════════════════════

// Format revenue (gốc VND) — dùng cho cột Revenue
$fmtRevenue = function(float $vndAmount, float $usdAmount) use ($currency, $displayMode) {
    switch ($displayMode) {
        case 'usd':  return $currency->formatUsd($usdAmount);
        case 'both': return $currency->formatVnd($vndAmount) . ' <small class="text-muted">(' . $currency->formatUsd($usdAmount) . ')</small>';
        default:     return $currency->formatVnd($vndAmount);
    }
};

// Format cost/profit (gốc USD) — dùng cho cột Cost, Profit
$fmtUsd = function($usdAmount) use ($currency, $displayMode) {
    if ($usdAmount === null) return '—';
    $usdAmount = (float)$usdAmount;
    switch ($displayMode) {
        case 'vnd':  return $currency->formatVnd($currency->toVnd($usdAmount));
        case 'both': return $currency->formatUsd($usdAmount) . ' <small class="text-muted">(' . $currency->formatVnd($currency->toVnd($usdAmount)) . ')</small>';
        default:     return $currency->formatUsd($usdAmount);
    }
};

// Compact format for stat cards — revenue (nhận cả VND lẫn USD)
$fmtStatRevenue = function(float $vndAmount, float $usdAmount) use ($currency, $displayMode) {
    switch ($displayMode) {
        case 'usd':  return $currency->formatUsdCompact($usdAmount);
        case 'both': return $currency->formatVndCompact($vndAmount) . ' (' . $currency->formatUsdCompact($usdAmount) . ')';
        default:     return $currency->formatVndCompact($vndAmount);
    }
};

// Format VND plain
$fmtVnd = function(float $vnd) {
    return number_format($vnd, 0, ',', '.') . ' ₫';
};

// Tab definitions
$tabs = [
    'index'    => ['icon' => 'fa-tachometer-alt', 'label' => $lang['reports_overview'] ?? 'Overview'],
    'revenue'  => ['icon' => 'fa-dollar-sign',    'label' => $lang['revenue_by_provider'] ?? 'Revenue by Provider'],
    'profit'   => ['icon' => 'fa-chart-line',     'label' => $lang['profit_analysis'] ?? 'Profit Analysis'],
    'products' => ['icon' => 'fa-cube',           'label' => $lang['product_performance'] ?? 'Product Performance'],
    'brands'   => ['icon' => 'fa-tags',           'label' => $lang['revenue_by_brand'] ?? 'Revenue by Brand'],
    'expiry'   => ['icon' => 'fa-exclamation-triangle', 'label' => $lang['expiry_forecast'] ?? 'Expiry Forecast'],
];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title"><i class="fas fa-chart-bar"></i> <?= $lang['reports_title'] ?? 'Reports' ?></h3>
    <div class="aio-toolbar">
        <?php if ($reportType !== 'index'): ?>
        <button class="aio-btn" onclick="exportReport()"><i class="fas fa-file-csv"></i> <?= $lang['export_csv'] ?? 'Export CSV' ?></button>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs + Filters -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div class="aio-sub-tabs" style="margin-bottom:0;border:none;">
        <?php foreach ($tabs as $k => $t): ?>
        <button class="<?= $reportType === $k ? 'active' : '' ?>" onclick="location.href='<?= $moduleLink ?>&page=reports&type=<?= $k ?>&period=<?= $period ?>'">
            <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php if ($reportType !== 'index'): ?>
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
        <select name="provider" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value="">All Providers</option>
            <?php foreach ($provNames as $slug => $name): ?>
            <option value="<?= $slug ?>" <?= ($filters['provider'] ?? '') === $slug ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if ($reportType !== 'index'): ?>
<div class="aio-alert aio-alert-info" style="font-size:12px;padding:8px 14px;margin-bottom:16px;">
    <i class="fas fa-info-circle"></i>
    <strong>Rate:</strong> <?= htmlspecialchars($rateFormatted) ?> |
    <strong>VAT:</strong> <?= $vatFormatted ?> |
    <strong>Display:</strong> <?= strtoupper($displayMode) ?>
    <a href="<?= $moduleLink ?>&page=settings" style="margin-left:8px;font-size:11px;">[Change]</a>
</div>
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════
// INDEX / OVERVIEW
// ═══════════════════════════════════════════════════════════════
if ($reportType === 'index'):
    $qs = $quickStats ?? [];
?>
<div class="aio-stats-grid">
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($qs['total_revenue_usd'] ?? 0) ?></div><div class="aio-stat-label">Revenue (This Month)</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon green"><i class="fas fa-chart-line"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($qs['total_profit_usd'] ?? 0) ?></div><div class="aio-stat-label">Profit | Margin: <?= $qs['profit_margin'] ?? 0 ?>%</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $qs['expiring_30d'] ?? 0 ?></div><div class="aio-stat-label">Expiring ≤30d (<?= $qs['expiring_7d'] ?? 0 ?> urgent)</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon purple"><i class="fas fa-trophy"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= htmlspecialchars($qs['top_provider'] ?? '—') ?></div><div class="aio-stat-label">Top Provider (<?= $qs['top_provider_orders'] ?? 0 ?> orders)</div></div></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;margin-top:20px;">
    <?php
    $reportCards = [
        'revenue'  => ['color' => 'blue',   'desc' => 'Revenue &amp; cost breakdown by provider. Profit margin analysis.'],
        'profit'   => ['color' => 'green',  'desc' => 'VND → remove VAT → USD → subtract cost = profit. Monthly trend.'],
        'products' => ['color' => 'orange', 'desc' => 'Top products ranked by orders, completion &amp; renewal rate.'],
        'brands'   => ['color' => 'purple', 'desc' => 'Revenue share by SSL brand. Market share doughnut chart.'],
        'expiry'   => ['color' => 'red',    'desc' => 'Certificates expiring in 7/30/60/90 days with urgency badges.'],
    ];
    foreach ($reportCards as $rk => $rc):
        $rt = $tabs[$rk] ?? [];
    ?>
    <div class="aio-card" style="cursor:pointer;border-left:3px solid var(--aio-<?= $rc['color'] ?>, #1890ff);"
         onclick="location.href='<?= $moduleLink ?>&page=reports&type=<?= $rk ?>&period=<?= $period ?>'">
        <div class="aio-card-body">
            <h4 style="margin:0 0 8px;"><i class="fas <?= $rt['icon'] ?? '' ?>"></i> <?= $rt['label'] ?? '' ?></h4>
            <p style="margin:0;font-size:13px;color:var(--aio-text-secondary);"><?= $rc['desc'] ?></p>
            <div style="margin-top:10px;font-size:12px;font-weight:600;color:var(--aio-primary);">View Report →</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>


<?php
// ═══════════════════════════════════════════════════════════════
// REVENUE BY PROVIDER
// ═══════════════════════════════════════════════════════════════
elseif ($reportType === 'revenue'):
    $providers = $data['providers'] ?? [];
    $totals    = $data['totals'] ?? [];
?>
<div class="aio-stats-grid">
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtStatRevenue($totals['revenue_vnd'] ?? 0, $totals['revenue_usd'] ?? 0) ?></div><div class="aio-stat-label">Total Revenue (<?= $totals['orders'] ?? 0 ?> orders)</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($totals['cost_usd'] ?? 0) ?></div><div class="aio-stat-label">Total Cost</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon <?= ($totals['profit_usd'] ?? 0) >= 0 ? 'green' : 'red' ?>"><i class="fas fa-chart-line"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($totals['profit_usd'] ?? 0) ?></div><div class="aio-stat-label">Net Profit</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon purple"><i class="fas fa-percentage"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $totals['margin'] ?? 0 ?>%</div><div class="aio-stat-label">Average Margin</div></div></div>
</div>

<div class="aio-card" style="margin-bottom:16px;">
    <div class="aio-card-header"><span><i class="fas fa-chart-bar"></i> Revenue by Provider</span></div>
    <div class="aio-card-body"><canvas id="revenueChart" height="200"></canvas></div>
</div>

<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-table"></i> Revenue Breakdown</span></div>
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($providers)): ?>
        <div class="aio-empty" style="padding:30px;"><i class="fas fa-chart-bar"></i><p>No revenue data for this period.</p></div>
        <?php else: ?>
        <div class="aio-table-wrapper"><table class="aio-table">
            <thead><tr><th>Provider</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $p):
                $profitClass = ($p['profit_usd'] ?? 0) >= 0 ? 'aio-margin-positive' : 'aio-margin-negative';
                $marginClass = ($p['margin'] ?? 0) >= 50 ? 'aio-badge-success' : (($p['margin'] ?? 0) >= 20 ? 'aio-badge-primary' : 'aio-badge-warning');
            ?>
            <tr>
                <td><span class="aio-provider-badge <?= $provBadge[$p['slug']] ?? '' ?>"><?= htmlspecialchars($p['name']) ?></span></td>
                <td class="text-right" style="font-weight:600;"><?= number_format($p['orders']) ?></td>
                <td class="text-right" style="font-weight:600;"><?= $fmtRevenue($p['revenue_vnd'], $p['revenue_usd']) ?></td>
                <td class="text-right"><?= $fmtUsd($p['cost_usd']) ?></td>
                <td class="text-right <?= $profitClass ?>" style="font-weight:600;"><?= ($p['profit_usd'] >= 0 ? '+' : '') . $fmtUsd($p['profit_usd']) ?></td>
                <td class="text-right"><span class="aio-badge <?= $marginClass ?>"><?= $p['margin'] ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot style="font-weight:700;background:#fafafa;"><tr>
                <td>TOTAL</td>
                <td class="text-right"><?= number_format($totals['orders']) ?></td>
                <td class="text-right"><?= $fmtRevenue($totals['revenue_vnd'] ?? 0, $totals['revenue_usd']) ?></td>
                <td class="text-right"><?= $fmtUsd($totals['cost_usd']) ?></td>
                <td class="text-right <?= ($totals['profit_usd'] ?? 0) >= 0 ? 'aio-margin-positive' : 'aio-margin-negative' ?>"><?= ($totals['profit_usd'] >= 0 ? '+' : '') . $fmtUsd($totals['profit_usd']) ?></td>
                <td class="text-right"><?= $totals['margin'] ?>%</td>
            </tr></tfoot>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chartData)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cd = <?= $chartData ?>;
    if (cd.labels && cd.labels.length && typeof Chart !== 'undefined') {
        new Chart(document.getElementById('revenueChart').getContext('2d'), {
            type: 'bar', data: { labels: cd.labels, datasets: [
                { label: 'Revenue (USD)', data: cd.revenue, backgroundColor: cd.colors },
                { label: 'Cost (USD)', data: cd.cost, backgroundColor: 'rgba(250,173,20,0.4)' }
            ]}, options: { responsive: true, indexAxis: 'y', plugins: { legend: { position: 'bottom' } },
                scales: { x: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v.toLocaleString(); } } } } }
        });
    }
});
</script>
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════
// PROFIT ANALYSIS
// ═══════════════════════════════════════════════════════════════
elseif ($reportType === 'profit'):
    $summary = $data['summary'] ?? [];
    $orders  = $data['orders'] ?? [];
?>
<div class="aio-alert aio-alert-info" style="font-size:12px;padding:10px 14px;margin-bottom:16px;">
    <i class="fas fa-calculator"></i>
    <strong>Formula:</strong> Revenue (VND + <?= $vatFormatted ?> VAT) → Remove VAT (÷ 1.1) → Convert USD (÷ <?= number_format($rate, 0) ?>) → Subtract Provider Cost = <strong>Profit</strong>
</div>

<div class="aio-stats-grid">
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-money-bill"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtVnd($summary['total_revenue_vnd'] ?? 0) ?></div><div class="aio-stat-label">Revenue (VND with VAT)</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-exchange-alt"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtVnd($summary['total_revenue_vnd_no_vat'] ?? 0) ?></div><div class="aio-stat-label">After VAT = <?= $currency->formatUsd($summary['total_revenue_usd'] ?? 0) ?></div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($summary['total_cost_usd'] ?? 0) ?></div><div class="aio-stat-label">Total Cost (providers)</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon <?= ($summary['total_profit_usd'] ?? 0) >= 0 ? 'green' : 'red' ?>"><i class="fas fa-chart-line"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtUsd($summary['total_profit_usd'] ?? 0) ?></div><div class="aio-stat-label">Net Profit | Margin: <?= $summary['profit_margin'] ?? 0 ?>%</div></div></div>
</div>

<div class="aio-card" style="margin-bottom:16px;">
    <div class="aio-card-header"><span><i class="fas fa-chart-bar"></i> Profit Trend</span></div>
    <div class="aio-card-body"><canvas id="profitChart" height="200"></canvas></div>
</div>

<?php if (!empty($orders)): ?>
<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-list"></i> Order Profit Detail (<?= count($orders) ?> orders)</span></div>
    <div class="aio-card-body" style="padding:0;"><div class="aio-table-wrapper"><table class="aio-table">
        <thead><tr><th>Date</th><th>Product</th><th>Provider</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($orders, 0, 50) as $o):
            $pc = ($o['profit_usd'] ?? 0) >= 0 ? 'aio-margin-positive' : 'aio-margin-negative';
        ?>
        <tr>
            <td class="text-nowrap"><?= htmlspecialchars($o['service_date'] ?? '—') ?></td>
            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($o['product_name'] ?? '—') ?></td>
            <td><span class="aio-provider-badge <?= $provBadge[$o['provider'] ?? ''] ?? '' ?>"><?= $provNames[$o['provider'] ?? ''] ?? '—' ?></span></td>
            <td class="text-right"><?= $fmtRevenue($o['revenue_vnd'] ?? 0, $o['revenue_usd'] ?? 0) ?></td>
            <td class="text-right"><?= $fmtUsd($o['cost_usd'] ?? 0) ?></td>
            <td class="text-right <?= $pc ?>" style="font-weight:600;"><?= ($o['profit_usd'] >= 0 ? '+' : '') . $fmtUsd($o['profit_usd'] ?? 0) ?></td>
            <td class="text-right"><?= $o['margin'] ?? 0 ?>%</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</div>
<?php endif; ?>

<?php if (!empty($chartData)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cd = <?= $chartData ?>;
    if (cd.labels && cd.labels.length && typeof Chart !== 'undefined') {
        new Chart(document.getElementById('profitChart').getContext('2d'), {
            type: 'bar', data: { labels: cd.labels, datasets: [
                { label: 'Revenue (USD)', data: cd.revenue, backgroundColor: 'rgba(24,144,255,0.3)', borderColor: '#1890ff', borderWidth: 1 },
                { label: 'Cost (USD)', data: cd.cost, backgroundColor: 'rgba(250,173,20,0.3)', borderColor: '#faad14', borderWidth: 1 },
                { label: 'Profit (USD)', data: cd.profit, backgroundColor: '#52c41a', borderColor: '#389e0d', borderWidth: 1 }
            ]}, options: { responsive: true, plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v.toLocaleString(); } } } } }
        });
    }
});
</script>
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════
// PRODUCT PERFORMANCE
// ═══════════════════════════════════════════════════════════════
elseif ($reportType === 'products'):
    $products = $data['products'] ?? [];
    $pSummary = $data['summary'] ?? [];
?>
<div class="aio-stats-grid">
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-box"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $pSummary['total_products'] ?? 0 ?></div><div class="aio-stat-label">Products</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon green"><i class="fas fa-shopping-cart"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= number_format($pSummary['total_orders'] ?? 0) ?></div><div class="aio-stat-label">Total Orders</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-check-circle"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $pSummary['avg_completion'] ?? 0 ?>%</div><div class="aio-stat-label">Avg Completion</div></div></div>
    <div class="aio-stat-card"><div class="aio-stat-icon purple"><i class="fas fa-sync"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= $pSummary['avg_renewal'] ?? 0 ?>%</div><div class="aio-stat-label">Avg Renewal Rate</div></div></div>
</div>

<?php if (!empty($products)): ?>
<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-trophy"></i> Product Ranking</span></div>
    <div class="aio-card-body" style="padding:0;"><div class="aio-table-wrapper"><table class="aio-table">
        <thead><tr><th style="width:30px;">#</th><th>Product</th><th>Provider</th><th>Type</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">Completion</th><th class="text-right">Renewal</th></tr></thead>
        <tbody>
        <?php foreach ($products as $i => $p):
            $medal = $i < 3 ? ['🥇','🥈','🥉'][$i] : ($i + 1);
            $typeClass = ($p['validation_type'] ?? '') === 'EV' ? 'aio-badge-success' : (($p['validation_type'] ?? '') === 'OV' ? 'aio-badge-purple' : 'aio-badge-primary');
        ?>
        <tr>
            <td style="font-weight:600;text-align:center;"><?= $medal ?></td>
            <td style="font-weight:500;"><?= htmlspecialchars($p['product_name'] ?? '—') ?></td>
            <td><span class="aio-provider-badge <?= $provBadge[$p['provider'] ?? ''] ?? '' ?>"><?= $provNames[$p['provider'] ?? ''] ?? '—' ?></span></td>
            <td><span class="aio-badge <?= $typeClass ?>"><?= $p['validation_type'] ?? 'DV' ?></span></td>
            <td class="text-right" style="font-weight:600;"><?= number_format($p['total_orders'] ?? 0) ?></td>
            <td class="text-right" style="font-weight:600;"><?= $fmtRevenue($p['revenue_vnd'] ?? 0, $p['revenue_usd'] ?? 0) ?></td>
            <td class="text-right">
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                    <div style="width:50px;height:6px;background:var(--aio-bg);border-radius:3px;overflow:hidden;"><div style="width:<?= $p['completion_rate'] ?? 0 ?>%;height:100%;background:<?= ($p['completion_rate'] ?? 0) >= 90 ? 'var(--aio-success)' : 'var(--aio-warning)' ?>;border-radius:3px;"></div></div>
                    <span style="font-size:12px;"><?= $p['completion_rate'] ?? 0 ?>%</span>
                </div>
            </td>
            <td class="text-right">
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                    <div style="width:50px;height:6px;background:var(--aio-bg);border-radius:3px;overflow:hidden;"><div style="width:<?= $p['renewal_rate'] ?? 0 ?>%;height:100%;background:<?= ($p['renewal_rate'] ?? 0) >= 60 ? 'var(--aio-success)' : (($p['renewal_rate'] ?? 0) >= 40 ? 'var(--aio-warning)' : 'var(--aio-danger)') ?>;border-radius:3px;"></div></div>
                    <span style="font-size:12px;"><?= $p['renewal_rate'] ?? 0 ?>%</span>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</div>
<?php else: ?>
<div class="aio-card"><div class="aio-card-body"><div class="aio-empty"><i class="fas fa-cube"></i><p>No product data for this period.</p></div></div></div>
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════
// REVENUE BY BRAND
// ═══════════════════════════════════════════════════════════════
elseif ($reportType === 'brands'):
    $brands  = $data['brands'] ?? [];
    $bTotals = $data['totals'] ?? [];
?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;margin-bottom:16px;">
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-chart-pie"></i> Market Share by Brand</span></div>
        <div class="aio-card-body" style="display:flex;justify-content:center;"><canvas id="brandChart" style="max-width:400px;max-height:300px;"></canvas></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="aio-stat-card"><div class="aio-stat-icon blue"><i class="fas fa-tags"></i></div>
            <div class="aio-stat-content"><div class="aio-stat-value"><?= $bTotals['total_brands'] ?? 0 ?></div><div class="aio-stat-label">Brands</div></div></div>
        <div class="aio-stat-card"><div class="aio-stat-icon green"><i class="fas fa-dollar-sign"></i></div>
            <div class="aio-stat-content"><div class="aio-stat-value"><?= $fmtStatRevenue($bTotals['total_revenue_vnd'] ?? 0, $bTotals['total_revenue_usd'] ?? 0) ?></div><div class="aio-stat-label">Total Revenue</div></div></div>
        <div class="aio-stat-card"><div class="aio-stat-icon orange"><i class="fas fa-shopping-cart"></i></div>
            <div class="aio-stat-content"><div class="aio-stat-value"><?= number_format($bTotals['total_orders'] ?? 0) ?></div><div class="aio-stat-label">Total Orders</div></div></div>
    </div>
</div>

<?php if (!empty($brands)): ?>
<div class="aio-card">
    <div class="aio-card-header"><span><i class="fas fa-list-ol"></i> Brand Revenue Ranking</span></div>
    <div class="aio-card-body" style="padding:0;"><div class="aio-table-wrapper"><table class="aio-table">
        <thead><tr><th>#</th><th>Brand</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">Market Share</th></tr></thead>
        <tbody>
        <?php foreach ($brands as $i => $b): ?>
        <tr>
            <td style="font-weight:600;"><?= $i + 1 ?></td>
            <td style="font-weight:500;"><?= htmlspecialchars($b['brand']) ?></td>
            <td class="text-right"><?= number_format($b['orders']) ?></td>
            <td class="text-right" style="font-weight:600;"><?= $fmtRevenue($b['revenue_vnd'] ?? 0, $b['revenue_usd'] ?? 0) ?></td>
            <td class="text-right"><span class="aio-badge aio-badge-primary"><?= $b['share_pct'] ?>%</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</div>
<?php endif; ?>

<?php if (!empty($chartData)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cd = <?= $chartData ?>;
    if (cd.labels && cd.labels.length && typeof Chart !== 'undefined') {
        new Chart(document.getElementById('brandChart').getContext('2d'), {
            type: 'doughnut', data: { labels: cd.labels, datasets: [{ data: cd.data, backgroundColor: cd.colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } },
                tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': $' + ctx.raw.toLocaleString(); } } } } }
        });
    }
});
</script>
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════
// EXPIRY FORECAST
// ═══════════════════════════════════════════════════════════════
elseif ($reportType === 'expiry'):
    $buckets = $data['buckets'] ?? [];
    $details = $data['details'] ?? [];
    $expiryDefs = [
        ['key' => '7',  'label' => '≤ 7 days',  'color' => 'red',    'icon' => 'fa-exclamation-circle'],
        ['key' => '30', 'label' => '8–30 days',  'color' => 'orange', 'icon' => 'fa-exclamation-triangle'],
        ['key' => '60', 'label' => '31–60 days', 'color' => 'blue',   'icon' => 'fa-info-circle'],
        ['key' => '90', 'label' => '61–90 days', 'color' => 'green',  'icon' => 'fa-calendar'],
    ];
?>
<div class="aio-stats-grid">
    <?php foreach ($expiryDefs as $b): ?>
    <div class="aio-stat-card"><div class="aio-stat-icon <?= $b['color'] ?>"><i class="fas <?= $b['icon'] ?>"></i></div>
        <div class="aio-stat-content"><div class="aio-stat-value"><?= number_format($buckets[$b['key']] ?? 0) ?></div><div class="aio-stat-label"><?= $b['label'] ?></div></div></div>
    <?php endforeach; ?>
</div>

<?php if (!empty($details)): ?>
<div class="aio-card">
    <div class="aio-card-header" style="background:var(--aio-warning-bg,#fffbe6);"><span><i class="fas fa-clock" style="color:var(--aio-warning);"></i> Certificates Expiring Soon (<?= count($details) ?>)</span></div>
    <div class="aio-card-body" style="padding:0;"><div class="aio-table-wrapper"><table class="aio-table">
        <thead><tr><th>Domain</th><th>Provider</th><th>Client</th><th>Expiry Date</th><th class="text-right">Days Left</th><th class="text-center">Action</th></tr></thead>
        <tbody>
        <?php foreach ($details as $cert):
            $days = $cert['days_left'] ?? 0;
            $urgClass = $days <= 7 ? 'aio-badge-danger' : ($days <= 30 ? 'aio-badge-warning' : 'aio-badge-primary');
            $rowBg = $days <= 7 ? 'background:rgba(255,77,79,0.05);' : '';
            $detailSource = match ($cert['source_table'] ?? '') {
                'mod_aio_ssl_orders' => 'aio', 'nicsrs_sslorders' => 'nicsrs', 'tblsslorders' => 'tblssl', default => '',
            };
        ?>
        <tr style="<?= $rowBg ?>">
            <td class="text-mono" style="font-weight:500;"><?= htmlspecialchars($cert['domain'] ?? '—') ?></td>
            <td><span class="aio-provider-badge <?= $provBadge[$cert['provider'] ?? ''] ?? '' ?>"><?= $provNames[$cert['provider'] ?? ''] ?? '—' ?></span></td>
            <td><?= htmlspecialchars($cert['client_name'] ?? '—') ?></td>
            <td class="text-nowrap"><?= htmlspecialchars($cert['expiry'] ?? '—') ?></td>
            <td class="text-right"><span class="aio-badge <?= $urgClass ?>"><?= $days <= 7 ? '🚨 ' : '' ?><?= $days ?> days</span></td>
            <td class="text-center"><a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $cert['order_id'] ?? '' ?>&source=<?= $detailSource ?>" class="aio-btn aio-btn-xs aio-btn-ghost"><i class="fas fa-eye"></i> View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</div>
<?php else: ?>
<div class="aio-card"><div class="aio-card-body"><div class="aio-empty"><i class="fas fa-calendar-check"></i><p>No certificates expiring within 90 days.</p></div></div></div>
<?php endif; ?>

<?php endif; ?>

<!-- Export JS -->
<script>
function exportReport() {
    AioSSL.ajax({
        page: 'reports', action: 'export',
        data: { type: '<?= htmlspecialchars($reportType) ?>', period: '<?= htmlspecialchars($period) ?>' },
        loadingMsg: 'Generating report...', successMessage: false,
        onSuccess: function(r) {
            if (r.csv) {
                var blob = new Blob([r.csv], { type: 'text/csv;charset=utf-8;' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = r.filename || 'report.csv';
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                AioSSL.toast('Report exported.', 'success');
            }
        }
    });
}
</script>