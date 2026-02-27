{* ══════════════════════════════════════════════════════════════════════
   FILE: view/complete.tpl — Issued certificate management
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/complete.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" data-serviceid="{$serviceid}">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-success">{$_LANG.issued|default:'Issued'}</span>
            <span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span>
        </div>
    </div>

    {* Progress — all complete *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_validated|default:'Validated'}</div></div>
        <div class="sslm-progress-step completed active"><div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div><div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div></div>
    </div>

    {* Certificate Details *}
    <div class="sslm-section">
        <div class="sslm-section-header"><h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_details|default:'Certificate Details'}</h3></div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.domain|default:'Domain'}</span><span class="sslm-info-value"><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.valid_from|default:'Valid From'}</span><span class="sslm-info-value">{$beginDate|escape:'html'}</span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.valid_until|default:'Valid Until'}</span><span class="sslm-info-value">{$endDate|escape:'html'}</span></div>
                <div class="sslm-info-item"><span class="sslm-info-label">{$_LANG.order_status|default:'Status'}</span><span class="sslm-info-value"><span class="sslm-badge sslm-badge-success">{$orderStatus|escape:'html'}</span></span></div>
            </div>
        </div>
    </div>

    {* Download Section *}
    {if $canDownload && $hasCert}
    <div class="sslm-section">
        <div class="sslm-section-header"><h3><i class="fas fa-download"></i> {$_LANG.download_certificate|default:'Download Certificate'}</h3></div>
        <div class="sslm-section-body">
            <div class="sslm-download-grid">
                <div class="sslm-download-card" onclick="SSLManager.download('apache')">
                    <div class="sslm-download-icon"><i class="fas fa-server"></i></div>
                    <div class="sslm-download-title">Apache / cPanel</div>
                    <div class="sslm-download-desc">.crt + .ca-bundle + .key</div>
                </div>
                <div class="sslm-download-card" onclick="SSLManager.download('nginx')">
                    <div class="sslm-download-icon"><i class="fab fa-linux"></i></div>
                    <div class="sslm-download-title">Nginx</div>
                    <div class="sslm-download-desc">.pem + .key</div>
                </div>
                <div class="sslm-download-card" onclick="SSLManager.download('all')">
                    <div class="sslm-download-icon" style="color:var(--sslm-success);"><i class="fas fa-file-archive"></i></div>
                    <div class="sslm-download-title">{$_LANG.all_formats|default:'All Formats'}</div>
                    <div class="sslm-download-desc">{$_LANG.complete_zip|default:'Complete ZIP'}</div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {* Management Actions *}
    <div class="sslm-actions">
        <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.refreshStatus()">
            <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh'}
        </button>
        {if $canReissue}
        <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=reissue" class="sslm-btn sslm-btn-outline">
            <i class="fas fa-redo-alt"></i> {$_LANG.reissue|default:'Reissue'}
        </a>
        {/if}
        {if $canRenew}
        <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLManager.renew()">
            <i class="fas fa-sync"></i> {$_LANG.renew|default:'Renew'}
        </button>
        {/if}
        {if $canRevoke}
        <button type="button" class="sslm-btn sslm-btn-danger sslm-btn-outline" onclick="SSLManager.confirmRevoke()">
            <i class="fas fa-ban"></i> {$_LANG.revoke|default:'Revoke'}
        </button>
        {/if}
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>