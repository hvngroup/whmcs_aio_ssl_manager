{**
 * NicSRS SSL Module - Complete Template
 * Certificate issued successfully - Download and management
 * 
 * @package    nicsrs_ssl
 * @version    2.1.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 *}

{* Load CSS *}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    {* Header *}
    <div class="sslm-header">
        <h2 class="sslm-title">
            <i class="fas fa-shield-alt"></i>
            {$_LANG.certificate_management|default:'Certificate Management'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-success">{$_LANG.issued|default:'Issued'}</span>
        </div>
    </div>

    {* Progress Steps - All Completed *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-check"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div>
        </div>
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-check"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div>
        </div>
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-check"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_validated|default:'Validated'}</div>
        </div>
        <div class="sslm-progress-step completed active">
            <div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div>
        </div>
    </div>

    {* Success Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon success">
            <i class="fas fa-certificate"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.certificate_ready|default:'Your Certificate is Ready!'}</div>
            <div class="sslm-status-desc">
                {$_LANG.certificate_ready_desc|default:'Your SSL certificate has been issued and is ready for installation. Download the certificate files below.'}
            </div>
        </div>
    </div>

    {* Certificate Info Card *}
    <div class="sslm-card sslm-card-success">
        <div class="sslm-card-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
            <div class="sslm-card-header-actions">
                <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh'}
                </button>
            </div>
        </div>
        <div class="sslm-card-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    <span class="sslm-code">{$certId|escape:'html'|default:'N/A'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-status-complete">
                        <i class="fas fa-check-circle"></i> {$_LANG.active|default:'Active'}
                    </span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.validation_type|default:'Validation'}</label>
                    <span class="sslm-badge sslm-badge-{$sslValidationType|default:'dv'}">{$sslValidationType|upper|default:'DV'}</span>
                </div>
            </div>

            {* Domains List *}
            {if $domainInfo}
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--sslm-border-color);">
                <label style="font-weight: 600; margin-bottom: 12px; display: block;">
                    <i class="fas fa-globe"></i> {$_LANG.secured_domains|default:'Secured Domains'}
                </label>
                {foreach from=$domainInfo item=domain name=domains}
                <div class="sslm-domain-status">
                    <div class="sslm-domain-status-icon verified">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="sslm-domain-status-content">
                        <div class="sslm-domain-status-name">{$domain.domainName|escape:'html'}</div>
                        <div class="sslm-domain-status-method">{$_LANG.verified|default:'Verified'}</div>
                    </div>
                </div>
                {/foreach}
            </div>
            {/if}
        </div>
    </div>

    {* Validity Period *}
    {if $beginDate && $endDate}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-calendar-check"></i> {$_LANG.validity_period|default:'Validity Period'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-validity-display">
                <div class="sslm-validity-item">
                    <span class="sslm-validity-label">{$_LANG.valid_from|default:'Valid From'}</span>
                    <span class="sslm-validity-value">{$beginDate|escape:'html'}</span>
                </div>
                <div class="sslm-validity-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="sslm-validity-item">
                    <span class="sslm-validity-label">{$_LANG.valid_until|default:'Valid Until'}</span>
                    <span class="sslm-validity-value sslm-highlight">{$endDate|escape:'html'}</span>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {* Download Section *}
    {if $hasCertificate}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-download"></i> {$_LANG.download_certificate|default:'Download Certificate'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-alert sslm-alert-info" style="margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i>
                <span>{$_LANG.download_info|default:'Select the format that matches your web server. If unsure, download "All Formats" to get everything.'}</span>
            </div>

            <div class="sslm-download-grid">
                {* Apache/Standard *}
                <div class="sslm-download-card" onclick="downloadCert('apache')">
                    <div class="sslm-download-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="sslm-download-title">Apache / Standard</div>
                    <div class="sslm-download-desc">.crt + .ca-bundle + .key</div>
                    <button type="button" class="sslm-btn sslm-btn-primary sslm-btn-sm">
                        <i class="fas fa-download"></i> {$_LANG.download|default:'Download'}
                    </button>
                </div>
                
                {* Nginx *}
                <div class="sslm-download-card" onclick="downloadCert('nginx')">
                    <div class="sslm-download-icon">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div class="sslm-download-title">Nginx</div>
                    <div class="sslm-download-desc">.pem + .key</div>
                    <button type="button" class="sslm-btn sslm-btn-primary sslm-btn-sm">
                        <i class="fas fa-download"></i> {$_LANG.download|default:'Download'}
                    </button>
                </div>
                
                {* IIS *}
                <div class="sslm-download-card" onclick="downloadCert('iis')">
                    <div class="sslm-download-icon">
                        <i class="fab fa-microsoft"></i>
                    </div>
                    <div class="sslm-download-title">IIS / Windows</div>
                    <div class="sslm-download-desc">.p12 (PKCS#12)</div>
                    <button type="button" class="sslm-btn sslm-btn-primary sslm-btn-sm">
                        <i class="fas fa-download"></i> {$_LANG.download|default:'Download'}
                    </button>
                </div>
                
                {* All Formats *}
                <div class="sslm-download-card" onclick="downloadCert('all')">
                    <div class="sslm-download-icon" style="color: var(--sslm-success);">
                        <i class="fas fa-file-archive"></i>
                    </div>
                    <div class="sslm-download-title">{$_LANG.all_formats|default:'All Formats'}</div>
                    <div class="sslm-download-desc">{$_LANG.complete_package|default:'Complete ZIP package'}</div>
                    <button type="button" class="sslm-btn sslm-btn-success sslm-btn-sm">
                        <i class="fas fa-download"></i> {$_LANG.download_all|default:'Download All'}
                    </button>
                </div>
            </div>

            {* Private Key Notice *}
            {if !$hasPrivateKey}
            <div class="sslm-alert sslm-alert-warning" style="margin-top: 20px;">
                <i class="fas fa-key"></i>
                <div>
                    <strong>{$_LANG.private_key_notice|default:'Private Key Not Available'}</strong>
                    <p style="margin: 4px 0 0 0;">{$_LANG.private_key_notice_desc|default:'The private key is not stored in our system. If you generated the CSR elsewhere, use your original private key.'}</p>
                </div>
            </div>
            {/if}
        </div>
    </div>
    {/if}

    {* Certificate Content (Collapsible) *}
    {if $certificate}
    <div class="sslm-section sslm-collapsible collapsed">
        <div class="sslm-section-header" onclick="this.parentElement.classList.toggle('collapsed')">
            <h3>
                <span><i class="fas fa-file-code"></i> {$_LANG.certificate_content|default:'Certificate Content'}</span>
                <i class="fas fa-chevron-down sslm-collapse-icon"></i>
            </h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-cert-display">
                <div class="sslm-cert-display-header">
                    <span class="sslm-cert-display-title">{$_LANG.ssl_certificate|default:'SSL Certificate'}</span>
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(document.getElementById('certContent').textContent)">
                        <i class="fas fa-copy"></i> {$_LANG.copy|default:'Copy'}
                    </button>
                </div>
                <div class="sslm-cert-display-body">
                    <pre id="certContent">{$certificate|escape:'html'}</pre>
                </div>
            </div>
            
            {if $caCertificate}
            <div class="sslm-cert-display" style="margin-top: 16px;">
                <div class="sslm-cert-display-header">
                    <span class="sslm-cert-display-title">{$_LANG.ca_bundle|default:'CA Bundle'}</span>
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(document.getElementById('caContent').textContent)">
                        <i class="fas fa-copy"></i> {$_LANG.copy|default:'Copy'}
                    </button>
                </div>
                <div class="sslm-cert-display-body">
                    <pre id="caContent">{$caCertificate|escape:'html'}</pre>
                </div>
            </div>
            {/if}
        </div>
    </div>
    {/if}

    {* Actions Section *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-cogs"></i> {$_LANG.certificate_actions|default:'Certificate Actions'}</h3>
        </div>
        <div class="sslm-section-body">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="button" class="sslm-btn sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
                </button>
                <button type="button" class="sslm-btn sslm-btn-outline" onclick="window.location.href='{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}&modop=custom&a=reissue'">
                    <i class="fas fa-redo"></i> {$_LANG.reissue|default:'Reissue Certificate'}
                </button>
                <button type="button" class="sslm-btn sslm-btn-danger" onclick="confirmRevoke()">
                    <i class="fas fa-ban"></i> {$_LANG.revoke|default:'Revoke Certificate'}
                </button>
            </div>
        </div>
    </div>

    {* Help Section *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.installation_help|default:'Installation Help'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <h4><i class="fas fa-server"></i> {$_LANG.apache_help|default:'Apache'}</h4>
                    <p>{$_LANG.apache_help_desc|default:'Upload .crt, .ca-bundle, and .key files. Update your VirtualHost configuration.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-cube"></i> {$_LANG.nginx_help|default:'Nginx'}</h4>
                    <p>{$_LANG.nginx_help_desc|default:'Use the .pem file (combined cert) with your .key file in server block.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fab fa-microsoft"></i> {$_LANG.iis_help|default:'IIS'}</h4>
                    <p>{$_LANG.iis_help_desc|default:'Import the .p12 file using IIS Manager or MMC snap-in.'}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{* Revoke Confirmation Modal *}
<div class="sslm-modal-overlay" id="revokeModal">
    <div class="sslm-modal">
        <div class="sslm-modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--sslm-error);"></i> {$_LANG.confirm_revoke|default:'Confirm Revocation'}</h3>
            <button type="button" class="sslm-modal-close" onclick="closeRevokeModal()">&times;</button>
        </div>
        <div class="sslm-modal-body">
            <div class="sslm-alert sslm-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>{$_LANG.warning|default:'Warning'}</strong>
                    <p style="margin: 4px 0 0 0;">{$_LANG.revoke_warning|default:'Revoking this certificate is permanent and cannot be undone. The certificate will become invalid immediately.'}</p>
                </div>
            </div>
            <p style="margin-top: 16px;">{$_LANG.revoke_confirm_question|default:'Are you sure you want to revoke this certificate?'}</p>
        </div>
        <div class="sslm-modal-footer">
            <button type="button" class="sslm-btn sslm-btn-secondary" onclick="closeRevokeModal()">
                {$_LANG.cancel|default:'Cancel'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-danger" onclick="doRevoke()">
                <i class="fas fa-ban"></i> {$_LANG.revoke_certificate|default:'Revoke Certificate'}
            </button>
        </div>
    </div>
</div>

{* JavaScript Configuration *}
<script>
window.sslmConfig = {
    ajaxUrl: "{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}",
    serviceid: "{$serviceid}",
    certId: "{$certId|escape:'javascript'}",
    lang: {
        download_started: '{$_LANG.download_started|default:"Download started"}',
        download_failed: '{$_LANG.download_failed|default:"Download failed"}',
        copied: '{$_LANG.copied|default:"Copied!"}',
        revoke_success: '{$_LANG.revoke_success|default:"Certificate revoked"}',
        revoke_failed: '{$_LANG.revoke_failed|default:"Revocation failed"}'
    }
};

/**
 * Download certificate in specified format
 * 
 * Supported formats:
 * - apache: .crt + .ca-bundle + .key (ZIP)
 * - nginx: .pem + .key
 * - iis: .p12 (PKCS#12) with password
 * - tomcat: .jks (Java KeyStore) with password
 * - all: Complete ZIP with all formats
 */
function downloadCert(format) {
    var btn = event.currentTarget.querySelector('.sslm-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {$_LANG.downloading|default:"Downloading..."}';
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.sslmConfig.ajaxUrl + '&step=downCert', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        // Reset button state
        if (btn) {
            btn.disabled = false;
            var btnText = format === 'all' 
                ? '<i class="fas fa-download"></i> {$_LANG.download_all|default:"Download All"}'
                : '<i class="fas fa-download"></i> {$_LANG.download|default:"Download"}';
            btn.innerHTML = btnText;
        }
        
        try {
            var response = JSON.parse(xhr.responseText);
            
            if (response.success && response.data) {
                // Decode base64 and trigger download
                var content = atob(response.data.content);
                var bytes = new Uint8Array(content.length);
                for (var i = 0; i < content.length; i++) {
                    bytes[i] = content.charCodeAt(i);
                }
                
                var blob = new Blob([bytes], { 
                    type: response.data.mime || 'application/octet-stream' 
                });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = response.data.filename;
                link.click();
                URL.revokeObjectURL(link.href);
                
                // Show success toast
                SSLManager.showToast(window.sslmConfig.lang.download_started, 'success');
                
                // Handle password notification for IIS/Tomcat/All formats
                var password = response.data.password || response.data.pkcsPassword;
                var jksPassword = response.data.jksPassword;
                
                if (password || jksPassword) {
                    showPasswordModal(format, password, jksPassword);
                }
                
                // Also download private key if available (for nginx/pem format)
                if (response.data.privateKey && response.data.privateKeyFilename) {
                    setTimeout(function() {
                        var keyContent = atob(response.data.privateKey);
                        var keyBytes = new Uint8Array(keyContent.length);
                        for (var i = 0; i < keyContent.length; i++) {
                            keyBytes[i] = keyContent.charCodeAt(i);
                        }
                        var keyBlob = new Blob([keyBytes], { type: 'application/x-pem-file' });
                        var keyLink = document.createElement('a');
                        keyLink.href = URL.createObjectURL(keyBlob);
                        keyLink.download = response.data.privateKeyFilename;
                        keyLink.click();
                        URL.revokeObjectURL(keyLink.href);
                    }, 500);
                }
                
                // Also download CA Bundle if available (for apache format single file fallback)
                if (response.data.caBundle && response.data.caBundleFilename) {
                    setTimeout(function() {
                        var caContent = atob(response.data.caBundle);
                        var caBytes = new Uint8Array(caContent.length);
                        for (var i = 0; i < caContent.length; i++) {
                            caBytes[i] = caContent.charCodeAt(i);
                        }
                        var caBlob = new Blob([caBytes], { type: 'application/x-x509-ca-cert' });
                        var caLink = document.createElement('a');
                        caLink.href = URL.createObjectURL(caBlob);
                        caLink.download = response.data.caBundleFilename;
                        caLink.click();
                        URL.revokeObjectURL(caLink.href);
                    }, 1000);
                }
                
            } else {
                SSLManager.showToast(response.message || window.sslmConfig.lang.download_failed, 'error');
            }
        } catch (e) {
            console.error('Download error:', e);
            SSLManager.showToast(window.sslmConfig.lang.download_failed, 'error');
        }
    };
    
    xhr.onerror = function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> {$_LANG.download|default:"Download"}';
        }
        SSLManager.showToast(window.sslmConfig.lang.download_failed, 'error');
    };
    
    xhr.send('format=' + encodeURIComponent(format));
}

/**
 * Show password modal for IIS/Tomcat formats
 * Using correct CSS structure: .sslm-modal-overlay > .sslm-modal
 */
function showPasswordModal(format, pkcsPassword, jksPassword) {
    var title, content;
    
    if (format === 'all') {
        title = '{$_LANG.password_info|default:"Certificate Passwords"}';
        content = '<div class="sslm-password-info">';
        if (pkcsPassword) {
            content += '<div class="sslm-password-item">';
            content += '<label><i class="fab fa-microsoft"></i> PKCS12/IIS Password:</label>';
            content += '<div class="sslm-password-box">';
            content += '<code class="sslm-password-value">' + escapeHtml(pkcsPassword) + '</code>';
            content += '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(\'' + escapeHtml(pkcsPassword) + '\')"><i class="fas fa-copy"></i></button>';
            content += '</div></div>';
        }
        if (jksPassword) {
            content += '<div class="sslm-password-item">';
            content += '<label><i class="fab fa-java"></i> JKS/Tomcat Password:</label>';
            content += '<div class="sslm-password-box">';
            content += '<code class="sslm-password-value">' + escapeHtml(jksPassword) + '</code>';
            content += '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(\'' + escapeHtml(jksPassword) + '\')"><i class="fas fa-copy"></i></button>';
            content += '</div></div>';
        }
        content += '</div>';
    } else if (format === 'iis' || format === 'pfx' || format === 'p12') {
        title = '{$_LANG.iis_password|default:"IIS/Windows Certificate Password"}';
        content = '<div class="sslm-password-info">';
        content += '<p class="sslm-password-desc">{$_LANG.iis_password_desc|default:"Use this password when importing the .p12 file into IIS or Windows Certificate Manager:"}</p>';
        content += '<div class="sslm-password-display">';
        content += '<code class="sslm-password-value">' + escapeHtml(pkcsPassword) + '</code>';
        content += '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(\'' + escapeHtml(pkcsPassword) + '\')"><i class="fas fa-copy"></i> {$_LANG.copy|default:"Copy"}</button>';
        content += '</div></div>';
    } else if (format === 'tomcat' || format === 'jks') {
        title = '{$_LANG.jks_password|default:"Tomcat/JKS Certificate Password"}';
        content = '<div class="sslm-password-info">';
        content += '<p class="sslm-password-desc">{$_LANG.jks_password_desc|default:"Use this password in your Tomcat server.xml configuration:"}</p>';
        content += '<div class="sslm-password-display">';
        content += '<code class="sslm-password-value">' + escapeHtml(jksPassword || pkcsPassword) + '</code>';
        content += '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.copyToClipboard(\'' + escapeHtml(jksPassword || pkcsPassword) + '\')"><i class="fas fa-copy"></i> {$_LANG.copy|default:"Copy"}</button>';
        content += '</div></div>';
    }
    
    // Create modal overlay (parent) with modal (child) inside
    // This matches the CSS structure: .sslm-modal-overlay.show > .sslm-modal
    var overlay = document.createElement('div');
    overlay.className = 'sslm-modal-overlay show';
    overlay.id = 'passwordModalOverlay';
    overlay.onclick = function(e) {
        if (e.target === overlay) closePasswordModal();
    };
    
    overlay.innerHTML = '\
        <div class="sslm-modal">\
            <div class="sslm-modal-header">\
                <h3><i class="fas fa-key"></i> ' + title + '</h3>\
                <button type="button" class="sslm-modal-close" onclick="closePasswordModal()">&times;</button>\
            </div>\
            <div class="sslm-modal-body">' + content + '</div>\
            <div class="sslm-modal-footer">\
                <button type="button" class="sslm-btn sslm-btn-primary" onclick="closePasswordModal()">\
                    <i class="fas fa-check"></i> {$_LANG.ok|default:"OK"}\
                </button>\
            </div>\
        </div>';
    
    document.body.appendChild(overlay);
}

/**
 * Close password modal
 */
function closePasswordModal() {
    var overlay = document.getElementById('passwordModalOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        setTimeout(function() {
            overlay.remove();
        }, 200);
    }
}

/**
 * Escape HTML special characters
 */
function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Revoke modal functions
function confirmRevoke() {
    document.getElementById('revokeModal').classList.add('show');
}

function closeRevokeModal() {
    document.getElementById('revokeModal').classList.remove('show');
}

function doRevoke() {
    var btn = document.querySelector('#revokeModal .sslm-btn-danger');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {$_LANG.revoking|default:"Revoking..."}';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.sslmConfig.ajaxUrl + '&step=revoke', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                SSLManager.showToast(window.sslmConfig.lang.revoke_success, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-ban"></i> {$_LANG.revoke_certificate|default:"Revoke Certificate"}';
                SSLManager.showToast(response.message || window.sslmConfig.lang.revoke_failed, 'error');
            }
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-ban"></i> {$_LANG.revoke_certificate|default:"Revoke Certificate"}';
            SSLManager.showToast(window.sslmConfig.lang.revoke_failed, 'error');
        }
    };
    
    xhr.send('certId=' + encodeURIComponent(window.sslmConfig.certId));
}
</script>

{* Load JavaScript *}
<script src="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/js/ssl-manager.js"></script>