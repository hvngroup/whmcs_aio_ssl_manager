<?php
/**
 * Orders List Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $orders      - array of order rows (with domain, client_name joins)
 *   $pagination  - array [total, page, limit, offset, pages]
 *   $filters     - array [statusFilter, providerFilter, search]
 *   $moduleLink  - string
 *   $lang        - array
 *   $csrfToken   - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

$providerNames = [
    'nicsrs_ssl' => 'NicSRS', 'SSLCENTERWHMCS' => 'GoGetSSL',
    'thesslstore_ssl' => 'TheSSLStore', 'thesslstore' => 'TheSSLStore',
    'ssl2buy' => 'SSL2Buy', 'aio_ssl' => 'AIO SSL',
];
$providerBadgeMap = [
    'nicsrs'=>'aio-provider-nicsrs', 'gogetssl'=>'aio-provider-gogetssl',
    'thesslstore'=>'aio-provider-thesslstore', 'ssl2buy'=>'aio-provider-ssl2buy',
];
$moduleToSlug = [
    'nicsrs_ssl'=>'nicsrs', 'SSLCENTERWHMCS'=>'gogetssl',
    'thesslstore_ssl'=>'thesslstore', 'thesslstore'=>'thesslstore',
    'ssl2buy'=>'ssl2buy', 'aio_ssl'=>'aio',
];

$statusFilter   = $filters['statusFilter'] ?? '';
$providerFilter = $filters['providerFilter'] ?? '';
$search         = $filters['search'] ?? '';
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-list-alt"></i> <?= $lang['orders_title'] ?? 'Order Management' ?>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn aio-btn-ghost" onclick="AioSSL.ajax({page:'orders',action:'bulk_refresh',data:{ids:getSelectedIds()},loadingMsg:'Refreshing...',onSuccess:function(){location.reload();}})">
            <i class="fas fa-sync-alt"></i> <?= $lang['bulk_refresh'] ?? 'Bulk Refresh' ?>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="aio-filters">
    <form method="get" action="<?= $moduleLink ?>" style="display:contents;">
        <input type="hidden" name="module" value="aio_ssl_admin" />
        <input type="hidden" name="page" value="orders" />

        <select name="provider" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value=""><?= $lang['all_providers'] ?? 'All Providers' ?></option>
            <?php foreach (['nicsrs_ssl','SSLCENTERWHMCS','thesslstore_ssl','ssl2buy','aio_ssl'] as $mod): ?>
            <option value="<?= $mod ?>" <?= $providerFilter === $mod ? 'selected' : '' ?>>
                <?= $providerNames[$mod] ?? $mod ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value=""><?= $lang['all_statuses'] ?? 'All Statuses' ?></option>
            <?php foreach (['Pending','Processing','Completed','Active','Expired','Cancelled','Awaiting Configuration'] as $st): ?>
            <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>

        <input type="search" name="search" class="aio-form-control" style="width:220px;"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="<?= $lang['search_orders'] ?? 'Search domain or client...' ?>" />
        <button type="submit" class="aio-btn"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Orders Table -->
<div class="aio-card">
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($orders)): ?>
        <div class="aio-empty" style="padding:40px;">
            <i class="fas fa-inbox"></i>
            <p><?= $lang['no_orders_found'] ?? 'No orders match your filters.' ?></p>
        </div>
        <?php else: ?>
        <div class="aio-table-wrapper">
            <table class="aio-table" id="orders-table">
                <thead>
                    <tr>
                        <th class="col-check"><input type="checkbox" class="aio-check-all" /></th>
                        <th><?= $lang['order_id'] ?? 'Order ID' ?></th>
                        <th><?= $lang['provider'] ?? 'Provider' ?></th>
                        <th><?= $lang['domain'] ?? 'Domain' ?></th>
                        <th><?= $lang['client'] ?? 'Client' ?></th>
                        <th><?= $lang['service'] ?? 'Service' ?></th>
                        <th><?= $lang['status'] ?? 'Status' ?></th>
                        <th><?= $lang['source'] ?? 'Source' ?></th>
                        <th><?= $lang['date'] ?? 'Date' ?></th>
                        <th class="text-center"><?= $lang['actions'] ?? 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $cfg = is_string($o->configdata) ? (json_decode($o->configdata, true) ?: []) : [];
                    $slug = $cfg['provider'] ?? ($moduleToSlug[$o->module] ?? '');
                    $isAio = ($o->module === 'aio_ssl');

                    // Status badge class
                    $st = $o->status ?? 'Unknown';
                    $stClass = 'aio-badge-default';
                    if (in_array($st, ['Completed','Issued','Active'])) $stClass = 'aio-badge-success';
                    elseif (in_array($st, ['Pending','Processing'])) $stClass = 'aio-badge-primary';
                    elseif (in_array($st, ['Expired','Cancelled','Revoked'])) $stClass = 'aio-badge-danger';
                    elseif (stripos($st, 'Awaiting') !== false) $stClass = 'aio-badge-warning';
                ?>
                <tr>
                    <td class="col-check"><input type="checkbox" class="aio-check-item" value="<?= $o->id ?>" /></td>
                    <td>
                        <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>" class="aio-link" style="font-weight:500;">
                            #<?= $o->id ?>
                        </a>
                    </td>
                    <td>
                        <span class="aio-provider-badge <?= $providerBadgeMap[$slug] ?? 'aio-badge-default' ?>">
                            <?= htmlspecialchars($providerNames[$o->module] ?? ucfirst($slug)) ?>
                        </span>
                    </td>
                    <td class="text-mono"><?= htmlspecialchars($o->domain ?? '—') ?></td>
                    <td>
                        <?php if (!empty($o->userid)): ?>
                        <a href="clientssummary.php?userid=<?= $o->userid ?>" class="aio-link-client">
                            <?= htmlspecialchars($o->client_name ?? 'Client #' . $o->userid) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($o->serviceid)): ?>
                        <a href="clientsservices.php?id=<?= $o->serviceid ?>" class="aio-link-service">
                            #<?= $o->serviceid ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="aio-badge <?= $stClass ?>"><?= htmlspecialchars($st) ?></span></td>
                    <td>
                        <span class="aio-badge <?= $isAio ? 'aio-source-aio' : 'aio-source-legacy' ?>">
                            <?= $isAio ? 'AIO' : 'Legacy' ?>
                        </span>
                    </td>
                    <td class="text-nowrap" style="color:var(--aio-text-secondary);">
                        <?= !empty($o->created_at) ? date('Y-m-d', strtotime($o->created_at)) : '—' ?>
                    </td>
                    <td class="text-center">
                        <div class="aio-btn-group">
                            <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>"
                               class="aio-btn aio-btn-xs aio-btn-ghost" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="aio-btn aio-btn-xs" onclick="AioSSL.refreshOrder(<?= $o->id ?>)" title="Refresh Status">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <div class="aio-pagination" style="padding:12px 16px;">
            <div class="aio-pagination-info">
                Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $pagination['limit'], $pagination['total']) ?>
                of <?= number_format($pagination['total']) ?> orders
            </div>
            <div class="aio-pagination-links">
                <?php
                $curPage = $pagination['page'];
                $totalPages = $pagination['pages'];
                $baseUrl = $moduleLink . '&page=orders'
                    . ($providerFilter ? '&provider=' . urlencode($providerFilter) : '')
                    . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')
                    . ($search ? '&search=' . urlencode($search) : '');

                if ($curPage > 1): ?>
                    <a href="<?= $baseUrl ?>&p=<?= $curPage - 1 ?>">‹ Prev</a>
                <?php endif;

                $start = max(1, $curPage - 2);
                $end = min($totalPages, $curPage + 2);
                for ($i = $start; $i <= $end; $i++):
                    if ($i === $curPage): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>&p=<?= $i ?>"><?= $i ?></a>
                    <?php endif;
                endfor;

                if ($curPage < $totalPages): ?>
                    <a href="<?= $baseUrl ?>&p=<?= $curPage + 1 ?>">Next ›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
function getSelectedIds() {
    var ids = [];
    $('.aio-check-item:checked').each(function() { ids.push($(this).val()); });
    return ids.join(',');
}
</script>