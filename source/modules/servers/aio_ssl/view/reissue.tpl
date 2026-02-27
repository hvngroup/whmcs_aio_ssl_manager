{* ══════════════════════════════════════════════════════════════════════
   FILE: view/reissue.tpl — Reissue certificate form
   Adapted from NicSRS ref: reissue.tpl
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-redo-alt"></i> {$_LANG.reissue_certificate|default:'Reissue Certificate'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
        </div>
    </div>

    <div class="sslm-alert sslm-alert-info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>{$_LANG.reissue_info|default:'About Reissuing'}</strong>
            <p>{$_LANG.reissue_desc|default:'Reissuing replaces your current certificate with a new one. A new CSR is required. The old certificate will be revoked after the new one is issued.'}</p>
        </div>
    </div>

    <form id="reissue-form" onsubmit="return false;">
        {* CSR Section *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">1</span> {$_LANG.csr_config|default:'CSR Configuration'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.reissue_csr_guide|default:'A new CSR is required for reissue. You can auto-generate one or paste your own.'}
                </p>

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
                        <textarea id="reissue-csr" name="csr" class="sslm-textarea sslm-code" rows="8"
                                  placeholder="-----BEGIN CERTIFICATE REQUEST-----"></textarea>
                    </div>
                </div>
                <input type="hidden" id="reissuePrivateKey" name="privateKey" />
            </div>
        </div>

        {* DCV Method *}
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
            <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sslm-btn sslm-btn-secondary">
                <i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}
            </a>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.submitReissue()">
                <i class="fas fa-paper-plane"></i> {$_LANG.submit_reissue|default:'Submit Reissue'}
            </button>
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
