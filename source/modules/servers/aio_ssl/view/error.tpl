{* ══════════════════════════════════════════════════════════════════════
   FILE: view/error.tpl
   ══════════════════════════════════════════════════════════════════════ *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-status-card">
        <div class="sslm-status-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$errorTitle|default:'Error'}</div>
            <div class="sslm-status-desc">{$errorMessage|escape:'html'}</div>
        </div>
    </div>
    <div style="margin-top:16px;">
        <a href="{$WEB_ROOT}/submitticket.php" class="sslm-btn sslm-btn-outline"><i class="fas fa-ticket-alt"></i> {$_LANG.contact_support|default:'Contact Support'}</a>
    </div>
</div>