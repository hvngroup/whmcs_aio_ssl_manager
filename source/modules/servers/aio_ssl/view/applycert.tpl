{**
 * AIO SSL Manager — Apply Certificate
 * Adapted from NicSRS ref: single-page form with sections
 * Sections: 1.Domain/SAN → 2.CSR → 3.Contacts(OV/EV) → 4.DCV → Submit
 * NO provider name shown to client
 *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">

    {* ── Header ── *}
    <div class="sslm-header">
        <h2 class="sslm-title">
            <i class="fas fa-shield-alt"></i>
            {$_LANG.configure_certificate|default:'Configure SSL Certificate'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-{$sslValidationType|default:'dv'}">{$sslValidationType|upper|default:'DV'}</span>
        </div>
    </div>

    {* ── Progress Steps ── *}
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

    {* ── Draft Notice / Welcome ── *}
    {if $hasDraft}
    <div class="sslm-status-card">
        <div class="sslm-status-icon info"><i class="fas fa-save"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.draft_found|default:'Draft Saved'}</div>
            <div class="sslm-status-desc">
                {$_LANG.draft_resume|default:'Your previous progress has been saved. You can continue where you left off.'}
                {if $lastSaved}<br><small>{$_LANG.last_saved|default:'Last saved'}: {$lastSaved|escape:'html'}</small>{/if}
            </div>
        </div>
    </div>
    {else}
    <div class="sslm-status-card">
        <div class="sslm-status-icon info"><i class="fas fa-edit"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$_LANG.configure_your_cert|default:'Configure Your SSL Certificate'}</div>
            <div class="sslm-status-desc">{$_LANG.apply_welcome|default:'Please fill in the information below to request your SSL certificate. Fields marked with * are required. You can save a draft at any time and come back later.'}</div>
        </div>
    </div>
    {/if}

    {* ── Error Display ── *}
    <div class="sslm-alert sslm-alert-danger" id="global-error" style="display:none;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="global-error-msg"></span>
    </div>

    {* ══════════════════════════════════════════════ *}
    {* FORM START                                     *}
    {* ══════════════════════════════════════════════ *}
    <form id="ssl-apply-form" onsubmit="return false;">
        <input type="hidden" name="serviceid" value="{$serviceid}" />

        {* ── Section 1: Domain Information ── *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">1</span> {$_LANG.domain_info|default:'Domain Information'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.domain_section_guide|default:'Enter the domain name(s) you want to protect and select a validation method. For Email validation, options will appear based on your domain.'}
                </p>

                {* Renewal Option *}
                <div class="sslm-form-group">
                    <label>{$_LANG.is_renew|default:'Is this a renewal?'}</label>
                    <div class="sslm-radio-group">
                        <label class="sslm-radio">
                            <input type="radio" name="isRenew" value="0" {if !$isRenew || $isRenew eq '0'}checked{/if}>
                            <span>{$_LANG.is_renew_option_new|default:'No, new certificate'}</span>
                        </label>
                        <label class="sslm-radio">
                            <input type="radio" name="isRenew" value="1" {if $isRenew eq '1'}checked{/if}>
                            <span>{$_LANG.is_renew_option_renew|default:'Yes, renewal'}</span>
                        </label>
                    </div>
                    <div class="sslm-form-hint">{$_LANG.is_renew_des|default:'Select "Yes" if renewing an existing certificate to receive bonus validity time.'}</div>
                </div>

                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-full">
                        <label>{$_LANG.primary_domain|default:'Primary Domain'}</label>
                        <input type="text" class="sslm-input" value="{$domain|escape:'html'}" readonly
                               style="background:#f5f5f5;" />
                        <div class="sslm-form-hint">{$_LANG.domain_from_service|default:'This domain is set from your service configuration.'}</div>
                    </div>
                </div>

                {* Multi-domain SAN *}
                {if $isMultiDomain}
                <div class="sslm-form-group" style="margin-top:12px;">
                    <label>{$_LANG.additional_domains|default:'Additional Domains (SAN)'}</label>
                    <p class="sslm-form-hint">{$_LANG.san_hint|default:'You can add up to'} {$maxDomains-1} {$_LANG.additional_domains_suffix|default:'additional domains.'}</p>
                    <div id="san-domains-list">
                        {if $configData.domainInfo}
                            {foreach from=$configData.domainInfo item=di key=k}
                                {if $k > 0}
                                <div class="sslm-san-row">
                                    <input type="text" name="sanDomains[]" class="sslm-input" value="{$di.domainName|escape:'html'}" placeholder="additional-domain.com" />
                                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-danger sslm-btn-outline" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                                </div>
                                {/if}
                            {/foreach}
                        {/if}
                    </div>
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="addSanDomain()">
                        <i class="fas fa-plus"></i> {$_LANG.add_domain|default:'Add Domain'}
                    </button>
                </div>
                {/if}
            </div>
        </div>

        {* ── Section 2: CSR Configuration ── *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">2</span> {$_LANG.csr_configuration|default:'CSR Configuration'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.csr_section_guide|default:'A CSR (Certificate Signing Request) contains your domain and organization info. You can auto-generate one or paste your own if you already have it.'}
                </p>

                {* Toggle: manual vs auto *}
                <div class="sslm-form-group">
                    <label class="sslm-toggle">
                        <input type="checkbox" id="isManualCsr" {if $configData.csr}checked{/if}>
                        <span class="sslm-toggle-slider"></span>
                        <span class="sslm-toggle-label">{$_LANG.is_manual_csr|default:'I have my own CSR'}</span>
                    </label>
                </div>

                {* Auto-generate section *}
                <div id="autoGenSection" class="sslm-csr-auto" style="{if $configData.csr}display:none{/if}">
                    <div class="sslm-alert sslm-alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>{$_LANG.auto_generate_csr|default:'CSR will be automatically generated based on your domain and contact information.'}</span>
                    </div>
                    <button type="button" id="generateCsrBtn" class="sslm-btn sslm-btn-primary">
                        <i class="fas fa-key"></i> {$_LANG.generate_csr|default:'Generate CSR'}
                    </button>
                </div>

                {* Manual CSR paste *}
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

                    {* CSR decode result *}
                    <div id="csrDecodeResult" class="sslm-csr-decode-result" style="display:none;">
                        <h4>{$_LANG.csr_info|default:'CSR Information'}</h4>
                        <table class="sslm-info-table">
                            <tr><td>{$_LANG.common_name|default:'Common Name'}:</td><td id="csrCN">-</td></tr>
                            <tr><td>{$_LANG.organization|default:'Organization'}:</td><td id="csrO">-</td></tr>
                            <tr><td>{$_LANG.country|default:'Country'}:</td><td id="csrC">-</td></tr>
                            <tr><td>{$_LANG.state|default:'State'}:</td><td id="csrST">-</td></tr>
                            <tr><td>{$_LANG.city|default:'City'}:</td><td id="csrL">-</td></tr>
                            <tr><td>{$_LANG.key_size|default:'Key Size'}:</td><td id="csrKeySize">-</td></tr>
                        </table>
                    </div>

                    <input type="hidden" id="privateKey" name="privateKey" value="{$configData.privateKey|escape:'html'}">
                </div>
            </div>
        </div>

        {* ── Section 3: Admin Contact (always shown) ── *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">3</span> {$_LANG.admin_contact|default:'Administrator Contact'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;">
                    <i class="fas fa-lightbulb"></i>
                    {$_LANG.contact_section_guide|default:'This information will appear on your certificate. The admin email will receive important notifications about your SSL certificate.'}
                </p>

                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.first_name|default:'First Name'} <span class="required">*</span></label>
                        <input type="text" name="adminFirstName" class="sslm-input"
                               value="{$configData.Administrator.firstName|default:$clientsdetails.firstname|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.last_name|default:'Last Name'} <span class="required">*</span></label>
                        <input type="text" name="adminLastName" class="sslm-input"
                               value="{$configData.Administrator.lastName|default:$clientsdetails.lastname|escape:'html'}" required />
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.email|default:'Email'} <span class="required">*</span></label>
                        <input type="email" name="adminEmail" class="sslm-input"
                               value="{$configData.Administrator.email|default:$clientsdetails.email|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="text" name="adminPhone" class="sslm-input"
                               value="{$configData.Administrator.phone|default:$clientsdetails.phonenumber|escape:'html'}" required />
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.title_job|default:'Job Title'}</label>
                        <input type="text" name="adminJobTitle" class="sslm-input"
                               value="{$configData.Administrator.jobTitle|escape:'html'}" />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.organization|default:'Organization'}</label>
                        <input type="text" name="adminOrganization" class="sslm-input"
                               value="{$configData.Administrator.organization|default:$clientsdetails.companyname|escape:'html'}" />
                    </div>
                </div>
            </div>
        </div>

        {* ── Section 4: Organization (OV/EV only) ── *}
        {if $sslValidationType != 'dv'}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">4</span> {$_LANG.organization_details|default:'Organization Details'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;">
                    <i class="fas fa-building"></i>
                    {$_LANG.org_section_guide|default:'Organization details are required for OV/EV certificates and will be verified by the Certificate Authority.'}
                </p>

                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.org_name|default:'Organization Name'} <span class="required">*</span></label>
                        <input type="text" name="organizationName" class="sslm-input"
                               value="{$configData.organizationInfo.organizationName|default:$clientsdetails.companyname|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.org_division|default:'Division / Department'}</label>
                        <input type="text" name="organizationDivision" class="sslm-input"
                               value="{$configData.organizationInfo.organizationDivision|escape:'html'}" />
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" name="organizationAddress" class="sslm-input"
                               value="{$configData.organizationInfo.organizationAddress|default:$clientsdetails.address1|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.city|default:'City'} <span class="required">*</span></label>
                        <input type="text" name="organizationCity" class="sslm-input"
                               value="{$configData.organizationInfo.organizationCity|default:$clientsdetails.city|escape:'html'}" required />
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.state|default:'State/Province'}</label>
                        <input type="text" name="organizationState" class="sslm-input"
                               value="{$configData.organizationInfo.organizationState|default:$clientsdetails.state|escape:'html'}" />
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.post_code|default:'Postal Code'} <span class="required">*</span></label>
                        <input type="text" name="organizationPostCode" class="sslm-input"
                               value="{$configData.organizationInfo.organizationPostCode|default:$clientsdetails.postcode|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-4">
                        <label>{$_LANG.country|default:'Country'} <span class="required">*</span></label>
                        <input type="text" name="organizationCountry" class="sslm-input" maxlength="2"
                               value="{$configData.organizationInfo.organizationCountry|default:$clientsdetails.countrycode|escape:'html'}" required />
                    </div>
                </div>
                <div class="sslm-form-row">
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="text" name="organizationPhone" class="sslm-input"
                               value="{$configData.organizationInfo.organizationPhone|default:$clientsdetails.phonenumber|escape:'html'}" required />
                    </div>
                    <div class="sslm-form-group sslm-col-6">
                        <label>{$_LANG.registration_number|default:'Registration Number'}</label>
                        <input type="text" name="organizationRegNumber" class="sslm-input"
                               value="{$configData.organizationInfo.organizationRegNumber|escape:'html'}" />
                    </div>
                </div>
            </div>
        </div>
        {/if}

        {* ── Section 5: DCV Method ── *}
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><span class="sslm-step-number">{if $sslValidationType != 'dv'}5{else}4{/if}</span> {$_LANG.dcv_method|default:'Domain Validation Method'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-help-text" style="margin-bottom:16px;">
                    <i class="fas fa-check-double"></i>
                    {$_LANG.dcv_section_guide|default:'Choose how you want to prove ownership of your domain. Email validation is the most common method.'}
                </p>

                <div class="sslm-form-group">
                    <label>{$_LANG.validation_method|default:'Validation Method'} <span class="required">*</span></label>
                    <select name="dcvMethod" id="dcvMethodSelect" class="sslm-select" onchange="toggleDcvEmail()">
                        <option value="email" {if ($configData.dcv_method|default:'email') == 'email'}selected{/if}>{$_LANG.email_validation|default:'Email Validation'}</option>
                        <option value="http" {if $configData.dcv_method == 'http'}selected{/if}>{$_LANG.http_file|default:'HTTP File Validation'}</option>
                        <option value="https" {if $configData.dcv_method == 'https'}selected{/if}>{$_LANG.https_file|default:'HTTPS File Validation'}</option>
                        <option value="dns" {if $configData.dcv_method == 'dns'}selected{/if}>{$_LANG.dns_cname|default:'DNS CNAME Validation'}</option>
                    </select>
                </div>

                {* Email selector (shown for email method) *}
                <div id="dcvEmailSection" class="sslm-form-group" {if ($configData.dcv_method|default:'email') != 'email'}style="display:none"{/if}>
                    <label>{$_LANG.approver_email|default:'Approver Email'} <span class="required">*</span></label>
                    <select name="approveremail" id="dcvEmailSelect" class="sslm-select">
                        <option value="">{$_LANG.loading|default:'Loading...'}</option>
                    </select>
                    <div class="sslm-form-hint">{$_LANG.approver_email_hint|default:'A verification email will be sent to this address.'}</div>
                </div>
            </div>
        </div>

        {* ── Form Actions ── *}
        <div class="sslm-form-actions">
            <button type="button" id="saveBtn" class="sslm-btn sslm-btn-secondary" onclick="saveDraft()">
                <i class="fas fa-save"></i> {$_LANG.save_draft|default:'Save Draft'}
            </button>
            <button type="submit" id="submitBtn" class="sslm-btn sslm-btn-primary" onclick="submitApply()">
                <i class="fas fa-paper-plane"></i> {$_LANG.submit_request|default:'Submit Request'}
            </button>
        </div>
    </form>

    {* ── Help Section ── *}
    <div class="sslm-section" style="margin-top:24px;">
        <div class="sslm-section-header">
            <h3><i class="fas fa-question-circle"></i> {$_LANG.need_help|default:'Need Help?'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-help-grid">
                <div class="sslm-help-item">
                    <h4><i class="fas fa-book"></i> {$_LANG.csr_guide_title|default:'CSR Guide'}</h4>
                    <p>{$_LANG.csr_guide_desc|default:'Not sure about CSR? Use the auto-generate option and we will create it for you.'}</p>
                </div>
                <div class="sslm-help-item">
                    <h4><i class="fas fa-life-ring"></i> {$_LANG.help_installation_title|default:'SSL Installation Service'}</h4>
                    <p>{$_LANG.help_installation_desc|default:'Our experts can install your SSL certificate for you quickly and securely.'}</p>
                    <a href="submitticket.php" class="sslm-btn sslm-btn-sm sslm-btn-outline">
                        <i class="fas fa-ticket-alt"></i> {$_LANG.open_ticket|default:'Open a Ticket'}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {* ── Loading Overlay ── *}
    <div class="sslm-loading" id="loading-overlay" style="display:none;">
        <div class="sslm-loading-spinner"></div>
        <div class="sslm-loading-text" id="loading-text">{$_LANG.processing|default:'Processing...'}</div>
    </div>
</div>

{* Hidden fields *}
<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<input type="hidden" id="h-domain" value="{$domain|escape:'html'}" />
<input type="hidden" id="h-product-code" value="{$productCode|escape:'html'}" />
<input type="hidden" id="h-validation-type" value="{$sslValidationType|default:'dv'}" />
<input type="hidden" id="h-is-multi" value="{if $isMultiDomain}1{else}0{/if}" />
<input type="hidden" id="h-max-domains" value="{$maxDomains|default:1}" />

<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>