<?php
/**
 * Products List Template
 * 
 * @var array $products Products list
 * @var array $vendors All vendors
 * @var array $availableVendors Vendors with products
 * @var string $currentVendor Current vendor filter
 * @var string $currentType Current type filter
 * @var string $search Search query
 * @var \NicsrsAdmin\Helper\Pagination $pagination Pagination object
 * @var int $total Total products
 * @var string|null $lastSync Last sync time
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */
use NicsrsAdmin\Helper\ViewHelper;

$helper = new ViewHelper();
?>

<div class="nicsrs-products">
    <!-- Header -->
    <div class="page-header clearfix">
        <h3 class="pull-left" style="margin: 0;">
            <i class="fa fa-cube"></i> SSL Products
            <small class="text-muted">(<?php echo number_format($total); ?> products)</small>
        </h3>
        <div class="pull-right">
            <div class="btn-group">
                <button type="button" class="btn btn-success" id="btnSyncAll">
                    <i class="fa fa-refresh"></i> Sync All
                </button>
                <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-right">
                    <?php foreach ($vendors as $v): ?>
                    <li>
                        <a href="#" class="sync-vendor" data-vendor="<?php echo $helper->e($v); ?>">
                            Sync <?php echo $helper->e($v); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Linked Stats Cards -->
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-md-4">
            <div class="panel panel-default" style="margin-bottom: 10px;">
                <div class="panel-body text-center" style="padding: 10px;">
                    <h4 style="margin: 0; color: #1890ff;">
                        <?php echo $linkedStats['total']; ?>
                    </h4>
                    <small class="text-muted">Total NicSRS Products</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <a href="<?php echo $modulelink; ?>&action=products&linked=1" style="text-decoration: none;">
                <div class="panel panel-default" style="margin-bottom: 10px; cursor: pointer;">
                    <div class="panel-body text-center" style="padding: 10px;">
                        <h4 style="margin: 0; color: #52c41a;">
                            <i class="fa fa-link"></i> <?php echo $linkedStats['linked']; ?>
                        </h4>
                        <small class="text-muted">Linked to WHMCS</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?php echo $modulelink; ?>&action=products&linked=0" style="text-decoration: none;">
                <div class="panel panel-default" style="margin-bottom: 10px; cursor: pointer;">
                    <div class="panel-body text-center" style="padding: 10px;">
                        <h4 style="margin: 0; color: #8c8c8c;">
                            <i class="fa fa-unlink"></i> <?php echo $linkedStats['not_linked']; ?>
                        </h4>
                        <small class="text-muted">Not Linked</small>
                    </div>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="panel panel-default">
        <div class="panel-body">
            <form method="get" class="form-inline">
                <input type="hidden" name="module" value="nicsrs_ssl_admin">
                <input type="hidden" name="action" value="products">
                
                <div class="form-group" style="margin-right: 10px;">
                    <select name="vendor" class="form-control" style="width: 150px;">
                        <option value="">All Vendors</option>
                        <?php foreach ($availableVendors as $v): ?>
                        <option value="<?php echo $helper->e($v); ?>" 
                                <?php echo $currentVendor === $v ? 'selected' : ''; ?>>
                            <?php echo $helper->e($v); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-right: 10px;">
                    <select name="type" class="form-control" style="width: 120px;">
                        <option value="">All Types</option>
                        <option value="dv" <?php echo $currentType === 'dv' ? 'selected' : ''; ?>>DV</option>
                        <option value="ov" <?php echo $currentType === 'ov' ? 'selected' : ''; ?>>OV</option>
                        <option value="ev" <?php echo $currentType === 'ev' ? 'selected' : ''; ?>>EV</option>
                    </select>
                </div>

                <div class="form-group" style="margin-right: 10px;">
                    <select name="linked" class="form-control" style="width: 140px;">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $linkedFilter === '1' ? 'selected' : ''; ?>>
                            Linked Only
                        </option>
                        <option value="0" <?php echo $linkedFilter === '0' ? 'selected' : ''; ?>>
                            Not Linked
                        </option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-right: 10px;">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search product..." 
                           value="<?php echo $helper->e($search); ?>"
                           style="width: 200px;">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Filter
                </button>
                
                <?php if ($currentVendor || $currentType || $search || $linkedFilter !== ''): ?>
                <a href="<?php echo $modulelink; ?>&action=products" class="btn btn-default">
                    <i class="fa fa-times"></i> Clear
                </a>
                <?php endif; ?>
                
                <?php if ($lastSync): ?>
                <span class="text-muted pull-right" style="line-height: 34px;">
                    <i class="fa fa-clock-o"></i> Last sync: <?php echo $helper->formatDate($lastSync, 'Y-m-d H:i'); ?>
                </span>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-striped table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 150px;">Product Code</th>
                        <th>Product Name</th>
                        <th style="width: 100px;">Vendor</th>
                        <th class="text-center" style="width: 60px;">Type</th>
                        <th class="text-center" style="width: 70px;">Wildcard</th>
                        <th class="text-center" style="width: 60px;">SAN</th>
                        <th class="text-center" style="width: 50px;">Max</th>
                        <th class="text-right" style="width: 80px;">1Y Price</th>
                        <th class="text-right" style="width: 80px;">2Y Price</th>
                        <th class="text-center" style="width: 150px;">WHMCS Product</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted" style="padding: 40px;">
                            <?php if ($currentVendor || $currentType || $search || $linkedFilter !== ''): ?>
                            No products found matching your filters.
                            <?php else: ?>
                            No products available. Please sync products first.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <code><?php echo $helper->e($product['product_code']); ?></code>
                        </td>
                        <td>
                            <strong><?php echo $helper->e($product['product_name']); ?></strong>
                        </td>
                        <td><?php echo $helper->e($product['vendor']); ?></td>
                        <td class="text-center">
                            <?php echo $helper->validationBadge($product['validation_type']); ?>
                        </td>
                        <td class="text-center">
                            <?php echo $helper->yesNoIcon($product['support_wildcard']); ?>
                        </td>
                        <td class="text-center">
                            <?php echo $helper->yesNoIcon($product['support_san']); ?>
                        </td>
                        <td class="text-center"><?php echo $product['max_domains']; ?></td>
                        <td class="text-right">
                            <?php echo $helper->formatPrice($product['price_1y']); ?>
                        </td>
                        <td class="text-right">
                            <?php echo $helper->formatPrice($product['price_2y']); ?>
                        </td>
                        <!-- NEW: WHMCS Product Linked Status Column -->
                        <td class="text-center">
                            <?php if ($product['is_linked']): ?>
                                <?php 
                                $whmcsProduct = $product['linked_whmcs'];
                                $productUrl = 'configproducts.php?action=edit&id=' . $whmcsProduct->id;
                                
                                // Determine status
                                if ($whmcsProduct->retired) {
                                    $statusClass = 'label-warning';
                                    $statusIcon = 'fa-archive';
                                    $statusTitle = 'Retired';
                                } elseif ($whmcsProduct->hidden) {
                                    $statusClass = 'label-info';
                                    $statusIcon = 'fa-eye-slash';
                                    $statusTitle = 'Hidden';
                                } else {
                                    $statusClass = 'label-success';
                                    $statusIcon = 'fa-check-circle';
                                    $statusTitle = 'Active';
                                }
                                ?>
                                <a href="<?php echo $productUrl; ?>" target="_blank" 
                                   class="label <?php echo $statusClass; ?>" 
                                   style="display: inline-block; margin-bottom: 3px;"
                                   title="<?php echo $helper->e($whmcsProduct->name); ?> - <?php echo $statusTitle; ?>">
                                    <i class="fa <?php echo $statusIcon; ?>"></i> Linked
                                </a>
                                <br>
                                <small class="text-muted" title="<?php echo $helper->e($whmcsProduct->name); ?>">
                                    #<?php echo $whmcsProduct->id; ?> - 
                                    <?php echo $whmcsProduct->name; ?>
                                </small>
                            <?php else: ?>
                                <span class="label label-default" title="Not linked to any WHMCS product">
                                    <i class="fa fa-unlink"></i> Not Linked
                                </span>
                                <br>
                                <a href="configproducts.php?action=create" target="_blank" 
                                   class="text-muted" style="font-size: 11px;">
                                    <i class="fa fa-plus"></i> Create
                                </a>
                            <?php endif; ?>
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

<!-- Sync Modal -->
<div class="modal fade" id="syncModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 40px;">
                <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                <h4 style="margin-top: 20px;">Syncing Products...</h4>
                <p class="text-muted">Please wait while we fetch products from NicSRS API.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modulelink = '<?php echo $modulelink; ?>&action=products';
    
    // Sync All Products
    document.getElementById('btnSyncAll').addEventListener('click', function() {
        if (!confirm('Sync products from all vendors? This may take a moment.')) {
            return;
        }
        
        var btn = this;
        btn.disabled = true;
        $('#syncModal').modal({backdrop: 'static', keyboard: false});
        
        $.ajax({
            url: modulelink,
            method: 'POST',
            data: { ajax_action: 'sync_all' },
            dataType: 'json',
            success: function(response) {
                $('#syncModal').modal('hide');
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    if (response.errors && response.errors.length) {
                        console.log('Sync errors:', response.errors);
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#syncModal').modal('hide');
                console.error('AJAX Error:', status, error);
                console.log('Response:', xhr.responseText);
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.disabled = false;
            }
        });
    });
    
    // Sync Specific Vendor
    document.querySelectorAll('.sync-vendor').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var vendor = this.getAttribute('data-vendor');
            
            if (!confirm('Sync products from ' + vendor + '?')) {
                return;
            }
            
            $('#syncModal').modal({backdrop: 'static', keyboard: false});
            
            $.ajax({
                url: modulelink,
                method: 'POST',
                data: {
                    ajax_action: 'sync_vendor',
                    vendor: vendor
                },
                dataType: 'json',
                success: function(response) {
                    $('#syncModal').modal('hide');
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    $('#syncModal').modal('hide');
                    console.error('AJAX Error:', status, error);
                    alert('Request failed. Please try again.');
                }
            });
        });
    });
});
</script>