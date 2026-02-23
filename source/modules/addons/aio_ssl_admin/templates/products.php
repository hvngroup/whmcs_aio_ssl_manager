<?php
/**
 * Products Template — Admin Addon (PHP Template)
 *
 * Variables: $products, $pagination, $vendors, $providers, $filters,
 *            $mappingStats, $moduleLink, $lang, $csrfToken
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */
if (!defined('WHMCS')) die('Access denied.');

$providerFilter   = $filters['providerFilter'] ?? '';
$vendorFilter     = $filters['vendorFilter'] ?? '';
$validationFilter = $filters['validationFilter'] ?? '';
$search           = $filters['search'] ?? '';

$providerBadge = [
    'nicsrs'=>'aio-provider-nicsrs','gogetssl'=>'aio-provider-gogetssl',
    'thesslstore'=>'aio-provider-thesslstore','ssl2buy'=>'aio-provider-ssl2buy',
];
$valBadge = ['dv'=>'aio-badge-success','ov'=>'aio-badge-primary','ev'=>'aio-badge-warning'];
$providerNames = ['nicsrs'=>'NicSRS','gogetssl'=>'GoGetSSL','thesslstore'=>'TheSSLStore','ssl2buy'=>'SSL2Buy'];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-cube"></i> <?= $lang['products_title'] ?? 'Product Catalog' ?>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn aio-btn-primary" onclick="AioSSL.syncProducts()">
            <i class="fas fa-sync-alt"></i> <?= $lang['sync_all'] ?? 'Sync All' ?>
        </button>
        <a href="<?= $moduleLink ?>&page=products&action=mapping" class="aio-btn aio-btn-ghost">
            <i class="fas fa-project-diagram"></i> <?= $lang['product_mapping'] ?? 'Product Mapping' ?>
        </a>
    </div>
</div>

<!-- Mapping Stats Bar -->
<?php if (!empty($mappingStats)): ?>
<div class="aio-alert aio-alert-info" style="display:flex;gap:24px;align-items:center;">
    <span><strong><?= $mappingStats['total_products'] ?? 0 ?></strong> total products</span>
    <span><strong style="color:var(--aio-success)"><?= $mappingStats['mapped_products'] ?? 0 ?></strong> mapped</span>
    <span><strong style="color:var(--aio-warning)"><?= $mappingStats['unmapped_products'] ?? 0 ?></strong> unmapped</span>
    <span><strong><?= $mappingStats['canonical_entries'] ?? 0 ?></strong> canonical entries</span>
    <span>Mapping rate: <strong><?= $mappingStats['mapping_rate'] ?? 0 ?>%</strong></span>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="aio-filters">
    <form method="get" style="display:contents;">
        <input type="hidden" name="module" value="aio_ssl_admin" />
        <input type="hidden" name="page" value="products" />

        <select name="provider" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid var(--aio-border);border-radius:4px;font-size:12px;">
            <option value=""><?= $lang['all_providers'] ?? 'All Providers' ?></option>
            <?php foreach ($providers ?? [] as $p): ?>
            <option value="<?= htmlspecialchars($p->slug) ?>" <?= $providerFilter === $p->slug ? 'selected' : '' ?>>
                <?= $providerNames[$p->slug] ?? $p->name ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="vendor" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid var(--aio-border);border-radius:4px;font-size:12px;">
            <option value=""><?= $lang['all_vendors'] ?? 'All Vendors' ?></option>
            <?php foreach ($vendors ?? [] as $v): ?>
            <option value="<?= htmlspecialchars($v) ?>" <?= $vendorFilter === $v ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="validation" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid var(--aio-border);border-radius:4px;font-size:12px;">
            <option value=""><?= $lang['all_validations'] ?? 'All Validations' ?></option>
            <option value="dv" <?= $validationFilter === 'dv' ? 'selected' : '' ?>>DV</option>
            <option value="ov" <?= $validationFilter === 'ov' ? 'selected' : '' ?>>OV</option>
            <option value="ev" <?= $validationFilter === 'ev' ? 'selected' : '' ?>>EV</option>
        </select>

        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $lang['search_products'] ?? 'Search products...' ?>"
               style="padding:7px 12px;border:1px solid var(--aio-border);border-radius:4px;font-size:12px;width:200px;" />
        <button type="submit" class="aio-btn aio-btn-sm"><i class="fas fa-search"></i></button>

        <div class="aio-filter-spacer"></div>

        <!-- Per-provider sync buttons -->
        <?php foreach ($providers ?? [] as $p): ?>
        <button type="button" class="aio-btn aio-btn-xs" onclick="AioSSL.syncProducts('<?= $p->slug ?>')" title="Sync <?= $providerNames[$p->slug] ?? $p->slug ?>">
            <i class="fas fa-sync-alt"></i> <?= $providerNames[$p->slug] ?? $p->slug ?>
        </button>
        <?php endforeach; ?>
    </form>
</div>

<!-- Products Table -->
<div class="aio-card">
    <div class="aio-card-body" style="padding:0;">
        <?php if (empty($products)): ?>
        <div class="aio-empty" style="padding:40px;">
            <i class="fas fa-cube"></i>
            <p><?= $lang['no_products'] ?? 'No products found. Sync your product catalogs first.' ?></p>
            <button class="aio-btn aio-btn-primary" onclick="AioSSL.syncProducts()">
                <i class="fas fa-sync-alt"></i> Sync Products Now
            </button>
        </div>
        <?php else: ?>
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th><?= $lang['provider'] ?? 'Provider' ?></th>
                        <th><?= $lang['product_code'] ?? 'Code' ?></th>
                        <th><?= $lang['product_name'] ?? 'Product Name' ?></th>
                        <th><?= $lang['vendor'] ?? 'Vendor' ?></th>
                        <th class="text-center"><?= $lang['validation_type'] ?? 'Val.' ?></th>
                        <th class="text-center"><?= $lang['wildcard'] ?? 'WC' ?></th>
                        <th class="text-center"><?= $lang['san'] ?? 'SAN' ?></th>
                        <th class="text-center"><?= $lang['max_domains'] ?? 'Domains' ?></th>
                        <th><?= $lang['price'] ?? 'Price (1yr)' ?></th>
                        <th class="text-center"><?= $lang['mapped'] ?? 'Mapped' ?></th>
                        <th class="text-nowrap"><?= $lang['last_sync'] ?? 'Synced' ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $slug = $p->provider_slug;
                    $pd = json_decode($p->price_data ?? '{}', true) ?: [];
                    $basePrice = $pd['base']['12'] ?? $pd['12'] ?? $pd['price012'] ?? null;
                    $isMapped = !empty($p->canonical_id);
                ?>
                <tr>
                    <td><span class="aio-provider-badge <?= $providerBadge[$slug] ?? 'aio-badge-default' ?>"><?= $providerNames[$slug] ?? $slug ?></span></td>
                    <td class="text-mono"><?= htmlspecialchars($p->product_code) ?></td>
                    <td><?= htmlspecialchars($p->product_name) ?></td>
                    <td><?= htmlspecialchars($p->vendor) ?></td>
                    <td class="text-center">
                        <span class="aio-badge <?= $valBadge[strtolower($p->validation_type)] ?? 'aio-badge-default' ?>">
                            <?= strtoupper($p->validation_type) ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $p->support_wildcard ? '<i class="fas fa-check" style="color:var(--aio-success)"></i>' : '—' ?></td>
                    <td class="text-center"><?= $p->support_san ? '<i class="fas fa-check" style="color:var(--aio-success)"></i>' : '—' ?></td>
                    <td class="text-center"><?= $p->max_domains > 1 ? $p->max_domains : '—' ?></td>
                    <td class="text-nowrap">
                        <?php if ($basePrice !== null): ?>
                            <strong>$<?= number_format($basePrice, 2) ?></strong>/yr
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($isMapped): ?>
                            <span class="aio-badge aio-badge-success" title="<?= htmlspecialchars($p->canonical_id) ?>"><i class="fas fa-link"></i> Mapped</span>
                        <?php else: ?>
                            <span class="aio-badge aio-badge-default"><i class="fas fa-unlink"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap" style="color:var(--aio-text-secondary);font-size:11px;">
                        <?= $p->last_sync ? date('m/d H:i', strtotime($p->last_sync)) : '—' ?>
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
                of <?= number_format($pagination['total']) ?>
            </div>
            <div class="aio-pagination-links">
                <?php
                $cp = $pagination['page'];
                $tp = $pagination['pages'];
                $base = $moduleLink . '&page=products'
                    . ($providerFilter ? '&provider=' . urlencode($providerFilter) : '')
                    . ($vendorFilter ? '&vendor=' . urlencode($vendorFilter) : '')
                    . ($validationFilter ? '&validation=' . urlencode($validationFilter) : '')
                    . ($search ? '&search=' . urlencode($search) : '');
                if ($cp > 1): ?><a href="<?= $base ?>&p=<?= $cp - 1 ?>">‹</a><?php endif;
                for ($i = max(1, $cp - 2); $i <= min($tp, $cp + 2); $i++):
                    if ($i === $cp): ?><span class="current"><?= $i ?></span>
                    <?php else: ?><a href="<?= $base ?>&p=<?= $i ?>"><?= $i ?></a><?php endif;
                endfor;
                if ($cp < $tp): ?><a href="<?= $base ?>&p=<?= $cp + 1 ?>">›</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>