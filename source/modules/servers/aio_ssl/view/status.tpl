{* ══════════════════════════════════════════════════════════════════════
   FILE: view/status.tpl — Generic status display (Cancelled/Expired/Revoked)
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/status.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
    </div>

    <div class="sslm-status-card">
        <div class="sslm-status-icon {if $orderStatus == 'Expired'}warning{else}danger{/if}">
            <i class="fas fa-{if $orderStatus == 'Expired'}hourglass-end{elseif $orderStatus == 'Revoked'}ban{else}times-circle{/if}"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$orderStatus|escape:'html'}</div>
            <div class="sslm-status-desc">
                {if $orderStatus == 'Cancelled'}{$_LANG.cancelled_desc|default:'This certificate order has been cancelled.'}
                {elseif $orderStatus == 'Expired'}{$_LANG.expired_desc|default:'This certificate has expired. Please renew to maintain secure connections.'}
                {elseif $orderStatus == 'Revoked'}{$_LANG.revoked_desc|default:'This certificate has been revoked and is no longer valid.'}
                {else}{$_LANG.status_desc|default:'Certificate status'}: {$orderStatus|escape:'html'}
                {/if}
            </div>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.domain|default:'Domain'}</span><span class="sslm-info-value"><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.provider|default:'Provider'}</span><span class="sslm-info-value"><span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span></span></div>
                {if $endDate}<div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.expired_on|default:'Expired On'}</span><span class="sslm-info-value">{$endDate|escape:'html'}</span></div>{/if}
            </div>
        </div>
    </div>

    <div class="sslm-actions">
        <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.refreshStatus()">
            <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh Status'}
        </button>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>