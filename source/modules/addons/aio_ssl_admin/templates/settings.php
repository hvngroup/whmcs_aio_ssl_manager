<?php
/**
 * Settings Template — Admin Addon (PHP Template)
 *
 * Variables passed via extract():
 *   $settings     - array [key => value]
 *   $syncStatus   - array [last_sync, providers[], errors[]]
 *   $moduleLink   - string
 *   $lang         - array
 *   $csrfToken    - string
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

if (!defined('WHMCS')) die('Access denied.');

// Helper to get setting value
$s = function($key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

// Current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
$tabs = [
    'general'       => ['icon' => 'fa-cog',        'label' => $lang['general'] ?? 'General'],
    'sync'          => ['icon' => 'fa-sync-alt',    'label' => $lang['auto_sync'] ?? 'Auto-Sync'],
    'notifications' => ['icon' => 'fa-bell',        'label' => $lang['notifications'] ?? 'Notifications'],
    'currency'      => ['icon' => 'fa-dollar-sign', 'label' => $lang['currency'] ?? 'Currency'],
];
?>

<!-- Page Header -->
<div class="aio-page-header">
    <h3 class="aio-page-title">
        <i class="fas fa-cog"></i> <?= $lang['settings_title'] ?? 'Settings' ?>
    </h3>
</div>

<!-- Settings Sub-Tabs -->
<div class="aio-sub-tabs">
    <?php foreach ($tabs as $key => $t): ?>
    <button class="<?= $tab === $key ? 'active' : '' ?>"
            onclick="location.href='<?= $moduleLink ?>&page=settings&tab=<?= $key ?>'">
        <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
    </button>
    <?php endforeach; ?>
</div>

<form id="aio-settings-form">
    <input type="hidden" name="token" value="<?= $csrfToken ?>" />
    <input type="hidden" name="tab" value="<?= $tab ?>" />

<?php if ($tab === 'general'): ?>
<!-- ══════════ GENERAL TAB ══════════ -->
<div class="aio-grid-2-equal">
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-desktop"></i> <?= $lang['display_settings'] ?? 'Display Settings' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-form-group">
                <label><?= $lang['items_per_page'] ?? 'Items Per Page' ?></label>
                <select name="items_per_page" class="aio-form-control">
                    <?php foreach ([10, 20, 25, 50, 100] as $v): ?>
                    <option value="<?= $v ?>" <?= $s('items_per_page', '25') == $v ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="aio-form-group">
                <label><?= $lang['date_format'] ?? 'Date Format' ?></label>
                <select name="date_format" class="aio-form-control">
                    <?php
                    $fmts = ['Y-m-d' => '2026-02-23', 'd/m/Y' => '23/02/2026', 'm/d/Y' => '02/23/2026', 'd M Y' => '23 Feb 2026'];
                    foreach ($fmts as $fmt => $ex):
                    ?>
                    <option value="<?= $fmt ?>" <?= $s('date_format', 'Y-m-d') === $fmt ? 'selected' : '' ?>><?= $ex ?> (<?= $fmt ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-shield-alt"></i> <?= $lang['security'] ?? 'Security' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-alert aio-alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <?= $lang['security_info'] ?? 'API credentials are encrypted using AES-256-CBC with HMAC integrity verification. Encryption key is derived from WHMCS cc_encryption_hash.' ?>
                </div>
            </div>
            <div class="aio-info-item">
                <label>Encryption Algorithm</label>
                <span>AES-256-CBC + HMAC-SHA256</span>
            </div>
            <div class="aio-info-item">
                <label>Module Version</label>
                <span><?= AIO_SSL_VERSION ?></span>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'sync'): ?>
<!-- ══════════ AUTO-SYNC TAB ══════════ -->
<div class="aio-grid-2-equal">
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-sync-alt"></i> <?= $lang['sync_config'] ?? 'Sync Configuration' ?></span>
        </div>
        <div class="aio-card-body">
            <div class="aio-form-group">
                <label><?= $lang['sync_enabled'] ?? 'Enable Auto-Sync' ?></label>
                <label class="aio-switch">
                    <input type="checkbox" name="sync_enabled" value="1" <?= $s('sync_enabled', '1') === '1' ? 'checked' : '' ?> />
                    <span class="slider"></span>
                </label>
                <div class="aio-form-hint"><?= $lang['sync_enabled_hint'] ?? 'Enable automatic synchronization via WHMCS cron.' ?></div>
            </div>
            <div class="aio-form-group">
                <label><?= $lang['status_sync_interval'] ?? 'Certificate Status Sync Interval' ?></label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" name="sync_status_interval" class="aio-form-control" style="width:100px"
                           value="<?= htmlspecialchars($s('sync_status_interval', '6')) ?>" min="1" max="72" />
                    <span class="aio-form-hint">hours</span>
                </div>
            </div>
            <div class="aio-form-group">
                <label><?= $lang['product_sync_interval'] ?? 'Product Catalog Sync Interval' ?></label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" name="sync_product_interval" class="aio-form-control" style="width:100px"
                           value="<?= htmlspecialchars($s('sync_product_interval', '24')) ?>" min="1" max="168" />
                    <span class="aio-form-hint">hours</span>
                </div>
            </div>
            <div class="aio-form-group">
                <label><?= $lang['sync_batch_size'] ?? 'Sync Batch Size' ?></label>
                <input type="number" name="sync_batch_size" class="aio-form-control" style="width:100px"
                       value="<?= htmlspecialchars($s('sync_batch_size', '50')) ?>" min="10" max="200" />
                <div class="aio-form-hint"><?= $lang['batch_hint'] ?? 'Number of orders to process per sync cycle.' ?></div>
            </div>
        </div>
    </div>

    <!-- Manual Sync Panel -->
    <div class="aio-card">
        <div class="aio-card-header">
            <span><i class="fas fa-play-circle"></i> <?= $lang['manual_sync'] ?? 'Manual Sync' ?></span>
        </div>
        <div class="aio-card-body">
            <p style="font-size:12px;color:var(--aio-text-secondary);margin-bottom:16px;">
                <?= $lang['manual_sync_desc'] ?? 'Trigger synchronization manually. This runs outside the normal cron schedule.' ?>
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button type="button" class="aio-btn aio-btn-primary" onclick="AioSSL.manualSync('products')" style="justify-content:center;">
                    <i class="fas fa-cube"></i> <?= $lang['sync_products'] ?? 'Sync Product Catalogs' ?>
                </button>
                <button type="button" class="aio-btn aio-btn-ghost" onclick="AioSSL.manualSync('status')" style="justify-content:center;">
                    <i class="fas fa-certificate"></i> <?= $lang['sync_statuses'] ?? 'Sync Certificate Statuses' ?>
                </button>
                <button type="button" class="aio-btn" onclick="AioSSL.manualSync('all')" style="justify-content:center;">
                    <i class="fas fa-sync-alt"></i> <?= $lang['sync_all'] ?? 'Run Full Sync' ?>
                </button>
            </div>

            <?php if (!empty($syncStatus)): ?>
            <div style="margin-top:16px;border-top:1px solid var(--aio-border-light);padding-top:12px;">
                <div style="font-size:12px;font-weight:600;margin-bottom:8px;"><?= $lang['sync_status'] ?? 'Last Sync Status' ?></div>
                <?php if (!empty($syncStatus['providers'])):
                    foreach ($syncStatus['providers'] as $slug => $ps): ?>
                <div class="aio-health-item" style="margin-bottom:6px;">
                    <div class="status-dot <?= !empty($ps['success']) ? 'ok' : 'err' ?>"></div>
                    <div>
                        <div class="provider-name"><?= htmlspecialchars(ucfirst($slug)) ?></div>
                        <div class="provider-info"><?= htmlspecialchars($ps['last_sync'] ?? 'Never') ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- ══════════ NOTIFICATIONS TAB ══════════ -->
<div class="aio-grid-2-equal">
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-bell"></i> <?= $lang['email_notifications'] ?? 'Email Notifications' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-form-group">
                <label><?= $lang['admin_email'] ?? 'Admin Email' ?></label>
                <input type="email" name="notify_admin_email" class="aio-form-control"
                       value="<?= htmlspecialchars($s('notify_admin_email', '')) ?>"
                       placeholder="admin@hvn.vn" />
                <div class="aio-form-hint"><?= $lang['admin_email_hint'] ?? 'Leave blank to use WHMCS default admin email.' ?></div>
            </div>
            <?php
            $toggles = [
                'notify_issuance'     => ['label' => $lang['notify_issuance'] ?? 'Certificate Issuance',     'desc' => 'Email when certificate is issued'],
                'notify_expiry'       => ['label' => $lang['notify_expiry'] ?? 'Certificate Expiry Warning', 'desc' => 'Email when certificate is expiring'],
                'notify_sync_errors'  => ['label' => $lang['notify_sync_errors'] ?? 'Sync Errors',          'desc' => 'Email when sync errors exceed threshold'],
                'notify_price_changes'=> ['label' => $lang['notify_price_changes'] ?? 'Price Changes',      'desc' => 'Email when provider prices change'],
            ];
            foreach ($toggles as $key => $info):
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--aio-border-light);">
                <div>
                    <div style="font-size:13px;font-weight:500;"><?= $info['label'] ?></div>
                    <div style="font-size:11px;color:var(--aio-text-secondary);"><?= $info['desc'] ?></div>
                </div>
                <label class="aio-switch">
                    <input type="checkbox" name="<?= $key ?>" value="1" <?= $s($key, '1') === '1' ? 'checked' : '' ?> />
                    <span class="slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-exclamation-triangle"></i> <?= $lang['expiry_settings'] ?? 'Expiry Warning Settings' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-form-group">
                <label><?= $lang['expiry_warning_days'] ?? 'Warning Threshold (days before expiry)' ?></label>
                <input type="number" name="notify_expiry_days" class="aio-form-control" style="width:100px"
                       value="<?= htmlspecialchars($s('notify_expiry_days', '30')) ?>" min="1" max="90" />
            </div>
            <div class="aio-alert aio-alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    Notifications use WHMCS <code>SendAdminEmail</code> Local API — not PHP <code>mail()</code>.
                    Emails are formatted with provider badges and urgency indicators.
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'currency'): ?>
<!-- ══════════ CURRENCY TAB ══════════ -->
<div class="aio-grid-2-equal">
    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-dollar-sign"></i> <?= $lang['currency_config'] ?? 'Currency Configuration' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-form-group">
                <label><?= $lang['currency_display'] ?? 'Display Currency' ?></label>
                <select name="currency_display" class="aio-form-control">
                    <option value="usd" <?= $s('currency_display', 'usd') === 'usd' ? 'selected' : '' ?>>USD Only</option>
                    <option value="vnd" <?= $s('currency_display') === 'vnd' ? 'selected' : '' ?>>VND Only</option>
                    <option value="both" <?= $s('currency_display') === 'both' ? 'selected' : '' ?>>Both (USD + VND)</option>
                </select>
            </div>
            <div class="aio-form-group">
                <label><?= $lang['exchange_rate'] ?? 'USD → VND Exchange Rate' ?></label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-weight:600;">1 USD =</span>
                    <input type="number" name="currency_usd_vnd_rate" class="aio-form-control" style="width:140px"
                           value="<?= htmlspecialchars($s('currency_usd_vnd_rate', '25000')) ?>" min="1" step="100" />
                    <span style="font-weight:600;">VND</span>
                </div>
                <div class="aio-form-hint"><?= $lang['rate_hint'] ?? 'Used for price display. Updated automatically if API integration is enabled.' ?></div>
            </div>
        </div>
    </div>

    <div class="aio-card">
        <div class="aio-card-header"><span><i class="fas fa-exchange-alt"></i> <?= $lang['auto_rate'] ?? 'Automatic Rate Update' ?></span></div>
        <div class="aio-card-body">
            <div class="aio-alert aio-alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    Exchange rate can be updated automatically via
                    <a href="https://exchangerate-api.com" target="_blank" class="aio-link">exchangerate-api.com</a>.
                    This is a planned feature for a future update.
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Save Button (All Tabs) -->
<div style="padding-top:16px;">
    <button type="button" class="aio-btn aio-btn-primary aio-btn-lg" onclick="AioSSL.saveSettings('#aio-settings-form')">
        <i class="fas fa-save"></i> <?= $lang['save_settings'] ?? 'Save Settings' ?>
    </button>
</div>

</form>