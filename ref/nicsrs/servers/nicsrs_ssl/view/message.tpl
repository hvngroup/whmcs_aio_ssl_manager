{**
 * NicSRS SSL Module - Message/Pending Template
 * Shows DCV instructions while certificate is pending validation
 * Reference template for UI consistency
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
            {$_LANG.certificate_status|default:'Certificate Status'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-warning">{$_LANG.pending|default:'Pending'}</span>
        </div>
    </div>

    {* Progress Steps *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-check"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div>
        </div>
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-check"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div>
        </div>
        <div class="sslm-progress-step active">
            <div class="sslm-progress-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div>
        </div>
    </div>

    {* Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.certificate_pending|default:'Certificate Pending Validation'}</div>
            <div class="sslm-status-desc">{$_LANG.message_desc|default:'Your certificate request has been submitted. Please complete domain validation below to receive your SSL certificate.'}</div>
        </div>
    </div>

    {* Order Info Card *}
    <div class="sslm-card">
        <div class="sslm-card-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.order_details|default:'Order Details'}</h3>
            <div class="sslm-card-header-actions">
                <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-primary" id="refreshStatusBtn" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh'}
                </button>
            </div>
        </div>
        <div class="sslm-card-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.order_id|default:'Order ID'}</label>
                    <span>#{$order->id|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    <span class="sslm-code">{$certId|escape:'html'|default:'N/A'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-status-pending">
                        <i class="fas fa-clock"></i> {$_LANG.pending_validation|default:'Pending Validation'}
                    </span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.order_date|default:'Order Date'}</label>
                    <span>{$order->provisiondate|escape:'html'|default:'N/A'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.primary_domain|default:'Primary Domain'}</label>
                    <span>{$configData.domainInfo[0].domainName|escape:'html'|default:'N/A'}</span>
                </div>
            </div>
        </div>
    </div>

    {* DCV Required Card *}
    <div class="sslm-card sslm-card-warning">
        <div class="sslm-card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> {$_LANG.action_required|default:'Action Required: Domain Validation'}</h3>
        </div>
        <div class="sslm-card-body">
            <div class="sslm-alert sslm-alert-info" style="margin-bottom: 20px;">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>{$_LANG.important|default:'Important'}:</strong>
                    {$_LANG.dcv_instruction_main|default:'Complete domain validation to receive your SSL certificate. Follow the instructions below for each domain.'}
                </div>
            </div>

            {* Domain DCV Cards *}
            {if $configData.domainInfo}
            {foreach from=$configData.domainInfo item=domain key=idx}
            {assign var="dcvMethod" value=$domain.dcvMethod|default:'CNAME_CSR_HASH'}
            <div class="sslm-dcv-card" data-domain="{$domain.domainName|escape:'html'}">
                <div class="sslm-dcv-card-header">
                    <div class="sslm-dcv-domain">
                        <i class="fas fa-globe"></i>
                        <span>{$domain.domainName|escape:'html'}</span>
                    </div>
                    <div class="sslm-dcv-card-actions">
                        <span class="sslm-dcv-method-tag">
                            {if $dcvMethod eq 'EMAIL'}
                                <i class="fas fa-envelope"></i> Email
                            {elseif $dcvMethod eq 'HTTP_CSR_HASH' || $dcvMethod eq 'HTTPS_CSR_HASH'}
                                <i class="fas fa-file-alt"></i> HTTP File
                            {else}
                                <i class="fas fa-server"></i> DNS CNAME
                            {/if}
                        </span>
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" 
                                onclick="SSLManager.openChangeDCVModal('{$domain.domainName|escape:'javascript'}', '{$dcvMethod|escape:'javascript'}')">
                            <i class="fas fa-exchange-alt"></i> {$_LANG.change|default:'Change'}
                        </button>
                    </div>
                </div>
                <div class="sslm-dcv-card-body">
                    {* DNS CNAME Validation *}
                    {if $dcvMethod eq 'CNAME_CSR_HASH'}
                    <p style="margin-bottom: 12px;">
                        <i class="fas fa-info-circle" style="color: var(--sslm-info);"></i>
                        {$_LANG.dns_instruction|default:'Add the following CNAME record to your DNS settings:'}
                    </p>
                    <div class="sslm-code-group">
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.record_type|default:'Type'}</div>
                            <div class="sslm-code-value">
                                <code>CNAME</code>
                            </div>
                        </div>
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.host_name|default:'Host/Name'}</div>
                            <div class="sslm-code-value">
                                <code>{$dcvDnsHost|escape:'html'|default:'_dnsauth'}.{$domain.domainName|escape:'html'}</code>
                                <button type="button" class="sslm-code-copy" onclick="SSLManager.copyToClipboard('{$dcvDnsHost|escape:'javascript'|default:'_dnsauth'}.{$domain.domainName|escape:'javascript'}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.points_to|default:'Points To'}</div>
                            <div class="sslm-code-value">
                                <code>{$dcvDnsValue|escape:'html'}</code>
                                <button type="button" class="sslm-code-copy" onclick="SSLManager.copyToClipboard('{$dcvDnsValue|escape:'javascript'}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="sslm-help-text" style="margin-top: 12px;">
                        <i class="fas fa-clock"></i> {$_LANG.dns_propagation|default:'DNS changes may take 5-30 minutes to propagate.'}
                    </p>

                    {* HTTP/HTTPS File Validation *}
                    {elseif $dcvMethod eq 'HTTP_CSR_HASH' || $dcvMethod eq 'HTTPS_CSR_HASH'}
                    <p style="margin-bottom: 12px;">
                        <i class="fas fa-info-circle" style="color: var(--sslm-info);"></i>
                        {$_LANG.http_instruction|default:'Create a file with the following content and upload it to your web server:'}
                    </p>
                    <div class="sslm-code-group">
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.file_url|default:'File URL'}</div>
                            <div class="sslm-code-value">
                                <code>{if $dcvMethod eq 'HTTPS_CSR_HASH'}https{else}http{/if}://{$domain.domainName|escape:'html'}/.well-known/pki-validation/{$dcvFileName|escape:'html'}</code>
                                <button type="button" class="sslm-code-copy" onclick="SSLManager.copyToClipboard('{if $dcvMethod eq 'HTTPS_CSR_HASH'}https{else}http{/if}://{$domain.domainName|escape:'javascript'}/.well-known/pki-validation/{$dcvFileName|escape:'javascript'}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.file_content|default:'Content'}</div>
                            <div class="sslm-code-value">
                                <code>{$dcvFileContent|escape:'html'}</code>
                                <button type="button" class="sslm-code-copy" onclick="SSLManager.copyToClipboard('{$dcvFileContent|escape:'javascript'}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 16px;">
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-secondary download-dcv-file" 
                                data-filename="{$dcvFileName|escape:'html'}" 
                                data-content="{$dcvFileContent|escape:'html'}">
                            <i class="fas fa-download"></i> {$_LANG.download_file|default:'Download File'}
                        </button>
                    </div>
                    <p class="sslm-help-text" style="margin-top: 12px;">
                        <i class="fas fa-folder"></i> {$_LANG.http_note|default:'Make sure the file is accessible via HTTP/HTTPS. Content-Type should be text/plain.'}
                    </p>

                    {* Email Validation *}
                    {elseif $dcvMethod eq 'EMAIL'}
                    <p style="margin-bottom: 12px;">
                        <i class="fas fa-info-circle" style="color: var(--sslm-info);"></i>
                        {$_LANG.email_instruction|default:'A validation email has been sent to:'}
                    </p>
                    <div class="sslm-code-group">
                        <div class="sslm-code-row">
                            <div class="sslm-code-label">{$_LANG.email|default:'Email'}</div>
                            <div class="sslm-code-value">
                                <code>{$domain.dcvEmail|default:"admin@`$domain.domainName`"|escape:'html'}</code>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 16px;">
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-primary resend-dcv-btn" data-domain="{$domain.domainName|escape:'html'}">
                            <i class="fas fa-paper-plane"></i> {$_LANG.resend_email|default:'Resend Email'}
                        </button>
                    </div>
                    <p class="sslm-help-text" style="margin-top: 12px;">
                        <i class="fas fa-envelope-open-text"></i> {$_LANG.email_note|default:'Check your inbox and spam folder. Click the validation link in the email.'}
                    </p>
                    {/if}
                </div>
            </div>
            {/foreach}
            {/if}
        </div>
    </div>

    {* Help Section *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.need_help|default:'Need Help?'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <h4><i class="fas fa-clock"></i> {$_LANG.how_long|default:'How long does it take?'}</h4>
                    <p>{$_LANG.time_note|default:'DNS: 5-30 minutes after propagation. HTTP: Usually instant. Email: Depends on when you click the link.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-sync"></i> {$_LANG.check_status|default:'Check Status'}</h4>
                    <p>{$_LANG.status_note|default:'Click "Refresh" button above to check if validation is complete and certificate is issued.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-exchange-alt"></i> {$_LANG.change_method|default:'Change Method'}</h4>
                    <p>{$_LANG.change_note|default:'Click "Change" next to each domain to switch to a different validation method.'}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{* Change DCV Modal *}
<div class="sslm-modal-overlay" id="changeDcvModal">
    <div class="sslm-modal">
        <div class="sslm-modal-header">
            <h3><i class="fas fa-exchange-alt"></i> {$_LANG.change_dcv_method|default:'Change Validation Method'}</h3>
            <button type="button" class="sslm-modal-close" onclick="SSLManager.closeChangeDCVModal()">&times;</button>
        </div>
        <div class="sslm-modal-body">
            <div class="sslm-form-group">
                <label>{$_LANG.domain|default:'Domain'}</label>
                <div id="dcvModalDomain" style="font-weight: 600; color: var(--sslm-primary); font-size: 16px;"></div>
            </div>
            <div class="sslm-form-group">
                <label>{$_LANG.new_dcv_method|default:'New Validation Method'} <span class="required">*</span></label>
                <select id="newDcvMethod" class="sslm-select">
                    <option value="">{$_LANG.select_method|default:'-- Select Method --'}</option>
                    <optgroup label="{$_LANG.file_dns_validation|default:'File/DNS Validation'}">
                        <option value="HTTP_CSR_HASH">{$_LANG.http_file|default:'HTTP File Validation'}</option>
                        <option value="HTTPS_CSR_HASH">{$_LANG.https_file|default:'HTTPS File Validation'}</option>
                        <option value="CNAME_CSR_HASH">{$_LANG.dns_cname|default:'DNS CNAME Validation'}</option>
                    </optgroup>
                    {* Email options sẽ được populate bởi JavaScript *}
                    <optgroup label="{$_LANG.email_validation|default:'Email Validation'}" class="dcv-email-options">
                        {* Dynamic email options will be added here *}
                    </optgroup>
                </select>
            </div>
            <div class="sslm-alert sslm-alert-info" style="margin-top: 16px;">
                <i class="fas fa-info-circle"></i>
                <div>{$_LANG.dcv_change_note|default:'After changing the validation method, you will need to complete the new validation process.'}</div>
            </div>
        </div>
        <div class="sslm-modal-footer">
            <button type="button" class="sslm-btn sslm-btn-secondary" onclick="SSLManager.closeChangeDCVModal()">
                {$_LANG.cancel|default:'Cancel'}
            </button>
            <button type="button" id="confirmDcvChangeBtn" class="sslm-btn sslm-btn-primary" onclick="SSLManager.confirmChangeDCV()">
                <i class="fas fa-check"></i> {$_LANG.confirm_change|default:'Confirm Change'}
            </button>
        </div>
    </div>
</div>

{* JavaScript Configuration *}
<script>
window.sslmConfig = {
    ajaxUrl: "{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}",
    serviceid: "{$serviceid}",
    configData: {$configData|json_encode nofilter},
    lang: {
        refresh_success: '{$_LANG.refresh_success|default:"Status refreshed"}',
        certificate_issued: '{$_LANG.certificate_issued|default:"Certificate has been issued!"}',
        still_pending: '{$_LANG.still_pending|default:"Still pending validation"}',
        dcv_changed: '{$_LANG.dcv_changed|default:"Validation method changed"}',
        dcv_email_sent: '{$_LANG.dcv_email_sent|default:"Validation email sent"}',
        copied: '{$_LANG.copied|default:"Copied!"}',
        copy_failed: '{$_LANG.copy_failed|default:"Copy failed"}'
    }
};

// Download DCV file handler
document.querySelectorAll('.download-dcv-file').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var filename = this.getAttribute('data-filename');
        var content = this.getAttribute('data-content');
        var blob = new Blob([content], { type: 'text/plain' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    });
});

// Resend DCV email handler
document.querySelectorAll('.resend-dcv-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var domain = this.getAttribute('data-domain');
        if (!domain) return;
        
        this.disabled = true;
        this.classList.add('sslm-loading');
        var btnRef = this;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.sslmConfig.ajaxUrl + '&step=resendDCVEmail', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            btnRef.disabled = false;
            btnRef.classList.remove('sslm-loading');
            
            try {
                var response = JSON.parse(xhr.responseText);
                SSLManager.showToast(
                    response.success ? window.sslmConfig.lang.dcv_email_sent : (response.message || 'Failed'),
                    response.success ? 'success' : 'error'
                );
            } catch (e) {
                SSLManager.showToast('Error', 'error');
            }
        };
        
        xhr.send('domain=' + encodeURIComponent(domain));
    });
});
</script>

{* Load JavaScript *}
<script src="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/js/ssl-manager.js"></script>