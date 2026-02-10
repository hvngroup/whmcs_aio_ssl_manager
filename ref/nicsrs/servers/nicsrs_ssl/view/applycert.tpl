{**
 * NicSRS SSL Module - Apply Certificate Template
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
            {$_LANG.configure_certificate|default:'Configure Certificate'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-{$sslValidationType|default:'dv'}">{$sslValidationType|upper|default:'DV'}</span>
        </div>
    </div>

    {* ============================================ *}
    {* NEW: Progress Steps *}
    {* ============================================ *}
    <div class="sslm-progress">
        <div class="sslm-progress-step active">
            <div class="sslm-progress-icon"><i class="fas fa-edit"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_configure|default:'Configure'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_submit|default:'Submit'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-check-circle"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_validation|default:'Validation'}</div>
        </div>
        <div class="sslm-progress-step">
            <div class="sslm-progress-icon"><i class="fas fa-certificate"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_issued|default:'Issued'}</div>
        </div>
    </div>

    {* ============================================ *}
    {* NEW: Draft Status Card *}
    {* ============================================ *}
    {if $configData.isDraft}
    <div class="sslm-status-card" style="margin-bottom: 20px;">
        <div class="sslm-status-icon info">
            <i class="fas fa-save"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.draft_saved|default:'Draft Saved'}</div>
            <div class="sslm-status-desc">
                {$_LANG.draft_desc|default:'Your progress has been saved. You can continue where you left off.'}
                {if $configData.lastSaved}
                <br><small>{$_LANG.last_saved|default:'Last saved'}: {$configData.lastSaved}</small>
                {/if}
            </div>
        </div>
    </div>
    {else}
    {* NEW: Welcome Alert for new applications *}
    <div class="sslm-alert sslm-alert-info">
        <i class="fas fa-info-circle"></i>
        <span>{$_LANG.apply_welcome|default:'Please fill in the information below to request your SSL certificate. Fields marked with * are required.'}</span>
    </div>
    {/if}

    {* Apply Form *}
    <form id="sslm-apply-form" class="sslm-form" method="post">
        
        {* ============================================ *}
        {* Step 1: Domain Configuration - PRESERVED *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">1</span> {$_LANG.domain_info|default:'Domain Information'}</h3>
            </div>
            <div class="sslm-section-body">
                {* NEW: Section guidance text *}
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.domain_section_guide|default:'Enter the domain name(s) you want to protect and select a validation method. For Email validation, options will appear based on your domain.'}
                </p>

                {* Is Renew Option - PRESERVED *}
                <div class="sslm-form-group">
                    <label>{$_LANG.is_renew|default:'Is this a renewal?'}</label>
                    <div class="sslm-radio-group">
                        <label class="sslm-radio">
                            <input type="radio" name="isRenew" value="0" 
                                {if (!$configData.originalfromOthers || $configData.originalfromOthers eq '0') && (!$configData.isRenew || $configData.isRenew eq '0')}checked{/if}>
                            <span>{$_LANG.is_renew_option_new|default:'No, new certificate'}</span>
                        </label>
                        <label class="sslm-radio">
                            <input type="radio" name="isRenew" value="1" 
                                {if $configData.originalfromOthers eq '1' || $configData.isRenew eq '1'}checked{/if}>
                            <span>{$_LANG.is_renew_option_renew|default:'Yes, renewal'}</span>
                        </label>
                    </div>
                    <p class="sslm-help-text">{$_LANG.is_renew_des|default:'Select "Yes" if renewing an existing certificate to receive bonus time.'}</p>
                </div>

                {* Domain List - PRESERVED STRUCTURE *}
                <div class="sslm-form-group">
                    <label>{$_LANG.domain_name|default:'Domain Name'} <span class="required">*</span></label>
                    <div id="domainList" class="sslm-domain-list">
                        {* Primary domain row - PRESERVED *}
                        <div class="sslm-domain-row" data-index="0">
                            <div class="sslm-domain-input-group">
                                <span class="sslm-domain-number">1</span>
                                <input type="text" 
                                       class="sslm-input sslm-domain-input" 
                                       name="domainName" 
                                       placeholder="{$_LANG.common_name|default:'example.com'}"
                                       value="{if isset($configData.domainInfo[0].domainName)}{$configData.domainInfo[0].domainName|escape:'html'}{/if}"
                                       required>
                                {* DCV Method dropdown - PRESERVED with email optgroup *}
                                <select class="sslm-select sslm-dcv-select" name="dcvMethod">
                                    <option value="">{$_LANG.please_choose|default:'-- Select DCV Method --'}</option>
                                    <optgroup label="{$_LANG.file_validation|default:'File/DNS Validation'}">
                                        <option value="HTTP_CSR_HASH" {if isset($configData.domainInfo[0].dcvMethod) && $configData.domainInfo[0].dcvMethod eq 'HTTP_CSR_HASH'}selected{/if}>
                                            {$_LANG.http_csr_hash|default:'HTTP File Validation'}
                                        </option>
                                        {if $supportOptions.supportHttps}
                                        <option value="HTTPS_CSR_HASH" {if isset($configData.domainInfo[0].dcvMethod) && $configData.domainInfo[0].dcvMethod eq 'HTTPS_CSR_HASH'}selected{/if}>
                                            {$_LANG.https_csr_hash|default:'HTTPS File Validation'}
                                        </option>
                                        {/if}
                                        <option value="CNAME_CSR_HASH" {if isset($configData.domainInfo[0].dcvMethod) && $configData.domainInfo[0].dcvMethod eq 'CNAME_CSR_HASH'}selected{/if}>
                                            {$_LANG.cname_csr_hash|default:'DNS CNAME Validation'}
                                        </option>
                                        <option value="DNS_CSR_HASH" {if isset($configData.domainInfo[0].dcvMethod) && $configData.domainInfo[0].dcvMethod eq 'DNS_CSR_HASH'}selected{/if}>
                                            {$_LANG.dns_csr_hash|default:'DNS TXT Validation'}
                                        </option>
                                    </optgroup>
                                    <optgroup label="{$_LANG.email_validation|default:'Email Validation'}" class="dcv-email-options">
                                        {* Email options populated by JavaScript *}
                                        {if isset($configData.domainInfo[0].dcvMethod) && $configData.domainInfo[0].dcvMethod|strpos:'@' !== false}
                                        <option value="{$configData.domainInfo[0].dcvMethod}" selected>{$configData.domainInfo[0].dcvMethod}</option>
                                        {/if}
                                    </optgroup>
                                </select>
                                <button type="button" class="sslm-btn sslm-btn-icon sslm-remove-domain" title="{$_LANG.remove_domain|default:'Remove'}" style="visibility: hidden;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        {* Additional domains from saved data - PRESERVED *}
                        {if isset($configData.domainInfo) && $configData.domainInfo|count > 1}
                            {foreach from=$configData.domainInfo item=domain key=idx}
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
                    
                    {* Add Domain Button - PRESERVED *}
                    {if $isMultiDomain || $maxDomains > 1}
                    <div class="sslm-domain-actions">
                        <button type="button" id="addDomainBtn" class="sslm-btn sslm-btn-secondary">
                            <i class="fas fa-plus"></i> {$_LANG.add_domain|default:'Add Domain'}
                        </button>
                        <span class="sslm-domain-count">
                            {$_LANG.max_domain|default:'Maximum domains'}: {$maxDomains|default:1}
                        </span>
                    </div>
                    {/if}
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 2: CSR Configuration - PRESERVED *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">2</span> {$_LANG.csr_configuration|default:'CSR Configuration'}</h3>
            </div>
            <div class="sslm-section-body">
                {* NEW: Section guidance text *}
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.csr_section_guide|default:'A CSR (Certificate Signing Request) contains your domain and organization info. You can auto-generate one or paste your own if you already have it.'}
                </p>

                {* CSR Toggle - PRESERVED *}
                <div class="sslm-form-group">
                    <label class="sslm-toggle">
                        <input type="checkbox" id="isManualCsr" {if $configData.csr}checked{/if}>
                        <span class="sslm-toggle-slider"></span>
                        <span class="sslm-toggle-label">{$_LANG.is_manual_csr|default:'I have my own CSR'}</span>
                    </label>
                </div>

                {* Auto Generate Section - PRESERVED *}
                <div id="autoGenSection" class="sslm-csr-auto" style="{if $configData.csr}display:none{/if}">
                    <div class="sslm-alert sslm-alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>{$_LANG.auto_generate_csr|default:'CSR will be automatically generated based on your domain and contact information.'}</span>
                    </div>
                    <button type="button" id="generateCsrBtn" class="sslm-btn sslm-btn-primary">
                        <i class="fas fa-key"></i> {$_LANG.generate_csr|default:'Generate CSR'}
                    </button>
                </div>

                {* Manual CSR Section - PRESERVED *}
                <div id="csrSection" class="sslm-csr-manual" style="{if !$configData.csr}display:none{/if}">
                    <div class="sslm-form-group">
                        <label for="csr">{$_LANG.csr|default:'CSR'} <span class="required">*</span></label>
                        <textarea id="csr" name="csr" class="sslm-textarea sslm-code" rows="8" 
                                  placeholder="-----BEGIN CERTIFICATE REQUEST-----">{$configData.csr|escape:'html'}</textarea>
                        <div class="sslm-textarea-actions">
                            <button type="button" id="decodeCsrBtn" class="sslm-btn sslm-btn-sm sslm-btn-secondary">
                                <i class="fas fa-search"></i> {$_LANG.decode_csr|default:'Decode CSR'}
                            </button>
                        </div>
                    </div>
                    
                    {* CSR Decode Result Display - PRESERVED *}
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
                    
                    {* Private Key (hidden storage) - PRESERVED *}
                    <input type="hidden" id="privateKey" name="privateKey" value="{$configData.privateKey|escape:'html'}">
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 3: Contact Information - PRESERVED *}
        {* ============================================ *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">3</span> {$_LANG.admin_contact|default:'Administrator Contact'}</h3>
            </div>
            <div class="sslm-section-body" id="personalcontactPart">
                {* Section guidance text *}
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.contact_section_guide|default:'This information will appear on your certificate. The admin email will receive important notifications about your SSL certificate. All fields marked with * are required by the Certificate Authority.'}
                </p>

                {* Row 1: First Name & Last Name (REQUIRED) *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.first_name|default:'First Name'} <span class="required">*</span></label>
                        <input type="text" name="adminFirstName" class="sslm-input" 
                            value="{$configData.Administrator.firstName|default:$client.firstname|escape:'html'}" 
                            required
                            placeholder="e.g. John">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.last_name|default:'Last Name'} <span class="required">*</span></label>
                        <input type="text" name="adminLastName" class="sslm-input" 
                            value="{$configData.Administrator.lastName|default:$client.lastname|escape:'html'}" 
                            required
                            placeholder="e.g. Doe">
                    </div>
                </div>

                {* Row 2: Email & Phone (REQUIRED) *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.email_address|default:'Email Address'} <span class="required">*</span></label>
                        <input type="email" name="adminEmail" class="sslm-input" 
                            value="{$configData.Administrator.email|default:$client.email|escape:'html'}" 
                            required
                            placeholder="e.g. admin@example.com">
                        <p class="sslm-help-text">
                            <i class="fas fa-info-circle"></i> {$_LANG.admin_email_note|default:'Certificate notifications will be sent to this email.'}
                        </p>
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.phone|default:'Phone/Mobile'} <span class="required">*</span></label>
                        <input type="tel" name="adminPhone" class="sslm-input" 
                            value="{$configData.Administrator.mobile|default:$client.phonenumber|escape:'html'}"
                            required
                            placeholder="e.g. +84.123456789">
                    </div>
                </div>

                {* Row 3: Organization & Job Title (REQUIRED) *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.organization_name|default:'Organization'} <span class="required">*</span></label>
                        <input type="text" name="adminOrganizationName" class="sslm-input" 
                            value="{$configData.Administrator.organization|default:$client.companyname|escape:'html'}"
                            required
                            placeholder="e.g. Acme Corporation">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.job_title|default:'Job Title'} <span class="required">*</span></label>
                        <input type="text" name="adminTitle" class="sslm-input" 
                            value="{$configData.Administrator.job|escape:'html'}"
                            required
                            placeholder="e.g. IT Manager, CEO, CTO">
                    </div>
                </div>

                {* Row 4: City & Address (REQUIRED) *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" name="adminAddress" class="sslm-input" 
                            value="{$configData.Administrator.address|default:$client.address1|escape:'html'}"
                            required
                            placeholder="e.g. 123 Main Street, Suite 100">
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.city|default:'City'} <span class="required">*</span></label>
                        <input type="text" name="adminCity" class="sslm-input" 
                            value="{$configData.Administrator.city|default:$client.city|escape:'html'}"
                            required
                            placeholder="e.g. Ho Chi Minh City">
                    </div>                    
                </div>

                {* Row 5: State & Postal Code & Country (REQUIRED) *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.state|default:'State/Province'} <span class="required">*</span></label>
                        <input type="text" name="adminProvince" class="sslm-input" 
                            value="{$configData.Administrator.state|default:$client.state|escape:'html'}"
                            required
                            placeholder="e.g. Ho Chi Minh">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.post_code|default:'Postal Code'} <span class="required">*</span></label>
                        <input type="text" name="adminPostCode" class="sslm-input" 
                            value="{$configData.Administrator.postCode|default:$client.postcode|escape:'html'}"
                            required
                            placeholder="e.g. 700000">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.country|default:'Country'} <span class="required">*</span></label>
                        <select name="adminCountry" class="sslm-select" required>
                            <option value="">{$_LANG.select_country|default:'-- Select Country --'}</option>
                            {foreach from=$countries item=country}
                            <option value="{$country.code}" 
                                {if ($configData.Administrator.country|default:$client.country) eq $country.code}selected{/if}>
                                {$country.name|escape:'html'}
                            </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {* ============================================ *}
        {* Step 4: Organization Information (OV/EV) - PRESERVED *}
        {* ============================================ *}
        {if $requiresOrganization}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">4</span> {$_LANG.organization_info|default:'Organization Information'}</h3>
            </div>
            <div class="sslm-section-body" id="organizationPart">
                {* NEW: Section guidance text *}
                <p class="sslm-help-text" style="margin-bottom: 16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.org_section_guide|default:'For OV/EV certificates, your organization details will be verified and displayed in the certificate. Please ensure all information is accurate.'}
                </p>

                {* All organization fields - PRESERVED EXACTLY *}
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.organization_name|default:'Organization Name'} <span class="required">*</span></label>
                        <input type="text" name="organizationName" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationName|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" name="organizationAddress" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationAddress|escape:'html'}" required>
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.city|default:'City'} <span class="required">*</span></label>
                        <input type="text" name="organizationCity" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationCity|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.state|default:'State/Province'}</label>
                        <input type="text" name="organizationState" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationState|escape:'html'}">
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.post_code|default:'Postal Code'} <span class="required">*</span></label>
                        <input type="text" name="organizationPostCode" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationPostCode|escape:'html'}" required>
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.country|default:'Country'} <span class="required">*</span></label>
                        <input type="text" name="organizationCountry" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationCountry|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="text" name="organizationPhone" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationPhone|escape:'html'}" required>
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.registration_number|default:'Registration Number'}</label>
                        <input type="text" name="organizationRegNumber" class="sslm-input" 
                               value="{$configData.organizationInfo.organizationRegNumber|escape:'html'}">
                    </div>
                </div>
            </div>
        </div>
        {/if}

        {* ============================================ *}
        {* Form Actions - PRESERVED *}
        {* ============================================ *}
        <div class="sslm-form-actions">
            <button type="button" id="saveBtn" class="sslm-btn sslm-btn-secondary" onclick="saveDraft()">
                <i class="fas fa-save"></i> {$_LANG.save_draft|default:'Save Draft'}
            </button>
            <button type="submit" id="submitBtn" class="sslm-btn sslm-btn-primary">
                <i class="fas fa-paper-plane"></i> {$_LANG.submit_request|default:'Submit Request'}
            </button>
        </div>
    </form>

    {* ============================================ *}
    {* NEW: Help Section with SSL Installation Service Promo *}
    {* ============================================ *}
    <div class="sslm-section" style="margin-top: 24px;">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.need_help|default:'Need Help?'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <h4><i class="fas fa-file-alt"></i> {$_LANG.what_is_dcv|default:'What is DCV?'}</h4>
                    <p>{$_LANG.dcv_explanation|default:'Domain Control Validation (DCV) proves you own the domain. Choose HTTP file, DNS record, or email validation based on your preference.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-key"></i> {$_LANG.what_is_csr|default:'What is CSR?'}</h4>
                    <p>{$_LANG.csr_explanation|default:'A Certificate Signing Request contains your domain info. Click "Generate CSR" to create one automatically, or paste your own if you have it.'}</p>
                </div>
                <div class="sslm-help-item" style="background: linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%); border: 1px solid #1890ff;">
                    <h4 style="color: #0050b3;"><i class="fas fa-tools"></i> {$_LANG.installation_service|default:'SSL Installation Service'}</h4>
                    <p style="color: #003a8c;">{$_LANG.installation_service_desc|default:'Don\'t want to install SSL yourself? Our experts can install and configure your SSL certificate for you. Fast, secure, and hassle-free!'}</p>
                    <a href="{$WEB_ROOT}/cart.php?a=add&pid=ssl-installation" class="sslm-btn sslm-btn-primary sslm-btn-sm" style="margin-top: 12px;">
                        <i class="fas fa-shopping-cart"></i> {$_LANG.order_installation|default:'Order Installation Service'}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{* ============================================ *}
{* Domain Row Template for JavaScript - PRESERVED *}
{* ============================================ *}
<template id="domainRowTemplate">
    <div class="sslm-domain-row">
        <div class="sslm-domain-input-group">
            <span class="sslm-domain-number"></span>
            <input type="text" 
                   class="sslm-input sslm-domain-input" 
                   name="domainName" 
                   placeholder="{$_LANG.common_name|default:'example.com'}">
            <select class="sslm-select sslm-dcv-select" name="dcvMethod">
                <option value="">{$_LANG.please_choose|default:'-- Select DCV Method --'}</option>
                <optgroup label="{$_LANG.file_validation|default:'File/DNS Validation'}">
                    <option value="HTTP_CSR_HASH">{$_LANG.http_csr_hash|default:'HTTP File Validation'}</option>
                    {if $supportOptions.supportHttps}
                    <option value="HTTPS_CSR_HASH">{$_LANG.https_csr_hash|default:'HTTPS File Validation'}</option>
                    {/if}
                    <option value="CNAME_CSR_HASH">{$_LANG.cname_csr_hash|default:'DNS CNAME Validation'}</option>
                    <option value="DNS_CSR_HASH">{$_LANG.dns_csr_hash|default:'DNS TXT Validation'}</option>
                </optgroup>
                <optgroup label="{$_LANG.email_validation|default:'Email Validation'}" class="dcv-email-options">
                    {* Email options will be populated by JavaScript *}
                </optgroup>
            </select>
            <button type="button" class="sslm-btn sslm-btn-icon sslm-remove-domain" title="{$_LANG.remove_domain|default:'Remove'}">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>

{* ============================================ *}
{* JavaScript Configuration - PRESERVED *}
{* ============================================ *}
<script>
window.sslmConfig = {
    ajaxUrl: '{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}',
    serviceid: '{$serviceid}',
    maxDomains: {$maxDomains|default:1},
    isMultiDomain: {if $isMultiDomain}true{else}false{/if},
    supportHttps: {if $supportOptions.supportHttps}true{else}false{/if},
    requiresOrganization: {if $requiresOrganization}true{else}false{/if},
    configData: {$configData|@json_encode nofilter},
    lang: {
        domain_required: '{$_LANG.domain_required|default:"Domain is required"}',
        validation_error: '{$_LANG.validation_error|default:"Please fill in all required fields"}',
        draft_saved: '{$_LANG.draft_saved|default:"Draft saved successfully"}',
        submit_success: '{$_LANG.submit_success|default:"Request submitted successfully"}',
        csr_generated: '{$_LANG.csr_generated|default:"CSR generated successfully"}',
        csr_decoded: '{$_LANG.csr_decoded|default:"CSR decoded successfully"}',
        enter_csr: '{$_LANG.enter_csr|default:"Please enter a CSR"}',
        invalid_csr: '{$_LANG.invalid_csr|default:"Invalid CSR format"}',
        error: '{$_LANG.error|default:"An error occurred"}',
        network_error: '{$_LANG.network_error|default:"Network error"}',
        max_domains_reached: '{$_LANG.max_domains_reached|default:"Maximum domains reached"}',
        please_choose: '{$_LANG.please_choose|default:"-- Select DCV Method --"}'
    }
};
</script>

{* Load JavaScript - PRESERVED *}
<script src="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/js/ssl-manager.js"></script>