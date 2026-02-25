<?php
/**
 * Import Page — Legacy Migration, Single Import, Bulk Import
 *
 * @package    AioSSL\Admin\Templates
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

defined('WHMCS') || die('Access Denied');
?>

<!-- Single Import Section -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-file-import"></i> <?= $lang['import_api'] ?? 'Import from Provider API' ?></span>
    </div>
    <div class="aio-card-body">
        <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
            <?= $lang['import_api_desc'] ?? 'Import a single certificate by providing the provider and remote order/certificate ID.' ?>
        </p>
        <form id="aio-single-import-form">
            <input type="hidden" name="token" value="<?= $csrfToken ?>" />

            <div class="aio-form-group">
                <label><?= $lang['provider'] ?? 'Provider' ?> <span class="required">*</span></label>
                <select name="provider" class="aio-form-control" required>
                    <option value="">— Select Provider —</option>
                    <option value="nicsrs">NicSRS</option>
                    <option value="gogetssl">GoGetSSL</option>
                    <option value="thesslstore">TheSSLStore</option>
                    <option value="ssl2buy">SSL2Buy</option>
                </select>
            </div>

            <div class="aio-form-group">
                <label><?= $lang['remote_id'] ?? 'Remote Order/Certificate ID' ?> <span class="required">*</span></label>
                <input type="text" name="remote_id" class="aio-form-control" required
                       placeholder="e.g. 123456" />
                <div class="aio-form-hint">The order or certificate ID from the provider's system.</div>
            </div>

            <div class="aio-form-group">
                <label><?= $lang['link_to_service'] ?? 'Link to WHMCS Service (optional)' ?></label>
                <input type="number" name="service_id" class="aio-form-control"
                       placeholder="e.g. 42" min="1" />
                <div class="aio-form-hint">WHMCS hosting/service ID to link this certificate to. Must use servertype=aio_ssl.</div>
            </div>

            <div class="aio-form-actions">
                <button type="button" class="aio-btn aio-btn-primary" onclick="AioSSL.importSingle()">
                    <i class="fas fa-file-import"></i> <?= $lang['import_btn'] ?? 'Import Certificate' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Import Section -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-file-csv"></i> <?= $lang['import_bulk'] ?? 'Bulk Import (CSV)' ?></span>
    </div>
    <div class="aio-card-body">
        <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
            <?= $lang['import_bulk_desc'] ?? 'Upload a CSV file with columns: provider, remote_id, service_id' ?>
        </p>
        <form id="aio-bulk-import-form" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= $csrfToken ?>" />

            <div class="aio-form-group">
                <label><?= $lang['upload_csv'] ?? 'Upload CSV File' ?> <span class="required">*</span></label>
                <input type="file" name="csv_file" class="aio-form-control" accept=".csv" required />
            </div>

            <div class="aio-alert aio-alert-info" style="font-size:12px;">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>CSV Format:</strong><br>
                    <code>provider,remote_id,service_id</code><br>
                    <code>nicsrs,123456,42</code><br>
                    <code>gogetssl,789012,</code> (service_id optional)
                </div>
            </div>

            <div class="aio-form-actions">
                <button type="button" class="aio-btn aio-btn-primary" onclick="bulkImport()">
                    <i class="fas fa-upload"></i> <?= $lang['upload_csv'] ?? 'Upload & Import' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Legacy Migration Section -->
<div class="aio-card" style="margin-top:16px;">
    <div class="aio-card-header" style="background:var(--aio-warning-bg);">
        <span><i class="fas fa-exchange-alt" style="color:var(--aio-warning)"></i> <?= $lang['import_legacy'] ?? 'Legacy Migration' ?></span>
    </div>
    <div class="aio-card-body">
        <p style="font-size:13px;margin-bottom:16px;">
            <?= $lang['import_legacy_desc'] ?? 'Migrate existing certificates from legacy modules (NicSRS, GoGetSSL, TheSSLStore, SSL2Buy).' ?>
        </p>
        <div class="aio-alert aio-alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Non-destructive migration:</strong> The "Claim" process creates a new record in
                <code>mod_aio_ssl_orders</code> with references to the original legacy table and order ID.
                Original records are never modified or deleted.
            </div>
        </div>

        <?php
        /**
         * Use MigrationService for accurate counts
         * - NicSRS: from nicsrs_sslorders table
         * - GoGetSSL: module='SSLCENTERWHMCS' in tblsslorders
         * - TheSSLStore: module IN ('thesslstore_ssl', 'thesslstorefullv2') in tblsslorders
         * - SSL2Buy: module='ssl2buy' in tblsslorders
         */
        $migrationService = new \AioSSL\Service\MigrationService();
        $legacyCounts = $migrationService->getLegacyCounts();
        $claimedCounts = $migrationService->getClaimedCounts();
        ?>

        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Legacy Module</th>
                        <th>Source Table</th>
                        <th>Module Names</th>
                        <th class="text-center">Total Orders</th>
                        <th class="text-center">Already Claimed</th>
                        <th class="text-center">Remaining</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $legacyInfo = [
                    'nicsrs' => [
                        'name'    => 'NicSRS SSL',
                        'table'   => 'nicsrs_sslorders',
                        'modules' => 'nicsrs_ssl',
                        'badge'   => 'aio-provider-nicsrs',
                    ],
                    'gogetssl' => [
                        'name'    => 'GoGetSSL',
                        'table'   => 'tblsslorders',
                        'modules' => 'SSLCENTERWHMCS',
                        'badge'   => 'aio-provider-gogetssl',
                    ],
                    'thesslstore' => [
                        'name'    => 'TheSSLStore',
                        'table'   => 'tblsslorders',
                        'modules' => 'thesslstore_ssl, thesslstorefullv2',
                        'badge'   => 'aio-provider-thesslstore',
                    ],
                    'ssl2buy' => [
                        'name'    => 'SSL2Buy',
                        'table'   => 'tblsslorders',
                        'modules' => 'ssl2buy',
                        'badge'   => 'aio-provider-ssl2buy',
                    ],
                ];

                foreach ($legacyInfo as $slug => $info):
                    $total   = $legacyCounts[$slug] ?? 0;
                    $claimed = $claimedCounts[$slug] ?? 0;
                    $remaining = max(0, $total - $claimed);
                ?>
                <tr>
                    <td><span class="aio-provider-badge <?= $info['badge'] ?>"><?= $info['name'] ?></span></td>
                    <td><code><?= $info['table'] ?></code></td>
                    <td><code style="font-size:11px;"><?= $info['modules'] ?></code></td>
                    <td class="text-center"><span class="aio-badge aio-badge-primary"><?= number_format($total) ?></span></td>
                    <td class="text-center"><span class="aio-badge aio-badge-success"><?= number_format($claimed) ?></span></td>
                    <td class="text-center">
                        <?php if ($remaining > 0): ?>
                        <span class="aio-badge aio-badge-warning"><?= number_format($remaining) ?></span>
                        <?php else: ?>
                        <span class="aio-badge aio-badge-success">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($total > 0): ?>
                        <button class="aio-btn aio-btn-xs aio-btn-ghost"
                                onclick="location.href='<?= $moduleLink ?>&page=orders&provider=<?= $slug ?>&source=legacy'"
                                title="View legacy orders">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($remaining > 0): ?>
                        <button class="aio-btn aio-btn-xs aio-btn-primary"
                                onclick="claimAllByProvider('<?= $slug ?>')"
                                title="Claim all unclaimed orders from <?= $info['name'] ?>">
                            <i class="fas fa-hand-holding"></i> Claim All
                        </button>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">No orders</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalRemaining = array_sum($legacyCounts) - array_sum($claimedCounts);
        if ($totalRemaining > 0):
        ?>
        <div style="margin-top:12px;text-align:right;">
            <button class="aio-btn aio-btn-warning" onclick="claimAllLegacy()">
                <i class="fas fa-magic"></i> <?= $lang['claim_all'] ?? 'Claim All Legacy Orders' ?>
                <span class="aio-badge aio-badge-light" style="margin-left:6px;"><?= number_format($totalRemaining) ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function bulkImport() {
    var fd = new FormData($('#aio-bulk-import-form')[0]);
    $.ajax({
        url: '<?= $moduleLink ?>&page=import&action=bulk&ajax=1',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() { AioSSL.showLoading('Importing...'); },
        complete: function() { AioSSL.hideLoading(); },
        success: function(resp) {
            if (resp.success) {
                AioSSL.toast(resp.message || 'Bulk import completed.', 'success', 5000);
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                AioSSL.toast(resp.message || 'Import failed.', 'error');
            }
        },
        error: function(xhr, s, e) { AioSSL.toast('Request failed: ' + e, 'error'); }
    });
}

function claimAllLegacy() {
    AioSSL.confirm(
        'Claim ALL remaining legacy orders from all modules?<br>' +
        '<small>New records will be created in <code>mod_aio_ssl_orders</code>. Original records will NOT be modified.</small>',
        function() {
            AioSSL.ajax({
                page: 'import',
                action: 'claim_all',
                loadingMsg: 'Claiming all legacy orders...',
                onSuccess: function(r) {
                    var msg = 'Claimed: ' + (r.claimed || 0) + ', Failed: ' + (r.failed || 0);
                    AioSSL.toast(msg, r.failed > 0 ? 'warning' : 'success', 5000);
                    setTimeout(function() { location.reload(); }, 2000);
                }
            });
        },
        { title: 'Claim All Legacy Orders', confirmText: 'Claim All' }
    );
}

function claimAllByProvider(provider) {
    AioSSL.confirm(
        'Claim all unclaimed orders from <strong>' + provider + '</strong>?<br>' +
        '<small>Original records will NOT be modified.</small>',
        function() {
            AioSSL.ajax({
                page: 'import',
                action: 'claim_provider',
                data: { provider: provider },
                loadingMsg: 'Claiming...',
                onSuccess: function(r) {
                    var msg = 'Claimed: ' + (r.claimed || 0) + ', Failed: ' + (r.failed || 0);
                    AioSSL.toast(msg, r.failed > 0 ? 'warning' : 'success', 5000);
                    setTimeout(function() { location.reload(); }, 2000);
                }
            });
        },
        { title: 'Claim Provider Orders', confirmText: 'Claim' }
    );
}
</script>