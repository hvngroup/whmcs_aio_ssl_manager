<?php
/**
 * Orders List Template — Unified across 3 tables
 *
 * Variables from OrderController::renderList():
 *   $orders      - array of unified order rows
 *   $pagination  - array [total, page, limit, offset, pages]
 *   $filters     - array [statusFilter, providerFilter, sourceFilter, search]
 *   $moduleLink  - string
 *   $lang        - array
 *   $csrfToken   - string
 */

if (!defined('WHMCS')) die('Access denied.');

$providerBadgeMap = [
    'nicsrs'      => 'aio-provider-nicsrs',
    'gogetssl'    => 'aio-provider-gogetssl',
    'thesslstore' => 'aio-provider-thesslstore',
    'ssl2buy'     => 'aio-provider-ssl2buy',
    'aio'         => 'aio-provider-aio',
];
$providerDisplayNames = [
    'nicsrs'      => 'NicSRS',
    'gogetssl'    => 'GoGetSSL',
    'thesslstore' => 'TheSSLStore',
    'ssl2buy'     => 'SSL2Buy',
    'aio'         => 'AIO SSL',
];

$statusFilter   = $filters['statusFilter'] ?? '';
$providerFilter = $filters['providerFilter'] ?? '';
$sourceFilter   = $filters['sourceFilter'] ?? '';
$search         = $filters['search'] ?? '';
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-list-alt"></i> <?= $lang['orders_title'] ?? 'Order Management' ?>
        <span class="aio-badge aio-badge-primary" style="font-size:11px;margin-left:8px;">
            <?= number_format($pagination['total'] ?? 0) ?> orders
        </span>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn aio-btn-ghost" onclick="bulkRefreshSelected()">
            <i class="fas fa-sync-alt"></i> <?= $lang['bulk_refresh'] ?? 'Bulk Refresh' ?>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="aio-filters" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <form method="get" action="" style="display:contents;">
        <input type="hidden" name="module" value="aio_ssl_admin" />
        <input type="hidden" name="page" value="orders" />

        <!-- Provider Filter (by slug) -->
        <select name="provider" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value=""><?= $lang['all_providers'] ?? 'All Providers' ?></option>
            <?php foreach (['nicsrs','gogetssl','thesslstore','ssl2buy'] as $slug): ?>
            <option value="<?= $slug ?>" <?= $providerFilter === $slug ? 'selected' : '' ?>>
                <?= $providerDisplayNames[$slug] ?>
            </option>
            <?php endforeach; ?>
        </select>

        <!-- Source Filter -->
        <select name="source" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value="">All Sources</option>
            <option value="aio" <?= $sourceFilter === 'aio' ? 'selected' : '' ?>>AIO (Native + Claimed)</option>
            <option value="legacy" <?= $sourceFilter === 'legacy' ? 'selected' : '' ?>>Legacy Only</option>
        </select>

        <!-- Status Filter -->
        <select name="status" class="aio-form-control" onchange="this.form.submit()" style="width:auto;">
            <option value=""><?= $lang['all_statuses'] ?? 'All Statuses' ?></option>
            <?php foreach (['Pending','Processing','Completed','active','complete','expired','cancelled','Awaiting Configuration'] as $st): ?>
            <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                <?= ucfirst($st) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <!-- Search -->
        <input type="text" name="search" class="aio-form-control" value="<?= htmlspecialchars($search) ?>"
               placeholder="<?= $lang['search_placeholder'] ?? 'Search domain, client, remote ID...' ?>"
               style="width:200px;" />
        <button type="submit" class="aio-btn aio-btn-ghost"><i class="fas fa-search"></i></button>

        <?php if ($statusFilter || $providerFilter || $sourceFilter || $search): ?>
        <a href="<?= $moduleLink ?>&page=orders" class="aio-btn aio-btn-ghost" title="Clear filters">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders Table -->
<div class="aio-card">
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()" /></th>
                        <th>ID</th>
                        <th>Source</th>
                        <th>Provider</th>
                        <th>Domain</th>
                        <th>Product / Service</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th>Expires</th>
                        <th>Updated</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="12" class="text-center" style="padding:40px;color:var(--aio-text-secondary);">
                        <i class="fas fa-inbox" style="font-size:24px;"></i><br>
                        No orders found.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($orders as $o):
                    $slug = $o->_provider_slug ?? $o->provider_slug ?? '';
                    $isLegacy = $o->_is_legacy ?? false;
                    $sourceTable = $o->_source_table ?? '';
                    $badgeClass = $providerBadgeMap[$slug] ?? '';
                    $providerName = $providerDisplayNames[$slug] ?? ucfirst($slug);
                    $domain = $o->display_domain ?? $o->domain ?? '';
                    $dateStr = $o->_sort_date ?? $o->created_at ?? $o->completiondate ?? '';

                    // ── Determine correct detail link source param ──
                    // This MUST match the actual table the record lives in.
                    // - mod_aio_ssl_orders → source=aio (even if it was claimed from legacy)
                    // - tblsslorders       → source=tblssl
                    // - nicsrs_sslorders   → source=nicsrs
                    $detailSource = match($sourceTable) {
                        'mod_aio_ssl_orders' => 'aio',
                        'nicsrs_sslorders'   => 'nicsrs',
                        'tblsslorders'       => 'tblssl',
                        default              => '',
                    };

                    // An order from mod_aio_ssl_orders is "AIO" regardless of
                    // whether it was originally claimed from a legacy table
                    $isAio = ($sourceTable === 'mod_aio_ssl_orders');

                    // ── Normalize status for display ──
                    $rawStatus = trim($o->status ?? 'Unknown');
                    $statusNorm = strtolower($rawStatus);
                    $statusMap = [
                        'active'                    => ['Active',     'aio-status-active',   'fa-check-circle'],
                        'complete'                  => ['Active',     'aio-status-active',   'fa-check-circle'],
                        'completed'                 => ['Active',     'aio-status-active',   'fa-check-circle'],
                        'configuration submitted'   => ['Active',     'aio-status-active',   'fa-check-circle'],
                        'issued'                    => ['Active',     'aio-status-active',   'fa-check-circle'],
                        'pending'                   => ['Pending',    'aio-status-pending',  'fa-clock'],
                        'processing'                => ['Processing', 'aio-status-pending',  'fa-spinner'],
                        'awaiting configuration'    => ['Awaiting',   'aio-status-awaiting', 'fa-hourglass-half'],
                        'awaiting'                  => ['Awaiting',   'aio-status-awaiting', 'fa-hourglass-half'],
                        'expired'                   => ['Expired',    'aio-status-expired',  'fa-calendar-times'],
                        'cancelled'                 => ['Cancelled',  'aio-status-cancelled','fa-ban'],
                        'canceled'                  => ['Cancelled',  'aio-status-cancelled','fa-ban'],
                        'revoked'                   => ['Revoked',    'aio-status-revoked',  'fa-times-circle'],
                        'rejected'                  => ['Rejected',   'aio-status-cancelled','fa-exclamation-circle'],
                        'draft'                     => ['Draft',      'aio-status-awaiting', 'fa-pencil-alt'],
                    ];
                    $statusInfo = $statusMap[$statusNorm] ?? [ucfirst($rawStatus), '', 'fa-question-circle'];
                    $statusLabel = $statusInfo[0];
                    $statusClass = $statusInfo[1];
                    $statusIcon  = $statusInfo[2];

                    // ── Parse dates ──
                    $configdata = json_decode($o->configdata ?? '', true) ?: [];

                    // Issued date: completiondate or begin_date from configdata
                    $issuedDate = null;
                    $completionRaw = $o->completiondate ?? $o->begin_date ?? $configdata['begin_date'] ?? null;
                    if (!empty($completionRaw) && $completionRaw !== '0000-00-00 00:00:00' && $completionRaw !== '0000-00-00') {
                        $issuedDate = $completionRaw;
                    }

                    // Expiry date: end_date from column or configdata
                    $expiryDate = $o->end_date ?? $configdata['end_date'] ?? null;
                    if (!empty($expiryDate) && ($expiryDate === '0000-00-00 00:00:00' || $expiryDate === '0000-00-00')) {
                        $expiryDate = null;
                    }

                    // Check if expiring soon (< 30 days)
                    $isExpiringSoon = false;
                    if ($expiryDate && strtotime($expiryDate) && strtotime($expiryDate) < strtotime('+30 days')) {
                        $isExpiringSoon = true;
                    }

                    // Updated date
                    $updatedDate = $o->updated_at ?? $o->created_at ?? null;
                    if (!empty($updatedDate) && ($updatedDate === '0000-00-00 00:00:00')) {
                        $updatedDate = null;
                    }

                    // Product & Service info
                    $productName = $o->product_name ?? $o->certtype ?? '—';
                    $serviceId = $o->serviceid ?? $o->service_id ?? 0;
                    $clientId = $o->client_id ?? $o->userid ?? 0;
                    $clientName = $o->client_name ?? '—';
                ?>
                <tr>
                    <td><input type="checkbox" class="order-checkbox" value="<?= $o->id ?>" data-source="<?= $sourceTable ?>" /></td>
                    <td>
                        <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>&source=<?= $detailSource ?>"
                           class="aio-link" style="font-weight:600;">
                            #<?= $o->id ?>
                        </a>
                    </td>
                    <td>
                        <span class="aio-badge <?= $isAio ? 'aio-source-aio' : 'aio-source-legacy' ?>"
                              style="font-size:10px;">
                            <?= $isAio ? 'AIO' : 'Legacy' ?>
                        </span>
                    </td>
                    <td>
                        <span class="aio-provider-badge <?= $badgeClass ?>"><?= $providerName ?></span>
                    </td>
                    <td style="font-family:monospace;font-size:11px;" title="<?= htmlspecialchars($domain) ?>">
                        <?= htmlspecialchars(mb_strimwidth($domain, 0, 35, '...')) ?>
                    </td>

                    <!-- Product / Service — link to WHMCS Client Services -->
                    <td style="font-size:12px;">
                        <?php if ($serviceId > 0): ?>
                            <a href="clientsservices.php?userid=<?= $clientId ?>&id=<?= $serviceId ?>"
                               class="aio-link" title="View Service #<?= $serviceId ?>">
                                <?= htmlspecialchars(mb_strimwidth($productName, 0, 40, '...')) ?>
                            </a>
                        <?php else: ?>
                            <span style="color:var(--aio-text-secondary);">
                                <?= htmlspecialchars(mb_strimwidth($productName, 0, 40, '...')) ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Client — link to WHMCS Client Profile -->
                    <td style="font-size:12px;">
                        <?php if ($clientId > 0): ?>
                            <a href="clientssummary.php?userid=<?= $clientId ?>"
                               class="aio-link" title="View Client #<?= $clientId ?>">
                                <?= htmlspecialchars($clientName) ?>
                            </a>
                        <?php else: ?>
                            <span style="color:var(--aio-text-secondary);"><?= htmlspecialchars($clientName) ?></span>
                        <?php endif; ?>
                    </td>

                    <!-- Normalized Status -->
                    <td>
                        <span class="aio-badge <?= $statusClass ?>" title="Raw: <?= htmlspecialchars($rawStatus) ?>">
                            <i class="fas <?= $statusIcon ?>" style="font-size:10px;margin-right:3px;"></i>
                            <?= $statusLabel ?>
                        </span>
                    </td>

                    <!-- Issued Date -->
                    <td class="text-nowrap" style="font-size:11px;color:var(--aio-text-secondary);">
                        <?= $issuedDate ? date('Y-m-d', strtotime($issuedDate)) : '<span style="color:#ccc;">—</span>' ?>
                    </td>

                    <!-- Expiry Date -->
                    <td class="text-nowrap" style="font-size:11px;">
                        <?php if ($expiryDate): ?>
                            <span style="color:<?= $isExpiringSoon ? 'var(--aio-danger)' : 'var(--aio-text-secondary)' ?>;<?= $isExpiringSoon ? 'font-weight:600;' : '' ?>">
                                <?= date('Y-m-d', strtotime($expiryDate)) ?>
                                <?php if ($isExpiringSoon): ?>
                                    <i class="fas fa-exclamation-triangle" style="font-size:10px;color:var(--aio-danger);" title="Expiring soon!"></i>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Last Updated -->
                    <td class="text-nowrap" style="font-size:11px;color:var(--aio-text-secondary);">
                        <?= $updatedDate ? date('Y-m-d', strtotime($updatedDate)) : '<span style="color:#ccc;">—</span>' ?>
                    </td>

                    <td class="text-center">
                        <div class="aio-btn-group">
                            <a href="<?= $moduleLink ?>&page=orders&action=detail&id=<?= $o->id ?>&source=<?= $detailSource ?>"
                               class="aio-btn aio-btn-xs aio-btn-ghost" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (!$isAio && $isLegacy): ?>
                            <button class="aio-btn aio-btn-xs aio-btn-primary"
                                    onclick="claimOrder(<?= $o->id ?>, '<?= $sourceTable ?>')"
                                    title="Claim this legacy order">
                                <i class="fas fa-hand-holding"></i>
                            </button>
                            <?php else: ?>
                            <button class="aio-btn aio-btn-xs" onclick="refreshOrder(<?= $o->id ?>, '<?= $sourceTable ?>')" title="Refresh Status">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if (($pagination['pages'] ?? 1) > 1): ?>
<div class="aio-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;">
    <div class="aio-pagination-info" style="font-size:12px;color:var(--aio-text-secondary);">
        Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $pagination['limit'], $pagination['total']) ?>
        of <?= number_format($pagination['total']) ?>
    </div>
    <div class="aio-pagination-links">
        <?php
        $baseUrl = $moduleLink . '&page=orders'
            . ($providerFilter ? '&provider=' . urlencode($providerFilter) : '')
            . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')
            . ($sourceFilter ? '&source=' . urlencode($sourceFilter) : '')
            . ($search ? '&search=' . urlencode($search) : '');

        for ($p = 1; $p <= $pagination['pages']; $p++):
            $active = ($p === $pagination['page']) ? 'aio-page-active' : '';
        ?>
        <a href="<?= $baseUrl ?>&p=<?= $p ?>" class="aio-btn aio-btn-xs <?= $active ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<script>
function toggleSelectAll() {
    var checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.order-checkbox').forEach(function(cb) { cb.checked = checked; });
}

function getSelectedIds() {
    var ids = [];
    document.querySelectorAll('.order-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
    return ids.join(',');
}

function refreshOrder(id, sourceTable) {
    AioSSL.ajax({
        page: 'orders', action: 'refresh_status',
        data: { id: id, source_table: sourceTable || 'mod_aio_ssl_orders' },
        loadingMsg: 'Refreshing...',
        onSuccess: function() { location.reload(); }
    });
}

function bulkRefreshSelected() {
    var ids = getSelectedIds();
    if (!ids) { AioSSL.toast('Select orders first.', 'warning'); return; }
    AioSSL.ajax({
        page: 'orders', action: 'bulk_refresh',
        data: { ids: ids },
        loadingMsg: 'Refreshing...',
        onSuccess: function(r) {
            AioSSL.toast(r.message || 'Done', 'success');
            setTimeout(function() { location.reload(); }, 1500);
        }
    });
}

function claimOrder(id, sourceTable) {
    AioSSL.confirm(
        'Claim this legacy order?<br><small>A new AIO record will be created. The original record will NOT be modified.</small>',
        function() {
            AioSSL.ajax({
                page: 'orders', action: 'claim',
                data: { id: id, source_table: sourceTable },
                loadingMsg: 'Claiming...',
                onSuccess: function(r) {
                    AioSSL.toast(r.message || 'Claimed!', 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                }
            });
        },
        { title: 'Claim Legacy Order', confirmText: 'Claim' }
    );
}
</script>