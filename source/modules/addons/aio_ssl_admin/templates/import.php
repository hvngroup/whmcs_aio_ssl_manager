<?php
/**
 * Import Page — Single Import, Bulk API, CSV, Legacy Migration, History
 *
 * Variables via extract():
 *   $enabledProviders - array of enabled provider info
 *   $legacyStats      - array of legacy module stats
 *   $importHistory    - array of recent import logs
 *   $summary          - array: total_imported, total_remaining
 *   $moduleLink       - string
 *   $lang             - array
 *   $csrfToken        - string
 *   $helper           - ViewHelper
 *
 * @package    AioSSL\Admin\Templates
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

defined('WHMCS') || die('Access Denied');

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$providerColors = [
    'nicsrs'      => 'aio-provider-nicsrs',
    'gogetssl'    => 'aio-provider-gogetssl',
    'thesslstore' => 'aio-provider-thesslstore',
    'ssl2buy'     => 'aio-provider-ssl2buy',
];

$totalRemaining = 0;
foreach ($legacyStats as $ls) {
    $totalRemaining += $ls['remaining'];
}
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-file-import"></i> <?= $lang['import'] ?? 'Import & Migration' ?>
    </h3>
    <div class="aio-toolbar">
        <span class="aio-badge aio-badge-light" style="font-size:12px;padding:6px 12px;">
            Imported: <strong><?= number_format($summary['total_imported'] ?? 0) ?></strong>
        </span>
        <?php if ($totalRemaining > 0): ?>
        <span class="aio-badge" style="font-size:12px;padding:6px 12px;background:var(--aio-warning-bg);color:#d48806;">
            Legacy Remaining: <strong><?= number_format($totalRemaining) ?></strong>
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" id="importTabs" style="margin-bottom:20px;">
    <li class="active"><a href="#tab-single" data-toggle="tab"><i class="fas fa-file-import"></i> Single Import</a></li>
    <li><a href="#tab-bulk-api" data-toggle="tab"><i class="fas fa-cloud-download-alt"></i> Bulk API Import</a></li>
    <li><a href="#tab-csv" data-toggle="tab"><i class="fas fa-file-csv"></i> CSV Import</a></li>
    <li><a href="#tab-legacy" data-toggle="tab"><i class="fas fa-exchange-alt"></i> Legacy Migration
        <?php if ($totalRemaining > 0): ?>
        <span class="aio-badge" style="font-size:10px;padding:2px 6px;margin-left:4px;background:var(--aio-warning-bg);color:#d48806;"><?= $totalRemaining ?></span>
        <?php endif; ?>
    </a></li>
    <li><a href="#tab-history" data-toggle="tab"><i class="fas fa-history"></i> History</a></li>
</ul>

<div class="tab-content">

<!-- ══════════════════════════════════════════════════════════════
     TAB 1: SINGLE IMPORT
     ══════════════════════════════════════════════════════════════ -->
<div class="tab-pane active" id="tab-single">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- Left: Form -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-file-import"></i> Import from Provider API</span>
            </div>
            <div class="aio-card-body">
                <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
                    Enter provider + remote ID → Fetch from API → Preview → Confirm Import.
                </p>
                <form id="aio-single-import-form">
                    <input type="hidden" name="token" value="<?= $csrfToken ?>" />

                    <div class="aio-form-group">
                        <label>Provider <span class="required">*</span></label>
                        <select name="provider" id="import-provider" class="aio-form-control" required>
                            <option value="">— Select Provider —</option>
                            <?php foreach ($enabledProviders as $p): ?>
                            <option value="<?= $e($p['slug']) ?>"><?= $e($p['name']) ?> (<?= ucfirst($p['tier']) ?> Tier)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="aio-form-group">
                        <label>Remote Order/Certificate ID <span class="required">*</span></label>
                        <input type="text" name="remote_id" id="import-remote-id" class="aio-form-control" required placeholder="e.g. 123456" />
                        <div class="aio-form-hint">
                            NicSRS: certId &bull; GoGetSSL: order_id &bull; TheSSLStore: TheSSLStoreOrderID &bull; SSL2Buy: OrderNumber
                        </div>
                    </div>

                    <div class="aio-form-group">
                        <label>Link to WHMCS Service (optional)</label>
                        <input type="number" name="service_id" id="import-service-id" class="aio-form-control" placeholder="e.g. 42" min="1" />
                        <div class="aio-form-hint">WHMCS hosting/service ID. Must use servertype=aio_ssl.</div>
                    </div>

                    <div class="aio-form-actions">
                        <button type="button" class="aio-btn aio-btn-primary" id="btn-lookup">
                            <i class="fas fa-search"></i> Fetch & Preview
                        </button>
                        <button type="button" class="aio-btn aio-btn-success" id="btn-import-single" style="display:none;">
                            <i class="fas fa-check"></i> Confirm Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Preview -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-eye"></i> Preview</span>
            </div>
            <div class="aio-card-body" id="import-preview">
                <div class="aio-empty" style="padding:40px 20px;">
                    <i class="fas fa-search" style="font-size:24px;color:var(--aio-text-secondary);"></i>
                    <p style="color:var(--aio-text-secondary);margin-top:8px;">Enter Provider + Remote ID, then click "Fetch & Preview"</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB 2: BULK API IMPORT
     ══════════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-bulk-api">
    <!-- Controls -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-cloud-download-alt"></i> Bulk Import from Provider API</span>
        </div>
        <div class="aio-card-body">
            <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
                Fetch all orders from Provider API → Select orders to import → Batch create into AIO.
            </p>

            <div class="aio-form-row" style="margin-bottom:16px;">
                <div class="aio-form-group" style="margin-bottom:0;">
                    <label>Provider</label>
                    <select id="bulk-provider" class="aio-form-control">
                        <option value="">— Select Provider —</option>
                        <?php foreach ($enabledProviders as $p): ?>
                            <?php if ($p['has_list']): ?>
                            <option value="<?= $e($p['slug']) ?>"><?= $e($p['name']) ?></option>
                            <?php else: ?>
                            <option value="<?= $e($p['slug']) ?>" disabled><?= $e($p['name']) ?> — No list API</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aio-form-group" style="margin-bottom:0;">
                    <label>&nbsp;</label>
                    <button type="button" class="aio-btn aio-btn-primary" id="btn-fetch-orders" style="margin-top:0;">
                        <i class="fas fa-download"></i> Fetch Orders from API
                    </button>
                </div>
            </div>

            <!-- API capability info -->
            <div class="aio-alert aio-alert-info" style="font-size:11px;">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Supported APIs:</strong>
                    GoGetSSL <code>GET /orders/ssl/all</code> &bull;
                    TheSSLStore <code>POST /order/query</code> &bull;
                    SSL2Buy <code>POST /getorderlist</code> (max 50/page) &bull;
                    NicSRS: <em>No list endpoint — use Single Import or CSV</em>
                </div>
            </div>
        </div>
    </div>

    <!-- Results table (populated via AJAX) -->
    <div id="bulk-results" style="display:none;">
        <div class="aio-card">
            <div class="aio-card-header">
                <span id="bulk-results-title">Fetched Orders</span>
                <div class="aio-toolbar">
                    <button type="button" class="aio-btn" id="btn-select-all-new" style="font-size:12px;">
                        <i class="fas fa-check-double"></i> Select All New
                    </button>
                    <button type="button" class="aio-btn aio-btn-success" id="btn-bulk-import" style="font-size:12px;">
                        <i class="fas fa-file-import"></i> Import Selected (<span id="bulk-selected-count">0</span>)
                    </button>
                </div>
            </div>
            <div class="aio-card-body" style="padding:0;">
                <div class="aio-table-wrapper">
                    <table class="aio-table" id="bulk-orders-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">
                                    <input type="checkbox" id="bulk-check-all" />
                                </th>
                                <th>Remote ID</th>
                                <th>Domain</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>AIO</th>
                            </tr>
                        </thead>
                        <tbody id="bulk-orders-body">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="aio-card-footer" style="display:flex;justify-content:space-between;align-items:center;">
                <span id="bulk-page-info" style="font-size:12px;color:var(--aio-text-secondary);"></span>
                <div id="bulk-pagination" style="display:flex;gap:4px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB 3: CSV IMPORT
     ══════════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-csv">
    <div class="aio-form-row">
        <!-- Left: Upload form -->
        <div class="aio-card" style="margin-bottom:0;">
            <div class="aio-card-header">
                <span><i class="fas fa-file-csv"></i> CSV Bulk Import</span>
            </div>
            <div class="aio-card-body">
                <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
                    Upload CSV → System calls provider API for each row → Creates AIO order records.
                </p>

                <form id="aio-csv-import-form" enctype="multipart/form-data">
                    <input type="hidden" name="token" value="<?= $csrfToken ?>" />

                    <div class="aio-form-group">
                        <label>Upload CSV File <span class="required">*</span></label>
                        <input type="file" name="csv_file" id="csv-file-input" class="aio-form-control" accept=".csv" required />
                    </div>

                    <div class="aio-form-actions">
                        <button type="button" class="aio-btn aio-btn-primary" id="btn-csv-import">
                            <i class="fas fa-upload"></i> Upload & Import
                        </button>
                        <button type="button" class="aio-btn" id="btn-csv-template">
                            <i class="fas fa-download"></i> Download Template
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Format guide -->
        <div class="aio-card" style="margin-bottom:0;">
            <div class="aio-card-header">
                <span><i class="fas fa-info-circle"></i> CSV Format Guide</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-alert aio-alert-info" style="font-size:12px;margin-bottom:12px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Columns:</strong> provider, remote_id, service_id (optional)
                    </div>
                </div>
                <pre style="margin:0;padding:12px;background:var(--aio-bg);border-radius:var(--aio-radius-sm);font-size:11px;line-height:1.8;border:1px solid var(--aio-border-light);overflow-x:auto;">provider,remote_id,service_id
nicsrs,78542,42
gogetssl,1001,
thesslstore,ABC-123,15
ssl2buy,ORD-456,</pre>
                <div style="margin-top:12px;font-size:11px;color:var(--aio-text-secondary);">
                    <strong>Remote ID per provider:</strong><br>
                    NicSRS: <code>certId</code> &bull;
                    GoGetSSL: <code>order_id</code> &bull;
                    TheSSLStore: <code>TheSSLStoreOrderID</code> &bull;
                    SSL2Buy: <code>OrderNumber</code>
                </div>
                <div style="margin-top:8px;font-size:11px;color:var(--aio-text-secondary);">
                    Max <strong>500</strong> rows per batch. service_id is optional.
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Results (shown after import) -->
    <div id="csv-results" style="display:none;margin-top:20px;">
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-clipboard-check"></i> Import Results</span>
            </div>
            <div class="aio-card-body" id="csv-results-body"></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB 4: LEGACY MIGRATION
     ══════════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-legacy">
    <div class="aio-alert aio-alert-warning" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Non-destructive migration:</strong> "Claim" creates a new record in
            <code>mod_aio_ssl_orders</code> with references to the original legacy table and order ID.
            Original records are <strong>never modified or deleted</strong>.
        </div>
    </div>

    <?php if (empty($legacyStats)): ?>
    <div class="aio-card">
        <div class="aio-card-body">
            <div class="aio-empty">
                <i class="fas fa-check-circle" style="color:var(--aio-success);"></i>
                <p>No legacy modules detected. All certificates are managed by AIO SSL.</p>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Legacy modules grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
        <?php foreach ($legacyStats as $ls): ?>
        <div class="aio-card" style="margin-bottom:0;">
            <div class="aio-card-header">
                <span>
                    <span class="aio-provider-badge <?= $providerColors[$ls['slug']] ?? '' ?>"><?= $e($ls['slug']) ?></span>
                    <?= $e($ls['name']) ?>
                </span>
                <span class="aio-badge aio-status-active">Detected</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-stats-mini" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:12px;">
                    <div class="aio-stat-mini">
                        <div class="aio-stat-mini-value"><?= number_format($ls['total']) ?></div>
                        <div class="aio-stat-mini-label">Total Orders</div>
                    </div>
                    <div class="aio-stat-mini" style="border-color:#b7eb8f;background:var(--aio-success-bg);">
                        <div class="aio-stat-mini-value" style="color:var(--aio-success);"><?= number_format($ls['claimed']) ?></div>
                        <div class="aio-stat-mini-label">Claimed</div>
                    </div>
                    <div class="aio-stat-mini <?= $ls['remaining'] > 0 ? 'aio-stat-warning' : '' ?>">
                        <div class="aio-stat-mini-value"><?= number_format($ls['remaining']) ?></div>
                        <div class="aio-stat-mini-label">Remaining</div>
                    </div>
                </div>

                <div style="font-size:11px;color:var(--aio-text-secondary);margin-bottom:12px;">
                    Table: <code><?= $e($ls['table']) ?></code> &bull;
                    Module: <code><?= $e($ls['module']) ?></code>
                </div>

                <?php if ($ls['remaining'] > 0): ?>
                <!-- Progress bar -->
                <div class="aio-validity-bar" style="margin-bottom:12px;">
                    <?php $pct = $ls['total'] > 0 ? round($ls['claimed'] / $ls['total'] * 100) : 0; ?>
                    <div class="aio-progress">
                        <div class="aio-progress-bar aio-progress-success" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="aio-validity-label">
                        <span><?= $pct ?>% claimed</span>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:8px;">
                    <?php if ($ls['remaining'] > 0): ?>
                    <button type="button" class="aio-btn aio-btn-primary" onclick="AioSSL.claimByProvider('<?= $e($ls['slug']) ?>')">
                        <i class="fas fa-file-import"></i> Claim All (<?= number_format($ls['remaining']) ?>)
                    </button>
                    <?php endif; ?>
                    <a href="<?= $moduleLink ?>&page=orders&filter_provider=<?= $e($ls['slug']) ?>" class="aio-btn">
                        <i class="fas fa-eye"></i> View Orders
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Claim All Button -->
    <?php if ($totalRemaining > 0): ?>
    <div style="text-align:center;padding:16px 0;">
        <button type="button" class="aio-btn aio-btn-warning" onclick="AioSSL.claimAllLegacy()" style="font-size:14px;padding:10px 28px;">
            <i class="fas fa-file-import"></i> Claim All Legacy Orders
            <span class="aio-badge aio-badge-light" style="margin-left:6px;"><?= number_format($totalRemaining) ?></span>
        </button>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB 5: HISTORY
     ══════════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-history">
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-history"></i> Import History</span>
        </div>
        <div class="aio-card-body" style="padding:0;">
            <div class="aio-table-wrapper">
                <table class="aio-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Source</th>
                            <th>Records</th>
                            <th>Success</th>
                            <th>Failed</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($importHistory)): ?>
                        <tr><td colspan="7" class="text-center" style="color:var(--aio-text-secondary);padding:24px;">No import history yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($importHistory as $h): ?>
                        <?php
                            $typeLabels = [
                                'api_single'    => ['label' => 'API Single',   'class' => 'aio-status-active'],
                                'api_bulk'      => ['label' => 'API Bulk',     'class' => 'aio-status-pending'],
                                'csv'           => ['label' => 'CSV',          'class' => 'aio-status-expiring'],
                                'migration'     => ['label' => 'Migration',    'class' => 'aio-source-legacy'],
                                'migration_all' => ['label' => 'Migration All','class' => 'aio-source-legacy'],
                            ];
                            $tl = $typeLabels[$h->type] ?? ['label' => $h->type, 'class' => ''];
                        ?>
                        <tr>
                            <td><?= $h->id ?></td>
                            <td><span class="aio-badge <?= $tl['class'] ?>"><?= $tl['label'] ?></span></td>
                            <td>
                                <?php if (isset($providerColors[$h->source])): ?>
                                <span class="aio-provider-badge <?= $providerColors[$h->source] ?>"><?= $e($h->source) ?></span>
                                <?php else: ?>
                                <?= $e($h->source) ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($h->total) ?></strong></td>
                            <td style="color:var(--aio-success);font-weight:600;"><?= number_format($h->success) ?></td>
                            <td style="color:<?= $h->failed > 0 ? 'var(--aio-danger)' : 'var(--aio-text-secondary)' ?>;font-weight:<?= $h->failed > 0 ? '600' : '400' ?>;">
                                <?= number_format($h->failed) ?>
                            </td>
                            <td style="font-size:12px;color:var(--aio-text-secondary);">
                                <?= $helper->formatDate($h->created_at ?? '', 'Y-m-d H:i') ?>
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

</div><!-- /tab-content -->

<script>
(function($) {
    'use strict';

    var moduleLink = window.aioModuleLink || '<?= $moduleLink ?>';

    // ═══════════════════════════════════════════════════════════
    //  TAB 1: SINGLE IMPORT
    // ═══════════════════════════════════════════════════════════

    // Lookup (preview)
    $('#btn-lookup').on('click', function() {
        var provider = $('#import-provider').val();
        var remoteId = $('#import-remote-id').val().trim();

        if (!provider || !remoteId) {
            AioSSL.toast('Provider and Remote ID are required.', 'warning');
            return;
        }

        AioSSL.ajax({
            page: 'import',
            action: 'lookup',
            data: { provider: provider, remote_id: remoteId },
            loadingMsg: 'Fetching certificate...',
            successMessage: false,
            onSuccess: function(resp) {
                renderPreview(resp.certificate);
                $('#btn-import-single').show();
            },
            onError: function(resp) {
                $('#import-preview').html(
                    '<div class="aio-alert aio-alert-danger"><i class="fas fa-times-circle"></i> ' +
                    AioSSL.escHtml(resp.message || 'Lookup failed') + '</div>'
                );
                $('#btn-import-single').hide();
            }
        });
    });

    /**
     * ============================================================
     * Replace the entire renderPreview() function and add helpers
     * inside the existing IIFE block in import.php <script>
     * 
     * Place these INSIDE the (function($) { 'use strict'; ... })(jQuery);
     * ============================================================
     */

    // ── Raw data helpers (scoped inside IIFE) ──────────────────

    function syntaxHighlight(obj) {
        var json = JSON.stringify(obj, null, 2);
        if (!json) return '';

        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        return json.replace(
            /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
            function(match) {
                var cls = '#fab387'; // number (peach)
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = '#89b4fa'; // key (blue)
                        match = match.replace(/:$/, '');
                        return '<span style="color:' + cls + '">' + match + '</span>:';
                    } else {
                        cls = '#a6e3a1'; // string (green)
                    }
                } else if (/true|false/.test(match)) {
                    cls = '#f38ba8'; // boolean (red)
                } else if (/null/.test(match)) {
                    cls = '#9399b2'; // null (gray)
                }
                return '<span style="color:' + cls + '">' + match + '</span>';
            }
        );
    }

    function normalizeStatusClass(status) {
        if (!status) return '';
        var s = status.toLowerCase();
        var map = {
            'active': 'aio-status-active',
            'issued': 'aio-status-active',
            'processing': 'aio-status-pending',
            'pending': 'aio-status-pending',
            'awaiting validation': 'aio-status-pending',
            'awaiting configuration': 'aio-status-pending',
            'cancelled': 'aio-status-cancelled',
            'canceled': 'aio-status-cancelled',
            'expired': 'aio-status-expired',
            'revoked': 'aio-status-cancelled',
            'rejected': 'aio-status-cancelled',
            'incomplete': 'aio-status-expiring',
            'unpaid': 'aio-status-expiring',
        };
        return map[s] || '';
    }

    // Store raw data reference inside IIFE scope
    var _rawDataCache = null;

    function renderPreview(cert) {
        if (!cert) return;

        var productDisplay = cert.product_name || cert.product_type || 'Unknown';
        if (cert.product_id && productDisplay !== 'Unknown') {
            productDisplay += ' <span style="color:var(--aio-text-secondary);font-size:11px;">(ID: ' + AioSSL.escHtml(String(cert.product_id)) + ')</span>';
        }

        var html = '<div class="aio-info-grid">';

        var fields = [
            ['Remote ID', AioSSL.escHtml(cert.remote_id)],
            ['Provider', AioSSL.escHtml(cert.provider_name)],
            ['Status', '<span class="aio-badge ' + normalizeStatusClass(cert.status) + '">' + AioSSL.escHtml(cert.status) + '</span>'],
            ['Product', productDisplay],
            ['Valid From', cert.begin_date || '—'],
            ['Valid To', cert.end_date || '—'],
            ['Serial', cert.serial_number || '—'],
            ['Has Certificate', cert.has_cert
                ? '<span style="color:var(--aio-success);font-weight:600;"><i class="fas fa-check-circle"></i> Yes</span>'
                : '<span style="color:var(--aio-warning);"><i class="fas fa-exclamation-circle"></i> No</span>'],
        ];

        $.each(fields, function(_, f) {
            html += '<div class="aio-info-row"><label>' + f[0] + '</label><span>' + f[1] + '</span></div>';
        });

        // Domains
        if (cert.domains && cert.domains.length) {
            html += '<div class="aio-info-row"><label>Domains (' + cert.domains.length + ')</label><span>';
            $.each(cert.domains, function(_, d) {
                html += '<code style="display:inline-block;margin:1px 4px 1px 0;padding:2px 6px;background:var(--aio-bg);border-radius:3px;font-size:11px;">' + AioSSL.escHtml(d) + '</code>';
            });
            html += '</span></div>';
        }

        html += '</div>';

        // ── Raw API Data (collapsible) — uses jQuery events, no inline onclick ──
        if (cert.raw_data) {
            _rawDataCache = cert.raw_data;

            html += '<div style="margin-top:16px;border-top:1px solid var(--aio-border-light);padding-top:12px;">';
            html += '<div class="js-raw-toggle" style="cursor:pointer;user-select:none;font-size:12px;color:var(--aio-text-secondary);display:flex;align-items:center;gap:6px;">';
            html += '<i class="fas fa-chevron-right js-raw-chevron" style="font-size:10px;transition:transform 0.2s;"></i>';
            html += '<i class="fas fa-code"></i> <strong>Raw API Data</strong>';
            html += '<span style="font-size:10px;background:var(--aio-bg);padding:2px 6px;border-radius:3px;">debug</span>';
            html += '</div>';
            html += '<div class="js-raw-content" style="display:none;margin-top:8px;">';
            html += '<div style="max-height:400px;overflow:auto;background:#1e1e2e;color:#cdd6f4;border-radius:6px;padding:12px;font-family:\'SF Mono\',Monaco,Consolas,monospace;font-size:11px;line-height:1.5;">';
            html += '<pre style="margin:0;white-space:pre-wrap;word-break:break-all;">' + syntaxHighlight(cert.raw_data) + '</pre>';
            html += '</div>';
            html += '<div style="margin-top:6px;">';
            html += '<button type="button" class="aio-btn js-raw-copy" style="font-size:11px;padding:4px 10px;"><i class="fas fa-copy"></i> Copy JSON</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }

        html += '<div class="aio-alert aio-alert-info" style="margin-top:12px;font-size:12px;">' +
                '<i class="fas fa-info-circle"></i> ' +
                'Import will create a new record in <code>mod_aio_ssl_orders</code> and store all cert data.' +
                '</div>';

        $('#import-preview').html(html);
    }

    // ── Event delegation for raw data toggle/copy (jQuery, inside IIFE) ──

    $(document).on('click', '.js-raw-toggle', function() {
        var $content = $(this).siblings('.js-raw-content');
        var $chevron = $(this).find('.js-raw-chevron');
        if ($content.is(':visible')) {
            $content.slideUp(200);
            $chevron.css('transform', 'rotate(0deg)');
        } else {
            $content.slideDown(200);
            $chevron.css('transform', 'rotate(90deg)');
        }
    });

    $(document).on('click', '.js-raw-copy', function(e) {
        e.stopPropagation();
        if (!_rawDataCache) return;
        var text = JSON.stringify(_rawDataCache, null, 2);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                AioSSL.toast('JSON copied to clipboard', 'success', 2000);
            });
        } else {
            var $ta = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo('body');
            $ta[0].select();
            document.execCommand('copy');
            $ta.remove();
            AioSSL.toast('JSON copied to clipboard', 'success', 2000);
        }
    });

    // Confirm import
    $('#btn-import-single').on('click', function() {
        AioSSL.ajax({
            page: 'import',
            action: 'single',
            data: $('#aio-single-import-form').serialize(),
            loadingMsg: 'Importing certificate...',
            onSuccess: function(resp) {
                if (resp.order_id) {
                    setTimeout(function() {
                        location.href = moduleLink + '&page=orders&action=detail&id=' + resp.order_id;
                    }, 1000);
                }
            }
        });
    });

    // ═══════════════════════════════════════════════════════════
    //  TAB 2: BULK API IMPORT
    // ═══════════════════════════════════════════════════════════

    var bulkCurrentPage = 1;
    var bulkProvider = '';

    $('#btn-fetch-orders').on('click', function() {
        bulkProvider = $('#bulk-provider').val();
        if (!bulkProvider) {
            AioSSL.toast('Select a provider.', 'warning');
            return;
        }
        bulkCurrentPage = 1;
        fetchBulkOrders();
    });

    function fetchBulkOrders() {
        AioSSL.ajax({
            page: 'import',
            action: 'fetch_orders',
            data: { provider: bulkProvider, page: bulkCurrentPage },
            loadingMsg: 'Fetching orders from API...',
            successMessage: false,
            onSuccess: function(resp) {
                renderBulkOrders(resp);
                $('#bulk-results').show();
            }
        });
    }

    function renderBulkOrders(data) {
        var $body = $('#bulk-orders-body');
        $body.empty();

        if (!data.orders || !data.orders.length) {
            $body.html('<tr><td colspan="7" class="text-center" style="padding:24px;color:var(--aio-text-secondary);">No orders found.</td></tr>');
            return;
        }

        $('#bulk-results-title').html(
            'Fetched <strong>' + data.orders.length + '</strong> orders from ' +
            '<span class="aio-provider-badge aio-provider-' + AioSSL.escHtml(bulkProvider) + '">' + AioSSL.escHtml(bulkProvider) + '</span>' +
            (data.total ? ' <span style="color:var(--aio-text-secondary);font-weight:400;">(total: ' + data.total + ')</span>' : '')
        );

        $.each(data.orders, function(_, o) {
            var disabled = o.already_imported ? ' disabled' : '';
            var opacity  = o.already_imported ? ' style="opacity:0.5;"' : '';
            var statusClass = normalizeStatusClass(o.status);
            var aioLabel = o.already_imported
                ? '<span class="aio-badge aio-source-legacy">Imported</span>'
                : '<span class="aio-badge aio-status-pending">Ready</span>';

            $body.append(
                '<tr' + opacity + '>' +
                '<td style="text-align:center;"><input type="checkbox" class="bulk-order-check" value="' + AioSSL.escHtml(o.remote_id) + '"' + disabled + ' /></td>' +
                '<td><code style="font-size:12px;">' + AioSSL.escHtml(o.remote_id) + '</code></td>' +
                '<td>' + AioSSL.escHtml(o.domain) + '</td>' +
                '<td style="font-size:11px;">' + AioSSL.escHtml(o.product) + '</td>' +
                '<td><span class="aio-badge ' + statusClass + '">' + AioSSL.escHtml(o.status) + '</span></td>' +
                '<td style="font-size:12px;">' + AioSSL.escHtml(o.end_date || '—') + '</td>' +
                '<td>' + aioLabel + '</td>' +
                '</tr>'
            );
        });

        // Pagination
        var $pag = $('#bulk-pagination');
        $pag.empty();
        var totalPages = data.total_pages || 1;
        if (totalPages > 1) {
            if (bulkCurrentPage > 1) {
                $pag.append('<button class="aio-btn" style="padding:4px 10px;font-size:11px;" onclick="AioSSL._bulkPage(' + (bulkCurrentPage - 1) + ')">← Prev</button>');
            }
            for (var p = 1; p <= Math.min(totalPages, 10); p++) {
                var cls = p === bulkCurrentPage ? 'aio-btn-primary' : '';
                $pag.append('<button class="aio-btn ' + cls + '" style="padding:4px 10px;font-size:11px;" onclick="AioSSL._bulkPage(' + p + ')">' + p + '</button>');
            }
            if (bulkCurrentPage < totalPages) {
                $pag.append('<button class="aio-btn" style="padding:4px 10px;font-size:11px;" onclick="AioSSL._bulkPage(' + (bulkCurrentPage + 1) + ')">Next →</button>');
            }
        }

        $('#bulk-page-info').text('Page ' + bulkCurrentPage + ' of ' + totalPages + (data.total ? ' — ' + data.total + ' orders total' : ''));
        updateBulkSelectedCount();
    }

    AioSSL._bulkPage = function(page) {
        bulkCurrentPage = page;
        fetchBulkOrders();
    };

    function normalizeStatusClass(status) {
        var s = (status || '').toLowerCase();
        if (s === 'active' || s === 'issued' || s === 'complete') return 'aio-status-active';
        if (s === 'pending' || s === 'processing') return 'aio-status-pending';
        if (s === 'expired') return 'aio-status-expired';
        if (s === 'cancelled' || s === 'revoked') return 'aio-status-cancelled';
        return '';
    }

    // Select all new
    $('#btn-select-all-new').on('click', function() {
        $('.bulk-order-check:not(:disabled)').prop('checked', true);
        updateBulkSelectedCount();
    });

    $('#bulk-check-all').on('change', function() {
        var checked = $(this).prop('checked');
        $('.bulk-order-check:not(:disabled)').prop('checked', checked);
        updateBulkSelectedCount();
    });

    $(document).on('change', '.bulk-order-check', function() {
        updateBulkSelectedCount();
    });

    function updateBulkSelectedCount() {
        var count = $('.bulk-order-check:checked').length;
        $('#bulk-selected-count').text(count);
    }

    // Bulk import selected
    $('#btn-bulk-import').on('click', function() {
        var ids = [];
        $('.bulk-order-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (!ids.length) {
            AioSSL.toast('Select at least one order to import.', 'warning');
            return;
        }

        AioSSL.confirm(
            'Import <strong>' + ids.length + '</strong> orders from <strong>' + AioSSL.escHtml(bulkProvider) + '</strong>?',
            function() {
                AioSSL.ajax({
                    page: 'import',
                    action: 'bulk_api',
                    data: { provider: bulkProvider, remote_ids: ids },
                    loadingMsg: 'Importing ' + ids.length + ' orders...',
                    onSuccess: function(resp) {
                        var msg = 'Imported: ' + (resp.imported || 0) + ', Failed: ' + (resp.failed || 0);
                        AioSSL.toast(msg, resp.failed > 0 ? 'warning' : 'success', 5000);
                        // Refresh the list
                        fetchBulkOrders();
                    }
                });
            },
            { title: 'Confirm Bulk Import', confirmText: 'Import ' + ids.length + ' Orders' }
        );
    });

    // ═══════════════════════════════════════════════════════════
    //  TAB 3: CSV IMPORT
    // ═══════════════════════════════════════════════════════════

    $('#btn-csv-import').on('click', function() {
        var fileInput = document.getElementById('csv-file-input');
        if (!fileInput.files || !fileInput.files[0]) {
            AioSSL.toast('Please select a CSV file.', 'warning');
            return;
        }

        var fd = new FormData($('#aio-csv-import-form')[0]);

        $.ajax({
            url: moduleLink + '&page=import&action=bulk_csv&ajax=1',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() { AioSSL.showLoading('Importing from CSV...'); },
            complete: function() { AioSSL.hideLoading(); },
            success: function(resp) {
                if (resp.success) {
                    AioSSL.toast(resp.message || 'CSV import completed.', resp.failed > 0 ? 'warning' : 'success', 5000);
                } else {
                    AioSSL.toast(resp.message || 'Import failed.', 'error');
                }
                renderCsvResults(resp);
            },
            error: function(xhr, s, e) {
                AioSSL.toast('Request failed: ' + e, 'error');
            }
        });
    });

    function renderCsvResults(resp) {
        var html = '';

        // Summary stats
        html += '<div class="aio-stats-mini" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:16px;">';
        html += '<div class="aio-stat-mini"><div class="aio-stat-mini-value">' + (resp.total || 0) + '</div><div class="aio-stat-mini-label">Total Rows</div></div>';
        html += '<div class="aio-stat-mini" style="border-color:#b7eb8f;background:var(--aio-success-bg);"><div class="aio-stat-mini-value" style="color:var(--aio-success);">' + (resp.imported || 0) + '</div><div class="aio-stat-mini-label">Imported</div></div>';
        html += '<div class="aio-stat-mini ' + (resp.failed > 0 ? 'aio-stat-danger' : '') + '"><div class="aio-stat-mini-value">' + (resp.failed || 0) + '</div><div class="aio-stat-mini-label">Failed</div></div>';
        html += '</div>';

        // Errors
        if (resp.errors && resp.errors.length) {
            html += '<div style="max-height:200px;overflow-y:auto;">';
            $.each(resp.errors, function(_, err) {
                html += '<div style="font-size:12px;padding:4px 0;color:var(--aio-danger);border-bottom:1px solid var(--aio-border-light);">' +
                         '<i class="fas fa-times-circle"></i> ' + AioSSL.escHtml(err) + '</div>';
            });
            html += '</div>';
        }

        $('#csv-results-body').html(html);
        $('#csv-results').show();
    }

    // CSV Template download
    $('#btn-csv-template').on('click', function() {
        AioSSL.ajax({
            page: 'import',
            action: 'csv_template',
            loading: false,
            successMessage: false,
            onSuccess: function(resp) {
                if (resp.csv) {
                    var blob = new Blob([resp.csv], { type: 'text/csv' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = resp.filename || 'import_template.csv';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            }
        });
    });

    // ═══════════════════════════════════════════════════════════
    //  TAB 4: LEGACY MIGRATION
    // ═══════════════════════════════════════════════════════════

    AioSSL.claimByProvider = function(slug) {
        AioSSL.confirm(
            'Claim all unclaimed orders from <strong>' + AioSSL.escHtml(slug) + '</strong>?<br>' +
            '<small style="color:var(--aio-text-secondary);">Original records will NOT be modified.</small>',
            function() {
                AioSSL.ajax({
                    page: 'import',
                    action: 'claim_provider',
                    data: { provider: slug },
                    loadingMsg: 'Claiming orders...',
                    onSuccess: function(r) {
                        var msg = 'Claimed: ' + (r.claimed || 0);
                        if (r.failed > 0) msg += ', Failed: ' + r.failed;
                        AioSSL.toast(msg, r.failed > 0 ? 'warning' : 'success', 5000);
                        setTimeout(function() { location.reload(); }, 2000);
                    }
                });
            },
            { title: 'Claim Provider Orders', confirmText: 'Claim All' }
        );
    };

    AioSSL.claimAllLegacy = function() {
        AioSSL.confirm(
            'Claim <strong>ALL</strong> remaining legacy orders from all modules?<br>' +
            '<small style="color:var(--aio-text-secondary);">New records will be created in <code>mod_aio_ssl_orders</code>. Original records will NOT be modified.</small>',
            function() {
                AioSSL.ajax({
                    page: 'import',
                    action: 'claim_all',
                    loadingMsg: 'Claiming all legacy orders...',
                    onSuccess: function(r) {
                        var msg = 'Claimed: ' + (r.claimed || 0);
                        if (r.failed > 0) msg += ', Failed: ' + r.failed;
                        AioSSL.toast(msg, r.failed > 0 ? 'warning' : 'success', 5000);
                        setTimeout(function() { location.reload(); }, 2000);
                    }
                });
            },
            { title: 'Claim All Legacy Orders', confirmText: 'Claim All', type: 'danger' }
        );
    };

})(jQuery);
</script>