{**
 * NicSRS SSL Module - Cancelled/Revoked/Expired Template
 * Shows certificate status with timeline and next steps
 * 
 * @package    nicsrs_ssl
 * @version    2.1.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 *}

{* Load CSS *}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">

{* Determine status type for styling *}
{if $status eq 'cancelled' || $status eq 'canceled'}
    {assign var="statusType" value="cancelled"}
    {assign var="statusIcon" value="times-circle"}
    {assign var="statusColor" value="error"}
{elseif $status eq 'revoked'}
    {assign var="statusType" value="revoked"}
    {assign var="statusIcon" value="ban"}
    {assign var="statusColor" value="error"}
{elseif $status eq 'expired'}
    {assign var="statusType" value="expired"}
    {assign var="statusIcon" value="calendar-times"}
    {assign var="statusColor" value="warning"}
{else}
    {assign var="statusType" value="inactive"}
    {assign var="statusIcon" value="minus-circle"}
    {assign var="statusColor" value="default"}
{/if}

<div class="sslm-container">
    {* Header *}
    <div class="sslm-header">
        <h2 class="sslm-title">
            <i class="fas fa-shield-alt"></i>
            {$_LANG.certificate_management|default:'Certificate Management'}
        </h2>
        <div class="sslm-header-info">
            <span class="sslm-product-name">{$productCode|escape:'html'}</span>
            {if $statusType eq 'cancelled'}
                <span class="sslm-badge sslm-badge-error">{$_LANG.cancelled|default:'Cancelled'}</span>
            {elseif $statusType eq 'revoked'}
                <span class="sslm-badge sslm-badge-error">{$_LANG.revoked|default:'Revoked'}</span>
            {elseif $statusType eq 'expired'}
                <span class="sslm-badge sslm-badge-warning">{$_LANG.expired|default:'Expired'}</span>
            {else}
                <span class="sslm-badge sslm-badge-default">{$status|escape:'html'}</span>
            {/if}
        </div>
    </div>

    {* Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon {$statusColor}">
            <i class="fas fa-{$statusIcon}"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">
                {if $statusType eq 'cancelled'}
                    {$_LANG.order_cancelled|default:'Order Cancelled'}
                {elseif $statusType eq 'revoked'}
                    {$_LANG.certificate_revoked|default:'Certificate Revoked'}
                {elseif $statusType eq 'expired'}
                    {$_LANG.certificate_expired|default:'Certificate Expired'}
                {else}
                    {$_LANG.certificate_inactive|default:'Certificate Inactive'}
                {/if}
            </div>
            <div class="sslm-status-desc">
                {if $statusType eq 'cancelled'}
                    {$_LANG.cancelled_desc|default:'This certificate order has been cancelled. If you need SSL protection for your website, please place a new order.'}
                {elseif $statusType eq 'revoked'}
                    {$_LANG.revoked_desc|default:'This certificate has been revoked and is no longer valid. Browsers will show security warnings if you continue using it.'}
                {elseif $statusType eq 'expired'}
                    {$_LANG.expired_desc|default:'This certificate has expired. Please renew your certificate to maintain SSL protection for your website.'}
                {else}
                    {$_LANG.inactive_desc|default:'This certificate is no longer active.'}
                {/if}
            </div>
        </div>
    </div>

    {* Certificate Information *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                {if $certId}
                <div class="sslm-info-item">
                    <label>{$_LANG.certificate_id|default:'Certificate ID'}</label>
                    <span class="sslm-code">{$certId|escape:'html'}</span>
                </div>
                {/if}
                <div class="sslm-info-item">
                    <label>{$_LANG.status|default:'Status'}</label>
                    <span>
                        {if $statusType eq 'cancelled'}
                            <span class="sslm-badge sslm-badge-error">{$_LANG.cancelled|default:'Cancelled'}</span>
                        {elseif $statusType eq 'revoked'}
                            <span class="sslm-badge sslm-badge-error">{$_LANG.revoked|default:'Revoked'}</span>
                        {elseif $statusType eq 'expired'}
                            <span class="sslm-badge sslm-badge-warning">{$_LANG.expired|default:'Expired'}</span>
                        {else}
                            <span class="sslm-badge sslm-badge-default">{$status|escape:'html'}</span>
                        {/if}
                    </span>
                </div>
                <div class="sslm-info-item">
                    <label>{$_LANG.product|default:'Product'}</label>
                    <span>{$productCode|escape:'html'}</span>
                </div>
                {if $configData.domainInfo[0].domainName}
                <div class="sslm-info-item">
                    <label>{$_LANG.domain|default:'Domain'}</label>
                    <span>{$configData.domainInfo[0].domainName|escape:'html'}</span>
                </div>
                {/if}
            </div>

            {* Validity dates if available *}
            {if $configData.applyReturn.beginDate || $configData.applyReturn.endDate}
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--sslm-border-color);">
                <div class="sslm-info-grid">
                    {if $configData.applyReturn.beginDate}
                    <div class="sslm-info-item">
                        <label>{$_LANG.valid_from|default:'Valid From'}</label>
                        <span>{$configData.applyReturn.beginDate|escape:'html'}</span>
                    </div>
                    {/if}
                    {if $configData.applyReturn.endDate}
                    <div class="sslm-info-item">
                        <label>{$_LANG.valid_until|default:'Valid Until'}</label>
                        <span class="{if $statusType eq 'expired'}sslm-text-error{/if}">{$configData.applyReturn.endDate|escape:'html'}</span>
                    </div>
                    {/if}
                </div>
            </div>
            {/if}
        </div>
    </div>

    {* Timeline / History *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-history"></i> {$_LANG.certificate_history|default:'Certificate History'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-timeline">
                {* Order Placed *}
                <div class="sslm-timeline-item completed">
                    <div class="sslm-timeline-marker"></div>
                    <div class="sslm-timeline-content">
                        <div class="sslm-timeline-title">{$_LANG.order_placed|default:'Order Placed'}</div>
                        <div class="sslm-timeline-date">
                            {if $configdata.applyTime}
                                {$configdata.applyTime|escape:'html'}
                            {elseif $order->provisiondate && $order->provisiondate != '0000-00-00'}
                                {$order->provisiondate|escape:'html'}
                            {else}
                                N/A
                            {/if}
                        </div>
                    </div>
                </div>
                
                {* Certificate Issued (if it was issued) *}
                {if $configdata.applyReturn.beginDate}
                <div class="sslm-timeline-item completed">
                    <div class="sslm-timeline-marker"></div>
                    <div class="sslm-timeline-content">
                        <div class="sslm-timeline-title">{$_LANG.certificate_issued|default:'Certificate Issued'}</div>
                        <div class="sslm-timeline-date">{$configdata.applyReturn.beginDate|escape:'html'}</div>
                    </div>
                </div>
                {/if}

                {* Final Status *}
                <div class="sslm-timeline-item {if $statusType eq 'expired'}warning{else}error{/if}">
                    <div class="sslm-timeline-marker"></div>
                    <div class="sslm-timeline-content">
                        <div class="sslm-timeline-title">
                            {if $statusType eq 'cancelled'}
                                {$_LANG.order_cancelled|default:'Order Cancelled'}
                            {elseif $statusType eq 'revoked'}
                                {$_LANG.certificate_revoked|default:'Certificate Revoked'}
                            {elseif $statusType eq 'expired'}
                                {$_LANG.certificate_expired|default:'Certificate Expired'}
                            {else}
                                {$_LANG.certificate_inactive|default:'Certificate Inactive'}
                            {/if}
                        </div>
                        <div class="sslm-timeline-date">
                            {if $statusType eq 'expired' && $configdata.applyReturn.endDate}
                                {$configdata.applyReturn.endDate|escape:'html'}
                            {elseif $statusType eq 'cancelled' && $configdata.cancelledAt}
                                {$configdata.cancelledAt|escape:'html'}
                            {elseif $statusType eq 'revoked' && $configdata.revokedAt}
                                {$configdata.revokedAt|escape:'html'}
                            {elseif $order->completiondate && $order->completiondate != '0000-00-00 00:00:00'}
                                {$order->completiondate|escape:'html'}
                            {else}
                                N/A
                            {/if}
                        </div>
                        {* Show cancel/revoke reason if available *}
                        {if $statusType eq 'cancelled' && $configdata.cancelReason}
                            <div class="sslm-timeline-desc">{$configdata.cancelReason|escape:'html'}</div>
                        {elseif $statusType eq 'revoked'}
                            <div class="sslm-timeline-desc">{$configdata.revokeReason|default:$_LANG.revoked_reason|default:'Revoked by request'}</div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* What's Next Section *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-arrow-right"></i> {$_LANG.whats_next|default:"What's Next?"}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-action-grid">
                {* Order New Certificate *}
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="sslm-action-card-title">{$_LANG.order_new|default:'Order New Certificate'}</div>
                    <div class="sslm-action-card-desc">
                        {$_LANG.order_new_desc|default:'Get a new SSL certificate to protect your website and keep your visitors safe.'}
                    </div>
                    <a href="{$WEB_ROOT}/cart.php?a=add&domain=register" class="sslm-btn sslm-btn-primary">
                        <i class="fas fa-shopping-cart"></i> {$_LANG.order_now|default:'Order Now'}
                    </a>
                </div>

                {* Contact Support *}
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon" style="background: var(--sslm-warning-bg); color: var(--sslm-warning);">
                        <i class="fas fa-life-ring"></i>
                    </div>
                    <div class="sslm-action-card-title">{$_LANG.need_help|default:'Need Help?'}</div>
                    <div class="sslm-action-card-desc">
                        {$_LANG.support_desc|default:'Have questions? Our support team is here to help you with any SSL-related issues.'}
                    </div>
                    <a href="{$WEB_ROOT}/submitticket.php" class="sslm-btn sslm-btn-warning">
                        <i class="fas fa-ticket-alt"></i> {$_LANG.open_ticket|default:'Open Ticket'}
                    </a>
                </div>

                {* View Services *}
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon" style="background: var(--sslm-success-bg); color: var(--sslm-success);">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="sslm-action-card-title">{$_LANG.view_services|default:'View Your Services'}</div>
                    <div class="sslm-action-card-desc">
                        {$_LANG.view_services_desc|default:'Check & manage your other active SSL certificates and hosting services.'}
                    </div>
                    <a href="{$WEB_ROOT}/clientarea.php?action=services" class="sslm-btn sslm-btn-success">
                        <i class="fas fa-list"></i> {$_LANG.my_services|default:'My Services'}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {* Additional Info for Expired Certificates *}
    {if $statusType eq 'expired'}
    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>{$_LANG.important_notice|default:'Important Notice'}</strong>
            <p style="margin: 4px 0 0 0;">
                {$_LANG.expired_warning|default:'An expired SSL certificate will cause security warnings in browsers. Visitors may see "Your connection is not private" errors. Please renew or order a new certificate as soon as possible to maintain trust with your users.'}
            </p>
        </div>
    </div>
    {/if}

    {* Additional Info for Revoked Certificates *}
    {if $statusType eq 'revoked'}
    <div class="sslm-alert sslm-alert-error">
        <i class="fas fa-ban"></i>
        <div>
            <strong>{$_LANG.revocation_notice|default:'Revocation Notice'}</strong>
            <p style="margin: 4px 0 0 0;">
                {$_LANG.revoked_warning|default:'This certificate has been added to Certificate Revocation Lists (CRL). Browsers will show security warnings when visiting your site. You must install a new certificate immediately.'}
            </p>
        </div>
    </div>
    {/if}
</div>

{* JavaScript Configuration *}
<script>
window.sslmConfig = {
    ajaxUrl: "{$WEB_ROOT}/clientarea.php?action=productdetails&id={$serviceid}",
    serviceid: "{$serviceid}",
    lang: {}
};
</script>

{* Load JavaScript *}
<script src="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/js/ssl-manager.js"></script>