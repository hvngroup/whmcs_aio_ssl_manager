{* ══════════════════════════════════════════════════════════════════════
   FILE: view/limited_provider.tpl — SSL2Buy config link
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info"><span class="sslm-product-name">{$productCode|escape:'html'}</span></div>
    </div>

    <div class="sslm-status-card">
        <div class="sslm-status-icon info"><i class="fas fa-external-link-alt"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.manage_externally|default:'Configure at Provider Portal'}</div>
            <div class="sslm-status-desc">{$_LANG.limited_desc|default:'Your certificate needs to be configured through the provider portal. Click the button below to access it.'}</div>
        </div>
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body" style="text-align:center;padding:30px;">
            {if $configLink}
            <a href="{$configLink|escape:'html'}" target="_blank" class="sslm-btn sslm-btn-primary sslm-btn-lg">
                <i class="fas fa-external-link-alt"></i> {$_LANG.open_portal|default:'Open Configuration Page'}
            </a>
            {if $pin}
            <div style="margin-top:15px;"><span class="sslm-form-hint">{$_LANG.your_pin|default:'Your PIN'}:</span> <code class="sslm-pin">{$pin|escape:'html'}</code></div>
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
                <div class="sslm-info-item"><label>{$_LANG.domain|default:'Domain'}</label><span><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><label>{$_LANG.status|default:'Status'}</label><span class="sslm-badge">{$orderStatus|escape:'html'}</span></div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>