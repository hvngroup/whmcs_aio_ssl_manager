<?php
/**
 * Reports Index Template - UPDATED
 * Reports dashboard with quick stats and navigation
 * 
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 * @var array $quickStats Quick statistics
 */

use NicsrsAdmin\Helper\CurrencyHelper;

$rateInfo = CurrencyHelper::getRateInfo();
?>

<div class="nicsrs-reports">
    
    <!-- Page Header -->
    <div class="page-header">
        <h3><i class="fa fa-bar-chart"></i> Reports & Analytics</h3>
        <p class="text-muted">Sales, profit, and performance reports for SSL certificates</p>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <div class="stat-icon text-primary">
                        <i class="fa fa-money fa-2x"></i>
                    </div>
                    <h3 class="stat-value"><?php echo CurrencyHelper::formatVnd($quickStats['this_month_sales_vnd'] ?? 0); ?></h3>
                    <p class="stat-label text-muted">This Month Revenue</p>
                    <small class="text-info">≈ <?php echo CurrencyHelper::formatUsd($quickStats['this_month_sales_usd'] ?? 0); ?></small>
                    <?php if (($quickStats['sales_growth'] ?? 0) != 0): ?>
                    <br>
                    <small class="<?php echo $quickStats['sales_growth'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fa fa-<?php echo $quickStats['sales_growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <?php echo abs(round($quickStats['sales_growth'], 1)); ?>% vs last month
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <div class="stat-icon text-success">
                        <i class="fa fa-shopping-cart fa-2x"></i>
                    </div>
                    <h3 class="stat-value"><?php echo number_format($quickStats['this_month_orders'] ?? 0); ?></h3>
                    <p class="stat-label text-muted">Orders This Month</p>
                    <small class="text-muted">Last month: <?php echo number_format($quickStats['last_month_orders'] ?? 0); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <div class="stat-icon text-info">
                        <i class="fa fa-certificate fa-2x"></i>
                    </div>
                    <h3 class="stat-value"><?php echo number_format($quickStats['active_certificates'] ?? 0); ?></h3>
                    <p class="stat-label text-muted">Active Certificates</p>
                    <small class="text-muted">Total completed orders</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <div class="stat-icon text-warning">
                        <i class="fa fa-exchange fa-2x"></i>
                    </div>
                    <h3 class="stat-value" style="font-size: 16px;"><?php echo $rateInfo['rate_formatted']; ?></h3>
                    <p class="stat-label text-muted">Exchange Rate</p>
                    <small class="text-muted">Updated: <?php echo $rateInfo['last_updated_formatted']; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="row">
        
        <!-- SSL Sales Report -->
        <div class="col-md-6">
            <div class="panel panel-default report-card">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-line-chart text-primary"></i> SSL Sales Report
                    </h4>
                </div>
                <div class="panel-body">
                    <p>View detailed sales data including revenue by product, time period, and client. Track sales trends and identify top-performing products.</p>
                    <ul class="report-features">
                        <li><i class="fa fa-check text-success"></i> Revenue by product & time</li>
                        <li><i class="fa fa-check text-success"></i> Sales trend charts</li>
                        <li><i class="fa fa-check text-success"></i> VND & USD display</li>
                        <li><i class="fa fa-check text-success"></i> Export to CSV</li>
                    </ul>
                    <a href="<?php echo $modulelink; ?>&action=reports&report=sales" class="btn btn-primary">
                        <i class="fa fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- SSL Profit Report -->
        <div class="col-md-6">
            <div class="panel panel-default report-card">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-money text-success"></i> SSL Profit Report
                    </h4>
                </div>
                <div class="panel-body">
                    <p>Calculate profit margins by comparing WHMCS revenue with NicSRS costs. Supports automatic VAT deduction and USD conversion.</p>
                    <ul class="report-features">
                        <li><i class="fa fa-check text-success"></i> Profit = Revenue - Cost</li>
                        <li><i class="fa fa-check text-success"></i> Auto 10% VAT deduction</li>
                        <li><i class="fa fa-check text-success"></i> Profit margin analysis</li>
                        <li><i class="fa fa-check text-success"></i> Configurable exchange rate</li>
                    </ul>
                    <a href="<?php echo $modulelink; ?>&action=reports&report=profit" class="btn btn-success">
                        <i class="fa fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Product Performance Report -->
        <div class="col-md-6">
            <div class="panel panel-default report-card">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-trophy text-warning"></i> Product Performance
                    </h4>
                </div>
                <div class="panel-body">
                    <p>Analyze product performance including best sellers, renewal rates, and completion rates. Compare products to optimize offerings.</p>
                    <ul class="report-features">
                        <li><i class="fa fa-check text-success"></i> Top products ranking</li>
                        <li><i class="fa fa-check text-success"></i> Completion rate metrics</li>
                        <li><i class="fa fa-check text-success"></i> Renewal rate tracking</li>
                        <li><i class="fa fa-check text-success"></i> Revenue per product</li>
                    </ul>
                    <a href="<?php echo $modulelink; ?>&action=reports&report=performance" class="btn btn-warning">
                        <i class="fa fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Revenue by Brand Report -->
        <div class="col-md-6">
            <div class="panel panel-default report-card">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-building text-info"></i> Revenue by Brand
                    </h4>
                </div>
                <div class="panel-body">
                    <p>See revenue breakdown by SSL vendor/brand. Identify which brands generate the most revenue and track market share.</p>
                    <ul class="report-features">
                        <li><i class="fa fa-check text-success"></i> Brand market share</li>
                        <li><i class="fa fa-check text-success"></i> Revenue distribution chart</li>
                        <li><i class="fa fa-check text-success"></i> Brand trend over time</li>
                        <li><i class="fa fa-check text-success"></i> Comparison table</li>
                    </ul>
                    <a href="<?php echo $modulelink; ?>&action=reports&report=brand" class="btn btn-info">
                        <i class="fa fa-arrow-right"></i> View Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency Settings Quick Link -->
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-md-8">
                    <h5><i class="fa fa-cog"></i> Currency Settings</h5>
                    <p class="text-muted" style="margin-bottom: 0;">
                        Current exchange rate: <strong><?php echo $rateInfo['rate_formatted']; ?></strong> 
                        (Last updated: <?php echo $rateInfo['last_updated_formatted']; ?>)
                        <br>
                        VAT rate: <strong><?php echo $rateInfo['vat_rate_formatted'] ?? '10%'; ?></strong> - Applied when calculating profit from VND revenue
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="<?php echo $modulelink; ?>&action=settings" class="btn btn-default">
                        <i class="fa fa-cog"></i> Update Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>