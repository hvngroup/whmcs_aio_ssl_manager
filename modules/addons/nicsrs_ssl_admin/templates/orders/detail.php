<?php
/**
 * Order Detail Template - UPDATED VERSION
 * 
 * @var object $order Order object
 * @var array $config Configuration data
 * @var array $domains Domain list
 * @var array $certInfo Certificate info
 * @var array $activityLogs Activity logs
 * @var string $clientName Client name
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */

use NicsrsAdmin\Helper\DcvHelper;

// Get primary domain and product name
$primaryDomain = $helper->getPrimaryDomain($order);
$productName = $helper->getProductName($order->certtype);

// Check CSR and Certificate availability
$hasCsr = !empty($config['csr']);
$hasCert = !empty($config['applyReturn']['certificate']);
$hasJks = !empty($config['applyReturn']['jks']);
$hasPkcs12 = !empty($config['applyReturn']['pkcs12']);

// Get vendor IDs
$vendorId = $config['applyReturn']['vendorId'] ?? '';
$vendorCertId = $config['applyReturn']['vendorCertId'] ?? '';

// Get cert status from applyReturn
$certStatus = '';
if (!empty($config['applyReturn']['issued']['status'])) {
    $certStatus = $config['applyReturn']['issued']['status'];
} elseif (!empty($config['applyReturn']['dcv']['status'])) {
    $certStatus = 'dcv_' . $config['applyReturn']['dcv']['status'];
} elseif (!empty($config['applyReturn']['application']['status'])) {
    $certStatus = 'app_' . $config['applyReturn']['application']['status'];
}

// Get last refresh time
$lastRefresh = $config['lastRefresh'] ?? '';

// Calculate renewal due (30 days before expiry)
$renewalDue = '';
if (!empty($config['applyReturn']['endDate'])) {
    $endTimestamp = strtotime($config['applyReturn']['endDate']);
    if ($endTimestamp) {
        $renewalDue = date('Y-m-d', $endTimestamp - (30 * 86400));
    }
}

// Get DCV data for instructions
$dcvData = [
    'DCVfileName' => $config['applyReturn']['DCVfileName'] ?? '',
    'DCVfileContent' => $config['applyReturn']['DCVfileContent'] ?? '',
    'DCVfilePath' => $config['applyReturn']['DCVfilePath'] ?? '',
    'DCVdnsHost' => $config['applyReturn']['DCVdnsHost'] ?? '',
    'DCVdnsValue' => $config['applyReturn']['DCVdnsValue'] ?? '',
    'DCVdnsType' => $config['applyReturn']['DCVdnsType'] ?? '',
];
?>

<div class="nicsrs-order-detail">
    
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li><a href="<?php echo $modulelink; ?>">Dashboard</a></li>
        <li><a href="<?php echo $modulelink; ?>&action=orders">Orders</a></li>
        <li class="active">Order #<?php echo $order->id; ?></li>
    </ol>

    <!-- Enhanced Header -->
    <div class="page-header clearfix" style="border-bottom: 2px solid #1890ff; padding-bottom: 15px; margin-bottom: 20px;">
        <div class="pull-left">
            <h3>
                <span title="OrderID"><i class="fa fa-certificate text-primary"></i> Order #<?php echo $order->id; ?></span>
                <span title="Product"><i class="fa fa-tag text-primary"></i> <?php echo $helper->e($productName); ?></span>
                <?php if ($primaryDomain !== '-'): ?>
                <span title="Domain"><i class="fa fa-globe text-primary"></i> <?php echo $helper->e($primaryDomain); ?></span>
                <?php endif; ?>
                <?php echo $helper->statusBadge($order->status); ?>
                <?php if ($certStatus): ?>
                <small class="text-muted" style="font-size: 12px;">
                    (<?php echo ucfirst(str_replace('_', ' ', $certStatus)); ?>)
                </small>
                <?php endif; ?>
            </h3>
        </div>
        <div class="pull-right">
            <a href="<?php echo $modulelink; ?>&action=orders" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Order Info + Client Info -->
        <div class="col-md-6">
            <!-- Order Information -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Order Information</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed">
                        <tr>
                            <th style="width: 140px;">Order ID:</th>
                            <td>#<?php echo $order->id; ?></td>
                        </tr>
                        <tr>
                            <th>Remote ID:</th>
                            <td>
                                <?php if ($order->remoteid): ?>
                                <code><?php echo $helper->e($order->remoteid); ?></code>
                                <?php else: ?>
                                <span class="text-muted">Not yet assigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- NEW: Vendor ID row -->
                        <?php if ($vendorId): ?>
                        <tr>
                            <th>Vendor ID:</th>
                            <td><code><?php echo $helper->e($vendorId); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- NEW: Vendor Cert ID row -->
                        <?php if ($vendorCertId): ?>
                        <tr>
                            <th>Vendor Cert ID:</th>
                            <td><code><?php echo $helper->e($vendorCertId); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- Service ID with link -->
                        <tr class="info">
                            <th>Service ID:</th>
                            <td>
                                <?php if ($order->serviceid): ?>
                                <a href="clientsservices.php?id=<?php echo $order->serviceid; ?>" target="_blank" class="btn btn-xs btn-info">
                                    #<?php echo $order->serviceid; ?> <i class="fa fa-external-link"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Product Name with code -->
                        <tr>
                            <th>Product NicSRS:</th>
                            <td>
                                <strong><?php echo $helper->e($productName); ?></strong>
                                <small class="text-muted">(<?php echo $helper->e($order->certtype); ?>)</small>
                            </td>
                        </tr>
                        <!-- Domain -->
                        <tr>
                            <th>Domain:</th>
                            <td>
                                <?php if ($primaryDomain !== '-'): ?>
                                <strong><?php echo $helper->e($primaryDomain); ?></strong>
                                <?php else: ?>
                                <span class="text-muted">Not configured</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Status with cert_status badge -->
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php echo $helper->statusBadge($order->status); ?>
                                <?php if ($certStatus && $certStatus !== 'done'): ?>
                                <span class="label label-info" style="margin-left: 5px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $certStatus)); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Created:</th>
                            <td><?php echo $helper->formatDateTime($order->provisiondate); ?></td>
                        </tr>
                        <?php if ($order->completiondate && $order->completiondate !== '0000-00-00 00:00:00'): ?>
                        <tr>
                            <th>Completed:</th>
                            <td><?php echo $helper->formatDateTime($order->completiondate); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- NEW: Last Refresh row -->
                        <?php if ($lastRefresh): ?>
                        <tr>
                            <th>Last Refresh:</th>
                            <td>
                                <span class="text-muted"><?php echo $helper->formatDateTime($lastRefresh); ?></span>
                                <small class="text-muted">(<?php echo $helper->timeAgo($lastRefresh); ?>)</small>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Client & Product/Service Information (UPDATED) -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user"></i> Client & Product/Service Information</h3>
                </div>
                <div class="panel-body">
                    <!-- Client Information -->
                    <h5 style="margin-top: 0; color: #1890ff; border-bottom: 1px solid #e8e8e8; padding-bottom: 8px;">
                        <i class="fa fa-user-circle-o"></i> Client Details
                    </h5>
                    <table class="table table-condensed" style="margin-bottom: 15px;">
                        <tr>
                            <th style="width: 160px;">Name:</th>
                            <td>
                                <?php if ($order->userid): ?>
                                <a href="clientssummary.php?userid=<?php echo $order->userid; ?>" target="_blank">
                                    <?php echo $helper->e($clientName); ?> <i class="fa fa-external-link"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">Unknown</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($order->companyname)): ?>
                        <tr>
                            <th>Company:</th>
                            <td><?php echo $helper->e($order->companyname); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if (!empty($order->client_email)): ?>
                                <a href="mailto:<?php echo $helper->e($order->client_email); ?>">
                                    <?php echo $helper->e($order->client_email); ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- WHMCS Product/Service Information (NEW) -->
                    <?php if ($order->serviceid): ?>
                    <h5 style="color: #52c41a; border-bottom: 1px solid #e8e8e8; padding-bottom: 8px;">
                        <i class="fa fa-cube"></i> WHMCS Product/Service
                        <a href="clientsservices.php?userid=<?php echo $order->userid; ?>&id=<?php echo $order->serviceid; ?>" 
                           target="_blank" class="btn btn-xs btn-success pull-right">
                            <i class="fa fa-external-link"></i> View Service
                        </a>
                    </h5>
                    <table class="table table-condensed" style="margin-bottom: 0;">
                        <tr>
                            <th style="width: 160px;">Service ID:</th>
                            <td>
                                <code>#<?php echo $order->serviceid; ?></code>
                                <?php if (!empty($order->service_status)): ?>
                                <?php 
                                $serviceStatusClass = 'default';
                                $serviceStatusLower = strtolower($order->service_status);
                                if ($serviceStatusLower === 'active') $serviceStatusClass = 'success';
                                elseif ($serviceStatusLower === 'suspended') $serviceStatusClass = 'warning';
                                elseif (in_array($serviceStatusLower, ['terminated', 'cancelled'])) $serviceStatusClass = 'danger';
                                elseif ($serviceStatusLower === 'pending') $serviceStatusClass = 'info';
                                ?>
                                <span class="label label-<?php echo $serviceStatusClass; ?>" style="margin-left: 5px;">
                                    <?php echo $helper->e($order->service_status); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Product/Service:</th>
                            <td>
                                <?php if (!empty($order->whmcs_product_name)): ?>
                                <strong><?php echo $helper->e($order->whmcs_product_name); ?></strong>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Domain:</th>
                            <td>
                                <?php if (!empty($order->service_domain)): ?>
                                <code><?php echo $helper->e($order->service_domain); ?></code>
                                <?php else: ?>
                                <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registration Date:</th>
                            <td>
                                <?php if (!empty($order->service_regdate) && $order->service_regdate !== '0000-00-00'): ?>
                                <?php echo $helper->formatDate($order->service_regdate); ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>First Payment:</th>
                            <td>
                                <?php if (isset($order->service_firstpaymentamount)): ?>
                                <strong><?php echo $helper->formatCurrency($order->service_firstpaymentamount); ?></strong>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Recurring Amount:</th>
                            <td>
                                <?php if (isset($order->service_amount)): ?>
                                <strong><?php echo $helper->formatCurrency($order->service_amount); ?></strong>
                                <?php if (!empty($order->service_billingcycle)): ?>
                                <span class="text-muted">/ <?php echo $helper->e($order->service_billingcycle); ?></span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Billing Cycle:</th>
                            <td>
                                <?php if (!empty($order->service_billingcycle)): ?>
                                <?php echo $helper->e($order->service_billingcycle); ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Next Due Date:</th>
                            <td>
                                <?php if (!empty($order->service_nextduedate) && $order->service_nextduedate !== '0000-00-00'): ?>
                                <?php 
                                $nextDue = strtotime($order->service_nextduedate);
                                $now = time();
                                $daysUntilDue = ceil(($nextDue - $now) / 86400);
                                ?>
                                <?php echo $helper->formatDate($order->service_nextduedate); ?>
                                <?php if ($daysUntilDue <= 0): ?>
                                <span class="label label-danger">Overdue</span>
                                <?php elseif ($daysUntilDue <= 7): ?>
                                <span class="label label-warning"><?php echo $daysUntilDue; ?> days</span>
                                <?php elseif ($daysUntilDue <= 30): ?>
                                <span class="label label-info"><?php echo $daysUntilDue; ?> days</span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php else: ?>
                    <!-- No linked service -->
                    <h5 style="color: #8c8c8c; border-bottom: 1px solid #e8e8e8; padding-bottom: 8px;">
                        <i class="fa fa-cube"></i> WHMCS Product/Service
                    </h5>
                    <div class="alert alert-warning" style="margin-bottom: 0;">
                        <i class="fa fa-exclamation-triangle"></i> 
                        This SSL order is not linked to any WHMCS service.
                        <br>
                        <small class="text-muted">
                            You can link it via the Import page or create a new service manually.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Certificate Info + Quick Actions -->
        <div class="col-md-6">
            <!-- Certificate Information -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-shield"></i> Certificate Information</h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($certInfo['begin_date'])): ?>
                    <table class="table table-condensed">
                        <tr>
                            <th style="width: 140px;">Cert ID:</th>
                            <td><code><?php echo $helper->e($certInfo['cert_id']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Valid From:</th>
                            <td><?php echo $helper->formatDate($certInfo['begin_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Valid Until:</th>
                            <td>
                                <?php echo $helper->formatDate($certInfo['end_date']); ?>
                                <?php echo $helper->daysLeftBadge($certInfo['end_date']); ?>
                            </td>
                        </tr>
                        
                        <!-- NEW: Renewal Due row -->
                        <?php if ($renewalDue): ?>
                        <tr>
                            <th>Renewal Due:</th>
                            <td>
                                <?php echo $helper->formatDate($renewalDue); ?>
                                <small class="text-muted">(30 days before expiry)</small>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <th>Certificate:</th>
                            <td>
                                <?php if ($certInfo['has_certificate']): ?>
                                <span class="text-success"><i class="fa fa-check"></i> Available</span>
                                <?php else: ?>
                                <span class="text-muted">Not yet issued</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- NEW: JKS and PKCS12 badges -->
                        <tr>
                            <th>Formats:</th>
                            <td>
                                <?php if ($hasJks): ?>
                                <span class="label label-success" title="Java KeyStore available">
                                    <i class="fa fa-coffee"></i> JKS
                                </span>
                                <?php endif; ?>
                                <?php if ($hasPkcs12): ?>
                                <span class="label label-success" title="PKCS12/PFX available" style="margin-left: 5px;">
                                    <i class="fa fa-windows"></i> PKCS12
                                </span>
                                <?php endif; ?>
                                <?php if (!$hasJks && !$hasPkcs12): ?>
                                <span class="text-muted">Standard formats only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php else: ?>
                    <p class="text-muted">Certificate not yet issued.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions - UPDATED with JKS/PKCS12 buttons -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="panel-body">
                    <div class="btn-group-vertical" style="width: 100%;">
                        <!-- Refresh Status -->
                        <button type="button" class="btn btn-default btn-order-action" data-action="refresh_status"
                                <?php echo empty($order->remoteid) ? 'disabled' : ''; ?>>
                            <i class="fa fa-refresh"></i> Refresh Status
                        </button>
                        
                        <button type="button" class="btn btn-info" id="btnEditOrder" title="Edit Order">
                            <i class="fa fa-edit"></i> Edit
                        </button>

                        <button type="button" class="btn btn-danger" id="btnDeleteOrder" title="Delete Order">
                            <i class="fa fa-trash"></i> Delete
                        </button>

                        <!-- Download CSR -->
                        <button type="button" class="btn btn-default" id="btnDownloadCsr"
                                <?php echo !$hasCsr ? 'disabled' : ''; ?>
                                title="<?php echo $hasCsr ? 'Download CSR file' : 'CSR not available'; ?>">
                            <i class="fa fa-file-code-o"></i> Download CSR
                        </button>
                        
                        <!-- Download Certificate (ZIP) -->
                        <button type="button" class="btn btn-success" id="btnDownloadCert"
                                <?php echo !$hasCert ? 'disabled' : ''; ?>
                                title="<?php echo $hasCert ? 'Download certificate package (ZIP)' : 'Certificate not yet issued'; ?>">
                            <i class="fa fa-download"></i> Download Cert (ZIP)
                        </button>
                        
                        <!-- Download JKS -->
                        <?php if ($hasJks): ?>
                        <button type="button" class="btn btn-info" id="btnDownloadJks"
                                title="Download Java KeyStore file">
                            <i class="fa fa-coffee"></i> Download JKS
                        </button>
                        <?php endif; ?>
                        
                        <!-- Download PKCS12/PFX -->
                        <?php if ($hasPkcs12): ?>
                        <button type="button" class="btn btn-info" id="btnDownloadPkcs12"
                                title="Download PKCS12/PFX file">
                            <i class="fa fa-windows"></i> Download PFX
                        </button>
                        <?php endif; ?>
                        
                        <hr style="margin: 10px 0;">
                        
                        <!-- Other actions -->
                        <button type="button" class="btn btn-warning btn-order-action" data-action="reissue"
                                <?php echo $order->status !== 'complete' ? 'disabled' : ''; ?>>
                            <i class="fa fa-repeat"></i> Reissue Certificate
                        </button>
                        
                        <button type="button" class="btn btn-primary btn-order-action" data-action="renew"
                                <?php echo $order->status !== 'complete' ? 'disabled' : ''; ?>>
                            <i class="fa fa-refresh"></i> Renew Certificate
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-order-action" data-action="cancel"
                                <?php echo in_array($order->status, ['cancelled', 'revoked', 'complete']) ? 'disabled' : ''; ?>>
                            <i class="fa fa-times"></i> Cancel Order
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-order-action" data-action="revoke"
                                <?php echo $order->status !== 'complete' ? 'disabled' : ''; ?>>
                            <i class="fa fa-ban"></i> Revoke Certificate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Domain Validation - Full Width -->
    <?php 
    $domainList = isset($config['domainInfo']) ? $config['domainInfo'] : [];
    $isPending = in_array($order->status, ['pending', 'draft']);
    ?>
    <?php if (!empty($domainList)): ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-globe"></i> Domain Validation</h3>
        </div>
        <div class="panel-body" style="padding: 0;">
            <table class="table table-striped table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>DCV Method</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domainList as $domain): ?>
                    <?php 
                    $domainName = $domain['domainName'] ?? $domain['domain'] ?? 'N/A';
                    $dcvMethod = $domain['dcvMethod'] ?? $domain['method'] ?? 'N/A';
                    $isVerified = isset($domain['isVerified']) ? $domain['isVerified'] : false;
                    $isVerifyStr = $domain['is_verify'] ?? '';
                    ?>
                    <tr>
                        <td><code><?php echo $helper->e($domainName); ?></code></td>
                        <td>
                            <!-- UPDATED: Use DcvHelper for display -->
                            <?php echo DcvHelper::getBadge($dcvMethod); ?>
                        </td>
                        <td class="text-center">
                            <?php echo DcvHelper::getVerificationBadge($isVerified, $isVerifyStr); ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$isVerified && !($isVerifyStr === 'verified')): ?>
                            <button type="button" class="btn btn-xs btn-warning btn-resend-dcv" 
                                    data-domain="<?php echo $helper->e($domainName); ?>">
                                <i class="fa fa-envelope"></i> Resend DCV
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- DCV Instructions Section for pending orders -->
        <?php if ($isPending && !empty($domainList)): ?>
        <?php 
        $firstDomain = $domainList[0];
        $firstDcvMethod = $firstDomain['dcvMethod'] ?? '';
        $instructions = DcvHelper::getInstructions($firstDcvMethod, $dcvData);
        ?>
        <?php if (!empty($instructions['title']) && !empty($instructions['values'])): ?>
        <div class="panel-footer">
            <h5><i class="fa fa-info-circle"></i> <?php echo $helper->e($instructions['title']); ?></h5>
            
            <!-- Steps -->
            <?php if (!empty($instructions['steps'])): ?>
            <ol style="margin-bottom: 15px;">
                <?php foreach ($instructions['steps'] as $step): ?>
                <li><?php echo $helper->e($step); ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
            
            <!-- Values table -->
            <?php if (!empty($instructions['values'])): ?>
            <table class="table table-condensed table-bordered" style="margin-bottom: 0; background: #fff;">
                <?php foreach ($instructions['values'] as $item): ?>
                <?php if (!empty($item['value'])): ?>
                <tr>
                    <th style="width: 150px;"><?php echo $helper->e($item['label']); ?>:</th>
                    <td>
                        <code style="word-break: break-all;"><?php echo $helper->e($item['value']); ?></code>
                        <button type="button" class="btn btn-xs btn-default pull-right btn-copy" 
                                data-clipboard="<?php echo $helper->e($item['value']); ?>"
                                title="Copy to clipboard">
                            <i class="fa fa-copy"></i>
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Activity Log -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-history"></i> Activity Log</h3>
        </div>
        <div class="panel-body" style="padding: 0;">
            <table class="table table-striped" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 150px;">Date</th>
                        <th style="width: 120px;">Action</th>
                        <th style="width: 150px;">Admin</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activityLogs)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No activity recorded</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($activityLogs as $log): ?>
                    <tr>
                        <td><small><?php echo $helper->formatDateTime($log->created_at); ?></small></td>
                        <td>
                            <span class="label label-default"><?php echo $helper->e($log->action); ?></span>
                        </td>
                        <td>
                            <?php 
                            $adminName = isset($log->admin_firstname) ? trim($log->admin_firstname . ' ' . $log->admin_lastname) : '';
                            echo $helper->e($adminName ?: $log->username ?: 'System');
                            ?>
                        </td>
                        <td><small class="text-muted"><?php echo $helper->truncate($log->new_value, 50); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Action Confirmation Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Confirm Action</h4>
            </div>
            <div class="modal-body">
                <p id="actionMessage"></p>
                <div id="reasonField" style="display: none;">
                    <label>Reason (optional):</label>
                    <textarea class="form-control" id="actionReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                <h4 style="margin-top: 15px;">Processing...</h4>
                <p class="text-muted" id="loadingMessage">Please wait</p>
            </div>
        </div>
    </div>
</div>

<!-- Password Display Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-key"></i> File Password</h4>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted">The password for the downloaded file is:</p>
                <div class="alert alert-info" style="font-family: monospace; font-size: 18px;">
                    <strong id="filePassword"></strong>
                </div>
                <button type="button" class="btn btn-default btn-copy-password" data-clipboard="">
                    <i class="fa fa-copy"></i> Copy Password
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Order Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-edit"></i> Edit Order #<?php echo $order->id; ?>
                </h4>
            </div>
            <div class="modal-body">
                <!-- Remote ID Field -->
                <div class="form-group">
                    <label for="editRemoteId">
                        <i class="fa fa-certificate"></i> Remote ID (Certificate ID)
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="editRemoteId" 
                           value="<?php echo $helper->e($order->remoteid); ?>"
                           placeholder="e.g., CERT-123456789">
                    <p class="help-block text-muted">
                        The certificate ID from NicSRS API. Leave empty to clear.
                    </p>
                </div>
                
                <!-- Service ID Field -->
                <div class="form-group">
                    <label for="editServiceId">
                        <i class="fa fa-link"></i> Linked WHMCS Service ID
                    </label>
                    <div class="input-group">
                        <input type="number" 
                               class="form-control" 
                               id="editServiceId" 
                               value="<?php echo (int) $order->serviceid; ?>"
                               min="0"
                               placeholder="Enter Service ID">
                        <?php if ($order->serviceid): ?>
                        <span class="input-group-btn">
                            <a href="clientsservices.php?id=<?php echo $order->serviceid; ?>" 
                               target="_blank" 
                               class="btn btn-default" 
                               title="View Current Service">
                                <i class="fa fa-external-link"></i>
                            </a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="help-block text-muted">
                        The WHMCS hosting service ID. Set to 0 to unlink.
                    </p>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> Changing these values may affect certificate operations.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmEditOrder">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Order Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #d9534f; color: white;">
                <button type="button" class="close" data-dismiss="modal" style="color: white;">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-trash"></i> Delete Order #<?php echo $order->id; ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle fa-lg"></i>
                    <strong>Warning:</strong> This will permanently delete Order #<?php echo $order->id; ?> 
                    from the local database. This cannot be undone!
                </div>
                
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Order Details:</strong></div>
                    <div class="panel-body">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="35%"><strong>Order ID:</strong></td>
                                <td>#<?php echo $order->id; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Remote ID:</strong></td>
                                <td><code><?php echo $helper->e($order->remoteid) ?: 'N/A'; ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Domain:</strong></td>
                                <td><?php echo $helper->e($order->domain ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><?php echo $helper->statusBadge($order->status); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <strong>Note:</strong> This only removes the local record. 
                    The certificate on NicSRS will NOT be affected.
                </div>
                
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="confirmDeleteCheck">
                        <strong>I understand this action is permanent.</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteOrder" disabled>
                    <i class="fa fa-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modulelink = '<?php echo $modulelink; ?>';
    var orderId = <?php echo $order->id; ?>;
    var currentAction = '';

    // CSR data for download
    var csrData = <?php echo json_encode($config['csr'] ?? ''); ?>;
    
    // Primary domain for filename
    var primaryDomain = <?php echo json_encode($primaryDomain !== '-' ? $primaryDomain : 'certificate'); ?>;

    // ===== Download CSR Button =====
    document.getElementById('btnDownloadCsr').addEventListener('click', function() {
        if (!csrData) {
            alert('CSR is not available.');
            return;
        }
        
        var blob = new Blob([csrData], { type: 'application/pkcs10' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = primaryDomain.replace(/\*/g, 'wildcard').replace(/\./g, '_') + '.csr';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    });

    // ===== Download Certificate (ZIP) =====
    document.getElementById('btnDownloadCert').addEventListener('click', function() {
        downloadFile('download_cert', 'Preparing certificate files...', 'application/zip');
    });

    // ===== NEW: Download JKS =====
    <?php if ($hasJks): ?>
    document.getElementById('btnDownloadJks').addEventListener('click', function() {
        downloadFile('download_jks', 'Preparing JKS file...', 'application/octet-stream', true);
    });
    <?php endif; ?>

    // ===== NEW: Download PKCS12/PFX =====
    <?php if ($hasPkcs12): ?>
    document.getElementById('btnDownloadPkcs12').addEventListener('click', function() {
        downloadFile('download_pkcs12', 'Preparing PFX file...', 'application/x-pkcs12', true);
    });
    <?php endif; ?>

    /**
     * Generic file download function
     */
    function downloadFile(action, loadingMsg, mimeType, showPassword) {
        $('#loadingModal').modal('show');
        $('#loadingMessage').text(loadingMsg);
        
        $.ajax({
            url: modulelink + '&action=order&id=' + orderId,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax_action: action,
                order_id: orderId
            },
            success: function(response) {
                $('#loadingModal').modal('hide');
                
                if (response.success && response.content) {
                    // Decode base64 and download
                    var binary = atob(response.content);
                    var len = binary.length;
                    var bytes = new Uint8Array(len);
                    for (var i = 0; i < len; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    
                    var blob = new Blob([bytes], { type: mimeType || 'application/octet-stream' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.name || 'download';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    // Show password modal if applicable
                    if (showPassword && response.password) {
                        $('#filePassword').text(response.password);
                        $('.btn-copy-password').attr('data-clipboard', response.password);
                        $('#passwordModal').modal('show');
                    }
                } else {
                    alert('Error: ' + (response.message || 'Failed to download file'));
                }
            },
            error: function(xhr, status, error) {
                $('#loadingModal').modal('hide');
                alert('Request failed: ' + error);
            }
        });
    }

    // ===== Action buttons (refresh, cancel, revoke, etc.) =====
    document.querySelectorAll('.btn-order-action').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentAction = this.dataset.action;
            var messages = {
                'refresh_status': 'Refresh certificate status from NicSRS API?',
                'cancel': 'Are you sure you want to cancel this order? This action cannot be undone.',
                'revoke': 'Are you sure you want to revoke this certificate? This action cannot be undone.',
                'reissue': 'Submit a reissue request for this certificate?',
                'renew': 'Submit a renewal request for this certificate?'
            };
            
            document.getElementById('actionMessage').textContent = messages[currentAction] || 'Proceed with this action?';
            document.getElementById('reasonField').style.display = 
                ['cancel', 'revoke'].includes(currentAction) ? 'block' : 'none';
            
            $('#actionModal').modal('show');
        });
    });

    // Confirm action
    document.getElementById('confirmAction').addEventListener('click', function() {
        var reason = document.getElementById('actionReason').value;
        
        $('#actionModal').modal('hide');
        $('#loadingModal').modal('show');
        $('#loadingMessage').text('Processing action...');

        $.ajax({
            url: modulelink + '&action=order&id=' + orderId,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax_action: currentAction,
                order_id: orderId,
                reason: reason
            },
            success: function(response) {
                $('#loadingModal').modal('hide');
                
                if (response.success) {
                    alert(response.message || 'Action completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                $('#loadingModal').modal('hide');
                alert('Request failed. Please try again.');
            }
        });
    });

    // ===== Resend DCV handler =====
    document.querySelectorAll('.btn-resend-dcv').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var domain = this.dataset.domain;
            var btnEl = this;
            
            if (!confirm('Resend DCV validation for ' + domain + '?')) return;
            
            btnEl.disabled = true;
            btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            
            $.ajax({
                url: modulelink + '&action=order&id=' + orderId,
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax_action: 'resend_dcv',
                    order_id: orderId,
                    domain: domain
                },
                success: function(response) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<i class="fa fa-envelope"></i> Resend DCV';
                    
                    if (response.success) {
                        alert('DCV resent successfully!');
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function() {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<i class="fa fa-envelope"></i> Resend DCV';
                    alert('Request failed. Please try again.');
                }
            });
        });
    });

    // ===== Copy to clipboard functionality =====
    document.querySelectorAll('.btn-copy').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = this.dataset.clipboard;
            copyToClipboard(text, this);
        });
    });

    document.querySelectorAll('.btn-copy-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = this.dataset.clipboard;
            copyToClipboard(text, this);
        });
    });

    function copyToClipboard(text, btn) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                var origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
                setTimeout(function() { btn.innerHTML = origHtml; }, 2000);
            });
        } else {
            // Fallback for older browsers
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
            setTimeout(function() { btn.innerHTML = origHtml; }, 2000);
        }
    }

    // ===== Edit Order =====
    document.getElementById('btnEditOrder').addEventListener('click', function() {
        $('#editOrderModal').modal('show');
    });

    document.getElementById('confirmEditOrder').addEventListener('click', function() {
        var remoteId = document.getElementById('editRemoteId').value;
        var serviceId = document.getElementById('editServiceId').value;
        
        $('#editOrderModal').modal('hide');
        $('#loadingModal').modal('show');
        $('#loadingMessage').text('Saving changes...');
        
        $.ajax({
            url: modulelink + '&action=order&id=' + orderId,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax_action: 'edit_order',
                order_id: orderId,
                remote_id: remoteId,
                service_id: serviceId
            },
            success: function(response) {
                $('#loadingModal').modal('hide');
                if (response.success) {
                    alert(response.message || 'Order updated successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to update order'));
                }
            },
            error: function() {
                $('#loadingModal').modal('hide');
                alert('Request failed. Please try again.');
            }
        });
    });

    // ===== Delete Order =====
    document.getElementById('btnDeleteOrder').addEventListener('click', function() {
        document.getElementById('confirmDeleteCheck').checked = false;
        document.getElementById('confirmDeleteOrder').disabled = true;
        $('#deleteOrderModal').modal('show');
    });

    document.getElementById('confirmDeleteCheck').addEventListener('change', function() {
        document.getElementById('confirmDeleteOrder').disabled = !this.checked;
    });

    document.getElementById('confirmDeleteOrder').addEventListener('click', function() {
        if (!document.getElementById('confirmDeleteCheck').checked) return;
        
        $('#deleteOrderModal').modal('hide');
        $('#loadingModal').modal('show');
        $('#loadingMessage').text('Deleting order...');
        
        $.ajax({
            url: modulelink + '&action=order&id=' + orderId,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax_action: 'delete_order',
                order_id: orderId
            },
            success: function(response) {
                $('#loadingModal').modal('hide');
                if (response.success) {
                    alert(response.message || 'Order deleted successfully');
                    window.location.href = modulelink + '&action=orders';
                } else {
                    alert('Error: ' + (response.message || 'Failed to delete order'));
                }
            },
            error: function() {
                $('#loadingModal').modal('hide');
                alert('Request failed. Please try again.');
            }
        });
    });
});
</script>