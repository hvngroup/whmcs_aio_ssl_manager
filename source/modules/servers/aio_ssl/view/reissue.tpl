{* ══════════════════════════════════════════════════════════════════════
   FILE: view/reissue.tpl — Reissue certificate form
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/reissue.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" data-serviceid="{$serviceid}">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-redo-alt"></i> {$_LANG.reissue_certificate|default:'Reissue Certificate'}</h2>
    </div>

    <div class="sslm-alert sslm-alert-info">
        <i class="fas fa-info-circle"></i>
        {$_LANG.reissue_desc|default:'Reissuing replaces your current certificate with a new one. The old certificate will be revoked after the new one is issued.'}
    </div>

    <div class="sslm-section">
        <div class="sslm-section-body">
            <div class="sslm-form-group">
                <label>{$_LANG.new_csr|default:'New CSR'} <span class="required">*</span></label>
                <textarea id="reissue-csr" class="sslm-textarea" rows="8" placeholder="-----BEGIN CERTIFICATE REQUEST-----"></textarea>
            </div>
            <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.generateCsrForReissue()">
                <i class="fas fa-cogs"></i> {$_LANG.auto_generate|default:'Auto-Generate CSR'}
            </button>

            <div class="sslm-form-group" style="margin-top:15px;">
                <label>{$_LANG.dcv_method|default:'Validation Method'}</label>
                <select id="reissue-dcv" class="sslm-select">
                    <option value="email">Email</option>
                    <option value="http">HTTP File</option>
                    <option value="https">HTTPS File</option>
                    <option value="dns">DNS (CNAME)</option>
                </select>
            </div>
        </div>
    </div>

    <div class="sslm-actions">
        <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sslm-btn sslm-btn-outline">
            <i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}
        </a>
        <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.submitReissue()">
            <i class="fas fa-paper-plane"></i> {$_LANG.submit_reissue|default:'Submit Reissue'}
        </button>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>