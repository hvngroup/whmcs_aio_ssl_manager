{* ══════════════════════════════════════════════════════════════════════
   FILE: view/pending.tpl — DCV management for pending certificates
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" data-serviceid="{$serviceid}">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-warning">{$_LANG.pending|default:'Pending'}</span>
            <span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span>
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
            <div class="sslm-status-desc">{$_LANG.validation_desc|default:'Complete domain validation below to receive your SSL certificate.'}</div>
        </div>
    </div>

    {* Domain Validation Status *}
    <div class="sslm-section">
        <div class="sslm-section-header"><h3><i class="fas fa-globe"></i> {$_LANG.domain_validation|default:'Domain Validation Status'}</h3></div>
        <div class="sslm-section-body">
            {if $dcvStatus}
            <table class="sslm-table">
                <thead><tr><th>{$_LANG.domain|default:'Domain'}</th><th>{$_LANG.method|default:'Method'}</th><th>{$_LANG.status|default:'Status'}</th><th>{$_LANG.action|default:'Action'}</th></tr></thead>
                <tbody>
                {foreach from=$dcvStatus item=dcv}
                <tr>
                    <td><code>{$dcv.domain|escape:'html'}</code></td>
                    <td><span class="sslm-badge">{$dcv.method|upper|default:'EMAIL'}</span></td>
                    <td><span class="sslm-badge sslm-badge-{if $dcv.status == 'validated'}success{else}warning{/if}">{$dcv.status|escape:'html'}</span></td>
                    <td>
                        {if $dcv.status != 'validated'}
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.resendDcv('{$dcv.domain|escape:'js'}')">
                            <i class="fas fa-redo"></i> {$_LANG.resend|default:'Resend'}
                        </button>
                        {else}
                        <span class="sslm-text-success"><i class="fas fa-check"></i></span>
                        {/if}
                    </td>
                </tr>
                {/foreach}
                </tbody>
            </table>
            {else}
            <p class="sslm-hint">{$_LANG.dcv_method_used|default:'DCV Method'}: <strong>{$dcvMethod|upper|default:'EMAIL'}</strong></p>
            <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLManager.resendDcv()">
                <i class="fas fa-redo"></i> {$_LANG.resend_validation|default:'Resend Validation'}
            </button>
            {/if}
        </div>
    </div>

    {* Actions *}
    <div class="sslm-actions">
        <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.refreshStatus()">
            <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
        </button>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>