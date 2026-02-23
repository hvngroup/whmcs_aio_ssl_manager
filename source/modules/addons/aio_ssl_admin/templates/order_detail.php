<?php
/**
 * Order Detail Template — Admin Addon (PHP Template)
 *
 * Variables: $order, $configdata, $activities, $moduleLink, $lang, $csrfToken
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$o = $order;
$cfg = $configdata ?: [];
$providerNames = ['nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore','ssl2buy'=>'SSL2Buy','aio_ssl'=>'AIO SSL'];
$moduleToSlug = ['nicsrs_ssl'=>'nicsrs','SSLCENTERWHMCS'=>'gogetssl','thesslstore_ssl'=>'thesslstore','thesslstore'=>'thesslstore','ssl2buy'=>'ssl2buy','aio_ssl'=>'aio'];
$providerBadge = ['nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl','thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy'];

$slug = $cfg['provider'] ?? ($moduleToSlug[$o->module] ?? '');
$isAio = ($o->module === 'aio_ssl');
$isLegacy = !$isAio;
$isTier2 = ($slug === 'ssl2buy');

// Status styling
$st = $o->status ?? 'Unknown';
$stClass = 'aio-badge-default';
if (in_array($st, ['Completed','Issued','Active'])) $stClass = 'aio-badge-success';
elseif (in_array($st, ['Pending','Processing'])) $stClass = 'aio-badge-primary';
elseif (in_array($st, ['Expired','Cancelled','Revoked'])) $stClass = 'aio-badge-danger';
elseif (stripos($st, 'Awaiting') !== false) $stClass = 'aio-badge-warning';

// Determine capabilities
$capabilities = [];
try {
    if ($slug && class_exists('AioSSL\Core\ProviderRegistry')) {
        $provider = \AioSSL\Core\ProviderRegistry::get($slug);
        $capabilities = $provider->getCapabilities();
    }
} catch (\Exception $e) {}
$canDownload = in_array('download', $capabilities);
$canReissue  = in_array('reissue', $capabilities);
$canRevoke   = in_array('revoke', $capabilities);
$canResendDcv = in_array('dcv_email', $capabilities) || in_array('resend_dcv', $capabilities);
?>

<!-- Breadcrumb -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <a href="<?= $moduleLink ?>&page=orders" class="aio-btn aio-btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Orders
    </a>
    <span style="font-size:16px;font-weight:600;color:var(--aio-heading);">
        Order #<?= $o->id ?>
    </span>
    <span class="aio-badge <?= $stClass ?>"><?= htmlspecialchars($st) ?></span>
    <span class="aio-provider-badge <?= $providerBadge[$slug] ?? 'aio-badge-default' ?>">
        <?= htmlspecialchars($providerNames[$o->module] ?? ucfirst($slug)) ?>
    </span>
    <?php if ($isLegacy): ?>
        <span class="aio-badge aio-source-legacy">Legacy</span>
    <?php else: ?>
        <span class="aio-badge aio-source-aio">AIO</span>
    <?php endif; ?>
</div>

<div class="aio-grid-2">
    <!-- Left Column: Details -->
    <div>
        <!-- Certificate Details -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-lock"></i> <?= $lang['certificate_details'] ?? 'Certificate Details' ?></span>
            </div>
            <div class="aio-card-body">
                <div class="aio-info-grid">
                    <div class="aio-info-item">
                        <label><?= $lang['order_id'] ?? 'Order ID' ?></label>
                        <span>#<?= $o->id ?></span>
                    </div>
                    <div class="aio-info-item">
                        <label><?= $lang['remote_id'] ?? 'Remote ID' ?></label>
                        <span class="aio-code"><?= htmlspecialchars($o->remoteid ?: 'N/A') ?></span>
                    </div>
                    <div class="aio-info-item">
                        <label><?= $lang['domain'] ?? 'Domain' ?></label>
                        <span style="font-family:monospace;"><?= htmlspecialchars($o->domain ?? '—') ?></span>
                    </div>
                    <div class="aio-info-item">
                        <label><?= $lang['client'] ?? 'Client' ?></label>
                        <?php if (!empty($o->userid)): ?>
                        <a href="clientssummary.php?userid=<?= $o->userid ?>" class="aio-link">
                            <?= htmlspecialchars($o->client_name ?? 'Client #' . $o->userid) ?>
                        </a>
                        <?php else: ?>
                        <span>—</span>
                        <?php endif; ?>
                    </div>
                    <div class="aio-info-item">
                        <label><?= $lang['service'] ?? 'Service' ?></label>
                        <?php if (!empty($o->serviceid)): ?>
                        <a href="clientsservices.php?id=<?= $o->serviceid ?>" class="aio-link-service">#<?= $o->serviceid ?></a>
                        <?php else: ?><span>—</span><?php endif; ?>
                    </div>
                    <div class="aio-info-item">
                        <label>Module</label>
                        <span><code><?= htmlspecialchars($o->module ?? '—') ?></code></span>
                    </div>
                    <?php if (!empty($cfg['canonical_id'])): ?>
                    <div class="aio-info-item">
                        <label>Canonical Product</label>
                        <span><?= htmlspecialchars($cfg['canonical_id']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($cfg['begin_date']) || !empty($cfg['beginDate'])): ?>
                    <div class="aio-info-item">
                        <label>Valid From</label>
                        <span><?= htmlspecialchars($cfg['begin_date'] ?? $cfg['beginDate'] ?? '—') ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($cfg['end_date']) || !empty($cfg['endDate'])): ?>
                    <div class="aio-info-item">
                        <label>Valid Until</label>
                        <span><?= htmlspecialchars($cfg['end_date'] ?? $cfg['endDate'] ?? '—') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Domain Validation -->
        <?php if (!empty($cfg['domainInfo'])): ?>
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-tasks"></i> <?= $lang['dcv_info'] ?? 'Domain Validation' ?></span>
            </div>
            <div class="aio-card-body" style="padding:0;">
                <div class="aio-table-wrapper">
                    <table class="aio-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>DCV Method</th>
                                <th>Status</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ((array)$cfg['domainInfo'] as $d):
                            $dStatus = $d['isVerified'] ?? $d['dcvStatus'] ?? false;
                        ?>
                        <tr>
                            <td class="text-mono"><?= htmlspecialchars($d['domainName'] ?? '—') ?></td>
                            <td><span class="aio-badge aio-badge-default"><?= htmlspecialchars($d['dcvMethod'] ?? '—') ?></span></td>
                            <td>
                                <?php if ($dStatus === true || $dStatus === 'validated'): ?>
                                    <span class="aio-badge aio-badge-success"><i class="fas fa-check"></i> Validated</span>
                                <?php else: ?>
                                    <span class="aio-badge aio-badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px;"><?= htmlspecialchars($d['dcvEmail'] ?? $d['approverEmail'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CSR / Certificate Data (collapsible) -->
        <?php if (!empty($cfg['csr']) || !empty($cfg['crt']) || !empty($cfg['cert'])): ?>
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-file-code"></i> Certificate Data</span>
            </div>
            <div class="aio-card-body">
                <?php if (!empty($cfg['csr'])): ?>
                <div class="aio-form-group">
                    <label>CSR <button class="aio-btn aio-btn-xs" onclick="AioSSL.copyToClipboard($('#csr-data').text())" style="margin-left:8px;"><i class="fas fa-copy"></i></button></label>
                    <textarea id="csr-data" class="aio-form-control" rows="3" readonly style="font-family:monospace;font-size:11px;"><?= htmlspecialchars(substr($cfg['csr'], 0, 500)) ?></textarea>
                </div>
                <?php endif; ?>

                <?php if (!empty($cfg['crt']) || !empty($cfg['cert'])): ?>
                <div class="aio-form-group">
                    <label>Certificate <button class="aio-btn aio-btn-xs" onclick="AioSSL.copyToClipboard($('#cert-data').text())" style="margin-left:8px;"><i class="fas fa-copy"></i></button></label>
                    <textarea id="cert-data" class="aio-form-control" rows="3" readonly style="font-family:monospace;font-size:11px;"><?= htmlspecialchars(substr($cfg['crt'] ?? $cfg['cert'] ?? '', 0, 500)) ?></textarea>
                </div>
                <?php endif; ?>

                <?php if (!empty($cfg['ca'])): ?>
                <div class="aio-form-group">
                    <label>CA Bundle</label>
                    <textarea class="aio-form-control" rows="2" readonly style="font-family:monospace;font-size:11px;"><?= htmlspecialchars(substr($cfg['ca'], 0, 300)) ?>…</textarea>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <?php if (!empty($activities)): ?>
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-history"></i> <?= $lang['activity_log'] ?? 'Activity Log' ?></span>
            </div>
            <div class="aio-card-body" style="padding:0;">
                <div class="aio-table-wrapper">
                    <table class="aio-table">
                        <thead><tr><th>Time</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php foreach ($activities as $a): ?>
                        <tr>
                            <td class="text-nowrap" style="font-size:11px;color:var(--aio-text-secondary);">
                                <?= htmlspecialchars($a->created_at ?? '') ?>
                            </td>
                            <td><span class="aio-badge aio-badge-default"><?= htmlspecialchars($a->action ?? '') ?></span></td>
                            <td style="font-size:12px;"><?= htmlspecialchars(substr($a->details ?? '', 0, 200)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Actions -->
    <div>
        <!-- Quick Actions -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-bolt"></i> Actions</span>
            </div>
            <div class="aio-card-body" style="display:flex;flex-direction:column;gap:8px;">
                <button class="aio-btn aio-btn-primary" onclick="AioSSL.refreshOrder(<?= $o->id ?>)" style="justify-content:center;">
                    <i class="fas fa-sync-alt"></i> <?= $lang['refresh_status'] ?? 'Refresh Status' ?>
                </button>

                <?php if ($canResendDcv && in_array($st, ['Pending', 'Processing'])): ?>
                <button class="aio-btn aio-btn-warning" onclick="AioSSL.resendDcv(<?= $o->id ?>)" style="justify-content:center;">
                    <i class="fas fa-envelope"></i> <?= $lang['resend_dcv'] ?? 'Resend DCV Email' ?>
                </button>
                <?php endif; ?>

                <?php if ($canDownload && in_array($st, ['Completed', 'Issued', 'Active'])): ?>
                <button class="aio-btn aio-btn-success" onclick="downloadCert(<?= $o->id ?>)" style="justify-content:center;">
                    <i class="fas fa-download"></i> <?= $lang['download_cert'] ?? 'Download Certificate' ?>
                </button>
                <?php endif; ?>

                <?php if ($canReissue && in_array($st, ['Completed', 'Issued', 'Active'])): ?>
                <button class="aio-btn aio-btn-ghost" onclick="AioSSL.toast('Reissue feature coming in Phase 3','info')" style="justify-content:center;">
                    <i class="fas fa-redo"></i> <?= $lang['reissue_cert'] ?? 'Reissue Certificate' ?>
                </button>
                <?php endif; ?>

                <?php if ($canRevoke && in_array($st, ['Completed', 'Issued', 'Active'])): ?>
                <div style="border-top:1px solid var(--aio-border-light);padding-top:8px;margin-top:4px;">
                    <button class="aio-btn aio-btn-danger" onclick="AioSSL.revokeOrder(<?= $o->id ?>)" style="justify-content:center;width:100%;">
                        <i class="fas fa-ban"></i> <?= $lang['revoke_cert'] ?? 'Revoke Certificate' ?>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($isTier2): ?>
                <div style="border-top:1px solid var(--aio-border-light);padding-top:8px;margin-top:4px;">
                    <div class="aio-alert aio-alert-info" style="margin:0;font-size:12px;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            SSL2Buy is a <strong>limited-tier</strong> provider.<br>
                            Reissue, revoke, and download are managed via the provider portal.
                        </div>
                    </div>
                    <button class="aio-btn aio-btn-ghost" onclick="getConfigLink(<?= $o->id ?>)" style="justify-content:center;width:100%;margin-top:8px;">
                        <i class="fas fa-external-link-alt"></i> Open Provider Portal
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($isLegacy): ?>
                <div style="border-top:1px solid var(--aio-border-light);padding-top:8px;margin-top:4px;">
                    <div class="aio-alert aio-alert-warning" style="margin:0;font-size:12px;">
                        <i class="fas fa-exchange-alt"></i>
                        <div>
                            This is a <strong>legacy order</strong> from <code><?= htmlspecialchars($o->module) ?></code>.
                            Claim it to manage via AIO.
                        </div>
                    </div>
                    <button class="aio-btn aio-btn-primary" onclick="claimOrder(<?= $o->id ?>)" style="justify-content:center;width:100%;margin-top:8px;">
                        <i class="fas fa-hand-holding"></i> <?= $lang['claim_order'] ?? 'Claim Legacy Order' ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Raw Config Data (Debug) -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-code"></i> Raw Config Data</span>
            </div>
            <div class="aio-card-body">
                <textarea class="aio-form-control" rows="8" readonly style="font-family:monospace;font-size:10px;background:var(--aio-bg);"><?= htmlspecialchars(json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
            </div>
        </div>
    </div>
</div>

<script>
function downloadCert(id) {
    AioSSL.ajax({
        page: 'orders', action: 'download', data: { id: id },
        loadingMsg: 'Downloading...', successMessage: false,
        onSuccess: function(r) {
            if (r.cert) {
                var blob = new Blob([r.cert], { type: 'application/x-pem-file' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = '<?= htmlspecialchars($o->domain ?? 'certificate') ?>.pem';
                a.click();
            } else {
                AioSSL.toast('Certificate data not available.', 'warning');
            }
        }
    });
}

function getConfigLink(id) {
    AioSSL.ajax({
        page: 'orders', action: 'config_link', data: { id: id },
        loadingMsg: 'Getting link...',
        onSuccess: function(r) {
            if (r.url) window.open(r.url, '_blank');
            else AioSSL.toast('Config link not available.', 'warning');
        }
    });
}

function claimOrder(id) {
    AioSSL.confirm(
        'Claim this legacy order?<br><small>A new AIO record will be created. The original record will not be modified.</small>',
        function() {
            AioSSL.ajax({
                page: 'orders', action: 'claim', data: { id: id },
                loadingMsg: 'Claiming...', onSuccess: function() { location.reload(); }
            });
        },
        { title: 'Claim Legacy Order', confirmText: 'Claim' }
    );
}
</script>