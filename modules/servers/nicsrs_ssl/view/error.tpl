{**
 * NicSRS SSL Module - Error Template
 * Enhanced with inline CSS fallback for reliability
 * 
 * @package    nicsrs_ssl
 * @version    2.1.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 *}

{* Load CSS - with fallback *}
{if $WEB_ROOT}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">
{else}
<link rel="stylesheet" href="/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">
{/if}

{* Inline fallback CSS - ensures error page always looks correct *}
<style>
.sslm-error-container {
    max-width: 800px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.sslm-error-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.sslm-error-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    font-size: 28px;
}
.sslm-error-icon.warning {
    background: #fff7e6;
    color: #fa8c16;
    border: 2px solid #ffd591;
}
.sslm-error-icon.critical {
    background: #fff1f0;
    color: #f5222d;
    border: 2px solid #ffa39e;
}
.sslm-error-title {
    font-size: 20px;
    font-weight: 600;
    color: #262626;
    margin: 0 0 8px 0;
}
.sslm-error-message {
    font-size: 14px;
    color: #595959;
    margin: 0 0 24px 0;
    line-height: 1.6;
}
.sslm-error-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.sslm-error-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.sslm-error-btn-primary {
    background: #1890ff;
    color: #fff;
    border-color: #1890ff;
}
.sslm-error-btn-primary:hover {
    background: #40a9ff;
    color: #fff;
    text-decoration: none;
}
.sslm-error-btn-secondary {
    background: #fff;
    color: #595959;
    border-color: #d9d9d9;
}
.sslm-error-btn-secondary:hover {
    color: #1890ff;
    border-color: #1890ff;
    text-decoration: none;
}
.sslm-error-details {
    background: #fafafa;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    text-align: left;
}
.sslm-error-details-title {
    font-size: 14px;
    font-weight: 600;
    color: #262626;
    margin: 0 0 12px 0;
}
.sslm-error-detail-row {
    display: flex;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}
.sslm-error-detail-row:last-child {
    border-bottom: none;
}
.sslm-error-detail-label {
    width: 120px;
    color: #8c8c8c;
    flex-shrink: 0;
}
.sslm-error-detail-value {
    color: #262626;
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 12px;
    word-break: break-all;
}
.sslm-error-help {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 20px;
}
.sslm-error-help-title {
    font-size: 14px;
    font-weight: 600;
    color: #262626;
    margin: 0 0 16px 0;
}
.sslm-error-help-items {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}
.sslm-error-help-item h4 {
    font-size: 13px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: #262626;
}
.sslm-error-help-item p {
    font-size: 12px;
    color: #8c8c8c;
    margin: 0;
    line-height: 1.5;
}
</style>

<div class="sslm-error-container">
    {* Error Card *}
    <div class="sslm-error-card">
        <div class="sslm-error-icon warning">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2 class="sslm-error-title">{$errorTitle|default:'An Error Occurred'}</h2>
        <p class="sslm-error-message">{$errorMessage|escape:'html'|default:'Something went wrong. Please try again.'}</p>
        
        <div class="sslm-error-actions">
            <button type="button" class="sslm-error-btn sslm-error-btn-primary" onclick="location.reload()">
                <i class="fas fa-redo"></i> Try Again
            </button>
            <a href="{if $WEB_ROOT}{$WEB_ROOT}{/if}/clientarea.php?action=productdetails&id={$serviceid}" class="sslm-error-btn sslm-error-btn-secondary">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
            <a href="{if $WEB_ROOT}{$WEB_ROOT}{/if}/submitticket.php" class="sslm-error-btn sslm-error-btn-secondary">
                <i class="fas fa-ticket-alt"></i> Open Ticket
            </a>
        </div>
    </div>

    {* Error Details *}
    {if $errorCode || $errorDetails || $errorTimestamp}
    <div class="sslm-error-details">
        <h3 class="sslm-error-details-title">
            <i class="fas fa-info-circle"></i> Error Details
        </h3>
        {if $errorCode}
        <div class="sslm-error-detail-row">
            <span class="sslm-error-detail-label">Error Code</span>
            <span class="sslm-error-detail-value">{$errorCode|escape:'html'}</span>
        </div>
        {/if}
        {if $errorDetails}
        <div class="sslm-error-detail-row">
            <span class="sslm-error-detail-label">Details</span>
            <span class="sslm-error-detail-value">{$errorDetails|escape:'html'}</span>
        </div>
        {/if}
        {if $errorTimestamp}
        <div class="sslm-error-detail-row">
            <span class="sslm-error-detail-label">Time</span>
            <span class="sslm-error-detail-value">{$errorTimestamp|escape:'html'}</span>
        </div>
        {/if}
    </div>
    {/if}

    {* Help Section *}
    <div class="sslm-error-help">
        <h3 class="sslm-error-help-title">
            <i class="fas fa-lightbulb"></i> Troubleshooting
        </h3>
        <div class="sslm-error-help-items">
            <div class="sslm-error-help-item">
                <h4><i class="fas fa-redo"></i> Try Again</h4>
                <p>Refresh the page or retry the operation. Temporary issues often resolve themselves.</p>
            </div>
            <div class="sslm-error-help-item">
                <h4><i class="fas fa-clock"></i> Wait a Moment</h4>
                <p>The server may be temporarily busy. Please wait a few minutes before trying again.</p>
            </div>
            <div class="sslm-error-help-item">
                <h4><i class="fas fa-life-ring"></i> Contact Support</h4>
                <p>If the problem persists, please contact our support team with the error details above.</p>
            </div>
        </div>
    </div>
</div>