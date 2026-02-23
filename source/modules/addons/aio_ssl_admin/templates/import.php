<?php
/**
 * Import Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $enabledProviders - array of enabled provider rows
 *   $moduleLink       - string
 *   $lang             - array
 *   $csrfToken        - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

$providerNames = [
    'nicsrs' => 'NicSRS', 'gogetssl' => 'GoGetSSL',
    'thesslstore' => 'TheSSLStore', 'ssl2buy' => 'SSL2Buy',
];

// Get enabled providers for dropdown
try {
    $enabledProviders = $enabledProviders ?? \WHMCS\Database\Capsule::table('mod_aio_ssl_providers')
        ->where('is_enabled', 1)->orderBy('sort_order')->get()->toArray();
} catch (\Exception $e) {
    $enabledProviders = [];
}
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-file-import"></i> <?= $lang['import_title'] ?? 'Import Certificates' ?>
    </h3>
</div>

<div class="aio-grid-2-equal">

    <!-- Single Import -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-certificate"></i> <?= $lang['import_single'] ?? 'Import Single Certificate' ?></span>
        </div>
        <div class="aio-card-body">
            <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
                <?= $lang['import_single_desc'] ?? 'Import a certificate from a provider using its remote order/certificate ID.' ?>
            </p>
            <form id="aio-import-form">
                <input type="hidden" name="token" value="<?= $csrfToken ?>" />

                <div class="aio-form-group">
                    <label><?= $lang['provider'] ?? 'Provider' ?> <span class="required">*</span></label>
                    <select name="provider" class="aio-form-control" required>
                        <option value="">— <?= $lang['select_provider_import'] ?? 'Select Provider' ?> —</option>
                        <?php foreach ($enabledProviders as $p): ?>
                        <option value="<?= htmlspecialchars($p->slug) ?>">
                            <?= htmlspecialchars($providerNames[$p->slug] ?? $p->name) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aio-form-group">
                    <label><?= $lang['remote_order_id'] ?? 'Remote Order/Certificate ID' ?> <span class="required">*</span></label>
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

    <!-- Bulk Import -->
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
        // Legacy order counts
        $legacyCounts = [];
        try {
            $legacyCounts['nicsrs'] = \WHMCS\Database\Capsule::table('nicsrs_sslorders')->count();
        } catch (\Exception $e) { $legacyCounts['nicsrs'] = 0; }

        $legacyModules = [
            'SSLCENTERWHMCS' => 'gogetssl',
            'thesslstore_ssl' => 'thesslstore',
            'ssl2buy' => 'ssl2buy',
        ];
        foreach ($legacyModules as $mod => $slug) {
            try {
                $legacyCounts[$slug] = \WHMCS\Database\Capsule::table('tblsslorders')
                    ->where('module', $mod)->count();
            } catch (\Exception $e) { $legacyCounts[$slug] = 0; }
        }
        ?>

        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Legacy Module</th>
                        <th>Source Table</th>
                        <th class="text-center">Total Orders</th>
                        <th class="text-center">Already Claimed</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $legacyInfo = [
                    'nicsrs'      => ['name'=>'NicSRS SSL',  'table'=>'nicsrs_sslorders', 'badge'=>'aio-provider-nicsrs'],
                    'gogetssl'    => ['name'=>'GoGetSSL',     'table'=>'tblsslorders',     'badge'=>'aio-provider-gogetssl'],
                    'thesslstore' => ['name'=>'TheSSLStore',  'table'=>'tblsslorders',     'badge'=>'aio-provider-thesslstore'],
                    'ssl2buy'     => ['name'=>'SSL2Buy',      'table'=>'tblsslorders',     'badge'=>'aio-provider-ssl2buy'],
                ];
                foreach ($legacyInfo as $slug => $info):
                    $total = $legacyCounts[$slug] ?? 0;
                    // Count claimed
                    $claimed = 0;
                    try {
                        $claimed = \WHMCS\Database\Capsule::table('mod_aio_ssl_orders')
                            ->where('legacy_module', $slug)->count();
                    } catch (\Exception $e) {}
                ?>
                <tr>
                    <td><span class="aio-provider-badge <?= $info['badge'] ?>"><?= $info['name'] ?></span></td>
                    <td><code><?= $info['table'] ?></code></td>
                    <td class="text-center"><span class="aio-badge aio-badge-primary"><?= number_format($total) ?></span></td>
                    <td class="text-center"><span class="aio-badge aio-badge-success"><?= number_format($claimed) ?></span></td>
                    <td class="text-center">
                        <?php if ($total > 0): ?>
                        <button class="aio-btn aio-btn-xs aio-btn-ghost"
                                onclick="location.href='<?= $moduleLink ?>&page=orders&provider=<?= $slug ?>&source=legacy'"
                                title="View legacy orders">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php else: ?>
                        <span class="text-muted">No orders</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
            } else {
                AioSSL.toast(resp.message || 'Import failed.', 'error');
            }
        },
        error: function(xhr, s, e) { AioSSL.toast('Request failed: ' + e, 'error'); }
    });
}
</script>