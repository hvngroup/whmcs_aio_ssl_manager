{* ══════════════════════════════════════════════════════════════════════
   FILE: view/message.tpl — Generic info message
   ══════════════════════════════════════════════════════════════════════ *}
{* Save as: modules/servers/aio_ssl/view/message.tpl *}

<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/aio_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    <div class="sslm-status-card">
        <div class="sslm-status-icon info"><i class="fas fa-info-circle"></i></div>
        <div class="sslm-status-content">
            <div class="sslm-status-title">{$messageTitle|default:'Information'}</div>
            <div class="sslm-status-desc">{$messageContent|escape:'html'}</div>
        </div>
    </div>
</div>