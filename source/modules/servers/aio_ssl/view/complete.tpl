{* ══════════════════════════════════════════════════════════════════════
   FILE: view/complete.tpl — Issued certificate
   Adapted from NicSRS ref: complete.tpl
   NO provider name shown. Capability-aware buttons.
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-success"><i class="fas fa-check-circle"></i> {$_LANG.issued|default:'Issued'}</span>
        </div>
    </div>

    {* Progress — all complete *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_validated|default:'Validated'}</div></div>
        <div class="sslm-progress-step completed active"><div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div><div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div></div>
    </div>

    {* Success Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon success"><i class="fas fa-certificate"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.certificate_ready|default:'Your Certificate is Ready!'}</div>
            <div class="sslm-status-desc">{$_LANG.certificate_ready_desc|default:'Your SSL certificate has been issued and is ready for installation. Download the certificate files below.'}</div>
        </div>
    </div>

    {* Certificate Info *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
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
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-badge-success"><i class="fas fa-check-circle"></i> {$_LANG.active|default:'Active'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    <span class="sslm-code">{$remoteId|escape:'html'|default:'N/A'}</span>
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

    {* Download Section *}
    {if $canDownload && $hasCert}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-download"></i> {$_LANG.download_certificate|default:'Download Certificate'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-download-grid">
                <div class="sslm-download-card" onclick="SSLManager.download('apache')">
                    <div class="sslm-download-icon"><i class="fas fa-server"></i></div>
                    <div class="sslm-download-title">Apache / cPanel</div>
                    <div class="sslm-download-desc">.crt + .ca-bundle + .key</div>
                    <button type="button" class="sslm-btn sslm-btn-primary sslm-btn-sm"><i class="fas fa-download"></i> {$_LANG.download|default:'Download'}</button>
                </div>
                <div class="sslm-download-card" onclick="SSLManager.download('nginx')">
                    <div class="sslm-download-icon"><i class="fab fa-linux"></i></div>
                    <div class="sslm-download-title">Nginx</div>
                    <div class="sslm-download-desc">.pem + .key</div>
                    <button type="button" class="sslm-btn sslm-btn-primary sslm-btn-sm"><i class="fas fa-download"></i> {$_LANG.download|default:'Download'}</button>
                </div>
                <div class="sslm-download-card" onclick="SSLManager.download('all')">
                    <div class="sslm-download-icon" style="color:var(--sslm-success);"><i class="fas fa-file-archive"></i></div>
                    <div class="sslm-download-title">{$_LANG.all_formats|default:'All Formats'}</div>
                    <div class="sslm-download-desc">{$_LANG.complete_package|default:'Complete ZIP package'}</div>
                    <button type="button" class="sslm-btn sslm-btn-success sslm-btn-sm"><i class="fas fa-download"></i> {$_LANG.download_all|default:'Download All'}</button>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {* Certificate Content (Collapsible) *}
    {if $hasCert}
    <div class="sslm-section sslm-collapsible collapsed">
        <div class="sslm-section-header" onclick="this.parentElement.classList.toggle('collapsed')" style="cursor:pointer;">
            <h3>
                <span><i class="fas fa-file-code"></i> {$_LANG.certificate_content|default:'Certificate Content'}</span>
                <i class="fas fa-chevron-down sslm-collapse-icon"></i>
            </h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-cert-display">
                <div class="sslm-cert-display-header">
                    <span>{$_LANG.ssl_certificate|default:'SSL Certificate'}</span>
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(document.getElementById('certContent').textContent)">
                        <i class="fas fa-copy"></i> {$_LANG.copy|default:'Copy'}
                    </button>
                </div>
                <div class="sslm-cert-display-body">
                    <pre id="certContent">{$configData.cert|escape:'html'}</pre>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {* Actions — capability-aware *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-cogs"></i> {$_LANG.certificate_actions|default:'Certificate Actions'}</h3>
        </div>
        <div class="sslm-section-body">
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button type="button" class="sslm-btn sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
                </button>
                {if $canReissue}
                <a href="clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=reissue" class="sslm-btn sslm-btn-outline">
                    <i class="fas fa-redo"></i> {$_LANG.reissue|default:'Reissue Certificate'}
                </a>
                {/if}
                {if $canRevoke}
                <button type="button" class="sslm-btn sslm-btn-danger sslm-btn-outline" onclick="SSLManager.confirmRevoke()">
                    <i class="fas fa-ban"></i> {$_LANG.revoke|default:'Revoke Certificate'}
                </button>
                {/if}
            </div>
        </div>
    </div>

    {* Help *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.installation_help|default:'Installation Help'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <h4><i class="fas fa-server"></i> Apache</h4>
                    <p>{$_LANG.apache_help_desc|default:'Upload .crt, .ca-bundle, and .key files. Update your VirtualHost configuration.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-cube"></i> Nginx</h4>
                    <p>{$_LANG.nginx_help_desc|default:'Use the .pem file (combined cert) with your .key file in server block.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-life-ring"></i> {$_LANG.help_installation_title|default:'SSL Installation Service'}</h4>
                    <p>{$_LANG.help_installation_desc|default:'Our experts can install your SSL certificate for you quickly and securely.'}</p>
                    <a href="submitticket.php" class="sslm-btn sslm-btn-sm sslm-btn-outline"><i class="fas fa-ticket-alt"></i> {$_LANG.open_ticket|default:'Open a Ticket'}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>