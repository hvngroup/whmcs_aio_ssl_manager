{**
 * NicSRS SSL Module - Migrated Certificate Template
 * 
 * Read-only view displayed when a certificate from another vendor
 * is detected for this service. Client cannot create a new NicSRS
 * order until admin clicks "Allow New Certificate" in admin area.
 * 
 * Variables:
 *   $vendorOrder         - (object) Order record from tblsslorders
 *   $vendorModule        - (string) Module name (e.g. 'gogetssl')
 *   $vendorModuleDisplay - (string) Friendly name (e.g. 'GoGetSSL')
 *   $vendorRemoteId      - (string) Certificate ID from vendor
 *   $vendorStatus        - (string) Certificate status
 *   $vendorCertType      - (string) Certificate type/product name
 *   $vendorDomains       - (array)  List of domain strings
 *   $vendorBeginDate     - (string|null) Certificate start date
 *   $vendorEndDate       - (string|null) Certificate end date
 *   $vendorDaysRemaining - (int|null)    Days until expiry
 *   $cert                - (array)  Current product cert config
 *   $productCode         - (string) Current product code/name
 * 
 * @package    nicsrs_ssl
 * @version    2.0.3
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 *}

{* Load CSS *}
{if !empty($WEB_ROOT)}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">
{/if}

<div class="sslm-container">

    {* =============================== *}
    {* Header                           *}
    {* =============================== *}
    <div class="sslm-header">
        <div class="sslm-header-content">
            <h2 class="sslm-title">
                <i class="fas fa-shield-alt"></i>
                SSL Certificate
            </h2>
            <span class="sslm-badge sslm-badge-warning" style="font-size: 13px; padding: 5px 14px;">
                <i class="fas fa-exchange-alt"></i>
                {$_LANG.managed_by|default:'Managed by'} {$vendorModuleDisplay|escape:'html'}
            </span>
        </div>
    </div>

    {* =============================== *}
    {* Migration Notice                 *}
    {* =============================== *}
    <div class="sslm-section">
        <div class="sslm-alert sslm-alert-warning" style="margin-bottom: 0;">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 20px; margin-top: 2px; flex-shrink: 0;"></i>
                <div>
                    <strong style="font-size: 14px;">
                        {$_LANG.migration_notice_title|default:'Certificate From Another Provider'}
                    </strong>
                    <p style="margin: 8px 0 0 0; line-height: 1.7; color: #8c6d1f;">
                        {$_LANG.migration_notice_body|default:'This product has an existing SSL certificate that was issued by a different provider. The certificate details are shown below for your reference. To request a new NicSRS certificate, please contact our support team.'}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {* =============================== *}
    {* Certificate Information          *}
    {* =============================== *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px;">

                {* Provider *}
                <div class="sslm-info-item">
                    <label style="font-size: 12px; color: #8c8c8c; text-transform: uppercase; letter-spacing: 0.5px;">
                        {$_LANG.provider|default:'Provider'}
                    </label>
                    <span style="font-size: 14px; font-weight: 500;">
                        {$vendorModuleDisplay|escape:'html'}
                    </span>
                </div>

                {* Certificate ID *}
                <div class="sslm-info-item">
                    <label style="font-size: 12px; color: #8c8c8c; text-transform: uppercase; letter-spacing: 0.5px;">
                        {$_LANG.certificate_id|default:'Certificate ID'}
                    </label>
                    <span style="font-size: 14px;">
                        <code style="background: #f5f5f5; padding: 2px 8px; border-radius: 3px; font-size: 13px;">
                            {$vendorRemoteId|escape:'html'}
                        </code>
                    </span>
                </div>

                {* Status *}
                <div class="sslm-info-item">
                    <label style="font-size: 12px; color: #8c8c8c; text-transform: uppercase; letter-spacing: 0.5px;">
                        {$_LANG.status|default:'Status'}
                    </label>
                    <span>
                        <span class="sslm-badge sslm-badge-success" style="font-size: 12px;">
                            {$vendorStatus|escape:'html'}
                        </span>
                    </span>
                </div>

                {* Product Type *}
                <div class="sslm-info-item">
                    <label style="font-size: 12px; color: #8c8c8c; text-transform: uppercase; letter-spacing: 0.5px;">
                        {$_LANG.current_product|default:'Current Product'}
                    </label>
                    <span style="font-size: 14px; font-weight: 500;">
                        {$productCode|escape:'html'}
                    </span>
                </div>

                {* Vendor Cert Type (if available and different from productCode) *}
                {if $vendorCertType && $vendorCertType != $productCode}
                <div class="sslm-info-item" style="grid-column: 1 / -1;">
                    <label style="font-size: 12px; color: #8c8c8c; text-transform: uppercase; letter-spacing: 0.5px;">
                        {$_LANG.original_cert_type|default:'Original Certificate Type'}
                    </label>
                    <span style="font-size: 14px;">
                        {$vendorCertType|escape:'html'}
                    </span>
                </div>
                {/if}

            </div>

            {* ---- Domains List ---- *}
            {if $vendorDomains}
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--sslm-border-color, #e8e8e8);">
                <label style="font-weight: 600; margin-bottom: 12px; display: block; font-size: 13px; color: #595959;">
                    <i class="fas fa-globe"></i> {$_LANG.secured_domains|default:'Secured Domains'}
                    <span class="sslm-badge" style="font-size: 11px; margin-left: 6px; background: #f0f0f0; color: #595959;">
                        {$vendorDomains|count}
                    </span>
                </label>
                {foreach from=$vendorDomains item=domain}
                    {if $domain}
                    <div class="sslm-domain-status" style="margin-bottom: 6px;">
                        <div class="sslm-domain-status-icon verified" style="width: 24px; height: 24px; font-size: 10px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="sslm-domain-status-content">
                            <div class="sslm-domain-status-name" style="font-size: 14px;">{$domain|escape:'html'}</div>
                        </div>
                    </div>
                    {/if}
                {/foreach}
            </div>
            {/if}
        </div>
    </div>

    {* =============================== *}
    {* Validity Period                  *}
    {* =============================== *}
    {if $vendorBeginDate || $vendorEndDate}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-calendar-check"></i> {$_LANG.validity_period|default:'Validity Period'}</h3>
        </div>
        <div class="sslm-section-body">

            {* Date display *}
            <div class="sslm-validity-display">
                {if $vendorBeginDate}
                <div class="sslm-validity-item">
                    <span class="sslm-validity-label">{$_LANG.valid_from|default:'Valid From'}</span>
                    <span class="sslm-validity-value">{$vendorBeginDate|escape:'html'}</span>
                </div>
                {/if}

                {if $vendorBeginDate && $vendorEndDate}
                <div class="sslm-validity-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
                {/if}

                {if $vendorEndDate}
                <div class="sslm-validity-item">
                    <span class="sslm-validity-label">{$_LANG.valid_until|default:'Valid Until'}</span>
                    <span class="sslm-validity-value sslm-highlight">{$vendorEndDate|escape:'html'}</span>
                </div>
                {/if}
            </div>

            {* Days remaining badge *}
            {if $vendorDaysRemaining !== null}
            <div style="margin-top: 20px; text-align: center;">
                {if $vendorDaysRemaining > 90}
                    <span class="sslm-badge sslm-badge-success" style="font-size: 13px; padding: 8px 20px;">
                        <i class="fas fa-check-circle"></i>
                        {$vendorDaysRemaining} {$_LANG.days_remaining|default:'days remaining'}
                    </span>
                {elseif $vendorDaysRemaining > 30}
                    <span class="sslm-badge sslm-badge-info" style="font-size: 13px; padding: 8px 20px;">
                        <i class="fas fa-clock"></i>
                        {$vendorDaysRemaining} {$_LANG.days_remaining|default:'days remaining'}
                    </span>
                {elseif $vendorDaysRemaining > 0}
                    <span class="sslm-badge sslm-badge-warning" style="font-size: 13px; padding: 8px 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        {$vendorDaysRemaining} {$_LANG.days_remaining|default:'days remaining'}
                    </span>
                {else}
                    <span class="sslm-badge sslm-badge-error" style="font-size: 13px; padding: 8px 20px;">
                        <i class="fas fa-times-circle"></i>
                        {$_LANG.certificate_expired|default:'Certificate Expired'}
                    </span>
                {/if}
            </div>
            {/if}

        </div>
    </div>
    {/if}

    {* =============================== *}
    {* Help / Contact Section           *}
    {* =============================== *}
    <div class="sslm-section">
        <div class="sslm-section-body" style="text-align: center; padding: 36px 24px;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #e6f7ff, #bae7ff); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <i class="fas fa-headset" style="font-size: 28px; color: #1890ff;"></i>
            </div>
            <h4 style="margin: 0 0 8px 0; color: #262626; font-size: 16px;">
                {$_LANG.need_new_cert_title|default:'Need a New Certificate?'}
            </h4>
            <p style="font-size: 14px; color: #8c8c8c; margin: 0 0 20px 0; max-width: 480px; display: inline-block; line-height: 1.7;">
                {$_LANG.need_new_cert_body|default:'If you would like to replace this certificate with a new NicSRS certificate, please contact our support team. An administrator will enable the new certificate issuance for your account.'}
            </p>
            <div>
                <a href="submitticket.php" class="sslm-btn sslm-btn-primary" style="padding: 10px 28px; font-size: 14px;">
                    <i class="fas fa-envelope"></i>
                    {$_LANG.contact_support|default:'Contact Support'}
                </a>
            </div>
        </div>
    </div>

</div>