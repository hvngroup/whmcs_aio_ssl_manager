<?php
/**
 * SSL Sales Report Template - UPDATED
 * 
 * CURRENCY NOTES:
 * - sale_amount_vnd = Original VND from WHMCS (including 10% VAT)
 * - sale_amount_usd = Converted to USD (after removing VAT)
 * 
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 * @var array $reportData Sales report data
 * @var array $chartData Chart data
 * @var array $productData Sales by product
 * @var array $vendors Available vendors
 * @var array $products Available products
 * @var array $datePresets Date presets
 * @var array $filters Current filters
 */

use NicsrsAdmin\Helper\CurrencyHelper;

$filters = $filters ?? [];
$summary = $reportData['summary'] ?? [];
$rateInfo = CurrencyHelper::getRateInfo();
?>

<div class="nicsrs-reports nicsrs-sales-report">
    
    <!-- Page Header -->
    <div class="page-header clearfix">
        <h3 class="pull-left">
            <i class="fa fa-line-chart"></i> SSL Sales Report
        </h3>
        <div class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=reports" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Reports
            </a>
            <a href="<?php echo $modulelink; ?>&action=reports&report=sales&export=csv&<?php echo http_build_query($filters); ?>" 
               class="btn btn-success">
                <i class="fa fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Currency Info Banner -->
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        <strong>Note:</strong> Revenue shown in USD is converted from VND (after removing 10% VAT).
        Exchange rate: <strong><?php echo $rateInfo['rate_formatted']; ?></strong>
        <small class="text-muted">(Updated: <?php echo $rateInfo['last_updated_formatted']; ?>)</small>
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
                <input type="hidden" name="report" value="sales">
                
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
                
                <div class="form-group" style="margin-right: 15px;">
                    <label>Status:</label>
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="complete" <?php echo ($filters['status'] ?? '') === 'complete' ? 'selected' : ''; ?>>Complete</option>
                        <option value="pending" <?php echo ($filters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="cancelled" <?php echo ($filters['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Apply
                </button>
                <a href="<?php echo $modulelink; ?>&action=reports&report=sales" class="btn btn-default">
                    <i class="fa fa-refresh"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <h2 class="text-primary"><?php echo CurrencyHelper::formatVnd($summary['total_sales_vnd'] ?? 0); ?></h2>
                    <p class="text-muted">Total Revenue (VND with VAT)</p>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatUsd($summary['total_sales_usd'] ?? 0); ?> (excl. VAT)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h2 class="text-success"><?php echo number_format($summary['order_count'] ?? 0); ?></h2>
                    <p class="text-muted">Total Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h2 class="text-info"><?php echo CurrencyHelper::formatVnd($summary['avg_order_value_vnd'] ?? 0); ?></h2>
                    <p class="text-muted">Average Order Value</p>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatUsd($summary['avg_order_value'] ?? 0); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <h2 class="text-warning"><?php echo CurrencyHelper::formatVnd($summary['total_recurring_vnd'] ?? 0); ?></h2>
                    <p class="text-muted">Recurring Revenue</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Sales Trend Chart -->
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-line-chart"></i> Sales Trend</h4>
                </div>
                <div class="panel-body">
                    <canvas id="salesTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Sales by Product Chart -->
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-pie-chart"></i> Sales by Product</h4>
                </div>
                <div class="panel-body">
                    <canvas id="productPieChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Data Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="fa fa-table"></i> Sales Details
                <span class="badge"><?php echo count($reportData['orders'] ?? []); ?> orders</span>
            </h4>
        </div>
        <div class="panel-body">
            <?php if (empty($reportData['orders'])): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No orders found for the selected filters.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="salesTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th>Client</th>
                            <th class="text-right">Amount (VND)</th>
                            <th class="text-right">Amount (USD)</th>
                            <th>Billing</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['orders'] as $order): ?>
                        <tr>
                            <td>
                                <a href="<?php echo $modulelink; ?>&action=orders&id=<?php echo $order['order_id']; ?>">
                                    #<?php echo $order['order_id']; ?>
                                </a>
                            </td>
                            <td><?php echo $helper->formatDate($order['provision_date']); ?></td>
                            <td>
                                <strong><?php echo $helper->e($order['product_name']); ?></strong>
                                <br><small class="text-muted"><?php echo $helper->e($order['product_code']); ?></small>
                            </td>
                            <td>
                                <span class="label label-default"><?php echo $helper->e($order['vendor']); ?></span>
                            </td>
                            <td>
                                <?php echo $helper->e($order['client_name']); ?>
                                <?php if (!empty($order['company_name'])): ?>
                                <br><small class="text-muted"><?php echo $helper->e($order['company_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <strong><?php echo CurrencyHelper::formatVnd($order['sale_amount_vnd'] ?? 0); ?></strong>
                            </td>
                            <td class="text-right">
                                <span class="text-info"><?php echo CurrencyHelper::formatUsd($order['sale_amount_usd'] ?? 0); ?></span>
                            </td>
                            <td><?php echo $helper->e($order['billing_cycle'] ?? 'N/A'); ?></td>
                            <td><?php echo $helper->statusBadge($order['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="active">
                            <th colspan="5" class="text-right">Total:</th>
                            <th class="text-right"><?php echo CurrencyHelper::formatVnd($summary['total_sales_vnd'] ?? 0); ?></th>
                            <th class="text-right"><?php echo CurrencyHelper::formatUsd($summary['total_sales_usd'] ?? 0); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sales by Product Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fa fa-list"></i> Sales by Product</h4>
        </div>
        <div class="panel-body">
            <?php if (empty($productData)): ?>
            <div class="alert alert-info">No product data available.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th class="text-center">Orders</th>
                            <th class="text-right">Total (VND)</th>
                            <th class="text-right">Total (USD)</th>
                            <th class="text-right">Avg Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productData as $product): ?>
                        <?php $product = (object) $product; ?>
                        <tr>
                            <td>
                                <strong><?php echo $helper->e($product->product_name ?? $product->product_code); ?></strong>
                                <br><small class="text-muted"><?php echo $helper->e($product->product_code); ?></small>
                            </td>
                            <td><span class="label label-default"><?php echo $helper->e($product->vendor ?? 'Unknown'); ?></span></td>
                            <td class="text-center"><?php echo number_format($product->order_count); ?></td>
                            <td class="text-right"><strong><?php echo CurrencyHelper::formatVnd($product->total_sales_vnd ?? 0); ?></strong></td>
                            <td class="text-right"><span class="text-info"><?php echo CurrencyHelper::formatUsd($product->total_sales ?? 0); ?></span></td>
                            <td class="text-right">
                                <?php 
                                $avg = $product->order_count > 0 ? ($product->total_sales ?? 0) / $product->order_count : 0;
                                echo CurrencyHelper::formatUsd($avg);
                                ?>
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
    // Sales Trend Chart
    var chartData = <?php echo json_encode($chartData ?? ['labels' => [], 'datasets' => ['revenue_usd' => [], 'orders' => []]]); ?>;
    
    if (chartData.labels && chartData.labels.length > 0) {
        var ctx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue (USD)',
                    data: chartData.datasets.revenue_usd || chartData.datasets.revenue || [],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Orders',
                    data: chartData.datasets.orders || [],
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: { display: true, text: 'Revenue (USD)' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { display: true, text: 'Orders' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    // Product Pie Chart
    var productData = <?php echo json_encode($productData ?? []); ?>;
    
    if (productData.length > 0) {
        var pieCtx = document.getElementById('productPieChart').getContext('2d');
        var labels = productData.slice(0, 5).map(function(p) { return p.product_name || p.product_code; });
        var values = productData.slice(0, 5).map(function(p) { return p.total_sales || 0; });
        
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>