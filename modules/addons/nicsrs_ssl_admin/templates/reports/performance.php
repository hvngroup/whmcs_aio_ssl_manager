<?php
/**
 * Product Performance Report Template - FIXED
 * Analyze product performance, best sellers, renewal rates
 * 
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 * @var array $reportData Report data from controller (contains 'products' and 'summary')
 * @var array $filters Applied filters
 * @var array $vendors Available vendors
 */

use NicsrsAdmin\Helper\CurrencyHelper;

// Extract products and summary from reportData
$products = $reportData['products'] ?? [];
$summary = $reportData['summary'] ?? [];
?>

<div class="nicsrs-report-performance">
    
    <!-- Page Header -->
    <div class="page-header">
        <h3>
            <i class="fa fa-trophy text-warning"></i> Product Performance Report
            <a href="<?php echo $modulelink; ?>&action=reports" class="btn btn-default btn-sm pull-right">
                <i class="fa fa-arrow-left"></i> Back to Reports
            </a>
        </h3>
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
                <input type="hidden" name="report" value="performance">
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>Date From:</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?php echo $filters['date_from'] ?? ''; ?>">
                </div>
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>Date To:</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?php echo $filters['date_to'] ?? ''; ?>">
                </div>
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>Vendor:</label>
                    <select class="form-control" name="vendor">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors ?? [] as $vendor): ?>
                        <option value="<?php echo $helper->e($vendor); ?>" 
                                <?php echo ($filters['vendor'] ?? '') === $vendor ? 'selected' : ''; ?>>
                            <?php echo $helper->e($vendor); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Apply
                </button>
                <a href="<?php echo $modulelink; ?>&action=reports&report=performance" class="btn btn-default">
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
                    <h2 class="text-primary"><?php echo number_format($summary['total_products'] ?? 0); ?></h2>
                    <p class="text-muted">Products Sold</p>
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
                    <h2 class="text-info"><?php echo CurrencyHelper::formatUsd($summary['total_revenue_usd'] ?? 0); ?></h2>
                    <p class="text-muted">Total Revenue (USD)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Chart -->
    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-bar-chart"></i> Top Products by Orders</h4>
                </div>
                <div class="panel-body">
                    <canvas id="topProductsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-pie-chart"></i> By Validation Type</h4>
                </div>
                <div class="panel-body">
                    <canvas id="validationTypeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="fa fa-table"></i> Product Performance Details
                <span class="badge"><?php echo count($products); ?> products</span>
            </h4>
        </div>
        <div class="panel-body">
            <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No product data found for the selected filters.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="performanceTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th>Type</th>
                            <th class="text-center">Total Orders</th>
                            <th class="text-center">Active</th>
                            <th class="text-center">Cancelled</th>
                            <th class="text-right">Revenue (USD)</th>
                            <th class="text-right">Avg Order</th>
                            <th class="text-center">Completion Rate</th>
                            <th class="text-center">Renewal Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <?php 
                        $completionRate = $product['completion_rate'] ?? 0;
                        $renewalRate = $product['renewal_rate'] ?? 0;
                        $completionClass = $completionRate >= 80 ? 'label-success' : ($completionRate >= 50 ? 'label-warning' : 'label-danger');
                        $renewalClass = $renewalRate >= 50 ? 'label-success' : ($renewalRate >= 25 ? 'label-warning' : 'label-default');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo $helper->e($product['product_name'] ?? 'Unknown'); ?></strong>
                                <br><small class="text-muted"><?php echo $helper->e($product['product_code'] ?? ''); ?></small>
                            </td>
                            <td><span class="label label-default"><?php echo $helper->e($product['vendor'] ?? 'Unknown'); ?></span></td>
                            <td><?php echo $helper->validationBadge($product['validation_type'] ?? 'dv'); ?></td>
                            <td class="text-center"><strong><?php echo number_format($product['total_orders'] ?? 0); ?></strong></td>
                            <td class="text-center text-success"><?php echo number_format($product['active_count'] ?? 0); ?></td>
                            <td class="text-center text-danger"><?php echo number_format($product['cancelled_count'] ?? 0); ?></td>
                            <td class="text-right"><strong><?php echo CurrencyHelper::formatUsd($product['total_revenue_usd'] ?? 0); ?></strong></td>
                            <td class="text-right"><?php echo CurrencyHelper::formatUsd($product['avg_order_value_usd'] ?? 0); ?></td>
                            <td class="text-center">
                                <span class="label <?php echo $completionClass; ?>">
                                    <?php echo number_format($completionRate, 1); ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="label <?php echo $renewalClass; ?>">
                                    <?php echo number_format($renewalRate, 1); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
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
    // Get products data from PHP - take top 10 for chart
    var products = <?php echo json_encode(array_slice($products, 0, 10)); ?>;
    
    // Top Products Chart - Vertical bar chart with dual Y axis
    var topProductsCanvas = document.getElementById('topProductsChart');
    if (topProductsCanvas && products && products.length > 0) {
        var labels = products.map(function(p) { 
            var name = p.product_name || p.product_code || 'Unknown';
            return name.length > 20 ? name.substring(0, 20) + '...' : name; 
        });
        var orders = products.map(function(p) { return parseInt(p.total_orders) || 0; });
        var revenue = products.map(function(p) { return parseFloat(p.total_revenue_usd) || 0; });        
        new Chart(topProductsCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Orders',
                    data: orders,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (USD)',
                    data: revenue,
                    type: 'line',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: false,
                    yAxisID: 'y1',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: { 
                            display: true, 
                            text: 'Orders' 
                        },
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { 
                            display: true, 
                            text: 'Revenue (USD)' 
                        },
                        grid: { 
                            drawOnChartArea: false 
                        },
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y1') {
                                    label += '$' + context.parsed.y.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                } else {
                                    label += context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    } else if (topProductsCanvas) {
        // Show no data message
        topProductsCanvas.parentNode.innerHTML = '<div class="alert alert-info text-center" style="margin: 20px 0;"><i class="fa fa-info-circle"></i> No product data available for chart</div>';
    }

    // Validation Type Pie Chart
    var validationCanvas = document.getElementById('validationTypeChart');
    if (validationCanvas && products && products.length > 0) {
        var typeData = {};
        products.forEach(function(p) {
            var type = (p.validation_type || 'dv').toUpperCase();
            var orders = parseInt(p.total_orders) || 0;
            typeData[type] = (typeData[type] || 0) + orders;
        });
        
        if (Object.keys(typeData).length > 0) {
            new Chart(validationCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(typeData),
                    datasets: [{
                        data: Object.values(typeData),
                        backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { 
                            position: 'bottom' 
                        }
                    }
                }
            });
        } else {
            validationCanvas.parentNode.innerHTML = '<div class="alert alert-info text-center" style="margin: 20px 0;"><i class="fa fa-info-circle"></i> No validation type data available</div>';
        }
    } else if (validationCanvas) {
        validationCanvas.parentNode.innerHTML = '<div class="alert alert-info text-center" style="margin: 20px 0;"><i class="fa fa-info-circle"></i> No validation type data available</div>';
    }
});
</script>