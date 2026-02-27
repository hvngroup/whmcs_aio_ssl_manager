{* ══════════════════════════════════════════════════════════════════════
   FILE: view/migrated.tpl — Legacy vendor cert (read-only view)
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/migrated.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-exchange-alt"></i> {$_LANG.legacy_certificate|default:'Legacy Certificate'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-{if $orderStatus == 'Completed' || $orderStatus == 'Active'}success{else}warning{/if}">{$orderStatus|escape:'html'}</span>
        </div>
    </div>

    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>{$_LANG.migrated_notice|default:'This certificate was managed by a different module.'}</strong>
            <p>{$_LANG.migrated_desc|default:'This is a read-only view. The certificate was originally managed by'} <code>{$legacyModule|escape:'html'}</code>.
            {$_LANG.migrated_contact|default:'Contact your administrator if you need to manage this certificate.'}</p>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.domain|default:'Domain'}</span><span class="sslm-info-value"><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.original_module|default:'Original Module'}</span><span class="sslm-info-value"><code>{$legacyModule|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.status|default:'Status'}</span><span class="sslm-info-value"><span class="sslm-badge">{$orderStatus|escape:'html'}</span></span></div>
                {if $beginDate}<div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.valid_from|default:'Valid From'}</span><span class="sslm-info-value">{$beginDate|escape:'html'}</span></div>{/if}
                {if $endDate}<div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.valid_until|default:'Valid Until'}</span><span class="sslm-info-value">{$endDate|escape:'html'}</span></div>{/if}
            </div>
        </div>
    </div>
</div>