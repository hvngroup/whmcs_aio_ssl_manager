<?php
/**
 * Order Detail Template — Section-based Layout
 *
 * Variables injected by OrderController::renderDetail():
 *   $order, $o, $configdata, $cfg, $sourceTable
 *   $providerSlug, $providerName, $isLegacy, $capabilities, $isTier2
 *   $isComplete, $isPending, $isTerminal, $isAwaiting
 *   $canRefresh, $canDownload, $canReissue, $canRenew, $canCancel, $canRevoke
 *   $canResendDcv, $canChangeDcv, $canConfigLink
 *   $hasCsr, $hasCert, $hasKey, $applyReturn, $domainList, $dcvData
 *   $beginDate, $endDate, $daysLeft, $validityPct, $renewalDue
 *   $clientName, $primaryDomain, $activities
 *   $moduleLink, $lang, $csrfToken, $helper
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$o   = $order;
$cfg = $configdata;
$ar  = $applyReturn;

// Provider badge CSS class
$providerBadgeMap = [
    'nicsrs'      => 'aio-provider-nicsrs',
    'gogetssl'    => 'aio-provider-gogetssl',
    'thesslstore' => 'aio-provider-thesslstore',
    'ssl2buy'     => 'aio-provider-ssl2buy',
    'aio'         => 'aio-provider-aio',
];
$providerBadgeClass = $providerBadgeMap[$providerSlug] ?? 'aio-badge-default';

// Status badge class
$st = $o->status ?? 'Unknown';
$stNorm = strtolower($st);
$stClass = 'aio-badge-default';
if ($isComplete)  $stClass = 'aio-badge-success';
elseif ($isPending)  $stClass = 'aio-badge-primary';
elseif ($isTerminal) $stClass = 'aio-badge-danger';
elseif ($isAwaiting) $stClass = 'aio-badge-warning';
elseif ($stNorm === 'expired') $stClass = 'aio-badge-danger';

// Validation type badge
$valType = '';
$certtype = $o->certtype ?? '';
if (stripos($certtype, '_ev_') !== false || stripos($certtype, '-ev-') !== false) $valType = 'EV';
elseif (stripos($certtype, '_ov_') !== false || stripos($certtype, '-ov-') !== false) $valType = 'OV';
else $valType = 'DV';
$valBadgeClass = ['DV' => 'aio-badge-primary', 'OV' => 'aio-badge-warning', 'EV' => 'aio-badge-success'][$valType] ?? 'aio-badge-default';

// Wildcard check
$isWildcard = (strpos($primaryDomain, '*') !== false)
    || stripos($certtype, 'wildcard') !== false;

// Product name — resolve from WHMCS product or certtype
$productName = $o->whmcs_product_name ?? '';
$certDisplayName = $productName ?: ucwords(str_replace(['_', '-'], ' ', $certtype));

// Helpers
$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$sourceLabel = ($sourceTable === 'mod_aio_ssl_orders') ? 'aio' : (($sourceTable === 'nicsrs_sslorders') ? 'nicsrs' : 'tblssl');
$lastRefresh = $cfg['lastRefresh'] ?? '';
$lastRefreshRel = '';
if ($lastRefresh) {
    $diff = time() - strtotime($lastRefresh);
    if ($diff < 60) $lastRefreshRel = 'just now';
    elseif ($diff < 3600) $lastRefreshRel = floor($diff/60) . 'm ago';
    elseif ($diff < 86400) $lastRefreshRel = floor($diff/3600) . 'h ago';
    else $lastRefreshRel = floor($diff/86400) . 'd ago';
}
?>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  BREADCRUMB                                                    -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-breadcrumb">
    <a href="<?= $moduleLink ?>">Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <a href="<?= $moduleLink ?>&page=orders">Orders</a>
    <i class="fas fa-chevron-right"></i>
    <span>Order #<?= $o->id ?></span>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  HEADER BAR                                                    -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-detail-header">
    <div class="aio-detail-header-left">
        <a href="<?= $moduleLink ?>&page=orders" class="aio-btn aio-btn-sm" title="Back to Orders">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="aio-detail-header-info">
            <div class="aio-detail-title">
                Order #<?= $o->id ?>
                <?php if ($primaryDomain): ?>
                    <span class="aio-detail-domain"><?= $e($primaryDomain) ?></span>
                <?php endif; ?>
            </div>
            <div class="aio-detail-badges">
                <span class="aio-badge <?= $stClass ?>"><?= $e($st) ?></span>
                <span class="aio-provider-badge <?= $providerBadgeClass ?>"><?= $e($providerName) ?></span>
                <?php if ($isLegacy): ?>
                    <span class="aio-badge aio-source-legacy">Legacy</span>
                <?php else: ?>
                    <span class="aio-badge aio-source-aio">AIO</span>
                <?php endif; ?>
                <span class="aio-badge <?= $valBadgeClass ?>"><?= $valType ?></span>
                <?php if ($isWildcard): ?>
                    <span class="aio-badge aio-badge-default">Wildcard</span>
                <?php endif; ?>
            </div>
            <?php if ($lastRefresh): ?>
            <div class="aio-detail-meta">
                Last synced: <?= $e($lastRefresh) ?>
                <span class="aio-text-muted">(<?= $lastRefreshRel ?>)</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="aio-detail-header-actions">
        <?php if ($canRefresh): ?>
        <button class="aio-btn aio-btn-ghost" onclick="orderAction('refresh_status')" id="btnRefresh">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <?php endif; ?>
        <button class="aio-btn aio-btn-ghost" onclick="toggleEditMode()" id="btnEdit">
            <i class="fas fa-edit"></i> Edit
        </button>
        <?php if ($canDownload && $hasCert): ?>
        <button class="aio-btn aio-btn-primary" onclick="toggleSection('downloadPanel')">
            <i class="fas fa-download"></i> Download Cert
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  DOWNLOAD PANEL (hidden by default)                            -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php if ($canDownload && $hasCert): ?>
<div id="downloadPanel" class="aio-card aio-section-collapsible" style="display:none;">
    <div class="aio-card-header">
        <span><i class="fas fa-box-open"></i> Download Certificate</span>
        <button class="aio-btn aio-btn-sm" onclick="toggleSection('downloadPanel')"><i class="fas fa-times"></i></button>
    </div>
    <div class="aio-card-body">
        <div class="aio-download-grid">
            <?php
            $formats = [
                ['all',    'All Formats', 'Complete ZIP', 'fas fa-file-archive', '#1890ff'],
                ['apache', 'Apache',      '.crt + .ca-bundle', 'fas fa-server', '#fa8c16'],
                ['nginx',  'Nginx',       '.pem bundle', 'fas fa-server', '#52c41a'],
                ['iis',    'IIS',         '.p12 (PKCS#12)', 'fab fa-windows', '#1890ff'],
                ['tomcat', 'Tomcat',      '.jks (KeyStore)', 'fab fa-java', '#722ed1'],
                ['key',    'Private Key', '.key file', 'fas fa-key', '#faad14'],
            ];
            foreach ($formats as [$fmt, $label, $desc, $icon, $color]):
            ?>
            <button class="aio-download-card" onclick="downloadCert('<?= $fmt ?>')">
                <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:20px;"></i>
                <strong><?= $label ?></strong>
                <small><?= $desc ?></small>
            </button>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($hasCsr): ?>
            <button class="aio-btn aio-btn-sm" onclick="copyToClipboard('csrContent')">
                <i class="fas fa-copy"></i> Copy CSR
            </button>
            <?php endif; ?>
            <?php if ($hasCert): ?>
            <button class="aio-btn aio-btn-sm" onclick="copyToClipboard('certContent')">
                <i class="fas fa-copy"></i> Copy Certificate
            </button>
            <?php endif; ?>
            <?php if (!empty($ar['caCertificate'])): ?>
            <button class="aio-btn aio-btn-sm" onclick="copyToClipboard('caContent')">
                <i class="fas fa-copy"></i> Copy CA Bundle
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hidden textareas for clipboard copy -->
<textarea id="csrContent" style="display:none;"><?= $e($cfg['csr'] ?? '') ?></textarea>
<textarea id="certContent" style="display:none;"><?= $e($ar['certificate'] ?? '') ?></textarea>
<textarea id="caContent" style="display:none;"><?= $e($ar['caCertificate'] ?? '') ?></textarea>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 1: ORDER & CLIENT INFORMATION                         -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-section">
    <h4 class="aio-section-title"><i class="fas fa-info-circle"></i> Order & Client Information</h4>
    <div class="aio-grid-2">

        <!-- Left: Order Information -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-clipboard-list"></i> Order Information</span>
            </div>
            <div class="aio-card-body">
                <!-- View Mode -->
                <div id="orderInfoView">
                    <div class="aio-info-grid">
                        <div class="aio-info-row">
                            <label>Order ID</label>
                            <span>#<?= $o->id ?></span>
                        </div>
                        <div class="aio-info-row">
                            <label>Status</label>
                            <span><span class="aio-badge <?= $stClass ?>"><?= $e($st) ?></span></span>
                        </div>
                        <div class="aio-info-row">
                            <label>Product</label>
                            <span>
                                <strong><?= $e($certDisplayName) ?></strong>
                                <br><small class="aio-text-muted"><?= $e($certtype) ?></small>
                            </span>
                        </div>
                        <div class="aio-info-row">
                            <label>Domain</label>
                            <span class="aio-code"><?= $e($primaryDomain ?: '—') ?></span>
                        </div>
                        <?php if (count($domainList) > 1): ?>
                        <div class="aio-info-row">
                            <label>SAN Domains</label>
                            <span>
                                <?php foreach ($domainList as $d): ?>
                                <span class="aio-code" style="display:inline-block;margin:1px 4px 1px 0;"><?= $e($d['domainName'] ?? '') ?></span>
                                <?php endforeach; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="aio-info-row">
                            <label>Service</label>
                            <span>
                                <?php if ($o->serviceid): ?>
                                <a href="clientsservices.php?id=<?= $o->serviceid ?>" target="_blank" class="aio-link">
                                    #<?= $o->serviceid ?> <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                </a>
                                <?php if ($o->service_billingcycle ?? ''): ?>
                                — <?= $e($o->service_billingcycle) ?>
                                <?php endif; ?>
                                <?php if ($o->service_amount ?? ''): ?>
                                — <?= $e(number_format((float)$o->service_amount, 0)) ?>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="aio-text-muted">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($productName): ?>
                        <div class="aio-info-row">
                            <label>WHMCS Product</label>
                            <span><?= $e($productName) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="aio-info-row">
                            <label>Validation</label>
                            <span>
                                <span class="aio-badge <?= $valBadgeClass ?>"><?= $valType ?></span>
                                <?php if ($isWildcard): ?>
                                <span class="aio-badge aio-badge-default" style="margin-left:4px;">Wildcard</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="aio-info-row">
                            <label>Created</label>
                            <span><?= $e($o->created_at ?? $o->provisiondate ?? '—') ?></span>
                        </div>
                        <?php if ($lastRefresh): ?>
                        <div class="aio-info-row">
                            <label>Last Synced</label>
                            <span><?= $e($lastRefresh) ?> <small class="aio-text-muted">(<?= $lastRefreshRel ?>)</small></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Validity Progress Bar -->
                    <?php if ($isComplete && $beginDate && $endDate): ?>
                    <div class="aio-validity-bar" style="margin-top:12px;">
                        <div class="aio-validity-label">
                            <span>📅 <?= $e($beginDate) ?></span>
                            <span>→ <?= $e($endDate) ?></span>
                        </div>
                        <div class="aio-progress">
                            <div class="aio-progress-bar <?= $daysLeft !== null && $daysLeft < 30 ? 'aio-progress-danger' : ($daysLeft < 90 ? 'aio-progress-warning' : 'aio-progress-success') ?>"
                                 style="width:<?= $validityPct ?>%;"
                                 title="<?= $validityPct ?>% elapsed"></div>
                        </div>
                        <div class="aio-validity-label">
                            <small class="aio-text-muted"><?= $validityPct ?>% elapsed</small>
                            <?php if ($daysLeft !== null): ?>
                            <small class="<?= $daysLeft < 30 ? 'aio-text-danger' : ($daysLeft < 90 ? 'aio-text-warning' : 'aio-text-success') ?>">
                                <strong><?= $daysLeft ?></strong> days left
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Mode (hidden by default) -->
                <div id="orderInfoEdit" style="display:none;">
                    <div class="aio-form-grid">
                        <div class="aio-form-group">
                            <label>Status</label>
                            <select id="editStatus" class="aio-form-control">
                                <?php foreach (['Awaiting Configuration','Pending','Complete','Cancelled','Revoked','Expired'] as $s): ?>
                                <option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aio-form-group">
                            <label>Remote ID</label>
                            <input type="text" id="editRemoteId" class="aio-form-control aio-code"
                                   value="<?= $e($o->remoteid ?? '') ?>" />
                        </div>
                        <div class="aio-form-group">
                            <label>Service ID</label>
                            <input type="number" id="editServiceId" class="aio-form-control"
                                   value="<?= (int)($o->serviceid ?? 0) ?>" />
                        </div>
                        <div class="aio-form-group">
                            <label>Domain</label>
                            <input type="text" id="editDomain" class="aio-form-control aio-code"
                                   value="<?= $e($o->domain ?? '') ?>" />
                        </div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <button class="aio-btn aio-btn-primary" onclick="saveOrderEdit()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button class="aio-btn" onclick="toggleEditMode()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Client + Service + Quick Stats -->
        <div>
            <!-- Client Information -->
            <div class="aio-card">
                <div class="aio-card-header">
                    <span><i class="fas fa-user"></i> Client Information</span>
                </div>
                <div class="aio-card-body">
                    <div class="aio-info-grid">
                        <div class="aio-info-row">
                            <label>Client</label>
                            <span>
                                <?php if ($o->userid ?? 0): ?>
                                <a href="clientssummary.php?userid=<?= $o->userid ?>" target="_blank" class="aio-link">
                                    <?= $e($clientName ?: 'Client #' . $o->userid) ?>
                                    <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                </a>
                                <?php else: ?>
                                <span class="aio-text-muted">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($o->companyname)): ?>
                        <div class="aio-info-row">
                            <label>Company</label>
                            <span><?= $e($o->companyname) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($o->client_email)): ?>
                        <div class="aio-info-row">
                            <label>Email</label>
                            <span><a href="mailto:<?= $e($o->client_email) ?>" class="aio-link"><?= $e($o->client_email) ?></a></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($o->phonenumber)): ?>
                        <div class="aio-info-row">
                            <label>Phone</label>
                            <span><?= $e($o->phonenumber) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="aio-card">
                <div class="aio-card-header">
                    <span><i class="fas fa-chart-bar"></i> Quick Stats</span>
                </div>
                <div class="aio-card-body">
                    <div class="aio-stats-mini">
                        <div class="aio-stat-mini <?= $daysLeft !== null && $daysLeft < 30 ? 'aio-stat-danger' : ($daysLeft !== null && $daysLeft < 90 ? 'aio-stat-warning' : '') ?>">
                            <div class="aio-stat-mini-value"><?= $daysLeft !== null ? $daysLeft : '—' ?></div>
                            <div class="aio-stat-mini-label">Days Left</div>
                        </div>
                        <div class="aio-stat-mini">
                            <div class="aio-stat-mini-value"><?= $renewalDue ? $helper->formatDate($renewalDue) : '—' ?></div>
                            <div class="aio-stat-mini-label">Renewal Due</div>
                        </div>
                        <div class="aio-stat-mini">
                            <div class="aio-stat-mini-value">
                                <span class="aio-provider-badge <?= $providerBadgeClass ?>" style="font-size:11px;"><?= $e($providerName) ?></span>
                            </div>
                            <div class="aio-stat-mini-label">Provider</div>
                        </div>
                        <div class="aio-stat-mini">
                            <div class="aio-stat-mini-value"><?= $isLegacy ? '<span class="aio-badge aio-source-legacy">Legacy</span>' : '<span class="aio-badge aio-source-aio">AIO</span>' ?></div>
                            <div class="aio-stat-mini-label">Source</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 2: CERTIFICATE DETAILS                                -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php if (!$isAwaiting): ?>
<div class="aio-section">
    <h4 class="aio-section-title"><i class="fas fa-lock"></i> Certificate Details</h4>
    <div class="aio-grid-2">

        <!-- Left: Certificate Metadata -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-shield-alt"></i> Certificate Metadata</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-info-grid">
                    <?php if ($isComplete && !empty($ar['serialNumber'])): ?>
                    <div class="aio-info-row">
                        <label>Serial Number</label>
                        <span class="aio-code" style="font-size:11px;"><?= $e($ar['serialNumber']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($isComplete): ?>
                    <div class="aio-info-row">
                        <label>Signature</label>
                        <span><?= $e($ar['signatureAlgorithm'] ?? $ar['signatureHashAlgorithm'] ?? 'SHA256-RSA') ?></span>
                    </div>
                    <div class="aio-info-row">
                        <label>Issuer</label>
                        <span><?= $e($ar['issuer'] ?? $ar['ca_issuer'] ?? '—') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="aio-info-row">
                        <label>Common Name</label>
                        <span class="aio-code"><?= $e($primaryDomain ?: '—') ?></span>
                    </div>
                    <?php if (count($domainList) > 1): ?>
                    <div class="aio-info-row">
                        <label>SANs</label>
                        <span><?= count($domainList) ?> domains</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($beginDate): ?>
                    <div class="aio-info-row">
                        <label>Valid From</label>
                        <span><?= $e($beginDate) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($endDate): ?>
                    <div class="aio-info-row">
                        <label>Valid To</label>
                        <span><?= $e($endDate) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="aio-info-row">
                        <label>Validation</label>
                        <span><span class="aio-badge <?= $valBadgeClass ?>"><?= $valType ?></span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: CSR Content -->
        <?php if ($hasCsr): ?>
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-file-code"></i> CSR (Certificate Signing Request)</span>
                <div style="display:flex;gap:4px;">
                    <button class="aio-btn aio-btn-sm" onclick="copyToClipboard('csrContent')" title="Copy CSR">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="aio-card-body">
                <textarea class="aio-form-control aio-code" rows="6" readonly
                          style="font-size:11px;resize:vertical;background:var(--aio-bg);"><?= $e($cfg['csr'] ?? '') ?></textarea>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Download Certificate (full-width, only when complete + hasCert) -->
    <?php if (!$canDownload && $isTier2 && $canConfigLink): ?>
    <div class="aio-card" style="margin-top:0;">
        <div class="aio-card-body" style="text-align:center;padding:20px;">
            <p class="aio-text-muted" style="margin-bottom:12px;">
                <i class="fas fa-info-circle"></i>
                Certificate download is managed through the provider portal for <strong><?= $e($providerName) ?></strong>.
            </p>
            <button class="aio-btn aio-btn-primary" onclick="openConfigLink()">
                <i class="fas fa-external-link-alt"></i> Open <?= $e($providerName) ?> Portal
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 3: DOMAIN VALIDATION                                  -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php if (!empty($domainList) && !$isAwaiting): ?>
<div class="aio-section">
    <h4 class="aio-section-title">
        <i class="fas fa-globe"></i> Domain Validation
        <?php if ($canResendDcv && $isPending): ?>
        <button class="aio-btn aio-btn-sm aio-btn-ghost" onclick="orderAction('resend_dcv')" style="margin-left:8px;">
            <i class="fas fa-paper-plane"></i> Resend All DCV
        </button>
        <?php endif; ?>
    </h4>
    <div class="aio-card">
        <div class="aio-card-body" style="padding:0;">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>DCV Method</th>
                        <th class="text-center">Status</th>
                        <?php if ($isPending): ?>
                        <th class="text-center" style="width:180px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domainList as $idx => $d):
                        $dName     = $d['domainName'] ?? $d['domain'] ?? 'N/A';
                        $dMethod   = $d['dcvMethod'] ?? $d['method'] ?? $d['validation_method'] ?? '—';
                        $dVerified = !empty($d['isVerified']) || !empty($d['is_verify']) || $isComplete;
                    ?>
                    <tr>
                        <td><span class="aio-code"><?= $e($dName) ?></span></td>
                        <td><?= $e(strtoupper($dMethod)) ?></td>
                        <td class="text-center">
                            <?php if ($dVerified): ?>
                            <span class="aio-badge aio-badge-success"><i class="fas fa-check"></i> Done</span>
                            <?php else: ?>
                            <span class="aio-badge aio-badge-warning"><i class="fas fa-clock"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isPending): ?>
                        <td class="text-center">
                            <?php if (!$dVerified): ?>
                            <button class="aio-btn aio-btn-sm" onclick="resendDcvForDomain('<?= $e($dName) ?>')">
                                <i class="fas fa-paper-plane"></i> Resend
                            </button>
                            <?php if ($canChangeDcv): ?>
                            <button class="aio-btn aio-btn-sm" onclick="showChangeDcvModal('<?= $e($dName) ?>', '<?= $e($dMethod) ?>')">
                                <i class="fas fa-exchange-alt"></i> Change
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <!-- DCV Instructions (for pending domains) -->
                    <?php if (!$dVerified && $isPending): ?>
                    <tr class="aio-dcv-detail-row">
                        <td colspan="<?= $isPending ? 4 : 3 ?>">
                            <div class="aio-dcv-instructions">
                                <?php
                                $mNorm = strtolower($dMethod);
                                if (strpos($mNorm, 'email') !== false):
                                ?>
                                <div class="aio-dcv-block">
                                    <strong>Email Validation:</strong>
                                    Approval email sent to the domain approver address.
                                    <button class="aio-btn aio-btn-sm" onclick="resendDcvForDomain('<?= $e($dName) ?>')" style="margin-left:8px;">
                                        <i class="fas fa-paper-plane"></i> Resend Email
                                    </button>
                                </div>
                                <?php elseif (strpos($mNorm, 'http') !== false): ?>
                                <div class="aio-dcv-block">
                                    <strong>HTTP File Validation:</strong>
                                    <?php if (!empty($dcvData['DCVfileName'])): ?>
                                    <div class="aio-dcv-detail">
                                        <label>File URL:</label>
                                        <code>http://<?= $e($dName) ?>/.well-known/pki-validation/<?= $e($dcvData['DCVfileName']) ?></code>
                                        <button class="aio-btn aio-btn-sm" onclick="copyText('http://<?= $e($dName) ?>/.well-known/pki-validation/<?= $e($dcvData['DCVfileName']) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <?php if (!empty($dcvData['DCVfileContent'])): ?>
                                    <div class="aio-dcv-detail">
                                        <label>File Content:</label>
                                        <code style="word-break:break-all;"><?= $e($dcvData['DCVfileContent']) ?></code>
                                        <button class="aio-btn aio-btn-sm" onclick="copyText('<?= $e($dcvData['DCVfileContent']) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php elseif (strpos($mNorm, 'dns') !== false || strpos($mNorm, 'cname') !== false): ?>
                                <div class="aio-dcv-block">
                                    <strong>DNS CNAME Validation:</strong>
                                    <?php if (!empty($dcvData['DCVdnsHost'])): ?>
                                    <div class="aio-dcv-detail">
                                        <label>Host:</label>
                                        <code><?= $e($dcvData['DCVdnsHost']) ?></code>
                                        <button class="aio-btn aio-btn-sm" onclick="copyText('<?= $e($dcvData['DCVdnsHost']) ?>')"><i class="fas fa-copy"></i></button>
                                    </div>
                                    <div class="aio-dcv-detail">
                                        <label>Type:</label>
                                        <code><?= $e($dcvData['DCVdnsType'] ?: 'CNAME') ?></code>
                                    </div>
                                    <div class="aio-dcv-detail">
                                        <label>Value:</label>
                                        <code style="word-break:break-all;"><?= $e($dcvData['DCVdnsValue']) ?></code>
                                        <button class="aio-btn aio-btn-sm" onclick="copyText('<?= $e($dcvData['DCVdnsValue']) ?>')"><i class="fas fa-copy"></i></button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 4: ACTIONS & MANAGEMENT                               -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-section">
    <h4 class="aio-section-title"><i class="fas fa-bolt"></i> Actions & Management</h4>
    <div class="aio-grid-3">

        <!-- Certificate Lifecycle -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-certificate"></i> Certificate Lifecycle</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-action-list">
                    <?php if ($canRefresh): ?>
                    <button class="aio-action-btn" onclick="orderAction('refresh_status')">
                        <i class="fas fa-sync-alt aio-text-primary"></i>
                        <div><strong>Refresh Status</strong><small>Sync from <?= $e($providerName) ?> API</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canResendDcv): ?>
                    <button class="aio-action-btn" onclick="orderAction('resend_dcv')">
                        <i class="fas fa-paper-plane aio-text-primary"></i>
                        <div><strong>Resend DCV Email</strong><small>Resend domain validation</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canDownload): ?>
                    <button class="aio-action-btn" onclick="toggleSection('downloadPanel')">
                        <i class="fas fa-download aio-text-success"></i>
                        <div><strong>Download Certificate</strong><small>PEM, CRT, P12, JKS formats</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canReissue): ?>
                    <button class="aio-action-btn" onclick="confirmAction('reissue', 'Submit a reissue request for this certificate?')">
                        <i class="fas fa-redo aio-text-primary"></i>
                        <div><strong>Reissue Certificate</strong><small>New CSR, same order</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canRenew): ?>
                    <button class="aio-action-btn" onclick="confirmAction('renew', 'Submit a renewal request for this certificate?')">
                        <i class="fas fa-sync aio-text-success"></i>
                        <div><strong>Renew Certificate</strong><small>Extend validity period</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                    <button class="aio-action-btn aio-action-danger" onclick="confirmDangerAction('cancel')">
                        <i class="fas fa-times-circle"></i>
                        <div><strong>Cancel Order</strong><small>Cancel on provider</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if ($canRevoke): ?>
                    <button class="aio-action-btn aio-action-danger" onclick="confirmDangerAction('revoke')">
                        <i class="fas fa-ban"></i>
                        <div><strong>Revoke Certificate</strong><small>Permanent — cannot undo</small></div>
                    </button>
                    <?php endif; ?>

                    <?php if (!$canRefresh && !$canResendDcv && !$canDownload && !$canReissue && !$canRenew && !$canCancel && !$canRevoke): ?>
                    <div class="aio-text-muted" style="padding:8px 0;font-size:12px;">
                        <i class="fas fa-info-circle"></i> No lifecycle actions available for this order status.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Administrative Actions -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-cog"></i> Administrative</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-action-list">
                    <button class="aio-action-btn" onclick="toggleEditMode()">
                        <i class="fas fa-edit aio-text-primary"></i>
                        <div><strong>Edit Order</strong><small>Modify status, IDs, domain</small></div>
                    </button>

                    <?php if ($o->serviceid): ?>
                    <a href="clientsservices.php?id=<?= $o->serviceid ?>" target="_blank" class="aio-action-btn" style="text-decoration:none;color:inherit;">
                        <i class="fas fa-server aio-text-primary"></i>
                        <div><strong>View in WHMCS</strong><small>Open service #<?= $o->serviceid ?></small></div>
                    </a>
                    <?php endif; ?>

                    <?php if ($o->userid): ?>
                    <a href="clientssummary.php?userid=<?= $o->userid ?>" target="_blank" class="aio-action-btn" style="text-decoration:none;color:inherit;">
                        <i class="fas fa-user aio-text-primary"></i>
                        <div><strong>View Client</strong><small><?= $e($clientName) ?></small></div>
                    </a>
                    <?php endif; ?>

                    <button class="aio-action-btn aio-action-danger" onclick="confirmDeleteOrder()">
                        <i class="fas fa-trash-alt"></i>
                        <div><strong>Delete Local Record</strong><small>Provider unaffected</small></div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Provider Information -->
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-plug"></i> Provider Information</span>
            </div>
            <div class="aio-card-body">
                <div class="aio-info-grid">
                    <div class="aio-info-row">
                        <label>Provider</label>
                        <span><span class="aio-provider-badge <?= $providerBadgeClass ?>"><?= $e($providerName) ?></span></span>
                    </div>
                    <div class="aio-info-row">
                        <label>Tier</label>
                        <span><?= $isTier2 ? '<span class="aio-badge aio-badge-warning">Limited</span>' : '<span class="aio-badge aio-badge-success">Full</span>' ?></span>
                    </div>
                    <div class="aio-info-row">
                        <label>Remote ID</label>
                        <span class="aio-code"><?= $e($o->remoteid ?: '—') ?></span>
                    </div>
                    <?php if (!empty($ar['vendorId'])): ?>
                    <div class="aio-info-row">
                        <label>Vendor ID</label>
                        <span class="aio-code"><?= $e($ar['vendorId']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ar['vendorCertId'])): ?>
                    <div class="aio-info-row">
                        <label>Vendor Cert ID</label>
                        <span class="aio-code"><?= $e($ar['vendorCertId']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="aio-info-row">
                        <label>Source Table</label>
                        <span class="aio-code" style="font-size:11px;"><?= $e($sourceTable) ?></span>
                    </div>
                    <div class="aio-info-row">
                        <label>Module</label>
                        <span class="aio-code"><?= $e($o->module ?? '—') ?></span>
                    </div>
                    <div class="aio-info-row">
                        <label>Capabilities</label>
                        <span>
                            <?php if (!empty($capabilities)): ?>
                                <?php foreach ($capabilities as $cap): ?>
                                <span class="aio-badge aio-badge-default" style="margin:1px;font-size:10px;"><?= $e($cap) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="aio-text-muted">None loaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if ($canConfigLink): ?>
                <div style="margin-top:12px;">
                    <button class="aio-btn aio-btn-ghost" onclick="openConfigLink()" style="width:100%;justify-content:center;">
                        <i class="fas fa-external-link-alt"></i> Open <?= $e($providerName) ?> Portal
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($isTier2): ?>
                <div class="aio-alert aio-alert-info" style="margin-top:12px;margin-bottom:0;font-size:12px;">
                    <i class="fas fa-info-circle"></i>
                    <div><strong><?= $e($providerName) ?></strong> is limited-tier. Some actions must be performed through the provider portal.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Legacy Order Banner -->
    <?php if ($isLegacy): ?>
    <div class="aio-alert aio-alert-warning" style="margin-top:12px;">
        <i class="fas fa-exchange-alt"></i>
        <div>
            <strong>Legacy Order</strong> — This order is from <code><?= $e($o->module ?? 'unknown') ?></code>
            (table: <code><?= $e($sourceTable) ?></code>).
            Claim it to manage via AIO.
            <button class="aio-btn aio-btn-sm aio-btn-primary" onclick="claimOrder()" style="margin-left:8px;">
                <i class="fas fa-hand-holding"></i> Claim to AIO
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 5: ACTIVITY LOG                                       -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-section">
    <h4 class="aio-section-title">
        <i class="fas fa-history"></i> Activity Log
        <span class="aio-badge aio-badge-default" style="font-size:10px;margin-left:4px;"><?= count($activities) ?></span>
    </h4>
    <div class="aio-card">
        <div class="aio-card-body" style="padding:0;">
            <?php if (empty($activities)): ?>
            <div style="padding:24px;text-align:center;" class="aio-text-muted">
                <i class="fas fa-inbox" style="font-size:24px;"></i><br>
                No activity recorded yet.
            </div>
            <?php else: ?>
            <table class="aio-table">
                <thead>
                    <tr>
                        <th style="width:140px;">Time</th>
                        <th>Action</th>
                        <th style="width:80px;">By</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $actionIcons = [
                        'refresh_status' => 'fa-sync-alt',    'download_cert'  => 'fa-download',
                        'reissue'        => 'fa-redo',        'renew'          => 'fa-sync',
                        'resend_dcv'     => 'fa-paper-plane', 'change_dcv'     => 'fa-exchange-alt',
                        'cancel'         => 'fa-times-circle','revoke'         => 'fa-ban',
                        'edit_order'     => 'fa-edit',        'create_order'   => 'fa-plus-circle',
                        'cert_issued'    => 'fa-check-circle','dcv_verified'   => 'fa-globe',
                        'sync_status'    => 'fa-sync-alt',    'claim_order'    => 'fa-hand-holding',
                        'delete_order'   => 'fa-trash-alt',
                    ];
                    foreach ($activities as $act):
                        $act = is_object($act) ? $act : (object)$act;
                        $aAction = $act->action ?? '';
                        $aIcon   = $actionIcons[$aAction] ?? 'fa-circle';
                        $aDesc   = $act->description ?? ucwords(str_replace('_', ' ', $aAction));
                        $aAdmin  = $act->admin_name ?? 'system';
                        $aDate   = $act->created_at ?? '';
                        $aDetail = $act->details ?? '';
                    ?>
                    <tr>
                        <td>
                            <span style="font-size:12px;"><?= $e($aDate ? date('d/m H:i', strtotime($aDate)) : '—') ?></span>
                            <?php if ($aDate): ?>
                            <br><small class="aio-text-muted"><?= $e(date('Y', strtotime($aDate))) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <i class="fas <?= $aIcon ?> aio-text-primary" style="margin-right:4px;"></i>
                            <?= $e($aDesc) ?>
                        </td>
                        <td>
                            <span class="aio-badge <?= $aAdmin === 'system' || $aAdmin === 'cron' ? 'aio-badge-default' : 'aio-badge-primary' ?>"
                                  style="font-size:10px;"><?= $e($aAdmin) ?></span>
                        </td>
                        <td><small class="aio-text-muted"><?= $e(is_string($aDetail) ? $aDetail : json_encode($aDetail)) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  SECTION 6: RAW CONFIG DATA (collapsible)                      -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="aio-section">
    <h4 class="aio-section-title aio-section-collapsible-toggle" onclick="toggleSection('rawDataContent')" style="cursor:pointer;">
        <i class="fas fa-code"></i> Raw Config Data
        <i class="fas fa-chevron-right aio-collapse-icon" id="rawDataIcon" style="font-size:11px;margin-left:4px;transition:transform .2s;"></i>
    </h4>
    <div id="rawDataContent" style="display:none;">
        <div class="aio-card">
            <div class="aio-card-body">
                <textarea class="aio-form-control aio-code" rows="12" readonly id="rawJson"
                          style="font-size:11px;resize:vertical;background:var(--aio-bg);border:1px solid var(--aio-border-light);"><?= $e(json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
                <div style="margin-top:8px;display:flex;gap:8px;">
                    <button class="aio-btn aio-btn-sm" onclick="copyToClipboard('rawJson')">
                        <i class="fas fa-copy"></i> Copy JSON
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  MODALS                                                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->

<!-- Action Confirmation Modal -->
<div id="actionModal" class="aio-modal" style="display:none;">
    <div class="aio-modal-overlay" onclick="closeModal('actionModal')"></div>
    <div class="aio-modal-content">
        <div class="aio-modal-header">
            <h4 id="actionModalTitle">Confirm Action</h4>
            <button class="aio-btn aio-btn-sm" onclick="closeModal('actionModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="aio-modal-body">
            <p id="actionModalMessage"></p>
            <div id="actionReasonField" style="display:none;margin-top:12px;">
                <label class="aio-form-label">Reason (required):</label>
                <textarea id="actionReason" class="aio-form-control" rows="3" placeholder="Enter reason..."></textarea>
            </div>
            <div id="actionDeleteConfirm" style="display:none;margin-top:12px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="deleteConfirmCheck" onchange="document.getElementById('confirmActionBtn').disabled = !this.checked;">
                    <span>I understand this action is <strong>permanent</strong>.</span>
                </label>
            </div>
        </div>
        <div class="aio-modal-footer">
            <button class="aio-btn" onclick="closeModal('actionModal')">Cancel</button>
            <button class="aio-btn aio-btn-primary" id="confirmActionBtn" onclick="executeAction()">Confirm</button>
        </div>
    </div>
</div>

<!-- Change DCV Modal -->
<div id="dcvModal" class="aio-modal" style="display:none;">
    <div class="aio-modal-overlay" onclick="closeModal('dcvModal')"></div>
    <div class="aio-modal-content">
        <div class="aio-modal-header">
            <h4>Change Validation Method</h4>
            <button class="aio-btn aio-btn-sm" onclick="closeModal('dcvModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="aio-modal-body">
            <div class="aio-form-group">
                <label>Domain:</label>
                <input type="text" id="dcvDomain" class="aio-form-control aio-code" readonly />
            </div>
            <div class="aio-form-group">
                <label>New Method:</label>
                <select id="dcvMethod" class="aio-form-control" onchange="toggleDcvEmail()">
                    <option value="email">Email Validation</option>
                    <option value="http">HTTP File Validation</option>
                    <option value="https">HTTPS File Validation</option>
                    <option value="dns">DNS CNAME Validation</option>
                </select>
            </div>
            <div class="aio-form-group" id="dcvEmailGroup" style="display:none;">
                <label>Approver Email:</label>
                <input type="email" id="dcvEmail" class="aio-form-control" placeholder="admin@domain.com" />
            </div>
        </div>
        <div class="aio-modal-footer">
            <button class="aio-btn" onclick="closeModal('dcvModal')">Cancel</button>
            <button class="aio-btn aio-btn-primary" onclick="submitChangeDcv()">Confirm Change</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  JAVASCRIPT                                                    -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<script>
(function() {
    var ML = '<?= $moduleLink ?>';
    var OID = <?= (int)$o->id ?>;
    var SRC = '<?= $e($sourceLabel) ?>';
    var pendingAction = '';
    var pendingActionIsDanger = false;

    // ── Toggle helpers ──
    window.toggleSection = function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var isHidden = el.style.display === 'none';
        el.style.display = isHidden ? '' : 'none';
        // Rotate icon for raw data
        if (id === 'rawDataContent') {
            var icon = document.getElementById('rawDataIcon');
            if (icon) icon.style.transform = isHidden ? 'rotate(90deg)' : '';
        }
    };

    window.toggleEditMode = function() {
        var view = document.getElementById('orderInfoView');
        var edit = document.getElementById('orderInfoEdit');
        var btn  = document.getElementById('btnEdit');
        if (!view || !edit) return;
        var isEditing = edit.style.display !== 'none';
        view.style.display = isEditing ? '' : 'none';
        edit.style.display = isEditing ? 'none' : '';
        if (btn) btn.innerHTML = isEditing
            ? '<i class="fas fa-edit"></i> Edit'
            : '<i class="fas fa-times"></i> Cancel Edit';
    };

    // ── AJAX helper ──
    function ajax(action, data, onSuccess, onError) {
        data = data || {};
        data.id = OID;
        data.source = SRC;
        AioSSL.ajax({
            page: 'orders',
            action: action,
            data: data,
            onSuccess: onSuccess || function(r) { location.reload(); },
            onError: onError || function(r) { AioSSL.toast(r.message || 'Error', 'error'); }
        });
    }

    // ── Simple actions ──
    window.orderAction = function(action) {
        ajax(action, {}, function(r) {
            AioSSL.toast(r.message || 'Done', 'success');
            if (action === 'refresh_status') {
                setTimeout(function() { location.reload(); }, 800);
            }
        });
    };

    // ── Confirm action (non-danger) ──
    window.confirmAction = function(action, msg) {
        pendingAction = action;
        pendingActionIsDanger = false;
        document.getElementById('actionModalTitle').textContent = 'Confirm Action';
        document.getElementById('actionModalMessage').textContent = msg;
        document.getElementById('actionReasonField').style.display = 'none';
        document.getElementById('actionDeleteConfirm').style.display = 'none';
        document.getElementById('confirmActionBtn').disabled = false;
        document.getElementById('confirmActionBtn').className = 'aio-btn aio-btn-primary';
        showModal('actionModal');
    };

    // ── Confirm danger action (cancel/revoke) ──
    window.confirmDangerAction = function(action) {
        pendingAction = action;
        pendingActionIsDanger = true;
        var titles = { cancel: 'Cancel Order #' + OID + '?', revoke: 'Revoke Certificate?' };
        var msgs = {
            cancel: 'This will cancel the certificate order on the provider. This action cannot be undone.',
            revoke: '⚠️ PERMANENT: Revoking this certificate makes it invalid immediately. This cannot be restored.'
        };
        document.getElementById('actionModalTitle').textContent = titles[action] || 'Confirm';
        document.getElementById('actionModalMessage').textContent = msgs[action] || 'Are you sure?';
        document.getElementById('actionReasonField').style.display = 'block';
        document.getElementById('actionReason').value = '';
        document.getElementById('actionDeleteConfirm').style.display = 'none';
        document.getElementById('confirmActionBtn').disabled = false;
        document.getElementById('confirmActionBtn').className = 'aio-btn aio-btn-danger';
        document.getElementById('confirmActionBtn').innerHTML = '<i class="fas fa-' + (action === 'revoke' ? 'ban' : 'times') + '"></i> Confirm ' + action.charAt(0).toUpperCase() + action.slice(1);
        showModal('actionModal');
    };

    // ── Confirm delete order ──
    window.confirmDeleteOrder = function() {
        pendingAction = 'delete_order';
        pendingActionIsDanger = true;
        document.getElementById('actionModalTitle').textContent = 'Delete Order #' + OID + '?';
        document.getElementById('actionModalMessage').textContent = 'This only removes the LOCAL record. The certificate on the provider will NOT be affected.';
        document.getElementById('actionReasonField').style.display = 'none';
        document.getElementById('actionDeleteConfirm').style.display = 'block';
        document.getElementById('deleteConfirmCheck').checked = false;
        document.getElementById('confirmActionBtn').disabled = true;
        document.getElementById('confirmActionBtn').className = 'aio-btn aio-btn-danger';
        document.getElementById('confirmActionBtn').innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
        showModal('actionModal');
    };

    // ── Execute pending action ──
    window.executeAction = function() {
        var data = {};
        if (pendingAction === 'cancel' || pendingAction === 'revoke') {
            data.reason = document.getElementById('actionReason').value;
            if (!data.reason) {
                AioSSL.toast('Reason is required', 'warning');
                return;
            }
        }
        closeModal('actionModal');
        ajax(pendingAction, data, function(r) {
            AioSSL.toast(r.message || 'Done', 'success');
            if (pendingAction === 'delete_order') {
                window.location.href = ML + '&page=orders';
            } else {
                setTimeout(function() { location.reload(); }, 800);
            }
        });
    };

    // ── Save order edit ──
    window.saveOrderEdit = function() {
        ajax('edit_order', {
            status:     document.getElementById('editStatus').value,
            remote_id:  document.getElementById('editRemoteId').value,
            service_id: document.getElementById('editServiceId').value,
            domain:     document.getElementById('editDomain').value
        }, function(r) {
            AioSSL.toast(r.message || 'Saved', 'success');
            setTimeout(function() { location.reload(); }, 600);
        });
    };

    // ── Download certificate ──
    window.downloadCert = function(format) {
        ajax('download', { format: format }, function(r) {
            if (r.cert) {
                var content = r.cert;
                var ext = '.pem';
                var mime = 'application/x-pem-file';

                if (format === 'key') {
                    content = r.private_key || '';
                    ext = '.key';
                } else if (format === 'apache') {
                    ext = '.crt';
                } else if (format === 'all') {
                    // For ZIP, content should be base64 — handle accordingly
                    ext = '.pem';
                }

                if (format !== 'key' && r.ca_bundle) {
                    content += '\n' + r.ca_bundle;
                }

                var blob = new Blob([content], { type: mime });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (r.domain || 'certificate') + ext;
                a.click();
                URL.revokeObjectURL(a.href);

                AioSSL.toast('Download started', 'success');
            } else {
                AioSSL.toast(r.message || 'No certificate data', 'warning');
            }
        });
    };

    // ── DCV actions ──
    window.resendDcvForDomain = function(domain) {
        ajax('resend_dcv', { domain: domain }, function(r) {
            AioSSL.toast(r.message || 'DCV resent for ' + domain, 'success');
        });
    };

    window.showChangeDcvModal = function(domain, currentMethod) {
        document.getElementById('dcvDomain').value = domain;
        var sel = document.getElementById('dcvMethod');
        // Try to pre-select current method
        var mNorm = (currentMethod || '').toLowerCase();
        if (mNorm.indexOf('email') >= 0) sel.value = 'email';
        else if (mNorm.indexOf('https') >= 0) sel.value = 'https';
        else if (mNorm.indexOf('http') >= 0) sel.value = 'http';
        else if (mNorm.indexOf('dns') >= 0 || mNorm.indexOf('cname') >= 0) sel.value = 'dns';
        toggleDcvEmail();
        showModal('dcvModal');
    };

    window.toggleDcvEmail = function() {
        var isEmail = document.getElementById('dcvMethod').value === 'email';
        document.getElementById('dcvEmailGroup').style.display = isEmail ? '' : 'none';
    };

    window.submitChangeDcv = function() {
        var domain = document.getElementById('dcvDomain').value;
        var method = document.getElementById('dcvMethod').value;
        var email  = document.getElementById('dcvEmail').value;
        closeModal('dcvModal');
        ajax('change_dcv', { domain: domain, method: method, email: email }, function(r) {
            AioSSL.toast(r.message || 'DCV method changed', 'success');
            setTimeout(function() { location.reload(); }, 800);
        });
    };

    // ── Claim legacy order ──
    window.claimOrder = function() {
        AioSSL.confirm(
            'Claim this legacy order?<br><small>A new AIO record will be created. The original record will not be modified.</small>',
            function() {
                ajax('claim', {}, function(r) {
                    AioSSL.toast(r.message || 'Order claimed', 'success');
                    if (r.new_id) {
                        window.location.href = ML + '&page=orders&action=detail&id=' + r.new_id + '&source=aio';
                    } else {
                        location.reload();
                    }
                });
            },
            { title: 'Claim Legacy Order', confirmText: 'Claim' }
        );
    };

    // ── Config link (SSL2Buy) ──
    window.openConfigLink = function() {
        ajax('config_link', {}, function(r) {
            if (r.url) window.open(r.url, '_blank');
            else AioSSL.toast('Config link not available', 'warning');
        });
    };

    // ── Clipboard ──
    window.copyToClipboard = function(elId) {
        var el = document.getElementById(elId);
        if (!el) return;
        var text = el.value || el.textContent;
        navigator.clipboard.writeText(text).then(function() {
            AioSSL.toast('Copied to clipboard', 'success', 1500);
        }).catch(function() {
            // Fallback
            el.select && el.select();
            document.execCommand('copy');
            AioSSL.toast('Copied', 'success', 1500);
        });
    };

    window.copyText = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            AioSSL.toast('Copied', 'success', 1500);
        });
    };

    // ── Modal helpers ──
    function showModal(id) { document.getElementById(id).style.display = 'flex'; }
    window.closeModal = function(id) { document.getElementById(id).style.display = 'none'; };
})();
</script>