<?php
/**
 * Price Compare Template — Admin Addon (PHP Template)
 *
 * Variables: $canonicals, $selectedId, $comparison, $moduleLink, $lang, $csrfToken
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
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-balance-scale"></i> <?= $lang['compare_title'] ?? 'Price Comparison' ?>
    </h3>
    <div class="aio-toolbar">
        <button class="aio-btn" onclick="exportCsv()">
            <i class="fas fa-file-csv"></i> <?= $lang['export_csv'] ?? 'Export CSV' ?>
        </button>
    </div>
</div>

<p style="color:var(--aio-text-secondary);font-size:13px;margin-bottom:16px;">
    <?= $lang['compare_desc'] ?? 'Compare reseller costs across providers for the same SSL product.' ?>
    <br><small>💡 Best prices highlighted • 📊 Margin = WHMCS Sell Price − Best Reseller Cost (excl. tax)</small>
</p>

<!-- Product Selector -->
<div class="aio-card">
    <div class="aio-card-body">
        <form method="get" style="display:flex;gap:12px;align-items:end;">
            <input type="hidden" name="module" value="aio_ssl_admin" />
            <input type="hidden" name="page" value="compare" />
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px;">
                    <?= $lang['select_product'] ?? 'Select Product' ?>
                </label>
                <select name="canonical_id" class="aio-form-control" onchange="this.form.submit()">
                    <option value="">— Select a canonical product —</option>
                    <?php
                    $grouped = [];
                    foreach ($canonicals as $c) {
                        $grouped[$c->vendor][] = $c;
                    }
                    ksort($grouped);
                    foreach ($grouped as $vendor => $items):
                    ?>
                    <optgroup label="<?= htmlspecialchars($vendor) ?>">
                        <?php foreach ($items as $c): ?>
                        <option value="<?= htmlspecialchars($c->canonical_id) ?>"
                                <?= $selectedId === $c->canonical_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c->canonical_name) ?> (<?= strtoupper($c->validation_type) ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="aio-btn aio-btn-primary">
                <i class="fas fa-search"></i> Compare
            </button>
        </form>
    </div>
</div>

<?php if ($comparison && !empty($comparison['providers'])): ?>
<?php
    $c = $comparison['canonical'];
    $providers = $comparison['providers'];
    $periods = $comparison['periods'];
    $best = $comparison['best'];
    $whmcs = $comparison['whmcs_price'];
?>

<!-- Product Info -->
<div class="aio-card" style="margin-top:16px;">
    <div class="aio-card-header">
        <span>
            <i class="fas fa-certificate"></i>
            <strong><?= htmlspecialchars($c['name']) ?></strong>
            — <?= htmlspecialchars($c['vendor']) ?>
            <span class="aio-badge <?= ['dv'=>'aio-badge-success','ov'=>'aio-badge-primary','ev'=>'aio-badge-warning'][$c['validation_type']] ?? 'aio-badge-default' ?>">
                <?= strtoupper($c['validation_type']) ?>
            </span>
        </span>
    </div>

    <!-- Price Comparison Table -->
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th><?= $lang['period'] ?? 'Period' ?></th>
                        <?php foreach ($providers as $slug => $pd): ?>
                        <th class="text-center">
                            <span class="aio-provider-badge <?= $providerBadge[$slug] ?? '' ?>"><?= $pd['name'] ?></span>
                            <br><small class="text-muted"><?= htmlspecialchars($pd['code']) ?></small>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center" style="background:var(--aio-success-bg);"><?= $lang['best_price'] ?? 'Best Price' ?></th>
                        <?php if ($whmcs): ?>
                        <th class="text-center"><?= $lang['whmcs_sell_price'] ?? 'WHMCS Sell' ?></th>
                        <th class="text-center"><?= $lang['margin'] ?? 'Margin' ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $period):
                    $bestSlug = $best[$period]['provider'] ?? null;
                    $bestPrice = $best[$period]['price'] ?? null;

                    // WHMCS sell price mapped to period
                    $sellPrice = null;
                    if ($whmcs) {
                        if ($period == 12) $sellPrice = $whmcs['annually'];
                        elseif ($period == 24) $sellPrice = $whmcs['biennially'];
                        elseif ($period == 36) $sellPrice = $whmcs['triennially'];
                    }
                    $margin = ($sellPrice && $bestPrice) ? ($sellPrice - $bestPrice) : null;
                ?>
                <tr>
                    <td style="font-weight:600;">
                        <?php
                        if ($period % 12 === 0) {
                            $yrs = $period / 12;
                            echo $yrs . ' ' . ($yrs > 1 ? 'Years' : 'Year');
                        } else {
                            echo $period . ' Months';
                        }
                        ?>
                    </td>
                    <?php foreach ($providers as $slug => $pd):
                        $price = $pd['prices'][$period] ?? null;
                        $isBest = ($slug === $bestSlug && $price !== null);
                    ?>
                    <td class="text-center <?= $isBest ? 'aio-price-best' : '' ?>">
                        <?php if ($price !== null): ?>
                            $<?= number_format($price, 2) ?>
                            <?php if ($isBest): ?> <i class="fas fa-trophy" style="font-size:10px;"></i><?php endif; ?>
                        <?php else: ?>
                            <span class="aio-price-na">N/A</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>

                    <!-- Best Price -->
                    <td class="text-center" style="background:var(--aio-success-bg);font-weight:700;color:var(--aio-success);">
                        <?php if ($bestPrice !== null): ?>
                            $<?= number_format($bestPrice, 2) ?>
                            <br><small><?= $best[$period]['name'] ?? '' ?></small>
                        <?php else: ?>—<?php endif; ?>
                    </td>

                    <?php if ($whmcs): ?>
                    <!-- WHMCS Sell Price -->
                    <td class="text-center">
                        <?= $sellPrice ? '$' . number_format($sellPrice, 2) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <!-- Margin -->
                    <td class="text-center">
                        <?php if ($margin !== null): ?>
                            <strong class="<?= $margin >= 0 ? 'aio-margin-positive' : 'aio-margin-negative' ?>">
                                <?= $margin >= 0 ? '+' : '' ?>$<?= number_format($margin, 2) ?>
                            </strong>
                            <?php if ($sellPrice > 0): ?>
                            <br><small class="text-muted"><?= round(($margin / $sellPrice) * 100, 1) ?>%</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($whmcs): ?>
    <div class="aio-card-footer" style="font-size:12px;color:var(--aio-text-secondary);">
        <i class="fas fa-link"></i> Linked WHMCS Product:
        <a href="configproducts.php?action=edit&id=<?= $whmcs['product_id'] ?>" class="aio-link">
            <?= htmlspecialchars($whmcs['product_name']) ?> (#<?= $whmcs['product_id'] ?>)
        </a>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($selectedId): ?>
<div class="aio-alert aio-alert-warning" style="margin-top:16px;">
    <i class="fas fa-exclamation-triangle"></i>
    No provider pricing data found for this product. Sync product catalogs first.
</div>

<?php else: ?>
<!-- No Selection State -->
<div class="aio-card" style="margin-top:16px;">
    <div class="aio-card-body">
        <div class="aio-empty">
            <i class="fas fa-balance-scale"></i>
            <p><?= $lang['no_comparison'] ?? 'Select a product to compare prices across providers.' ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function exportCsv() {
    AioSSL.ajax({
        page: 'compare',
        action: 'export_csv',
        loadingMsg: 'Generating CSV...',
        successMessage: false,
        onSuccess: function(resp) {
            if (resp.csv) {
                var blob = new Blob([resp.csv], { type: 'text/csv' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = resp.filename || 'price_comparison.csv';
                a.click();
                URL.revokeObjectURL(url);
                AioSSL.toast('CSV exported.', 'success');
            }
        }
    });
}
</script>