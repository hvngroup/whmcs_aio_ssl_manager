<?php
/**
 * Provider Add/Edit Form Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $mode             - 'add' | 'edit'
 *   $provider         - object|null (existing provider row for edit)
 *   $credentials      - array (decrypted, for edit only)
 *   $availableSlugs   - array (for add mode)
 *   $credentialFields - array [slug => fields[]]
 *   $providerTiers    - array [slug => tier]
 *   $moduleLink       - string
 *   $lang             - array
 *   $csrfToken        - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

$isEdit = ($mode === 'edit');
$formTitle = $isEdit
    ? ($lang['edit_provider'] ?? 'Edit Provider') . ': ' . htmlspecialchars($provider->name ?? '')
    : ($lang['add_provider'] ?? 'Add New Provider');

$providerNames = [
    'nicsrs'      => 'NicSRS',
    'gogetssl'    => 'GoGetSSL',
    'thesslstore' => 'TheSSLStore',
    'ssl2buy'     => 'SSL2Buy',
];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-<?= $isEdit ? 'pencil-alt' : 'plus-circle' ?>"></i> <?= $formTitle ?>
    </h3>
    <div class="aio-toolbar">
        <a href="<?= $moduleLink ?>&page=providers" class="aio-btn">
            <i class="fas fa-arrow-left"></i> <?= $lang['back_to_list'] ?? 'Back to Providers' ?>
        </a>
    </div>
</div>

<div class="aio-grid-2-equal">
    <!-- Left: Form -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-cog"></i> <?= $lang['provider_config'] ?? 'Provider Configuration' ?></span>
        </div>
        <div class="aio-card-body">
            <form id="aio-provider-form">
                <input type="hidden" name="token" value="<?= $csrfToken ?>" />
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $provider->id ?>" />
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($provider->slug) ?>" />
                <?php endif; ?>

                <!-- Provider Slug (Add mode: dropdown) -->
                <?php if (!$isEdit): ?>
                <div class="aio-form-group">
                    <label><?= $lang['provider_type'] ?? 'Provider Type' ?> <span class="required">*</span></label>
                    <select name="slug" id="provider-slug" class="aio-form-control" required
                            onchange="AioSSL.loadCredentialFields(this.value); updateProviderInfo(this.value);">
                        <option value="">— <?= $lang['select_provider'] ?? 'Select a Provider' ?> —</option>
                        <?php foreach ($availableSlugs as $s): ?>
                        <option value="<?= $s ?>"><?= $providerNames[$s] ?? ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="aio-form-group">
                    <label><?= $lang['provider_type'] ?? 'Provider Type' ?></label>
                    <input type="text" class="aio-form-control" value="<?= $providerNames[$provider->slug] ?? $provider->slug ?>" disabled />
                </div>
                <?php endif; ?>

                <!-- Display Name -->
                <div class="aio-form-group">
                    <label><?= $lang['display_name'] ?? 'Display Name' ?> <span class="required">*</span></label>
                    <input type="text" name="name" class="aio-form-control" required
                           value="<?= $isEdit ? htmlspecialchars($provider->name) : '' ?>"
                           placeholder="e.g. NicSRS Production" />
                </div>

                <!-- API Mode -->
                <div class="aio-form-group">
                    <label><?= $lang['api_mode'] ?? 'API Mode' ?></label>
                    <select name="api_mode" class="aio-form-control">
                        <option value="live" <?= ($isEdit && $provider->api_mode === 'live') ? 'selected' : '' ?>>
                            🟢 Live (Production)
                        </option>
                        <option value="sandbox" <?= ($isEdit && $provider->api_mode === 'sandbox') ? 'selected' : '' ?>>
                            🟡 Sandbox (Testing)
                        </option>
                    </select>
                    <div class="aio-form-hint"><?= $lang['api_mode_hint'] ?? 'Use Sandbox for testing. Switch to Live for production.' ?></div>
                </div>

                <!-- Dynamic Credential Fields -->
                <div id="aio-credential-fields">
                    <?php if ($isEdit):
                        $fields = $credentialFields[$provider->slug] ?? [];
                        foreach ($fields as $f):
                            $val = $credentials[$f['key']] ?? '';
                            $masked = $val ? str_repeat('•', max(0, strlen($val) - 4)) . substr($val, -4) : '';
                    ?>
                    <div class="aio-form-group">
                        <label>
                            <?= htmlspecialchars($f['label']) ?>
                            <?php if (!empty($f['required'])): ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <input type="<?= $f['type'] === 'password' ? 'password' : 'text' ?>"
                               name="credentials[<?= $f['key'] ?>]"
                               class="aio-form-control"
                               value=""
                               placeholder="<?= $masked ? 'Current: ' . $masked . ' (leave blank to keep)' : '' ?>"
                               <?= $f['required'] && !$isEdit ? 'required' : '' ?> />
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Sort Order -->
                <div class="aio-form-group">
                    <label><?= $lang['sort_order'] ?? 'Sort Order' ?></label>
                    <input type="number" name="sort_order" class="aio-form-control" min="0"
                           value="<?= $isEdit ? (int)$provider->sort_order : 0 ?>" />
                    <div class="aio-form-hint"><?= $lang['sort_order_hint'] ?? 'Lower values appear first in lists.' ?></div>
                </div>

                <!-- Form Actions -->
                <div class="aio-form-actions">
                    <button type="button" class="aio-btn aio-btn-primary" onclick="saveProvider()">
                        <i class="fas fa-save"></i> <?= $isEdit ? ($lang['save_changes'] ?? 'Save Changes') : ($lang['add_provider'] ?? 'Add Provider') ?>
                    </button>
                    <?php if ($isEdit): ?>
                    <button type="button" class="aio-btn aio-btn-ghost" onclick="AioSSL.testProvider('<?= $provider->slug ?>')">
                        <i class="fas fa-satellite-dish"></i> <?= $lang['test_connection'] ?? 'Test Connection' ?>
                    </button>
                    <?php endif; ?>
                    <a href="<?= $moduleLink ?>&page=providers" class="aio-btn">
                        <?= $lang['cancel'] ?? 'Cancel' ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right: Info Panel -->
    <div>
        <!-- Provider Info -->
        <div class="aio-card" id="provider-info-card">
            <div class="aio-card-header">
                <span><i class="fas fa-info-circle"></i> <?= $lang['provider_info'] ?? 'Provider Information' ?></span>
            </div>
            <div class="aio-card-body" id="provider-info-content">
                <?php if ($isEdit): ?>
                    <?php $slug = $provider->slug; ?>
                <?php else: ?>
                    <div class="text-muted" style="text-align:center;padding:20px;">
                        <i class="fas fa-hand-pointer" style="font-size:24px;display:block;margin-bottom:8px;color:var(--aio-border)"></i>
                        <?= $lang['select_provider_info'] ?? 'Select a provider type to see details.' ?>
                    </div>
                <?php endif; ?>

                <?php if ($isEdit): ?>
                <div class="aio-info-grid" style="grid-template-columns:1fr;">
                    <div class="aio-info-item">
                        <label>Tier</label>
                        <?php $t = $providerTiers[$slug] ?? 'full'; ?>
                        <span class="aio-badge <?= $t === 'full' ? 'aio-tier-full' : 'aio-tier-limited' ?>">
                            <?= ucfirst($t) ?> Tier
                        </span>
                    </div>
                    <div class="aio-info-item">
                        <label>Authentication</label>
                        <span>
                            <?php
                            $authMethods = [
                                'nicsrs'      => 'API Token (form field)',
                                'gogetssl'    => 'Session-based (POST /auth/)',
                                'thesslstore' => 'JSON body (PartnerCode + AuthToken)',
                                'ssl2buy'     => 'JSON body (PartnerEmail + ApiKey)',
                            ];
                            echo $authMethods[$slug] ?? 'Unknown';
                            ?>
                        </span>
                    </div>
                    <div class="aio-info-item">
                        <label>Created</label>
                        <span><?= htmlspecialchars($provider->created_at ?? '—') ?></span>
                    </div>
                    <?php if ($provider->last_test): ?>
                    <div class="aio-info-item">
                        <label>Last Test</label>
                        <span>
                            <?php if ($provider->test_result): ?>
                                <i class="fas fa-check-circle" style="color:var(--aio-success)"></i>
                            <?php else: ?>
                                <i class="fas fa-times-circle" style="color:var(--aio-danger)"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($provider->last_test) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($provider->sync_error_count > 0): ?>
                    <div class="aio-info-item">
                        <label>Sync Errors</label>
                        <span class="aio-badge aio-badge-danger"><?= $provider->sync_error_count ?> errors</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Connection Result -->
        <?php if ($isEdit): ?>
        <div class="aio-card">
            <div class="aio-card-header">
                <span><i class="fas fa-satellite-dish"></i> <?= $lang['connection_test'] ?? 'Connection Test' ?></span>
            </div>
            <div class="aio-card-body">
                <div id="test-result-<?= $provider->slug ?>" class="aio-test-result"></div>
                <p class="aio-form-hint" style="margin-top:8px;">
                    <?= $lang['test_hint'] ?? 'Click "Test Connection" to verify API credentials are valid.' ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function saveProvider() {
    var $form = $('#aio-provider-form');
    AioSSL.ajax({
        page: 'providers',
        action: 'save',
        data: $form.serialize(),
        loadingMsg: 'Saving provider...',
        onSuccess: function(resp) {
            if (resp.redirect) {
                location.href = resp.redirect;
            } else {
                location.href = '<?= $moduleLink ?>&page=providers';
            }
        }
    });
}

function updateProviderInfo(slug) {
    var info = {
        nicsrs:      { tier: 'Full', auth: 'API Token (form field)', note: 'Supports Sectigo, GlobalSign, DigiCert products.' },
        gogetssl:    { tier: 'Full', auth: 'Session-based (POST /auth/)', note: 'Token auto-refreshes on 401.' },
        thesslstore: { tier: 'Full', auth: 'JSON body auth', note: 'Renew uses neworder with isRenewalOrder=true.' },
        ssl2buy:     { tier: 'Limited', auth: 'JSON body auth', note: 'No download/reissue/revoke. Config link only.' },
    };
    var $c = $('#provider-info-content');
    if (!slug || !info[slug]) {
        $c.html('<div class="text-muted" style="text-align:center;padding:20px;"><i class="fas fa-hand-pointer" style="font-size:24px;display:block;margin-bottom:8px;color:var(--aio-border)"></i>Select a provider type to see details.</div>');
        return;
    }
    var d = info[slug];
    $c.html(
        '<div class="aio-info-grid" style="grid-template-columns:1fr">' +
        '<div class="aio-info-item"><label>Tier</label><span class="aio-badge ' + (d.tier === 'Full' ? 'aio-tier-full' : 'aio-tier-limited') + '">' + d.tier + ' Tier</span></div>' +
        '<div class="aio-info-item"><label>Authentication</label><span>' + d.auth + '</span></div>' +
        '<div class="aio-info-item"><label>Note</label><span>' + d.note + '</span></div>' +
        '</div>'
    );
}
</script>