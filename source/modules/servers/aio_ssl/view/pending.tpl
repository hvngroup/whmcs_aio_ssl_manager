{* ══════════════════════════════════════════════════════════════════════
   FILE: view/pending.tpl — Pending / DCV management
   Adapted from NicSRS ref: manage.tpl
   NO provider name shown
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-warning">{$_LANG.pending_validation|default:'Pending Validation'}</span>
        </div>
    </div>

    {* Progress *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div></div>
        <div class="sslm-progress-step active"><div class="sslm-progress-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div></div>
        <div class="sslm-progress-step"><div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div><div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div></div>
    </div>

    {* Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon warning"><i class="fas fa-clock"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.awaiting_validation|default:'Awaiting Domain Validation'}</div>
            <div class="sslm-status-desc">{$_LANG.validation_desc|default:'Your certificate request has been submitted. Complete the domain validation below to receive your SSL certificate.'}</div>
        </div>
    </div>

    {* Order Info *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.order_info|default:'Order Information'}</h3>
            <div class="sslm-card-header-actions">
                <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh'}
                </button>
            </div>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.domain|default:'Domain'}</label>
                    <span><code>{$domain|escape:'html'}</code></span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-badge-warning">{$orderStatus|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.dcv_method|default:'Validation Method'}</label>
                    <span class="sslm-badge">{$dcvMethod|upper|default:'EMAIL'}</span>
                </div>
            </div>
        </div>
    </div>

    {* DCV Domain Status Table *}
    {if $dcvStatus}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-globe"></i> {$_LANG.domain_validation|default:'Domain Validation Status'}</h3>
        </div>
        <div class="sslm-section-body">
            <table class="sslm-table">
                <thead><tr>
                    <th>{$_LANG.domain|default:'Domain'}</th>
                    <th>{$_LANG.method|default:'Method'}</th>
                    <th>{$_LANG.status|default:'Status'}</th>
                    <th>{$_LANG.action|default:'Action'}</th>
                </tr></thead>
                <tbody>
                {foreach from=$dcvStatus item=dcv}
                <tr>
                    <td><code>{$dcv.domain|escape:'html'}</code></td>
                    <td><span class="sslm-badge">{$dcv.method|upper|default:'EMAIL'}</span></td>
                    <td>
                        {if $dcv.status == 'validated' || $dcv.status == 'verified'}
                            <span class="sslm-badge sslm-badge-success"><i class="fas fa-check"></i> {$_LANG.verified|default:'Verified'}</span>
                        {else}
                            <span class="sslm-badge sslm-badge-warning">{$_LANG.un_verified|default:'Unverified'}</span>
                        {/if}
                    </td>
                    <td>
                        {if $dcv.status != 'validated' && $dcv.status != 'verified'}
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.resendDcv('{$dcv.domain|escape:'js'}')">
                            <i class="fas fa-redo"></i> {$_LANG.resend|default:'Resend'}
                        </button>
                        {/if}
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
    {else}
    {* No per-domain status — show simple resend *}
    <div class="sslm-section">
        <div class="sslm-section-body" style="text-align:center;padding:30px;">
            <p>{$_LANG.dcv_pending_desc|default:'Please complete domain validation to proceed.'}</p>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.resendDcv()">
                <i class="fas fa-envelope"></i> {$_LANG.resend_validation|default:'Resend Validation Email'}
            </button>
        </div>
    </div>
    {/if}

    {* Cancel Order *}
    <div class="sslm-section">
        <div class="sslm-section-body">
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button type="button" class="sslm-btn sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
                </button>
                <button type="button" class="sslm-btn sslm-btn-danger sslm-btn-outline" onclick="SSLManager.confirmCancel()">
                    <i class="fas fa-times"></i> {$_LANG.cancel_order|default:'Cancel Order'}
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>
