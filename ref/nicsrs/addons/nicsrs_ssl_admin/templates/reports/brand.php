<?php
/**
 * Revenue by Brand Report Template - FIXED
 * 
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 * @var array $reportData Brand report data
 * @var array $trendData Trend chart data
 * @var array $datePresets Date presets
 * @var array $filters Current filters
 */

use NicsrsAdmin\Helper\CurrencyHelper;

$filters = $filters ?? [];
$brands = $reportData['brands'] ?? [];
$summary = $reportData['summary'] ?? [];
$trendData = $trendData ?? ['labels' => [], 'datasets' => []];
?>

<div class="nicsrs-reports nicsrs-brand-report">
    
    <!-- Page Header -->
    <div class="page-header clearfix">
        <h3 class="pull-left">
            <i class="fa fa-building"></i> Revenue by Brand Report
        </h3>
        <div class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=reports" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Reports
            </a>
            <a href="<?php echo $modulelink; ?>&action=reports&report=brand&export=csv&<?php echo http_build_query($filters); ?>" 
               class="btn btn-success">
                <i class="fa fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fa fa-filter"></i> Filters</h4>
        </div>
        <div class="panel-body">
            <form method="get" class="form-inline">
                <input type="hidden" name="module" value="nicsrs_ssl_admin">
                <input type="hidden" name="action" value="reports">
                <input type="hidden" name="report" value="brand">
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>Date From:</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?php echo $helper->e($filters['date_from'] ?? ''); ?>">
                </div>
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>To:</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?php echo $helper->e($filters['date_to'] ?? ''); ?>">
                </div>

                <div class="form-group" style="margin-right: 15px;">
                    <label>Period:</label>
                    <select class="form-control" name="period">
                        <option value="month" <?php echo ($filters['period'] ?? 'month') === 'month' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarter" <?php echo ($filters['period'] ?? '') === 'quarter' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="year" <?php echo ($filters['period'] ?? '') === 'year' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Apply
                </button>
                <a href="<?php echo $modulelink; ?>&action=reports&report=brand" class="btn btn-default">
                    <i class="fa fa-refresh"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-4">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <h2 class="text-primary"><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd'] ?? 0); ?></h2>
                    <p class="text-muted">Total Revenue (VND)</p>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatUsd($summary['total_revenue_usd'] ?? 0); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h2 class="text-success"><?php echo number_format($summary['total_orders'] ?? 0); ?></h2>
                    <p class="text-muted">Total Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h2 class="text-info"><?php echo number_format($summary['brand_count'] ?? 0); ?></h2>
                    <p class="text-muted">Active Brands</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Brand Distribution Pie -->
        <div class="col-md-5">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-pie-chart"></i> Revenue Distribution</h4>
                </div>
                <div class="panel-body">
                    <?php if (empty($brands)): ?>
                    <div class="alert alert-info">No data available</div>
                    <?php else: ?>
                    <canvas id="brandPieChart" height="280"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Brand Trend Chart -->
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-area-chart"></i> Revenue Trend by Brand</h4>
                </div>
                <div class="panel-body">
                    <?php if (empty($trendData['labels'])): ?>
                    <div class="alert alert-info">No trend data available</div>
                    <?php else: ?>
                    <canvas id="brandTrendChart" height="170"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Brand Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="fa fa-table"></i> Revenue by Brand
                <span class="badge"><?php echo count($brands); ?> brands</span>
            </h4>
        </div>
        <div class="panel-body">
            <?php if (empty($brands)): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No brand data found for the selected filters.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="brandTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Brand / Vendor</th>
                            <th class="text-center">Orders</th>
                            <th class="text-right">Revenue (VND)</th>
                            <th class="text-right">Revenue (USD)</th>
                            <th class="text-center">Market Share</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($brands as $brand): ?>
                        <?php 
                        $shareClass = $brand['percentage'] >= 30 ? 'success' : ($brand['percentage'] >= 15 ? 'info' : 'default');
                        ?>
                        <tr>
                            <td><strong>#<?php echo $rank++; ?></strong></td>
                            <td>
                                <span class="label label-primary" style="font-size: 14px;">
                                    <?php echo $helper->e($brand['vendor']); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo number_format($brand['order_count']); ?></td>
                            <td class="text-right"><strong><?php echo CurrencyHelper::formatVnd($brand['total_revenue_vnd']); ?></strong></td>
                            <td class="text-right"><span class="text-info"><?php echo CurrencyHelper::formatUsd($brand['total_revenue_usd']); ?></span></td>
                            <td class="text-center">
                                <span class="label label-<?php echo $shareClass; ?>">
                                    <?php echo number_format($brand['percentage'], 1); ?>%
                                </span>
                            </td>
                            <td style="width: 200px;">
                                <div class="progress" style="margin-bottom: 0;">
                                    <div class="progress-bar progress-bar-<?php echo $shareClass; ?>" 
                                         role="progressbar"
                                         style="width: <?php echo min($brand['percentage'], 100); ?>%;">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="active">
                            <th colspan="2" class="text-right">Total:</th>
                            <th class="text-center"><?php echo number_format($summary['total_orders'] ?? 0); ?></th>
                            <th class="text-right"><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd'] ?? 0); ?></th>
                            <th class="text-right"><?php echo CurrencyHelper::formatUsd($summary['total_revenue_usd'] ?? 0); ?></th>
                            <th class="text-center"><span class="label label-primary">100%</span></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Brand data from PHP
    var brandsData = <?php echo json_encode($brands); ?>;
    var trendData = <?php echo json_encode($trendData); ?>;
    
    // Brand colors mapping
    var brandColors = {
        'Sectigo': '#1a5276',
        'Comodo': '#2980b9',
        'Positive': '#2980b9',
        'DigiCert': '#27ae60',
        'GeoTrust': '#8e44ad',
        'Thawte': '#d35400',
        'RapidSSL': '#c0392b',
        'GoGetSSL': '#16a085',
        'GlobalSign': '#2c3e50',
        'Unknown': '#95a5a6'
    };
    
    var defaultColors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#7f8c8d'];
    
    function getColor(vendor, index) {
        return brandColors[vendor] || defaultColors[index % defaultColors.length];
    }

    // ========== PIE CHART ==========
    var pieCanvas = document.getElementById('brandPieChart');
    if (pieCanvas && brandsData && brandsData.length > 0) {
        var pieCtx = pieCanvas.getContext('2d');
        var pieLabels = brandsData.map(function(b) { return b.vendor || 'Unknown'; });
        var pieValues = brandsData.map(function(b) { return parseFloat(b.total_revenue_usd) || 0; });
        var pieColors = brandsData.map(function(b, i) { return getColor(b.vendor, i); });
        
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieValues,
                    backgroundColor: pieColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = context.raw;
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return context.label + ': $' + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // ========== TREND CHART ==========
    var trendCanvas = document.getElementById('brandTrendChart');
    if (trendCanvas && trendData && trendData.labels && trendData.labels.length > 0 && trendData.datasets) {
        var trendCtx = trendCanvas.getContext('2d');
        var datasets = [];
        var vendorIndex = 0;
        
        // Build datasets from trendData.datasets object
        for (var vendor in trendData.datasets) {
            if (trendData.datasets.hasOwnProperty(vendor)) {
                var color = getColor(vendor, vendorIndex);
                datasets.push({
                    label: vendor,
                    data: trendData.datasets[vendor],
                    borderColor: color,
                    backgroundColor: color + '40', // 25% opacity
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                });
                vendorIndex++;
            }
        }
        
        if (datasets.length > 0) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Period'
                            }
                        },
                        y: {
                            display: true,
                            stacked: false,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue (USD)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.raw.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>