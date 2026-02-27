{* ══════════════════════════════════════════════════════════════════════
   FILE: view/migrated.tpl — Legacy vendor cert (read-only)
   Shows vendor info since this IS a legacy order
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-exchange-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-badge sslm-badge-{if $orderStatus == 'Completed' || $orderStatus == 'Active' || $orderStatus == 'Issued'}success{else}warning{/if}">{$orderStatus|escape:'html'}</span>
        </div>
    </div>

    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>{$_LANG.migrated_notice|default:'Legacy Certificate'}</strong>
            <p>{$_LANG.migrated_desc|default:'This certificate was provisioned by a previous module. It is shown here in read-only mode. Contact support if you need to manage or renew this certificate.'}</p>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.domain|default:'Domain'}</label>
                    <span><code>{$domain|escape:'html'}</code></span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge">{$orderStatus|escape:'html'}</span>
                </div>
                {if $beginDate}
                <div class="sslm-info-item">
                    <label>{$_LANG.valid_from|default:'Valid From'}</label>
                    <span>{$beginDate|escape:'html'}</span>
                </div>
                {/if}
                {if $endDate}
                <div class="sslm-info-item">
                    <label>{$_LANG.valid_until|default:'Valid Until'}</label>
                    <span>{$endDate|escape:'html'}</span>
                </div>
                {/if}
            </div>
        </div>
    </div>
</div>
