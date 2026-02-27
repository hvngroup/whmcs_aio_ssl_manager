{* ══════════════════════════════════════════════════════════════════════
   FILE: view/status.tpl — Cancelled / Expired / Revoked / Rejected
   Rich action cards: order new, contact support, view services
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container" id="aio-ssl-app">
    <div class="sslm-header">
        <h2 class="sslm-title"><i class="fas fa-shield-alt"></i> {$_LANG.certificate_management|default:'Certificate Management'}</h2>
    </div>

    {* Status Card *}
    <div class="sslm-status-card">
        <div class="sslm-status-icon {if $orderStatus == 'Expired'}warning{else}danger{/if}">
            <i class="fas fa-{if $orderStatus == 'Expired'}hourglass-end{elseif $orderStatus == 'Revoked'}ban{else}times-circle{/if}"></i>
        </div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">
                {if $orderStatus == 'Cancelled'}{$_LANG.cancelled|default:'Order Cancelled'}
                {elseif $orderStatus == 'Expired'}{$_LANG.expired|default:'Certificate Expired'}
                {elseif $orderStatus == 'Revoked'}{$_LANG.revoked|default:'Certificate Revoked'}
                {elseif $orderStatus == 'Rejected'}{$_LANG.rejected|default:'Request Rejected'}
                {else}{$orderStatus|escape:'html'}{/if}
            </div>
            <div class="sslm-status-desc">
                {if $orderStatus == 'Cancelled'}{$_LANG.cancelled_desc|default:'This certificate order has been cancelled.'}
                {elseif $orderStatus == 'Expired'}{$_LANG.expired_desc|default:'This certificate has expired. Please renew or order a new certificate to maintain secure connections.'}
                {elseif $orderStatus == 'Revoked'}{$_LANG.revoked_desc|default:'This certificate has been revoked and is no longer valid.'}
                {elseif $orderStatus == 'Rejected'}{$_LANG.rejected_desc|default:'This certificate request was rejected by the Certificate Authority.'}
                {else}{$_LANG.status_unknown_desc|default:'Please contact support for assistance.'}{/if}
            </div>
        </div>
    </div>

    {* Cert Info *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-info-circle"></i> {$_LANG.certificate_info|default:'Certificate Information'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-info-grid">
                <div class="sslm-info-item"><label>{$_LANG.domain|default:'Domain'}</label><span><code>{$domain|escape:'html'}</code></span></div>
                <div class="sslm-info-item"><label>{$_LANG.product|default:'Product'}</label><span>{$productCode|escape:'html'}</span></div>
                {if $endDate}<div class="sslm-info-item"><label>{$_LANG.valid_until|default:'Expired On'}</label><span>{$endDate|escape:'html'}</span></div>{/if}
            </div>
        </div>
    </div>

    {* Expired Warning *}
    {if $orderStatus == 'Expired'}
    <div class="sslm-alert sslm-alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>{$_LANG.important_notice|default:'Important Notice'}</strong>
            <p style="margin:4px 0 0 0;">{$_LANG.expired_warning|default:'An expired SSL certificate will cause security warnings in browsers. Visitors may see "Your connection is not private" errors. Please renew or order a new certificate as soon as possible.'}</p>
        </div>
    </div>
    {/if}

    {* ── Action Cards ── *}
    <div class="sslm-section">
        <div class="sslm-section-header">
            <h3><i class="fas fa-arrow-right"></i> {$_LANG.what_next|default:'What\'s Next?'}</h3>
        </div>
        <div class="sslm-section-body">
            <div class="sslm-action-cards">
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon" style="background:#e6f7ff;color:var(--sslm-primary);"><i class="fas fa-shopping-cart"></i></div>
                    <div class="sslm-action-card-title">{$_LANG.order_new|default:'Order New Certificate'}</div>
                    <div class="sslm-action-card-desc">{$_LANG.order_new_desc|default:'Get a new SSL certificate to keep your website secure.'}</div>
                    <a href="{$WEB_ROOT}/cart.php" class="sslm-btn sslm-btn-primary sslm-btn-sm"><i class="fas fa-shopping-cart"></i> {$_LANG.order_now|default:'Order Now'}</a>
                </div>
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon" style="background:#fffbe6;color:var(--sslm-warning);"><i class="fas fa-life-ring"></i></div>
                    <div class="sslm-action-card-title">{$_LANG.need_help|default:'Need Help?'}</div>
                    <div class="sslm-action-card-desc">{$_LANG.support_desc|default:'Our support team is here to help you with any SSL-related issues.'}</div>
                    <a href="{$WEB_ROOT}/submitticket.php" class="sslm-btn sslm-btn-outline sslm-btn-sm"><i class="fas fa-ticket-alt"></i> {$_LANG.open_ticket|default:'Open Ticket'}</a>
                </div>
                <div class="sslm-action-card">
                    <div class="sslm-action-card-icon" style="background:#f6ffed;color:var(--sslm-success);"><i class="fas fa-list"></i></div>
                    <div class="sslm-action-card-title">{$_LANG.view_services|default:'Your Services'}</div>
                    <div class="sslm-action-card-desc">{$_LANG.view_services_desc|default:'Check and manage your other active SSL certificates.'}</div>
                    <a href="{$WEB_ROOT}/clientarea.php?action=services" class="sslm-btn sslm-btn-outline sslm-btn-sm"><i class="fas fa-list"></i> {$_LANG.my_services|default:'My Services'}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="h-serviceid" value="{$serviceid}" />
<script src="{$WEB_ROOT}/modules/servers/aio_ssl/assets/js/ssl-manager.js"></script>