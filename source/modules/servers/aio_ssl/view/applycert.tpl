{**
 * AIO SSL Manager — Apply Certificate (Multi-step Wizard)
 *
 * Steps: 1. CSR → 2. DCV → 3. Contacts (OV/EV) → 4. Confirm & Submit
 * Template engine: Smarty (constraint C2)
 * CSS: Ant Design-inspired (constraint C9)
 *
 * Variables: $serviceid, $domain, $productCode, $sslValidationType,
 *            $providerSlug, $hasDraft, $draft, $draftStep, $isMultiDomain,
 *            $maxDomains, $canAutoGenerate, $moduleLink
 *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app" data-serviceid="{$serviceid}" data-provider="{$providerSlug|escape}">

    {* ── Header ── *}
    <div class="sslm-header">
        <h2 class="sslm-title">
            <i class="fas fa-shield-alt"></i>
            {$_LANG.configure_certificate|default:'Configure SSL Certificate'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            <span class="sslm-badge sslm-badge-{$sslValidationType|default:'dv'}">{$sslValidationType|upper|default:'DV'}</span>
            <span class="sslm-badge sslm-badge-provider">{$providerSlug|upper|escape:'html'}</span>
        </div>
    </div>

    {* ── Progress Steps ── *}
    <div class="sslm-progress" id="progress-bar">
        <div class="sslm-progress-step active" data-step="1">
            <div class="sslm-progress-icon"><i class="fas fa-key"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_csr|default:'CSR'}</div>
        </div>
        <div class="sslm-progress-step" data-step="2">
            <div class="sslm-progress-icon"><i class="fas fa-check-double"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_dcv|default:'Validation'}</div>
        </div>
        {if $sslValidationType != 'dv'}
        <div class="sslm-progress-step" data-step="3">
            <div class="sslm-progress-icon"><i class="fas fa-user-tie"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_contacts|default:'Contacts'}</div>
        </div>
        {/if}
        <div class="sslm-progress-step" data-step="{if $sslValidationType != 'dv'}4{else}3{/if}">
            <div class="sslm-progress-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="sslm-progress-label">{$_LANG.step_submit|default:'Submit'}</div>
        </div>
    </div>

    {* ── Draft Resume ── *}
    {if $hasDraft}
    <div class="sslm-alert sslm-alert-info" id="draft-notice">
        <i class="fas fa-save"></i>
        <div>
            <strong>{$_LANG.draft_found|default:'Draft Found'}</strong>
            <p>{$_LANG.draft_resume|default:'You have unsaved progress. Continue where you left off?'}</p>
            <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-primary" onclick="SSLWizard.resumeDraft()">
                {$_LANG.resume|default:'Resume'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLWizard.startFresh()">
                {$_LANG.start_fresh|default:'Start Fresh'}
            </button>
        </div>
    </div>
    {/if}

    {* ── Global Error Display ── *}
    <div class="sslm-alert sslm-alert-danger" id="global-error" style="display:none;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="global-error-msg"></span>
    </div>

    {* ══════════════════════════════════════════════════════ *}
    {* STEP 1: CSR                                           *}
    {* ══════════════════════════════════════════════════════ *}
    <div class="sslm-step" id="step-1">
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><i class="fas fa-key"></i> {$_LANG.csr_title|default:'Certificate Signing Request (CSR)'}</h3>
            </div>
            <div class="sslm-section-body">

                {* CSR Input Method Tabs *}
                <div class="sslm-tabs" id="csr-tabs">
                    <button type="button" class="sslm-tab active" data-tab="paste" onclick="SSLWizard.switchCsrTab('paste')">
                        <i class="fas fa-paste"></i> {$_LANG.paste_csr|default:'Paste CSR'}
                    </button>
                    <button type="button" class="sslm-tab" data-tab="generate" onclick="SSLWizard.switchCsrTab('generate')">
                        <i class="fas fa-cogs"></i> {$_LANG.generate_csr|default:'Auto-Generate'}
                    </button>
                </div>

                {* Paste CSR Panel *}
                <div class="sslm-tab-panel active" id="panel-paste">
                    <textarea id="csr-input" class="sslm-textarea" rows="10"
                              placeholder="-----BEGIN CERTIFICATE REQUEST-----&#10;...&#10;-----END CERTIFICATE REQUEST-----">{$draft.csr|default:''}</textarea>
                    <button type="button" class="sslm-btn sslm-btn-outline sslm-btn-sm" onclick="SSLWizard.decodeCsr()">
                        <i class="fas fa-search"></i> {$_LANG.decode_csr|default:'Decode CSR'}
                    </button>
                </div>

                {* Generate CSR Panel *}
                <div class="sslm-tab-panel" id="panel-generate">
                    <div class="sslm-form-grid">
                        <div class="sslm-form-group sslm-col-full">
                            <label>{$_LANG.common_name|default:'Common Name (Domain)'} <span class="required">*</span></label>
                            <input type="text" id="gen-domain" class="sslm-input" value="{$domain|escape:'html'}" placeholder="example.com" />
                        </div>
                        <div class="sslm-form-group">
                            <label>{$_LANG.organization|default:'Organization'}</label>
                            <input type="text" id="gen-org" class="sslm-input" value="" />
                        </div>
                        <div class="sslm-form-group">
                            <label>{$_LANG.email|default:'Email'}</label>
                            <input type="email" id="gen-email" class="sslm-input" value="" />
                        </div>
                        <div class="sslm-form-group">
                            <label>{$_LANG.city|default:'City'}</label>
                            <input type="text" id="gen-city" class="sslm-input" />
                        </div>
                        <div class="sslm-form-group">
                            <label>{$_LANG.state|default:'State/Province'}</label>
                            <input type="text" id="gen-state" class="sslm-input" />
                        </div>
                        <div class="sslm-form-group">
                            <label>{$_LANG.country|default:'Country'}</label>
                            <input type="text" id="gen-country" class="sslm-input" maxlength="2" placeholder="VN" />
                        </div>
                    </div>
                    <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLWizard.generateCsr()">
                        <i class="fas fa-cogs"></i> {$_LANG.generate|default:'Generate CSR & Private Key'}
                    </button>
                </div>

                {* CSR Decode Result *}
                <div id="csr-decoded" class="sslm-info-box" style="display:none;">
                    <h4><i class="fas fa-info-circle"></i> {$_LANG.csr_info|default:'CSR Information'}</h4>
                    <div class="sslm-info-grid" id="csr-info-grid"></div>
                </div>

                {* Private Key Warning *}
                <div id="private-key-box" class="sslm-alert sslm-alert-warning" style="display:none;">
                    <i class="fas fa-key"></i>
                    <div>
                        <strong>{$_LANG.private_key_generated|default:'Private Key Generated'}</strong>
                        <p>{$_LANG.private_key_warning|default:'Save this private key securely. It will not be stored after you leave this page.'}</p>
                        <textarea id="private-key-display" class="sslm-textarea" rows="6" readonly></textarea>
                        <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLWizard.copyKey()">
                            <i class="fas fa-copy"></i> {$_LANG.copy|default:'Copy'}
                        </button>
                    </div>
                </div>

                {* Multi-domain SAN fields *}
                {if $isMultiDomain}
                <div class="sslm-section" style="margin-top:20px;">
                    <h4><i class="fas fa-globe"></i> {$_LANG.additional_domains|default:'Additional Domains (SAN)'}</h4>
                    <p class="sslm-hint">{$_LANG.san_hint|default:'Maximum'} {$maxDomains} {$_LANG.domains|default:'domains'}</p>
                    <div id="san-domains-list"></div>
                    <button type="button" class="sslm-btn sslm-btn-sm sslm-btn-outline" onclick="SSLWizard.addSanDomain()">
                        <i class="fas fa-plus"></i> {$_LANG.add_domain|default:'Add Domain'}
                    </button>
                </div>
                {/if}
            </div>
        </div>

        <div class="sslm-actions">
            <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLWizard.saveDraft()">
                <i class="fas fa-save"></i> {$_LANG.save_draft|default:'Save Draft'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLWizard.nextStep(2)">
                {$_LANG.next|default:'Next'} <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>

    {* ══════════════════════════════════════════════════════ *}
    {* STEP 2: DCV (Domain Control Validation)               *}
    {* ══════════════════════════════════════════════════════ *}
    <div class="sslm-step" id="step-2" style="display:none;">
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><i class="fas fa-check-double"></i> {$_LANG.dcv_title|default:'Domain Control Validation'}</h3>
            </div>
            <div class="sslm-section-body">
                <p class="sslm-hint">{$_LANG.dcv_desc|default:'Select a validation method for each domain.'}</p>

                <div id="dcv-domains-container">
                    {* Dynamically populated by JS *}
                </div>
            </div>
        </div>

        <div class="sslm-actions">
            <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLWizard.prevStep(1)">
                <i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLWizard.saveDraft()">
                <i class="fas fa-save"></i> {$_LANG.save_draft|default:'Save Draft'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLWizard.nextStep({if $sslValidationType != 'dv'}3{else}submit{/if})">
                {if $sslValidationType != 'dv'}
                    {$_LANG.next|default:'Next'} <i class="fas fa-arrow-right"></i>
                {else}
                    <i class="fas fa-paper-plane"></i> {$_LANG.submit|default:'Submit Order'}
                {/if}
            </button>
        </div>
    </div>

    {* ══════════════════════════════════════════════════════ *}
    {* STEP 3: Contacts (OV/EV only)                         *}
    {* ══════════════════════════════════════════════════════ *}
    {if $sslValidationType != 'dv'}
    <div class="sslm-step" id="step-3" style="display:none;">
        <div class="sslm-section">
            <div class="sslm-section-header">
                <h3><i class="fas fa-user-tie"></i> {$_LANG.contact_info|default:'Organization & Contact Information'}</h3>
            </div>
            <div class="sslm-section-body">

                {* Organization *}
                <h4>{$_LANG.organization_details|default:'Organization Details'}</h4>
                <div class="sslm-form-grid">
                    <div class="sslm-form-group sslm-col-full">
                        <label>{$_LANG.org_name|default:'Organization Name'} <span class="required">*</span></label>
                        <input type="text" id="org-name" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_division|default:'Division'}</label>
                        <input type="text" id="org-division" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="tel" id="org-phone" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_address|default:'Address'} <span class="required">*</span></label>
                        <input type="text" id="org-address" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_city|default:'City'} <span class="required">*</span></label>
                        <input type="text" id="org-city" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_state|default:'State/Province'}</label>
                        <input type="text" id="org-state" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_country|default:'Country'} <span class="required">*</span></label>
                        <input type="text" id="org-country" class="sslm-input" maxlength="2" placeholder="VN" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.org_zip|default:'Postal Code'}</label>
                        <input type="text" id="org-zip" class="sslm-input" />
                    </div>
                </div>

                {* Admin Contact *}
                <h4 style="margin-top:20px;">{$_LANG.admin_contact|default:'Administrator Contact'}</h4>
                <div class="sslm-form-grid">
                    <div class="sslm-form-group">
                        <label>{$_LANG.first_name|default:'First Name'} <span class="required">*</span></label>
                        <input type="text" id="admin-firstname" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.last_name|default:'Last Name'} <span class="required">*</span></label>
                        <input type="text" id="admin-lastname" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.email|default:'Email'} <span class="required">*</span></label>
                        <input type="email" id="admin-email" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.phone|default:'Phone'} <span class="required">*</span></label>
                        <input type="tel" id="admin-phone" class="sslm-input" />
                    </div>
                    <div class="sslm-form-group">
                        <label>{$_LANG.title_job|default:'Job Title'}</label>
                        <input type="text" id="admin-title" class="sslm-input" />
                    </div>
                </div>
            </div>
        </div>

        <div class="sslm-actions">
            <button type="button" class="sslm-btn sslm-btn-outline" onclick="SSLWizard.prevStep(2)">
                <i class="fas fa-arrow-left"></i> {$_LANG.back|default:'Back'}
            </button>
            <button type="button" class="sslm-btn sslm-btn-primary" onclick="SSLWizard.nextStep('submit')">
                <i class="fas fa-paper-plane"></i> {$_LANG.submit|default:'Submit Order'}
            </button>
        </div>
    </div>
    {/if}

    {* ── Loading Overlay ── *}
    <div class="sslm-loading" id="loading-overlay" style="display:none;">
        <div class="sslm-loading-spinner"></div>
        <div class="sslm-loading-text" id="loading-text">{$_LANG.processing|default:'Processing...'}</div>
    </div>
</div>

{* ── Hidden Data ── *}
<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<input type="hidden" id="h-domain" value="{$domain|escape:'html'}" />
<input type="hidden" id="h-product-code" value="{$productCode|escape:'html'}" />
<input type="hidden" id="h-validation-type" value="{$sslValidationType|default:'dv'}" />
<input type="hidden" id="h-provider" value="{$providerSlug|escape:'html'}" />
<input type="hidden" id="h-is-multi" value="{if $isMultiDomain}1{else}0{/if}" />
<input type="hidden" id="h-max-domains" value="{$maxDomains|default:1}" />
<input type="hidden" id="h-module-link" value="{$moduleLink|escape:'html'}" />
<input type="hidden" id="h-draft-data" value='{$draft|@json_encode}' />

<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>