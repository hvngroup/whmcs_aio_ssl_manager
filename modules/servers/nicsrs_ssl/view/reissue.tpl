{**
 * NicSRS SSL Module - Reissue Certificate Template
 * 
 * @package    nicsrs_ssl
 * @version    2.1.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 *}

{* Load CSS *}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">

{* Parse configData *}
{if is_string($configData) && $configData}
    {assign var="cfgData" value=$configData|json_decode:true}
{elseif is_array($configData)}
    {assign var="cfgData" value=$configData}
{else}
    {assign var="cfgData" value=[]}
{/if}

{* Determine Certificate ID with fallbacks *}
{assign var="displayCertId" value=""}
{if !empty($certId) && $certId neq 'N/A'}
    {assign var="displayCertId" value=$certId}
{elseif !empty($order->remoteid)}
    {assign var="displayCertId" value=$order->remoteid}
{elseif !empty($cfgData.applyReturn.vendorCertId)}
    {assign var="displayCertId" value=$cfgData.applyReturn.vendorCertId}
{elseif !empty($cfgData.applyReturn.vendorId)}
    {assign var="displayCertId" value=$cfgData.applyReturn.vendorId}
{/if}

<div class="sslm-container">
    {* ============================================ *}
    {* Header *}
    {* ============================================ *}
    <div class="sslm-header">
        <h2 class="sslm-title">
            <i class="fas fa-redo"></i>
            {$_LANG.reissue_certificate|default:'Reissue Certificate'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-{$sslValidationType|default:'dv'}">{$sslValidationType|upper|default:'DV'}</span>
        </div>
    </div>

    {* ============================================ *}
    {* Progress Steps (like applycert) *}
    {* ============================================ *}
    <div class="sslm-progress">
        <div class="sslm-progress-step completed">
            <div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div>
        </div>
        <div class="sslm-progress-step active">
            <div class="sslm-progress-icon"><i class="fas fa-redo"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_reissue|default:'Reissue'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-check-circle"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_new_cert|default:'New Certificate'}</div>
        </div>
    </div>

    {* ============================================ *}
    {* Warning Alert *}
    {* ============================================ *}
    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>{$_LANG.reissue_warning_title|default:'Important Information'}</strong>
            <p style="margin: 8px 0 0 0;">{$_LANG.reissue_warning|default:'Reissuing will generate a new certificate. The previous certificate will remain valid until it expires or you choose to revoke it.'}</p>
        </div>
    </div>

    {* ============================================ *}
    {* Original Certificate Info Card *}
    {* ============================================ *}
    <div class="sslm-card sslm-card-info">
        <div class="sslm-card-header">
            <h3><i class="fas fa-certificate"></i> {$_LANG.current_certificate|default:'Current Certificate'}</h3>
        </div>
        <div class="sslm-card-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    {if $displayCertId}
                        <span class="sslm-code">{$displayCertId|escape:'html'}</span>
                    {else}
                        <span class="sslm-code sslm-text-muted">{$_LANG.pending|default:'Pending'}</span>
                    {/if}
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span class="sslm-badge sslm-badge-success">
                        <i class="fas fa-check-circle"></i> {$_LANG.active|default:'Active'}
                    </span>
                </div>
                {if $cfgData.domainInfo[0].domainName}
                <div class="sslm-info-item">
                    <label>{$_LANG.primary_domain|default:'Primary Domain'}</label>
                    <span>{$cfgData.domainInfo[0].domainName|escape:'html'}</span>
                </div>
                {/if}
                {if $cfgData.applyReturn.endDate}
                <div class="sslm-info-item">
                    <label>{$_LANG.expires|default:'Expires'}</label>
                    <span>{$cfgData.applyReturn.endDate|escape:'html'}</span>
                </div>
                {/if}
            </div>

            {* Original Domains List *}
            {if $originalDomains}
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--sslm-border-color);">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">
                    <i class="fas fa-list"></i> {$_LANG.original_domains|default:'Original Domains'}
                </label>
                {foreach from=$originalDomains item=domain}
                <div class="sslm-domain-status" style="margin-bottom: 8px;">
                    <div class="sslm-domain-status-icon verified">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="sslm-domain-status-content">
                        <div class="sslm-domain-status-name">{$domain|escape:'html'}</div>
                    </div>
                </div>
                {/foreach}
            </div>
            {/if}
        </div>
    </div>

    {* ============================================ *}
    {* Step 1: Reissue Reason *}
    {* ============================================ *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><span class="sslm-step-number">1</span> {$_LANG.reissue_reason|default:'Reason for Reissue'}</h3>
        </div>
        <div class="sslm-section-body">
            <p class="sslm-help-text" style="margin-bottom: 16px;">
                <i class="fas fa-lightbulb"></i>
                {$_LANG.reissue_reason_guide|default:'Please select the reason for reissuing your certificate. This helps us process your request appropriately.'}
            </p>

            <div class="sslm-form-group">
                <label>{$_LANG.select_reason|default:'Why are you reissuing this certificate?'}</label>
                <select name="reissueReason" id="reissueReason" class="sslm-select">
                    <option value="">{$_LANG.select_reason_placeholder|default:'-- Select Reason --'}</option>
                    <option value="key_compromise">{$_LANG.reason_key_compromise|default:'Private Key Compromised'}</option>
                    <option value="domain_change">{$_LANG.reason_domain_change|default:'Domain Name Change'}</option>
                    <option value="server_change">{$_LANG.reason_server_change|default:'Server Migration'}</option>
                    <option value="lost_key">{$_LANG.reason_lost_key|default:'Lost Private Key'}</option>
                    <option value="csr_change">{$_LANG.reason_csr_change|default:'Need New CSR'}</option>
                    <option value="other">{$_LANG.reason_other|default:'Other'}</option>
                </select>
                <p class="sslm-help-text">
                    <i class="fas fa-info-circle"></i> {$_LANG.reissue_reason_help|default:'If your private key was compromised, we recommend also revoking the current certificate after the new one is issued.'}
                </p>
            </div>

            <div id="revokeWarning" class="sslm-alert sslm-alert-error" style="display: none; margin-top: 16px;">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>{$_LANG.security_notice|default:'Security Notice'}</strong>
                    <p style="margin: 4px 0 0 0;">{$_LANG.key_compromise_warning|default:'If your private key has been compromised, you should also revoke the current certificate after the new one is issued.'}</p>
                </div>
            </div>
        </div>
    </div>

    {* ============================================ *}
    {* Reissue Form *}
    {* ============================================ *}
    <form id="sslm-reissue-form" class="sslm-form" method="post">
        <input type="hidden" name="reissueReason" id="reissueReasonHidden" value="">

        {* ============================================ *}
        {* Step 2: Domain Configuration (like applycert) *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">2</span> {$_LANG.domain_info|default:'Domain Information'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.reissue_domain_guide|default:'You can keep the same domains or modify them. Select a validation method for each domain. For Email validation, options will appear based on your domain.'}
                </p>

                {* Domain List - Same structure as applycert *}
                <div class="sslm-form-group">
                    <label>{$_LANG.domain_name|default:'Domain Name'} <span class="required">*</span></label>
                    <div id="domainList" class="sslm-domain-list">
                        {* Primary domain row *}
                        <div class="sslm-domain-row" data-index="0">
                            <div class="sslm-domain-input-group">
                                <span class="sslm-domain-number">1</span>
                                <input type="text" 
                                       class="sslm-input sslm-domain-input" 
                                       name="domainName" 
                                       placeholder="{$_LANG.common_name|default:'example.com'}"
                                       value="{if isset($cfgData.domainInfo[0].domainName)}{$cfgData.domainInfo[0].domainName|escape:'html'}{/if}"
                                       required>
                                {* DCV Method dropdown - Same as applycert with optgroups *}
                                <select class="sslm-select sslm-dcv-select" name="dcvMethod">
                                    <option value="">{$_LANG.please_choose|default:'-- Select DCV Method --'}</option>
                                    <optgroup label="{$_LANG.file_validation|default:'File/DNS Validation'}">
                                        <option value="HTTP_CSR_HASH" {if isset($cfgData.domainInfo[0].dcvMethod) && $cfgData.domainInfo[0].dcvMethod eq 'HTTP_CSR_HASH'}selected{/if}>
                                            {$_LANG.http_csr_hash|default:'HTTP File Validation'}
                                        </option>
                                        {if $supportOptions.supportHttps}
                                        <option value="HTTPS_CSR_HASH" {if isset($cfgData.domainInfo[0].dcvMethod) && $cfgData.domainInfo[0].dcvMethod eq 'HTTPS_CSR_HASH'}selected{/if}>
                                            {$_LANG.https_csr_hash|default:'HTTPS File Validation'}
                                        </option>
                                        {/if}
                                        <option value="CNAME_CSR_HASH" {if isset($cfgData.domainInfo[0].dcvMethod) && $cfgData.domainInfo[0].dcvMethod eq 'CNAME_CSR_HASH'}selected{/if}>
                                            {$_LANG.cname_csr_hash|default:'DNS CNAME Validation'}
                                        </option>
                                        <option value="DNS_CSR_HASH" {if isset($cfgData.domainInfo[0].dcvMethod) && $cfgData.domainInfo[0].dcvMethod eq 'DNS_CSR_HASH'}selected{/if}>
                                            {$_LANG.dns_csr_hash|default:'DNS TXT Validation'}
                                        </option>
                                    </optgroup>
                                    <optgroup label="{$_LANG.email_validation|default:'Email Validation'}" class="dcv-email-options">
                                        {* Email options populated by JavaScript *}
                                        {if isset($cfgData.domainInfo[0].dcvMethod) && $cfgData.domainInfo[0].dcvMethod|strpos:'@' !== false}
                                        <option value="{$cfgData.domainInfo[0].dcvMethod}" selected>{$cfgData.domainInfo[0].dcvMethod}</option>
                                        {/if}
                                    </optgroup>
                                </select>
                                <button type="button" class="sslm-btn sslm-btn-icon sslm-remove-domain" title="{$_LANG.remove_domain|default:'Remove'}" style="visibility: hidden;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        {* Additional domains from saved data *}
                        {if isset($cfgData.domainInfo) && $cfgData.domainInfo|count > 1}
                            {foreach from=$cfgData.domainInfo item=domain key=idx}
                                {if $idx > 0}
                                <div class="sslm-domain-row" data-index="{$idx}">
                                    <div class="sslm-domain-input-group">
                                        <span class="sslm-domain-number">{$idx+1}</span>
                                        <input type="text" 
                                               class="sslm-input sslm-domain-input" 
                                               name="domainName" 
                                               placeholder="{$_LANG.common_name|default:'example.com'}"
                                               value="{$domain.domainName|escape:'html'}">
                                        <select class="sslm-select sslm-dcv-select" name="dcvMethod">
                                            <option value="">{$_LANG.please_choose|default:'-- Select DCV Method --'}</option>
                                            <optgroup label="{$_LANG.file_validation|default:'File/DNS Validation'}">
                                                <option value="HTTP_CSR_HASH" {if $domain.dcvMethod eq 'HTTP_CSR_HASH'}selected{/if}>{$_LANG.http_csr_hash|default:'HTTP File Validation'}</option>
                                                {if $supportOptions.supportHttps}
                                                <option value="HTTPS_CSR_HASH" {if $domain.dcvMethod eq 'HTTPS_CSR_HASH'}selected{/if}>{$_LANG.https_csr_hash|default:'HTTPS File Validation'}</option>
                                                {/if}
                                                <option value="CNAME_CSR_HASH" {if $domain.dcvMethod eq 'CNAME_CSR_HASH'}selected{/if}>{$_LANG.cname_csr_hash|default:'DNS CNAME Validation'}</option>
                                                <option value="DNS_CSR_HASH" {if $domain.dcvMethod eq 'DNS_CSR_HASH'}selected{/if}>{$_LANG.dns_csr_hash|default:'DNS TXT Validation'}</option>
                                            </optgroup>
                                            <optgroup label="{$_LANG.email_validation|default:'Email Validation'}" class="dcv-email-options">
                                                {if $domain.dcvMethod|strpos:'@' !== false}
                                                <option value="{$domain.dcvMethod}" selected>{$domain.dcvMethod}</option>
                                                {/if}
                                            </optgroup>
                                        </select>
                                        <button type="button" class="sslm-btn sslm-btn-icon sslm-remove-domain" title="{$_LANG.remove_domain|default:'Remove'}">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                {/if}
                            {/foreach}
                        {/if}
                    </div>

                    {* Add Domain & Counter (same as applycert) *}
                    {if $isMultiDomain}
                    <div class="sslm-domain-actions">
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLManager.addDomainRow()">
                            <i class="fas fa-plus"></i> {$_LANG.add_domain|default:'Add Domain'}
                        </button>
                        <span class="sslm-domain-count">
                            {$_LANG.domains_used|default:'Domains'}: <span id="domainCount">{$cfgData.domainInfo|count|default:1}</span> / {$maxDomains|default:10}
                        </span>
                    </div>
                    {/if}
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 3: CSR Configuration (like applycert) *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">3</span> {$_LANG.csr_config|default:'CSR Configuration'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.reissue_csr_guide|default:'A new CSR is required for reissue. You can auto-generate one or paste your own if you already have it.'}
                </p>

                {* CSR Toggle - Same as applycert (sslm-toggle) *}
                <div class="sslm-form-group">
                    <label class="sslm-toggle">
                        <input type="checkbox" id="isManualCsr" {if $cfgData.csr}checked{/if}>
                        <span class="sslm-toggle-slider"></span>
                        <span class="sslm-toggle-label">{$_LANG.is_manual_csr|default:'I have my own CSR'}</span>
                    </label>
                </div>

                {* Auto Generate Section - Same as applycert *}
                <div id="autoGenSection" class="sslm-csr-auto" style="{if $cfgData.csr}display:none{/if}">
                    <div class="sslm-alert sslm-alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>{$_LANG.auto_generate_csr|default:'CSR will be automatically generated based on your domain and contact information.'}</span>
                    </div>
                    <button type="button" id="generateCsrBtn" class="sslm-btn sslm-btn-primary">
                        <i class="fas fa-key"></i> {$_LANG.generate_csr|default:'Generate CSR'}
                    </button>
                </div>

                {* Manual CSR Section - Same as applycert *}
                <div id="csrSection" class="sslm-csr-manual" style="{if !$cfgData.csr}display:none{/if}">
                    <div class="sslm-form-group">
                        <label for="csr">{$_LANG.csr|default:'CSR'} <span class="required">*</span></label>
                        <textarea id="csr" name="csr" class="sslm-textarea sslm-code" rows="8" 
                                  placeholder="-----BEGIN CERTIFICATE REQUEST-----">{$cfgData.csr|escape:'html'}</textarea>
                        <div class="sslm-textarea-actions">
                            <button type="button" id="decodeCsrBtn" class="sslm-btn sslm-btn-sm sslm-btn-secondary">
                                <i class="fas fa-search"></i> {$_LANG.decode_csr|default:'Decode CSR'}
                            </button>
                        </div>
                    </div>

                    {* CSR Decode Result Display - Same as applycert *}
                    <div id="csrDecodeResult" class="sslm-csr-decode-result" style="display:none;">
                        <h4>{$_LANG.csr_info|default:'CSR Information'}</h4>
                        <table class="sslm-info-table">
                            <tr><td>{$_LANG.common_name|default:'Common Name'}:</td><td id="csrCN">-</td></tr>
                            <tr><td>{$_LANG.organization|default:'Organization'}:</td><td id="csrO">-</td></tr>
                            <tr><td>{$_LANG.country|default:'Country'}:</td><td id="csrC">-</td></tr>
                            <tr><td>{$_LANG.state|default:'State'}:</td><td id="csrST">-</td></tr>
                            <tr><td>{$_LANG.city|default:'City'}:</td><td id="csrL">-</td></tr>
                            <tr><td>{$_LANG.key_size|default:'Key Size'}:</td><td id="csrKeySize">-</td></tr>
                            <tr><td>{$_LANG.key_type|default:'Key Type'}:</td><td id="csrKeyType">-</td></tr>
                        </table>
                    </div>

                    {* Private Key (hidden storage) *}
                    <input type="hidden" id="privateKey" name="privateKey" value="{$cfgData.privateKey|escape:'html'}">
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 4: Contact Information (like applycert Step 3) *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">4</span> {$_LANG.admin_contact|default:'Administrator Contact'}</h3>
            </div>
            <div class="sslm-section-body" id="personalcontactPart">
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.contact_section_guide|default:'This information will appear on your certificate. The admin email will receive important notifications about your SSL certificate. All fields marked with * are required by the Certificate Authority.'}
                </p>

                {* Row 1: First Name & Last Name *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.first_name|default:'First Name'} <span class="required">*</span></label>
                        <input type="text" name="adminFirstName" class="sslm-input" 
                            value="{$cfgData.Administrator.firstName|default:$clientsdetails.firstname|escape:'html'}" 
                            required
                            placeholder="e.g. John">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.last_name|default:'Last Name'} <span class="required">*</span></label>
                        <input type="text" name="adminLastName" class="sslm-input" 
                            value="{$cfgData.Administrator.lastName|default:$clientsdetails.lastname|escape:'html'}" 
                            required
                            placeholder="e.g. Doe">
                    </div>
                </div>

                {* Row 2: Email & Phone *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.email_address|default:'Email Address'} <span class="required">*</span></label>
                        <input type="email" name="adminEmail" class="sslm-input" 
                            value="{$cfgData.Administrator.email|default:$clientsdetails.email|escape:'html'}" 
                            required
                            placeholder="e.g. admin@example.com">
                        <p class="sslm-help-text">
                            <i class="fas fa-info-circle"></i> {$_LANG.admin_email_note|default:'Certificate notifications will be sent to this email.'}
                        </p>
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.phone|default:'Phone/Mobile'} <span class="required">*</span></label>
                        <input type="tel" name="adminPhone" class="sslm-input" 
                            value="{$cfgData.Administrator.mobile|default:$clientsdetails.phonenumber|escape:'html'}"
                            required
                            placeholder="e.g. +84.123456789">
                    </div>
                </div>

                {* Row 3: Organization & Job Title *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.organization_name|default:'Organization'} <span class="required">*</span></label>
                        <input type="text" name="adminOrganizationName" class="sslm-input" 
                            value="{$cfgData.Administrator.organization|default:$clientsdetails.companyname|escape:'html'}"
                            required
                            placeholder="e.g. Acme Corporation">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.job_title|default:'Job Title'} <span class="required">*</span></label>
                        <input type="text" name="adminTitle" class="sslm-input" 
                            value="{$cfgData.Administrator.job|escape:'html'}"
                            required
                            placeholder="e.g. IT Manager, CEO, CTO">
                    </div>
                </div>

                {* Row 4: Address & City *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" name="adminAddress" class="sslm-input" 
                            value="{$cfgData.Administrator.address|default:$clientsdetails.address1|escape:'html'}"
                            required
                            placeholder="e.g. 123 Main Street, Suite 100">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.city|default:'City'} <span class="required">*</span></label>
                        <input type="text" name="adminCity" class="sslm-input" 
                            value="{$cfgData.Administrator.city|default:$clientsdetails.city|escape:'html'}"
                            required
                            placeholder="e.g. Ho Chi Minh City">
                    </div>
                </div>

                {* Row 5: State, Postal Code & Country *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.state|default:'State/Province'} <span class="required">*</span></label>
                        <input type="text" name="adminProvince" class="sslm-input" 
                            value="{$cfgData.Administrator.state|default:$clientsdetails.state|escape:'html'}"
                            required
                            placeholder="e.g. Ho Chi Minh">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.post_code|default:'Postal Code'} <span class="required">*</span></label>
                        <input type="text" name="adminPostCode" class="sslm-input" 
                            value="{$cfgData.Administrator.postCode|default:$clientsdetails.postcode|escape:'html'}"
                            required
                            placeholder="e.g. 700000">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.country|default:'Country'} <span class="required">*</span></label>
                        <select name="adminCountry" class="sslm-select" required>
                            <option value="">{$_LANG.select_country|default:'-- Select Country --'}</option>
                            {foreach from=$countries item=country}
                            <option value="{$country.code}" 
                                {if ($cfgData.Administrator.country|default:$clientsdetails.country) eq $country.code}selected{/if}>
                                {$country.name|escape:'html'}
                            </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 5: Organization Information (OV/EV only) *}
        {* ============================================ *}
        {if $requiresOrganization}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">5</span> {$_LANG.organization_info|default:'Organization Information'}</h3>
            </div>
            <div class="sslm-section-body" id="organizationPart">
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.org_section_guide|default:'For OV/EV certificates, your organization details will be verified and displayed in the certificate. Please ensure all information is accurate.'}
                </p>

                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.organization_name|default:'Organization Name'} <span class="required">*</span></label>
                        <input type="text" name="organizationName" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationName|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" name="organizationAddress" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationAddress|escape:'html'}" required>
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.city|default:'City'} <span class="required">*</span></label>
                        <input type="text" name="organizationCity" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationCity|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.state|default:'State/Province'}</label>
                        <input type="text" name="organizationState" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationState|escape:'html'}">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.post_code|default:'Postal Code'} <span class="required">*</span></label>
                        <input type="text" name="organizationPostCode" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationPostCode|escape:'html'}" required>
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.country|default:'Country'} <span class="required">*</span></label>
                        <input type="text" name="organizationCountry" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationCountry|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="text" name="organizationPhone" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationPhone|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.registration_number|default:'Registration Number'}</label>
                        <input type="text" name="organizationRegNumber" class="sslm-input" 
                               value="{$cfgData.organizationInfo.organizationRegNumber|escape:'html'}">
                    </div>
                </div>
            </div>
        </div>
        {/if}

        {* ============================================ *}
        {* Form Actions *}
        {* ============================================ *}
        <div class="sslm-form-actions">
            <a href="{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}" class="sslm-btn sslm-btn-secondary">
                <i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}
            </a>
            <button type="submit" id="submitBtn" class="sslm-btn sslm-btn-primary sslm-btn-lg">
                <i class="fas fa-redo"></i> {$_LANG.submit_reissue|default:'Submit Reissue Request'}
            </button>
        </div>
    </form>

    {* ============================================ *}
    {* Need Help Section (like applycert) *}
    {* ============================================ *}
    <div class="sslm-section" style="margin-top: 24px;">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.need_help|default:'Need Help?'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <div class="sslm-help-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="sslm-help-content">
                        <h4>{$_LANG.help_guide_title|default:'Reissue Guide'}</h4>
                        <p>{$_LANG.help_reissue_guide_desc|default:'Learn about the certificate reissue process and when you should reissue your SSL certificate.'}</p>
                    </div>
                </div>
                <div class="sslm-help-item">
                    <div class="sslm-help-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="sslm-help-content">
                        <h4>{$_LANG.help_support_title|default:'Contact Support'}</h4>
                        <p>{$_LANG.help_support_desc|default:'Need assistance? Our support team is available 24/7 to help you with your SSL certificate.'}</p>
                        <a href="{$WEB_ROOT}/submitticket.php" class="sslm-btn sslm-btn-sm sslm-btn-outline">
                            <i class="fas fa-envelope"></i> {$_LANG.open_ticket|default:'Open a Ticket'}
                        </a>
                    </div>
                </div>
                <div class="sslm-help-item">
                    <div class="sslm-help-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="sslm-help-content">
                        <h4>{$_LANG.help_installation_title|default:'SSL Installation Service'}</h4>
                        <p>{$_LANG.help_installation_desc|default:'Don\'t want to install it yourself? Our experts can install your SSL certificate for you quickly and securely.'}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* ============================================ *}
{* JavaScript Configuration *}
{* ============================================ *}
<script>
window.sslmConfig = {
    ajaxUrl: "{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}",
    serviceid: "{$serviceid}",
    maxDomains: {$maxDomains|default:10},
    isMultiDomain: {if $isMultiDomain}true{else}false{/if},
    isReissue: true,
    configData: {$configData|json_encode nofilter},
    supportOptions: {
        supportHttps: {if $supportOptions.supportHttps}true{else}false{/if},
        supportNormal: {if $supportOptions.supportNormal}true{else}false{/if}
    },
    lang: {
        validation_error: '{$_LANG.validation_error|default:"Please fill in all required fields"}',
        submit_success: '{$_LANG.submit_success|default:"Reissue request submitted successfully"}',
        submit_failed: '{$_LANG.submit_failed|default:"Submission failed. Please try again."}',
        at_least_one_domain: '{$_LANG.at_least_one_domain|default:"At least one domain is required"}',
        submitting: '{$_LANG.submitting|default:"Submitting..."}',
        generating_csr: '{$_LANG.generating_csr|default:"Generating CSR..."}',
        csr_generated: '{$_LANG.csr_generated|default:"CSR generated successfully"}',
        domain_required: '{$_LANG.domain_required|default:"Please enter a domain name first"}',
        select_method: '{$_LANG.select_method|default:"Please select a validation method"}',
        please_choose: '{$_LANG.please_choose|default:"-- Select DCV Method --"}'
    }
};

// Show warning for key compromise
document.getElementById('reissueReason').addEventListener('change', function() {
    var warning = document.getElementById('revokeWarning');
    var hidden = document.getElementById('reissueReasonHidden');
    hidden.value = this.value;
    
    if (this.value === 'key_compromise') {
        warning.style.display = 'flex';
    } else {
        warning.style.display = 'none';
    }
});
</script>

{* Load JavaScript - reuses ssl-manager.js *}
<script src="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/js/ssl-manager.js"></script>