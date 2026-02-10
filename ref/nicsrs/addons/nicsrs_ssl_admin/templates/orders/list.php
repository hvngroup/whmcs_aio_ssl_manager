<?php
/**
 * Orders List Template
 * 
 * @var array $orders Orders list
 * @var array $statusCounts Status counts
 * @var int $expiringCount Count of expiring certificates
 * @var int $expiredCount Count of expired certificates  
 * @var string $currentStatus Current status filter
 * @var string $search Search query
 * @var \NicsrsAdmin\Helper\Pagination $pagination Pagination object
 * @var int $total Total orders
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */
?>

<div class="nicsrs-orders">
    
    <!-- Header -->
    <div class="page-header clearfix">
        <h3 class="pull-left">
            <i class="fa fa-shopping-cart"></i> SSL Orders
            <small class="text-muted">(<?php echo number_format($total); ?> orders)</small>
        </h3>
    </div>

    <!-- Status Filters -->
    <ul class="nav nav-pills" style="margin-bottom: 20px;">
        <li class="<?php echo empty($currentStatus) ? 'active' : ''; ?>">
            <a href="<?php echo $modulelink; ?>&action=orders">
                All <span class="badge"><?php echo array_sum($statusCounts); ?></span>
            </a>
        </li>
        <?php 
        // Status list with display labels
        $statusList = [
            'awaiting' => 'Awaiting',
            'draft' => 'Draft',
            'pending' => 'Pending', 
            'complete' => 'Complete',
            'cancelled' => 'Cancelled',
            'revoked' => 'Revoked',
            'expired' => 'Expired',
        ];
        foreach ($statusList as $statusKey => $statusLabel): 
            $count = isset($statusCounts[$statusKey]) ? $statusCounts[$statusKey] : 0;
            // Show tab if has count OR is currently selected
            if ($count == 0 && $currentStatus !== $statusKey) continue;
        ?>
        <li class="<?php echo $currentStatus === $statusKey ? 'active' : ''; ?>">
            <a href="<?php echo $modulelink; ?>&action=orders&status=<?php echo $statusKey; ?>">
                <?php echo $statusLabel; ?> <span class="badge"><?php echo $count; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <!-- Special filter: Expiring Soon (dynamic, based on endDate) -->
        <?php if (isset($expiringCount) && ($expiringCount > 0 || $currentStatus === 'expiring')): ?>
        <li class="<?php echo $currentStatus === 'expiring' ? 'active' : ''; ?>">
            <a href="<?php echo $modulelink; ?>&action=orders&status=expiring" 
               style="<?php echo $currentStatus !== 'expiring' ? 'color: #d48806; background-color: #fffbe6;' : ''; ?>">
                <i class="fa fa-clock-o"></i> Expiring Soon 
                <span class="badge" style="background-color: #d48806;"><?php echo $expiringCount; ?></span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Search Form -->
    <div class="panel panel-default">
        <div class="panel-body">
            <form method="get" class="form-inline">
                <input type="hidden" name="module" value="nicsrs_ssl_admin">
                <input type="hidden" name="action" value="orders">
                <?php if ($currentStatus): ?>
                <input type="hidden" name="status" value="<?php echo $helper->e($currentStatus); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <div class="input-group" style="width: 350px;">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by domain, client name, email, or remote ID..."
                               value="<?php echo $helper->e($search); ?>">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-default">
                                <i class="fa fa-search"></i> Search
                            </button>
                        </span>
                    </div>
                </div>
                
                <?php if ($search): ?>
                <a href="<?php echo $modulelink; ?>&action=orders<?php echo $currentStatus ? '&status=' . $currentStatus : ''; ?>" 
                   class="btn btn-link">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="panel panel-default">
        <div class="panel-body" style="padding: 0;">
            <table class="table table-striped table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>Client</th>
                        <th>Product</th>
                        <th class="text-center">Status</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 40px;">
                            No orders found<?php echo $search ? ' matching your search' : ''; ?>.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $order['id']; ?>">
                                <strong>#<?php echo $order['id']; ?></strong>
                            </a>
                            <?php if ($order['remoteid']): ?>
                            <br><small class="text-muted"><?php echo $order['remoteid']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $helper->truncate($order['domain'], 30); ?></strong>
                        </td>
                        <td>
                            <?php if ($order['userid']): ?>
                            <a href="clientssummary.php?userid=<?php echo $order['userid']; ?>" target="_blank">
                                <?php echo $helper->e($order['client_name']); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">Unknown</span>
                            <?php endif; ?>
                            <?php if ($order['companyname']): ?>
                            <br><small class="text-muted"><?php echo $helper->e($order['companyname']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $productName = $helper->getProductName($order['certtype']);
                            ?>
                            <small title="<?php echo $helper->e($order['certtype']); ?>">
                                <?php echo $helper->e($productName); ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?php echo $helper->statusBadge($order['status']); ?>
                        </td>
                        <td>
                            <?php if ($order['end_date']): ?>
                                <?php echo $helper->daysLeftBadge($order['end_date']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo $helper->formatDate($order['provisiondate']); ?></small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $order['id']; ?>" 
                                   class="btn btn-xs btn-primary" title="View Details">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <?php if ($order['serviceid']): ?>
                                <a href="clientsservices.php?id=<?php echo $order['serviceid']; ?>" 
                                   class="btn btn-xs btn-default" title="View Service" target="_blank">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination->getTotalPages() > 1): ?>
        <div class="panel-footer clearfix">
            <div class="pull-left text-muted" style="line-height: 34px;">
                <?php echo $pagination->getInfo(); ?>
            </div>
            <div class="pull-right">
                <?php echo $pagination->render(); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>