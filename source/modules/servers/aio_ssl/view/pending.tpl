{* ══════════════════════════════════════════════════════════════════════
   FILE: view/pending.tpl — Pending / DCV Management
   Rich info: order details card, domain validation status, DCV instructions
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-warning">{$_LANG.pending_validation|default:'Pending Validation'}</span>
        </div>
    </div>

    {* Progress *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_ordered|default:'Ordered'}</div></div>
        <div class="sslm-progress-step completed"><div class="sslm-progress-icon"><i class="fas fa-check"></i></div><div class="sslm-progress-label">{$_LANG.step_submitted|default:'Submitted'}</div></div>
        <div class="sslm-progress-step active"><div class="sslm-progress-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div></div>
        <div class="sslm-progress-step"><div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div><div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div></div>
    </div>

    {* Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon warning"><i class="fas fa-clock"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.awaiting_validation|default:'Awaiting Domain Validation'}</div>
            <div class="sslm-status-desc">{$_LANG.validation_desc|default:'Your certificate request has been submitted. Complete the domain validation below to receive your SSL certificate.'}</div>
        </div>
    </div>

    {* ── Order Details Card ── *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.order_details|default:'Order Details'}</h3>
            <div class="sslm-card-header-actions">
                <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-secondary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh|default:'Refresh'}
                </button>
            </div>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    <span class="sslm-code">{$certId|escape:'html'|default:'N/A'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-badge-warning"><i class="fas fa-clock"></i> {$_LANG.pending_validation|default:'Pending Validation'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.primary_domain|default:'Primary Domain'}</label>
                    <span><code>{$domain|escape:'html'}</code></span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.validation_method|default:'Validation Method'}</label>
                    <span class="sslm-badge">{$dcvMethod|upper|default:'EMAIL'}</span>
                </div>
                {if $submittedAt}
                <div class="sslm-info-item">
                    <label>{$_LANG.submitted_at|default:'Submitted'}</label>
                    <span>{$submittedAt|escape:'html'}</span>
                </div>
                {/if}
            </div>
        </div>
    </div>

    {* ── Domain Validation Status ── *}
    {if $domainInfo}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-tasks"></i> {$_LANG.validation_status|default:'Validation Status'}</h3>
        </div>
        <div class="sslm-section-body">
            {foreach from=$domainInfo item=dmn key=idx}
            {assign var="isVerified" value=$dmn.isVerified|default:false}
            {assign var="dMethod" value=$dmn.dcvMethod|default:$dcvMethod}
            <div class="sslm-domain-status-row">
                <div class="sslm-domain-status-icon {if $isVerified}verified{else}pending{/if}">
                    <i class="fas fa-{if $isVerified}check-circle{else}clock{/if}"></i>
                </div>
                <div class="sslm-domain-status-content">
                    <div class="sslm-domain-name"><code>{$dmn.domainName|escape:'html'}</code></div>
                    <div class="sslm-domain-meta">
                        <span class="sslm-badge sslm-badge-sm">{$dMethod|upper}</span>
                        {if $isVerified}
                            <span class="sslm-badge sslm-badge-success sslm-badge-sm"><i class="fas fa-check"></i> {$_LANG.verified|default:'Verified'}</span>
                        {else}
                            <span class="sslm-badge sslm-badge-warning sslm-badge-sm">{$_LANG.un_verified|default:'Unverified'}</span>
                        {/if}
                    </div>
                </div>
                {if !$isVerified}
                <div class="sslm-domain-status-actions">
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.resendDcv('{$dmn.domainName|escape:'js'}')">
                        <i class="fas fa-redo"></i> {$_LANG.resend|default:'Resend'}
                    </button>
                </div>
                {/if}
            </div>
            {/foreach}
        </div>
    </div>
    {/if}

    {* ── DCV Instructions Card ── *}
    <div class="sslm-section sslm-card-warning">
        <div class="sslm-section-header">
            <h3><i class="fas fa-exclamation-triangle"></i> {$_LANG.action_required|default:'Action Required: Domain Validation'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-alert sslm-alert-info" style="margin-bottom:16px;">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>{$_LANG.important|default:'Important'}:</strong>
                    {$_LANG.dcv_instruction_main|default:'Complete domain validation to receive your SSL certificate. Follow the instructions below based on your chosen method.'}
                </div>
            </div>

            {if $dcvMethod == 'email' || $dcvMethod == 'EMAIL'}
            <div class="sslm-instruction-block">
                <h4><i class="fas fa-envelope"></i> {$_LANG.email_validation|default:'Email Validation'}</h4>
                <ol class="sslm-steps-list">
                    <li>{$_LANG.dcv_email_step1|default:'A verification email has been sent to the approver email address.'}</li>
                    <li>{$_LANG.dcv_email_step2|default:'Check your inbox (and spam folder) for the validation email.'}</li>
                    <li>{$_LANG.dcv_email_step3|default:'Click the approval link in the email to complete validation.'}</li>
                </ol>
                <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.resendDcv()">
                    <i class="fas fa-envelope"></i> {$_LANG.resend_validation|default:'Resend Validation Email'}
                </button>
            </div>
            {elseif $dcvMethod == 'http' || $dcvMethod == 'HTTP_CSR_HASH' || $dcvMethod == 'https' || $dcvMethod == 'HTTPS_CSR_HASH'}
            <div class="sslm-instruction-block">
                <h4><i class="fas fa-file-alt"></i> {$_LANG.http_validation|default:'HTTP/HTTPS File Validation'}</h4>
                <ol class="sslm-steps-list">
                    <li>{$_LANG.dcv_http_step1|default:'Create a file with the validation content provided by the Certificate Authority.'}</li>
                    <li>{$_LANG.dcv_http_step2|default:'Upload the file to your web server at the specified path.'}</li>
                    <li>{$_LANG.dcv_http_step3|default:'Ensure the file is accessible via HTTP/HTTPS, then click Refresh Status.'}</li>
                </ol>
            </div>
            {elseif $dcvMethod == 'dns' || $dcvMethod == 'CNAME_CSR_HASH' || $dcvMethod == 'DNS'}
            <div class="sslm-instruction-block">
                <h4><i class="fas fa-network-wired"></i> {$_LANG.dns_validation|default:'DNS CNAME Validation'}</h4>
                <ol class="sslm-steps-list">
                    <li>{$_LANG.dcv_dns_step1|default:'Add a CNAME record to your DNS zone with the values provided by the Certificate Authority.'}</li>
                    <li>{$_LANG.dcv_dns_step2|default:'Wait for DNS propagation (may take up to 24 hours).'}</li>
                    <li>{$_LANG.dcv_dns_step3|default:'Click Refresh Status once DNS has propagated.'}</li>
                </ol>
            </div>
            {/if}
        </div>
    </div>

    {* ── Actions ── *}
    <div class="sslm-section">
        <div class="sslm-section-body">
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLManager.refreshStatus()">
                    <i class="fas fa-sync-alt"></i> {$_LANG.refresh_status|default:'Refresh Status'}
                </button>
                <button type="button" class="sslm-btn sslm-btn-danger sslm-btn-outline" onclick="SSLManager.confirmCancel()">
                    <i class="fas fa-times"></i> {$_LANG.cancel_order|default:'Cancel Order'}
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>