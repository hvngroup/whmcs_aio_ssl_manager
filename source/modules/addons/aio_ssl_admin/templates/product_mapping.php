<?php
/**
 * Product Mapping Template — Admin Addon (PHP Template)
 *
 * Variables: $mappings, $unmapped, $mapStats, $moduleLink, $lang, $csrfToken
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$providerNames = ['nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore','ssl2buy'=>'SSL2Buy'];
$providerBadge = [
    'nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl',
    'thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy',
];
$valBadge = ['dv'=>'aio-badge-success','ov'=>'aio-badge-primary','ev'=>'aio-badge-warning'];

// Build product options per provider for dropdowns
$providerProducts = [];
foreach (['nicsrs','gogetssl','thesslstore','ssl2buy'] as $slug) {
    try {
        $providerProducts[$slug] = \WHMCS\Database\Capsule::table('mod_aio_ssl_products')
            ->where('provider_slug', $slug)
            ->orderBy('product_name')
            ->get(['product_code', 'product_name'])
            ->toArray();
    } catch (\Exception $e) {
        $providerProducts[$slug] = [];
    }
}
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-project-diagram"></i> <?= $lang['product_mapping'] ?? 'Product Mapping' ?>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn aio-btn-primary" onclick="runAutoMap()">
            <i class="fas fa-magic"></i> Auto-Map Products
        </button>
        <button class="aio-btn aio-btn-ghost" onclick="$('#create-canonical-modal').show()">
            <i class="fas fa-plus"></i> Create Canonical Entry
        </button>
        <a href="<?= $moduleLink ?>&page=products" class="aio-btn">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<!-- Mapping Stats -->
<?php if (!empty($mapStats)): ?>
<div class="aio-stats-grid" style="margin-bottom:20px;">
    <div class="aio-stat-card">
        <div class="aio-stat-icon blue"><i class="fas fa-th-list"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= $mapStats['canonical_entries'] ?? 0 ?></div>
            <div class="aio-stat-label">Canonical Entries</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon green"><i class="fas fa-link"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= $mapStats['mapped_products'] ?? 0 ?></div>
            <div class="aio-stat-label">Mapped Products</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon orange"><i class="fas fa-unlink"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= $mapStats['unmapped_products'] ?? 0 ?></div>
            <div class="aio-stat-label">Unmapped Products</div>
        </div>
    </div>
    <div class="aio-stat-card">
        <div class="aio-stat-icon <?= ($mapStats['mapping_rate'] ?? 0) >= 80 ? 'green' : 'orange' ?>"><i class="fas fa-percentage"></i></div>
        <div class="aio-stat-content">
            <div class="aio-stat-value"><?= $mapStats['mapping_rate'] ?? 0 ?>%</div>
            <div class="aio-stat-label">Mapping Rate</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Canonical Mapping Table -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-table"></i> Canonical Product Mappings</span>
        <span style="font-size:12px;color:var(--aio-text-secondary);"><?= count($mappings ?? []) ?> entries</span>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($mappings)): ?>
        <div class="aio-empty" style="padding:40px;">
            <i class="fas fa-project-diagram"></i>
            <p>No canonical product mappings yet. Run Auto-Map or create entries manually.</p>
        </div>
        <?php else: ?>
        <div class="aio-table-wrapper">
            <table class="aio-table" id="mapping-table">
                <thead>
                    <tr>
                        <th>Canonical ID</th>
                        <th>Product Name</th>
                        <th>Vendor</th>
                        <th class="text-center">Val.</th>
                        <th class="text-center">
                            <span class="aio-provider-badge aio-provider-nicsrs">NicSRS</span>
                        </th>
                        <th class="text-center">
                            <span class="aio-provider-badge aio-provider-gogetssl">GoGetSSL</span>
                        </th>
                        <th class="text-center">
                            <span class="aio-provider-badge aio-provider-thesslstore">TheSSLStore</span>
                        </th>
                        <th class="text-center">
                            <span class="aio-provider-badge aio-provider-ssl2buy">SSL2Buy</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mappings as $m):
                    $codes = [
                        'nicsrs'      => $m->nicsrs_code,
                        'gogetssl'    => $m->gogetssl_code,
                        'thesslstore' => $m->thesslstore_code,
                        'ssl2buy'     => $m->ssl2buy_code,
                    ];
                ?>
                <tr>
                    <td><code style="font-size:11px;"><?= htmlspecialchars($m->canonical_id) ?></code></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($m->canonical_name) ?></td>
                    <td><?= htmlspecialchars($m->vendor) ?></td>
                    <td class="text-center">
                        <span class="aio-badge <?= $valBadge[strtolower($m->validation_type)] ?? 'aio-badge-default' ?>">
                            <?= strtoupper($m->validation_type) ?>
                        </span>
                    </td>
                    <?php foreach (['nicsrs','gogetssl','thesslstore','ssl2buy'] as $slug):
                        $code = $codes[$slug];
                    ?>
                    <td class="text-center">
                        <select class="aio-form-control" style="font-size:11px;padding:4px 6px;width:auto;min-width:120px;"
                                onchange="saveMapping('<?= htmlspecialchars($m->canonical_id) ?>', '<?= $slug ?>', this.value)"
                                data-canonical="<?= htmlspecialchars($m->canonical_id) ?>"
                                data-provider="<?= $slug ?>">
                            <option value="">— None —</option>
                            <?php foreach ($providerProducts[$slug] as $pp): ?>
                            <option value="<?= htmlspecialchars($pp->product_code) ?>"
                                    <?= $code === $pp->product_code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pp->product_code) ?> — <?= htmlspecialchars(substr($pp->product_name, 0, 40)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($code): ?>
                            <div style="font-size:10px;color:var(--aio-success);margin-top:2px;">
                                <i class="fas fa-check"></i> <?= htmlspecialchars($code) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Unmapped Products -->
<?php if (!empty($unmapped)): ?>
<div class="aio-card" style="margin-top:20px;">
    <div class="aio-card-header" style="background:var(--aio-warning-bg);">
        <span><i class="fas fa-exclamation-triangle" style="color:var(--aio-warning)"></i> Unmapped Products (<?= count($unmapped) ?>)</span>
        <button class="aio-btn aio-btn-sm aio-btn-warning" onclick="runAutoMap()">
            <i class="fas fa-magic"></i> Auto-Map
        </button>
    </div>
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Vendor</th>
                        <th class="text-center">Val.</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($unmapped as $u): ?>
                <tr>
                    <td>
                        <span class="aio-provider-badge <?= $providerBadge[$u->provider_slug] ?? '' ?>">
                            <?= $providerNames[$u->provider_slug] ?? $u->provider_slug ?>
                        </span>
                    </td>
                    <td class="text-mono"><?= htmlspecialchars($u->product_code) ?></td>
                    <td><?= htmlspecialchars($u->product_name) ?></td>
                    <td><?= htmlspecialchars($u->vendor ?? '') ?></td>
                    <td class="text-center">
                        <span class="aio-badge <?= $valBadge[strtolower($u->validation_type ?? '')] ?? 'aio-badge-default' ?>">
                            <?= strtoupper($u->validation_type ?? '—') ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <button class="aio-btn aio-btn-xs aio-btn-ghost"
                                onclick="createCanonicalFromProduct('<?= htmlspecialchars($u->product_name, ENT_QUOTES) ?>', '<?= htmlspecialchars($u->vendor ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u->validation_type ?? 'dv', ENT_QUOTES) ?>', '<?= htmlspecialchars($u->product_type ?? 'ssl', ENT_QUOTES) ?>')">
                            <i class="fas fa-plus"></i> Create Canonical
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create Canonical Modal (hidden by default) -->
<div id="create-canonical-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:10000;align-items:center;justify-content:center;">
    <div class="aio-modal" style="width:500px;">
        <div class="aio-modal-header">
            <span>Create Canonical Product Entry</span>
            <button class="aio-modal-close" onclick="$('#create-canonical-modal').hide()">&times;</button>
        </div>
        <div class="aio-modal-body">
            <form id="canonical-form">
                <input type="hidden" name="token" value="<?= $csrfToken ?>" />
                <div class="aio-form-row">
                    <div class="aio-form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" id="can-name" class="aio-form-control" required placeholder="e.g. Sectigo PositiveSSL" />
                    </div>
                    <div class="aio-form-group">
                        <label>Vendor / CA <span class="required">*</span></label>
                        <input type="text" name="vendor" id="can-vendor" class="aio-form-control" required placeholder="e.g. Sectigo" />
                    </div>
                </div>
                <div class="aio-form-row">
                    <div class="aio-form-group">
                        <label>Validation Type</label>
                        <select name="validation_type" id="can-val" class="aio-form-control">
                            <option value="dv">DV</option>
                            <option value="ov">OV</option>
                            <option value="ev">EV</option>
                        </select>
                    </div>
                    <div class="aio-form-group">
                        <label>Product Type</label>
                        <select name="product_type" id="can-type" class="aio-form-control">
                            <option value="ssl">Standard SSL</option>
                            <option value="wildcard">Wildcard</option>
                            <option value="multi_domain">Multi-Domain</option>
                            <option value="code_signing">Code Signing</option>
                            <option value="email">Email / S/MIME</option>
                        </select>
                    </div>
                </div>
                <div class="aio-form-group">
                    <label>Canonical ID (auto-generated if blank)</label>
                    <input type="text" name="canonical_id" class="aio-form-control" placeholder="e.g. sectigo-positivessl" />
                    <div class="aio-form-hint">Format: vendor-productname (lowercase, hyphenated)</div>
                </div>
            </form>
        </div>
        <div class="aio-modal-footer">
            <button class="aio-btn" onclick="$('#create-canonical-modal').hide()">Cancel</button>
            <button class="aio-btn aio-btn-primary" onclick="submitCanonical()">
                <i class="fas fa-plus"></i> Create Entry
            </button>
        </div>
    </div>
</div>

<script>
function saveMapping(canonicalId, providerSlug, productCode) {
    AioSSL.ajax({
        page: 'products',
        action: 'save_mapping',
        data: { canonical_id: canonicalId, provider_slug: providerSlug, product_code: productCode },
        loading: false,
        successMessage: false,
        onSuccess: function(r) {
            AioSSL.toast(r.message || 'Mapping saved.', 'success', 2000);
        }
    });
}

function runAutoMap() {
    AioSSL.ajax({
        page: 'products',
        action: 'auto_map',
        loadingMsg: 'Running auto-mapping...',
        onSuccess: function(r) {
            AioSSL.toast('Mapped ' + (r.mapped || 0) + ' products. ' + (r.unmapped || 0) + ' unmapped.', 'success', 5000);
            setTimeout(function() { location.reload(); }, 1500);
        }
    });
}

function submitCanonical() {
    AioSSL.ajax({
        page: 'products',
        action: 'create_canonical',
        data: $('#canonical-form').serialize(),
        loadingMsg: 'Creating...',
        onSuccess: function(r) {
            $('#create-canonical-modal').hide();
            location.reload();
        }
    });
}

function createCanonicalFromProduct(name, vendor, val, type) {
    $('#can-name').val(name);
    $('#can-vendor').val(vendor);
    $('#can-val').val(val);
    $('#can-type').val(type);
    $('#create-canonical-modal').css('display', 'flex');
}
</script>