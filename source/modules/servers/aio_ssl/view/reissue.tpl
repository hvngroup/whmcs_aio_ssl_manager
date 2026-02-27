{* ══════════════════════════════════════════════════════════════════════
   FILE: view/reissue.tpl — Reissue Certificate
   Includes current cert info card + warning
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-redo-alt"></i> {$_LANG.reissue_certificate|default:'Reissue Certificate'}</h2>
        <div class="sslm-header-info"><span class="sslm-product-name">{$productCode|escape:'html'}</span></div>
    </div>

    {* Progress *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_issued|default:'Current Cert'}</div></div>
        <div class="sslm-progress-step active"><div class="sslm-progress-icon"><i class="fas fa-redo"></i></div><div class="sslm-progress-label">{$_LANG.step_reissue|default:'Reissue'}</div></div>
        <div class="sslm-progress-step"><div class="sslm-progress-icon"><i class="fas fa-check-circle"></i></div><div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div></div>
        <div class="sslm-progress-step"><div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div><div class="sslm-progress-label">{$_LANG.step_new_cert|default:'New Certificate'}</div></div>
    </div>

    {* Warning *}
    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>{$_LANG.reissue_warning_title|default:'Important Information'}</strong>
            <p style="margin:8px 0 0 0;">{$_LANG.reissue_warning|default:'Reissuing will generate a new certificate. The previous certificate will remain valid until it expires or is revoked.'}</p>
        </div>
    </div>

    {* Current Certificate Info *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-certificate"></i> {$_LANG.current_certificate|default:'Current Certificate'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.domain|default:'Domain'}</label>
                    <span><code>{$domain|escape:'html'}</code></span>
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

    {* Reissue Form *}
    <form id="reissue-form" onsubmit="return false;">
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">1</span> {$_LANG.csr_config|default:'New CSR'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;"><i class="fas fa-lightbulb"></i> {$_LANG.reissue_csr_guide|default:'A new CSR is required for reissue. You can auto-generate one or paste your own.'}</p>
                <div class="sslm-form-group">
                    <label class="sslm-toggle">
                        <input type="checkbox" id="reissueManualCsr" checked>
                        <span class="sslm-toggle-slider"></span>
                        <span class="sslm-toggle-label">{$_LANG.is_manual_csr|default:'I have my own CSR'}</span>
                    </label>
                </div>
                <div id="reissueAutoGen" style="display:none;">
                    <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.generateCsrForReissue()">
                        <i class="fas fa-key"></i> {$_LANG.generate_csr|default:'Generate CSR'}
                    </button>
                </div>
                <div id="reissueCsrSection">
                    <div class="sslm-form-group">
                        <label>{$_LANG.new_csr|default:'New CSR'} <span class="required">*</span></label>
                        <textarea id="reissue-csr" name="csr" class="sslm-textarea sslm-code" rows="8" placeholder="-----BEGIN CERTIFICATE REQUEST-----"></textarea>
                    </div>
                </div>
                <input type="hidden" id="reissuePrivateKey" name="privateKey" />
            </div>
        </div>

        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">2</span> {$_LANG.dcv_method|default:'Validation Method'}</h3>
            </div>
            <div class="sslm-section-body">
                <div class="sslm-form-group">
                    <select id="reissue-dcv" name="dcvMethod" class="sslm-select">
                        <option value="email">{$_LANG.email_validation|default:'Email Validation'}</option>
                        <option value="http">{$_LANG.http_file|default:'HTTP File Validation'}</option>
                        <option value="https">{$_LANG.https_file|default:'HTTPS File Validation'}</option>
                        <option value="dns">{$_LANG.dns_cname|default:'DNS CNAME Validation'}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="sslm-form-actions">
            <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sslm-btn sslm-btn-secondary"><i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}</a>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.submitReissue()"><i class="fas fa-paper-plane"></i> {$_LANG.submit_reissue|default:'Submit Reissue'}</button>
        </div>
    </form>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<input type="hidden" id="h-domain" value="{$domain|escape:'html'}" />
<script>
document.getElementById('reissueManualCsr').addEventListener('change', function() {
    document.getElementById('reissueCsrSection').style.display = this.checked ? '' : 'none';
    document.getElementById('reissueAutoGen').style.display = this.checked ? 'none' : '';
});
</script>
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>