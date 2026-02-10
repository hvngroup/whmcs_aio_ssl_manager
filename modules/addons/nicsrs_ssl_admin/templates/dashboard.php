<?php
/**
 * Dashboard Template
 * 
 * @var array $statistics Dashboard statistics
 * @var array $recentOrders Recent orders list
 * @var array $expiringCertificates Expiring certificates
 * @var array $statusDistribution Status distribution data
 * @var array $monthlyOrders Monthly orders data
 * @var bool $apiConnected API connection status
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */
?>

<div class="nicsrs-dashboard">
    
    <!-- API Status Alert -->
    <?php if (!$apiConnected): ?>
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>API Not Connected!</strong> 
        Please configure your NicSRS API token in the 
        <a href="<?php echo $modulelink; ?>&action=settings">Settings</a> page.
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon bg-primary">
                    <i class="fa fa-shopping-cart"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?php echo number_format($statistics['total_orders']); ?></div>
                    <div class="stats-label">Total Orders</div>
                    <?php if ($statistics['trend'] != 0): ?>
                    <div class="stats-trend <?php echo $statistics['trend'] > 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fa fa-<?php echo $statistics['trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <?php echo abs($statistics['trend']); ?>% this month
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon bg-warning">
                    <i class="fa fa-clock-o"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?php echo number_format($statistics['pending_orders']); ?></div>
                    <div class="stats-label">Pending Orders</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon bg-success">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?php echo number_format($statistics['issued_certs']); ?></div>
                    <div class="stats-label">Issued Certificates</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon bg-danger">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-value"><?php echo number_format($statistics['expiring_soon']); ?></div>
                    <div class="stats-label">Expiring Soon (30d)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-pie-chart"></i> Certificate Status Distribution</h3>
                </div>
                <div class="panel-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Monthly Orders</h3>
                </div>
                <div class="panel-body">
                    <canvas id="ordersChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders - Full Width -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-list"></i> Recent Orders
                        <a href="<?php echo $modulelink; ?>&action=orders" class="btn btn-xs btn-default pull-right">
                            View All <i class="fa fa-arrow-right"></i>
                        </a>
                    </h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Order ID</th>
                                <th>Domain</th>
                                <th>Product</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 120px;">Created</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 30px;">
                                    <i class="fa fa-inbox fa-2x"></i><br>
                                    No orders found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $order['id']; ?>">
                                        <strong>#<?php echo $order['id']; ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo $helper->e($order['domain']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $displayProductName = $order['product_name'] 
                                        ?: $helper->getProductName($order['certtype']);
                                    echo $helper->e($displayProductName);
                                    ?>
                                    <?php if ($order['certtype'] && $order['certtype'] !== $displayProductName): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($order['certtype']); ?></small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($order['userid'])): ?>
                                    <a href="clientssummary.php?userid=<?php echo $order['userid']; ?>" target="_blank">
                                        <?php echo $helper->e($order['client_name']); ?>
                                    </a>
                                    <?php if (!empty($order['companyname'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($order['companyname']); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($order['serviceid'])): ?>
                                    <a href="clientsservices.php?id=<?php echo $order['serviceid']; ?>" target="_blank">
                                        #<?php echo $order['serviceid']; ?>
                                    </a>
                                    <?php if (!empty($order['service_product_name'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($order['service_product_name']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($order['service_domain'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($order['service_domain']); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Not linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $helper->statusBadge($order['status']); ?>
                                </td>
                                <td>
                                    <?php echo $helper->formatDate($order['provisiondate'], 'Y-m-d'); ?>
                                </td>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $order['id']; ?>" 
                                       class="btn btn-xs btn-primary" title="View Details">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiring Soon - Full Width -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-calendar-times-o text-danger"></i> Certificates Expiring Soon (30 days)
                        <span class="badge" style="background: #ff4d4f; margin-left: 10px;">
                            <?php echo count($expiringCertificates); ?>
                        </span>
                    </h3>
                </div>
                <div class="panel-body" style="padding: 0;">
                    <table class="table table-striped table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Order ID</th>
                                <th>Domain</th>
                                <th>Product</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th style="width: 100px;">Expires</th>
                                <th style="width: 100px;">Days Left</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiringCertificates)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 30px;">
                                    <i class="fa fa-check-circle fa-2x text-success"></i><br>
                                    No certificates expiring in the next 30 days
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expiringCertificates as $cert): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $cert['id']; ?>">
                                        <strong>#<?php echo $cert['id']; ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo $helper->e($cert['domain']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $displayProductName = $cert['product_name'] 
                                        ?: $helper->getProductName($cert['certtype']);
                                    echo $helper->e($displayProductName);
                                    ?>
                                    <?php if ($cert['certtype'] && $cert['certtype'] !== $displayProductName): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($cert['certtype']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($cert['userid'])): ?>
                                    <a href="clientssummary.php?userid=<?php echo $cert['userid']; ?>" target="_blank">
                                        <?php echo $helper->e($cert['client_name']); ?>
                                    </a>
                                    <?php if (!empty($cert['companyname'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($cert['companyname']); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($cert['serviceid'])): ?>
                                    <a href="clientsservices.php?id=<?php echo $cert['serviceid']; ?>" target="_blank">
                                        #<?php echo $cert['serviceid']; ?>
                                    </a>
                                    <?php if (!empty($cert['service_product_name'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($cert['service_product_name']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($cert['service_domain'])): ?>
                                    <br><small class="text-muted"><?php echo $helper->e($cert['service_domain']); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Not linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $helper->formatDate($cert['end_date'], 'Y-m-d'); ?>
                                </td>
                                <td>
                                    <?php echo $helper->daysLeftBadge($cert['end_date']); ?>
                                </td>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $cert['id']; ?>" 
                                       class="btn btn-xs btn-warning" title="View & Renew">
                                        <i class="fa fa-refresh"></i> Renew
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== Status Distribution Pie Chart =====
    var statusData = <?php echo json_encode($statusDistribution); ?>;
    var statusLabels = [];
    var statusValues = [];
    var statusColors = [];
    
    var colorMap = {
        'complete': '#52c41a',
        'pending': '#faad14',
        'awaiting': '#8c8c8c',
        'draft': '#1890ff',
        'cancelled': '#ff4d4f',
        'revoked': '#ff7875'
    };
    
    // Build arrays from object
    for (var status in statusData) {
        if (statusData.hasOwnProperty(status)) {
            statusLabels.push(status.charAt(0).toUpperCase() + status.slice(1));
            statusValues.push(statusData[status]);
            statusColors.push(colorMap[status] || '#d9d9d9');
        }
    }
    
    // Only create chart if there's data
    if (statusValues.length > 0) {
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }

    // ===== Monthly Orders Bar Chart =====
    var monthlyData = <?php echo json_encode(array_values($monthlyOrders)); ?>;
    
    // Extract labels and values
    var monthLabels = [];
    var monthValues = [];
    
    if (monthlyData && monthlyData.length > 0) {
        for (var i = 0; i < monthlyData.length; i++) {
            monthLabels.push(monthlyData[i].short || monthlyData[i].month);
            monthValues.push(monthlyData[i].count || 0);
        }
    }
    
    // Only create chart if there's data
    if (monthLabels.length > 0) {
        new Chart(document.getElementById('ordersChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Orders',
                    data: monthValues,
                    backgroundColor: '#1890ff',
                    borderRadius: 4,
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    } else {
        // Show message if no data
        document.getElementById('ordersChart').parentNode.innerHTML = 
            '<div class="text-center text-muted" style="padding: 50px;">' +
            '<i class="fa fa-bar-chart fa-2x"></i><br>No order data available</div>';
    }
});
</script>