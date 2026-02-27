{* ══════════════════════════════════════════════════════════════════════
   FILE: view/status.tpl — Cancelled / Expired / Revoked / Rejected
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
    </div>

    <div class="sslm-status-card">
        <div class="sslm-status-icon {if $orderStatus == 'Expired'}warning{else}danger{/if}">
            <i class="fas fa-{if $orderStatus == 'Expired'}hourglass-end{elseif $orderStatus == 'Revoked'}ban{else}times-circle{/if}"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">
                {if $orderStatus == 'Cancelled'}{$_LANG.cancelled|default:'Cancelled'}
                {elseif $orderStatus == 'Expired'}{$_LANG.expired|default:'Expired'}
                {elseif $orderStatus == 'Revoked'}{$_LANG.revoked|default:'Revoked'}
                {elseif $orderStatus == 'Rejected'}{$_LANG.rejected|default:'Rejected'}
                {else}{$orderStatus|escape:'html'}
                {/if}
            </div>
            <div class="sslm-status-desc">
                {if $orderStatus == 'Cancelled'}{$_LANG.cancelled_desc|default:'This certificate order has been cancelled.'}
                {elseif $orderStatus == 'Expired'}{$_LANG.expired_desc|default:'This certificate has expired. Please renew or order a new certificate.'}
                {elseif $orderStatus == 'Revoked'}{$_LANG.revoked_desc|default:'This certificate has been revoked and is no longer valid.'}
                {elseif $orderStatus == 'Rejected'}{$_LANG.rejected_desc|default:'This certificate request was rejected by the Certificate Authority.'}
                {else}{$_LANG.status_unknown_desc|default:'Please contact support for assistance.'}
                {/if}
            </div>
        </div>
    </div>

    <div class="sslm-section">
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
                {if $endDate}
                <div class="sslm-info-item">
                    <label>{$_LANG.valid_until|default:'Valid Until'}</label>
                    <span>{$endDate|escape:'html'}</span>
                </div>
                {/if}
            </div>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body">
            <button type="button" class="sslm-btn sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
            </button>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>