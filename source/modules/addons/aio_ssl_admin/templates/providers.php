<?php
/**
 * Provider List Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $providers      - array of provider DB rows
 *   $availableSlugs - array of slugs not yet registered
 *   $canAdd         - bool
 *   $moduleLink     - string
 *   $moduleVersion  - string
 *   $lang           - array
 *   $csrfToken      - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

$tierLabels = [
    'full'    => ['label' => $lang['tier_full'] ?? 'Full Tier',    'class' => 'aio-tier-full'],
    'limited' => ['label' => $lang['tier_limited'] ?? 'Limited Tier', 'class' => 'aio-tier-limited'],
];

$providerColors = [
    'nicsrs'       => 'aio-provider-nicsrs',
    'gogetssl'     => 'aio-provider-gogetssl',
    'thesslstore'  => 'aio-provider-thesslstore',
    'ssl2buy'      => 'aio-provider-ssl2buy',
];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-plug"></i> <?= $lang['providers_title'] ?? 'SSL Providers' ?>
    </h3>
    <div class="aio-toolbar">
        <?php if ($canAdd): ?>
        <a href="<?= $moduleLink ?>&page=providers&action=add" class="aio-btn aio-btn-primary">
            <i class="fas fa-plus"></i> <?= $lang['add_provider'] ?? 'Add Provider' ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($providers)): ?>
<!-- Empty State -->
<div class="aio-card">
    <div class="aio-card-body">
        <div class="aio-empty">
            <i class="fas fa-plug"></i>
            <p><?= $lang['no_providers'] ?? 'No providers configured yet.' ?></p>
            <?php if ($canAdd): ?>
            <a href="<?= $moduleLink ?>&page=providers&action=add" class="aio-btn aio-btn-primary">
                <i class="fas fa-plus"></i> <?= $lang['add_first_provider'] ?? 'Add Your First Provider' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Provider Table -->
<div class="aio-card">
    <div class="aio-card-body" style="padding:0;">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th><?= $lang['provider_name'] ?? 'Provider' ?></th>
                        <th><?= $lang['slug'] ?? 'Slug' ?></th>
                        <th><?= $lang['tier'] ?? 'Tier' ?></th>
                        <th><?= $lang['api_mode'] ?? 'API Mode' ?></th>
                        <th><?= $lang['status'] ?? 'Status' ?></th>
                        <th><?= $lang['last_test'] ?? 'Last Test' ?></th>
                        <th><?= $lang['last_sync'] ?? 'Last Sync' ?></th>
                        <th><?= $lang['products_count'] ?? 'Products' ?></th>
                        <th class="text-right"><?= $lang['balance'] ?? 'Balance' ?></th>                        
                        <th class="text-center"><?= $lang['actions'] ?? 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($providers as $p):
                    $slug = $p->slug;
                    $badgeClass = $providerColors[$slug] ?? 'aio-badge-default';
                    $tier = $tierLabels[$p->tier] ?? $tierLabels['full'];
                    $isEnabled = (bool) $p->is_enabled;
                    $testOk = $p->test_result === 1 || $p->test_result === '1';
                    $testFail = $p->test_result === 0 || $p->test_result === '0';
                ?>
                <tr>
                    <!-- Provider Name + Badge -->
                    <td>
                        <span class="aio-provider-badge <?= $badgeClass ?>"><?= htmlspecialchars($p->name) ?></span>
                    </td>

                    <!-- Slug -->
                    <td><code><?= htmlspecialchars($slug) ?></code></td>

                    <!-- Tier -->
                    <td>
                        <span class="aio-badge <?= $tier['class'] ?>"><?= $tier['label'] ?></span>
                    </td>

                    <!-- API Mode -->
                    <td>
                        <?php if ($p->api_mode === 'sandbox'): ?>
                            <span class="aio-badge aio-badge-warning"><i class="fas fa-flask"></i> Sandbox</span>
                        <?php else: ?>
                            <span class="aio-badge aio-badge-success"><i class="fas fa-globe"></i> Live</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td>
                        <?php if ($isEnabled): ?>
                            <span class="aio-badge aio-badge-success"><i class="fas fa-check"></i> <?= $lang['enabled'] ?? 'Enabled' ?></span>
                        <?php else: ?>
                            <span class="aio-badge aio-badge-default"><i class="fas fa-ban"></i> <?= $lang['disabled'] ?? 'Disabled' ?></span>
                        <?php endif; ?>
                    </td>

                    <!-- Last Test -->
                    <td class="text-nowrap">
                        <?php if ($p->last_test): ?>
                            <?php if ($testOk): ?>
                                <span style="color:var(--aio-success)"><i class="fas fa-check-circle"></i></span>
                            <?php elseif ($testFail): ?>
                                <span style="color:var(--aio-danger)"><i class="fas fa-times-circle"></i></span>
                            <?php endif; ?>
                            <small><?= htmlspecialchars($p->last_test) ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Last Sync -->
                    <td class="text-nowrap">
                        <?php if ($p->last_sync): ?>
                            <small><?= htmlspecialchars($p->last_sync) ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Product Count -->
                    <td class="text-center">
                        <?php
                        try {
                            $prodCount = \WHMCS\Database\Capsule::table('mod_aio_ssl_products')
                                ->where('provider_slug', $slug)->count();
                        } catch (\Exception $e) {
                            $prodCount = 0;
                        }
                        ?>
                        <span class="aio-badge aio-badge-primary"><?= $prodCount ?></span>
                    </td>

                    <!-- Provider Balance -->                    
                    <td class="text-right" id="balance-<?= htmlspecialchars($p->slug) ?>">
                        <?php if (!empty($balanceSupport[$p->slug])): ?>
                            <span class="aio-balance-loading text-muted">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        <?php else: ?>
                            <span class="text-muted" title="Balance not supported by this provider">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td class="text-center">
                        <div class="aio-btn-group">
                            <button type="button" class="aio-btn aio-btn-sm aio-btn-ghost"
                                    onclick="AioSSL.testProvider('<?= $slug ?>')"
                                    title="<?= $lang['test_connection'] ?? 'Test Connection' ?>">
                                <i class="fas fa-satellite-dish"></i>
                            </button>
                            <a href="<?= $moduleLink ?>&page=providers&action=edit&id=<?= $p->id ?>"
                               class="aio-btn aio-btn-sm"
                               title="<?= $lang['edit'] ?? 'Edit' ?>">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                            <button type="button" class="aio-btn aio-btn-sm"
                                    onclick="AioSSL.toggleProvider(<?= $p->id ?>, '<?= htmlspecialchars($p->name, ENT_QUOTES) ?>')"
                                    title="<?= $isEnabled ? ($lang['disable'] ?? 'Disable') : ($lang['enable'] ?? 'Enable') ?>">
                                <i class="fas <?= $isEnabled ? 'fa-toggle-on' : 'fa-toggle-off' ?>"
                                   style="color:<?= $isEnabled ? 'var(--aio-success)' : 'var(--aio-text-secondary)' ?>"></i>
                            </button>
                            <button type="button" class="aio-btn aio-btn-sm"
                                    onclick="AioSSL.deleteProvider(<?= $p->id ?>, '<?= htmlspecialchars($p->name, ENT_QUOTES) ?>')"
                                    title="<?= $lang['delete'] ?? 'Delete' ?>"
                                    style="color:var(--aio-danger)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                        <!-- Test result area -->
                        <div id="test-result-<?= $slug ?>" class="aio-test-result"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Provider Capability Matrix -->
<div class="aio-card">
    <div class="aio-card-header">
        <span><i class="fas fa-th-list"></i> <?= $lang['capability_matrix'] ?? 'Provider Capability Matrix' ?></span>
    </div>
    <div class="aio-card-body" style="padding:0">
        <div class="aio-table-wrapper">
            <table class="aio-table">
                <thead>
                    <tr>
                        <th><?= $lang['capability'] ?? 'Capability' ?></th>
                        <?php foreach ($providers as $p): ?>
                        <th class="text-center">
                            <span class="aio-provider-badge <?= $providerColors[$p->slug] ?? '' ?>"><?= htmlspecialchars($p->name) ?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $capabilities = [
                        'Place Order'      => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>true],
                        'Get Status'       => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>true],
                        'Download Cert'    => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>false],
                        'Reissue'          => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>false],
                        'Renew'            => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>false],
                        'Revoke'           => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>false],
                        'Cancel / Refund'  => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>false],
                        'DCV Management'   => ['nicsrs'=>true, 'gogetssl'=>true, 'thesslstore'=>true, 'ssl2buy'=>'partial'],
                        'Balance Check'    => ['nicsrs'=>false,'gogetssl'=>true, 'thesslstore'=>true,'ssl2buy'=>true],
                        'Config Link'      => ['nicsrs'=>false, 'gogetssl'=>false, 'thesslstore'=>false, 'ssl2buy'=>true],
                    ];
                    foreach ($capabilities as $cap => $map):
                    ?>
                    <tr>
                        <td><strong><?= $cap ?></strong></td>
                        <?php foreach ($providers as $p):
                            $val = $map[$p->slug] ?? false;
                        ?>
                        <td class="text-center">
                            <?php if ($val === true): ?>
                                <span style="color:var(--aio-success)"><i class="fas fa-check-circle"></i></span>
                            <?php elseif ($val === 'partial'): ?>
                                <span style="color:var(--aio-warning)"><i class="fas fa-exclamation-circle"></i></span>
                            <?php else: ?>
                                <span style="color:var(--aio-text-secondary)"><i class="fas fa-minus-circle"></i></span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>