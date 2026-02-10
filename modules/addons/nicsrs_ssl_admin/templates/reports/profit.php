<?php
/**
 * SSL Profit Report Template - UPDATED
 * 
 * CURRENCY NOTES:
 * - Revenue from WHMCS is VND (including 10% VAT)
 * - Cost from NicSRS is USD (no VAT)
 * - Profit calculated in USD after converting revenue
 * 
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 * @var array $reportData Profit report data
 * @var array $chartData Chart data
 * @var array $rateInfo Exchange rate info
 * @var string $displayMode Currency display mode
 * @var array $vendors Available vendors
 * @var array $products Available products
 * @var array $datePresets Date presets
 * @var array $filters Current filters
 */

use NicsrsAdmin\Helper\CurrencyHelper;

$filters = $filters ?? [];
$summary = $reportData['summary'] ?? [];
$rateInfo = $rateInfo ?? CurrencyHelper::getRateInfo();
?>

<div class="nicsrs-reports nicsrs-profit-report">
    
    <!-- Page Header -->
    <div class="page-header clearfix">
        <h3 class="pull-left">
            <i class="fa fa-money"></i> SSL Profit Report
        </h3>
        <div class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=reports" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Reports
            </a>
            <a href="<?php echo $modulelink; ?>&action=reports&report=profit&export=csv&<?php echo http_build_query($filters); ?>" 
               class="btn btn-success">
                <i class="fa fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Exchange Rate Info -->
    <div class="alert alert-info">
        <div class="row">
            <div class="col-md-8">
                <i class="fa fa-info-circle"></i>
                <strong>Calculation:</strong> 
                Revenue (VND with 10% VAT) → Remove VAT → Convert to USD → Subtract NicSRS Cost = Profit
                <br>
                <i class="fa fa-exchange"></i> 
                <strong>Rate:</strong> <?php echo $rateInfo['rate_formatted']; ?>
                | <strong>VAT:</strong> <?php echo $rateInfo['vat_rate_formatted'] ?? '10%'; ?>
                <small class="text-muted">(Updated: <?php echo $rateInfo['last_updated_formatted']; ?>)</small>
            </div>
            <div class="col-md-4 text-right">
                <a href="<?php echo $modulelink; ?>&action=settings" class="btn btn-sm btn-default">
                    <i class="fa fa-cog"></i> Update Exchange Rate
                </a>
            </div>
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
                <input type="hidden" name="report" value="profit">
                
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
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Apply
                </button>
                <a href="<?php echo $modulelink; ?>&action=reports&report=profit" class="btn btn-default">
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
                    <h4 class="text-muted">Revenue (VND)</h4>
                    <h2 class="text-primary"><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd'] ?? 0); ?></h2>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatUsd($summary['total_revenue_usd'] ?? 0); ?> (excl. VAT)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-danger">
                <div class="panel-body text-center">
                    <h4 class="text-muted">Cost (USD)</h4>
                    <h2 class="text-danger"><?php echo CurrencyHelper::formatUsd($summary['total_cost_usd'] ?? 0); ?></h2>
                    <small class="text-muted">NicSRS wholesale price</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h4 class="text-muted">Profit (USD)</h4>
                    <h2 class="text-success"><?php echo CurrencyHelper::formatUsd($summary['total_profit_usd'] ?? 0); ?></h2>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatVnd($summary['total_profit_vnd'] ?? 0); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h4 class="text-muted">Profit Margin</h4>
                    <h2 class="text-info"><?php echo number_format($summary['profit_margin'] ?? 0, 1); ?>%</h2>
                    <small class="text-muted"><?php echo number_format($summary['order_count'] ?? 0); ?> orders</small>
                </div>
            </div>
        </div>
    </div>

    <!-- VAT Breakdown -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fa fa-calculator"></i> VAT Breakdown</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4 text-center">
                    <h5>Total Revenue (with VAT)</h5>
                    <h4><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd'] ?? 0); ?></h4>
                </div>
                <div class="col-md-4 text-center">
                    <h5>VAT Amount (10%)</h5>
                    <h4 class="text-warning"><?php echo CurrencyHelper::formatVnd($summary['total_vat_vnd'] ?? 0); ?></h4>
                </div>
                <div class="col-md-4 text-center">
                    <h5>Revenue (excl. VAT)</h5>
                    <h4><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd_without_vat'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Profit Chart -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fa fa-bar-chart"></i> Profit Trend</h4>
        </div>
        <div class="panel-body">
            <canvas id="profitTrendChart" height="80"></canvas>
        </div>
    </div>

    <!-- Profit Data Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">
                <i class="fa fa-table"></i> Profit Details
                <span class="badge"><?php echo count($reportData['orders'] ?? []); ?> orders</span>
            </h4>
        </div>
        <div class="panel-body">
            <?php if (empty($reportData['orders'])): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No completed orders found for the selected filters.
                <br><small>Note: Profit report only includes completed orders.</small>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="profitTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th class="text-right">Revenue (VND)</th>
                            <th class="text-right">Revenue (USD)</th>
                            <th class="text-right">Cost (USD)</th>
                            <th class="text-right">Profit (USD)</th>
                            <th class="text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['orders'] as $order): ?>
                        <?php 
                        $profitClass = $order['profit_usd'] >= 0 ? 'text-success' : 'text-danger';
                        $marginClass = $order['profit_margin'] >= 20 ? 'label-success' : ($order['profit_margin'] >= 10 ? 'label-warning' : 'label-danger');
                        $displayDate = $order['service_date'] ?? $order['date'] ?? $order['provision_date'] ?? '';
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo $modulelink; ?>&action=orders&id=<?php echo $order['order_id']; ?>">
                                    #<?php echo $order['order_id']; ?>
                                </a>
                            </td>
                            <td><?php echo $helper->formatDate($displayDate); ?></td>
                            <td>
                                <strong><?php echo $helper->e($order['product_name']); ?></strong>
                                <br><small class="text-muted"><?php echo $helper->e($order['product_code']); ?></small>
                            </td>
                            <td><span class="label label-default"><?php echo $helper->e($order['vendor']); ?></span></td>
                            <td class="text-right"><?php echo CurrencyHelper::formatVnd($order['revenue_vnd_with_vat']); ?></td>
                            <td class="text-right"><?php echo CurrencyHelper::formatUsd($order['sale_amount_usd']); ?></td>
                            <td class="text-right text-danger"><?php echo CurrencyHelper::formatUsd($order['cost_usd']); ?></td>
                            <td class="text-right <?php echo $profitClass; ?>">
                                <strong><?php echo CurrencyHelper::formatUsd($order['profit_usd']); ?></strong>
                            </td>
                            <td class="text-right">
                                <span class="label <?php echo $marginClass; ?>">
                                    <?php echo number_format($order['profit_margin'], 1); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="active">
                            <th colspan="4" class="text-right">Total:</th>
                            <th class="text-right"><?php echo CurrencyHelper::formatVnd($summary['total_revenue_vnd'] ?? 0); ?></th>
                            <th class="text-right"><?php echo CurrencyHelper::formatUsd($summary['total_revenue_usd'] ?? 0); ?></th>
                            <th class="text-right text-danger"><?php echo CurrencyHelper::formatUsd($summary['total_cost_usd'] ?? 0); ?></th>
                            <th class="text-right text-success"><?php echo CurrencyHelper::formatUsd($summary['total_profit_usd'] ?? 0); ?></th>
                            <th class="text-right">
                                <span class="label label-info"><?php echo number_format($summary['profit_margin'] ?? 0, 1); ?>%</span>
                            </th>
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
    var chartData = <?php echo json_encode($chartData ?? ['labels' => [], 'datasets' => ['revenue' => [], 'cost' => [], 'profit' => []]]); ?>;
    
    if (chartData.labels && chartData.labels.length > 0) {
        var ctx = document.getElementById('profitTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue (USD)',
                    data: chartData.datasets.revenue || [],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Cost (USD)',
                    data: chartData.datasets.cost || [],
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }, {
                    label: 'Profit (USD)',
                    data: chartData.datasets.profit || [],
                    type: 'line',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Amount (USD)' }
                    }
                }
            }
        });
    }
});
</script>