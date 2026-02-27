{* ══════════════════════════════════════════════════════════════════════
   FILE: view/limited_provider.tpl — SSL2Buy config link view
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/limited_provider.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-warning">{$_LANG.limited_api|default:'Limited API'}</span>
        </div>
    </div>

    <div class="sslm-alert sslm-alert-info">
        <i class="fas fa-external-link-alt"></i>
        <div>
            <strong>{$_LANG.manage_externally|default:'Manage at Provider Portal'}</strong>
            <p>{$_LANG.limited_desc|default:'This provider uses a limited API. Please complete configuration and manage your certificate at the provider portal.'}</p>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body" style="text-align:center; padding:30px;">
            {if $configLink}
            <a href="{$configLink|escape:'html'}" target="_blank" class="sslm-btn sslm-btn-primary sslm-btn-lg">
                <i class="fas fa-external-link-alt"></i> {$_LANG.open_portal|default:'Open Provider Portal'}
            </a>
            {if $pin}
            <div style="margin-top:15px;">
                <span class="sslm-hint">{$_LANG.your_pin|default:'Your PIN'}:</span>
                <code class="sslm-pin">{$pin|escape:'html'}</code>
            </div>
            {/if}
            {else}
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.getConfigLink()">
                <i class="fas fa-link"></i> {$_LANG.get_config_link|default:'Get Configuration Link'}
            </button>
            {/if}
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.domain|default:'Domain'}</span><span class="sslm-info-value"><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.status|default:'Status'}</span><span class="sslm-info-value"><span class="sslm-badge">{$orderStatus|escape:'html'}</span></span></div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>