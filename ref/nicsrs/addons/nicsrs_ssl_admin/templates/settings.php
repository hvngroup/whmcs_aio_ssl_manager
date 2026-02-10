<?php
/**
 * Settings Template
 * 
 * @var array $settings Current settings
 * @var array $activityLogs Recent activity logs
 * @var bool $apiConnected API connection status
 * @var string $apiToken Masked API token
 * @var string $csrfToken CSRF token
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */

use NicsrsAdmin\Helper\CurrencyHelper;
?>

<div class="nicsrs-settings">
    
    <!-- Header -->
    <div class="page-header">
        <h3><i class="fa fa-cog"></i> Module Settings</h3>
    </div>

    <div class="row">
        <!-- Settings Form -->
        <div class="col-md-6">
            
            <!-- API Configuration -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-plug"></i> API Configuration</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>API Token Status:</label>
                        <div>
                            <?php if ($apiConnected): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Connected</span>
                            <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Not Connected</span>
                            <?php endif; ?>
                            <span class="text-muted" style="margin-left: 10px;">
                                Token: <?php echo $helper->e($apiToken); ?>
                            </span>
                        </div>
                        <p class="help-block">
                            API token is configured in <strong>Setup → Addon Modules → NicSRS SSL Admin</strong>
                        </p>
                    </div>
                    <button type="button" class="btn btn-default" id="btnTestApi">
                        <i class="fa fa-plug"></i> Test API Connection
                    </button>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-bell"></i> Notification Settings</h3>
                </div>
                <div class="panel-body">
                    <form id="settingsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="email_on_issuance" value="1"
                                       <?php echo !empty($settings['email_on_issuance']) ? 'checked' : ''; ?>>
                                Email admin when certificate is issued
                            </label>
                        </div>
                        
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="email_on_expiry" value="1"
                                       <?php echo !empty($settings['email_on_expiry']) ? 'checked' : ''; ?>>
                                Email admin before certificate expires
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Expiry Warning Days:</label>
                            <input type="number" name="expiry_days" class="form-control" style="width: 100px;"
                                   value="<?php echo isset($settings['expiry_days']) ? (int)$settings['expiry_days'] : 30; ?>"
                                   min="1" max="90">
                            <p class="help-block">Send expiry warning this many days before certificate expires</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Admin Email:</label>
                            <input type="email" name="admin_email" class="form-control" style="width: 300px;"
                                   value="<?php echo $helper->e($settings['admin_email'] ?? ''); ?>"
                                   placeholder="admin@example.com">
                            <p class="help-block">Leave empty to use WHMCS system email</p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Auto-Sync Settings Panel -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-refresh"></i> Auto-Sync Settings
                        <span class="pull-right">
                            <span id="sync-status-badge" class="label label-default">
                                <i class="fa fa-circle-o-notch fa-spin"></i> Loading...
                            </span>
                        </span>
                    </h3>
                </div>
                <div class="panel-body">
                    
                    <!-- Sync Status Display -->
                    <div id="sync-status-info" class="alert alert-info" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="fa fa-certificate"></i> Status Sync:</strong>
                                <ul class="list-unstyled" style="margin-top: 5px; margin-bottom: 0;">
                                    <li>Last Sync: <span id="last-status-sync">-</span></li>
                                    <li>Next Sync: <span id="next-status-sync">-</span></li>
                                    <li>Pending Certs: <span id="pending-certs-count" class="badge">0</span></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <strong><i class="fa fa-cubes"></i> Product Sync:</strong>
                                <ul class="list-unstyled" style="margin-top: 5px; margin-bottom: 0;">
                                    <li>Last Sync: <span id="last-product-sync">-</span></li>
                                    <li>Next Sync: <span id="next-product-sync">-</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Error Alert (hidden by default) -->
                    <div id="sync-error-alert" class="alert alert-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Sync Warning:</strong>
                        <span id="sync-error-message">Auto-sync has encountered errors.</span>
                    </div>

                    <!-- Enable Auto-Sync Checkbox -->
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="auto_sync_status" value="1" form="settingsForm"
                                id="auto_sync_status"
                                <?php echo !empty($settings['auto_sync_status']) ? 'checked' : ''; ?>>
                            <strong>Enable automatic status sync</strong>
                        </label>
                        <p class="help-block">
                            When enabled, certificate statuses will be automatically synchronized with NicSRS API 
                            based on the configured interval via WHMCS cron.
                        </p>
                    </div>
                    
                    <!-- Status Sync Interval -->
                    <div class="form-group">
                        <label>Status Sync Interval (hours):</label>
                        <div class="input-group" style="width: 150px;">
                            <input type="number" name="sync_interval_hours" class="form-control"
                                form="settingsForm" id="sync_interval_hours"
                                value="<?php echo isset($settings['sync_interval_hours']) ? (int)$settings['sync_interval_hours'] : 6; ?>"
                                min="1" max="24">
                            <span class="input-group-addon">hours</span>
                        </div>
                        <p class="help-block">
                            How often to sync pending certificate statuses (1-24 hours). 
                            Recommended: 6 hours for normal usage.
                        </p>
                    </div>
                    
                    <!-- Product Sync Interval -->
                    <div class="form-group">
                        <label>Product Sync Interval (hours):</label>
                        <div class="input-group" style="width: 150px;">
                            <input type="number" name="product_sync_hours" class="form-control"
                                form="settingsForm" id="product_sync_hours"
                                value="<?php echo isset($settings['product_sync_hours']) ? (int)$settings['product_sync_hours'] : 24; ?>"
                                min="1" max="168">
                            <span class="input-group-addon">hours</span>
                        </div>
                        <p class="help-block">
                            How often to sync product list and pricing from NicSRS API (1-168 hours). 
                            Recommended: 24 hours.
                        </p>
                    </div>

                    <!-- Batch Size -->
                    <div class="form-group">
                        <label>Sync Batch Size:</label>
                        <div class="input-group" style="width: 150px;">
                            <input type="number" name="sync_batch_size" class="form-control"
                                form="settingsForm" id="sync_batch_size"
                                value="<?php echo isset($settings['sync_batch_size']) ? (int)$settings['sync_batch_size'] : 50; ?>"
                                min="10" max="200">
                            <span class="input-group-addon">certs</span>
                        </div>
                        <p class="help-block">
                            Maximum certificates to process per sync run (10-200). 
                            Lower values reduce API load but may take longer to sync all certificates.
                        </p>
                    </div>

                    <hr>

                    <!-- Manual Sync Buttons -->
                    <div class="form-group">
                        <label>Manual Sync:</label>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" id="btn-sync-status">
                                <i class="fa fa-refresh"></i> Sync Certificate Status
                            </button>
                            <button type="button" class="btn btn-default" id="btn-sync-products">
                                <i class="fa fa-cubes"></i> Sync Products
                            </button>
                            <button type="button" class="btn btn-info" id="btn-check-expiring">
                                <i class="fa fa-clock-o"></i> Check Expiring
                            </button>
                        </div>
                        <p class="help-block">
                            Manually trigger sync without waiting for cron schedule.
                        </p>
                    </div>

                    <!-- Sync Log (last 5 entries) -->
                    <div class="form-group" id="sync-log-section" style="display: none;">
                        <label>Recent Sync Activity:</label>
                        <div id="sync-log-list" class="well well-sm" style="max-height: 150px; overflow-y: auto; font-size: 12px;">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                </div>
            </div>

            <!-- Display Settings -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-desktop"></i> Display Settings</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>Date Format:</label>
                        <select name="date_format" class="form-control" style="width: 200px;" form="settingsForm">
                            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>
                                2025-01-19
                            </option>
                            <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>
                                19/01/2025
                            </option>
                            <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>
                                01/19/2025
                            </option>
                            <option value="d M Y" <?php echo ($settings['date_format'] ?? '') === 'd M Y' ? 'selected' : ''; ?>>
                                19 Jan 2025
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Currency Settings (for Reports) -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-money"></i> Currency Settings (Reports)</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted" style="margin-bottom: 15px;">
                        Configure exchange rate for profit reports. NicSRS costs are in USD, 
                        this rate is used to convert to VND for local currency reporting.
                    </p>
                    
                    <div class="form-group">
                        <label>USD to VND Exchange Rate:</label>
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-addon">1 USD =</span>
                            <input type="number" class="form-control" 
                                id="usd_vnd_rate" 
                                name="usd_vnd_rate" 
                                form="settingsForm"
                                value="<?php echo isset($settings['usd_vnd_rate']) ? $helper->e($settings['usd_vnd_rate']) : '25000'; ?>"
                                min="1000" max="50000" step="100">
                            <span class="input-group-addon">VND</span>
                        </div>
                        <?php 
                        // Get currency info if available
                        $rateInfo = isset($currencyInfo) ? $currencyInfo : \NicsrsAdmin\Helper\CurrencyHelper::getRateInfo();
                        ?>
                        <p class="help-block">
                            Current: <?php echo $rateInfo['rate_formatted']; ?>
                            &nbsp;|&nbsp;
                            Last updated: <?php echo $rateInfo['last_updated_formatted']; ?>
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>Display Currency in Reports:</label>
                        <select class="form-control" 
                                id="currency_display" 
                                name="currency_display" 
                                form="settingsForm"
                                style="max-width: 200px;">
                            <option value="usd" <?php echo (($settings['currency_display'] ?? 'both') === 'usd') ? 'selected' : ''; ?>>
                                USD Only
                            </option>
                            <option value="vnd" <?php echo (($settings['currency_display'] ?? 'both') === 'vnd') ? 'selected' : ''; ?>>
                                VND Only
                            </option>
                            <option value="both" <?php echo (($settings['currency_display'] ?? 'both') === 'both') ? 'selected' : ''; ?>>
                                Both (USD & VND)
                            </option>
                        </select>
                        <p class="help-block">Choose how currency values are displayed in reports</p>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-info" id="btnUpdateExchangeRate">
                            <i class="fa fa-refresh"></i> Update Rate from Internet
                        </button>
                        <span class="text-muted" style="margin-left: 10px;">
                            Fetches current rate from exchangerate-api.com
                        </span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <button type="button" class="btn btn-primary btn-lg" id="btnSaveSettings">
                <i class="fa fa-save"></i> Save Settings
            </button>
        </div>

        <!-- Activity Log -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left"><i class="fa fa-history"></i> Recent Activity</h3>
                    <div class="pull-right">
                        <button type="button" class="btn btn-xs btn-default" id="btnExportLogs" title="Export Logs">
                            <i class="fa fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-danger" id="btnClearLogs" title="Clear Old Logs">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="panel-body" style="padding: 0; max-height: 600px; overflow-y: auto;">
                    <table class="table table-striped table-condensed" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activityLogs)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">No activity recorded</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td><small><?php echo $helper->timeAgo($log->created_at); ?></small></td>
                                <td>
                                    <span class="label label-default">
                                        <?php echo $helper->truncate($log->action, 20); ?>
                                    </span>
                                    <?php if ($log->entity_type && $log->entity_id): ?>
                                    <br><small class="text-muted">
                                        <?php echo ucfirst($log->entity_type); ?> #<?php echo $log->entity_id; ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php 
                                        $adminName = trim(($log->firstname ?? '') . ' ' . ($log->lastname ?? ''));
                                        echo $helper->e($adminName ?: $log->username ?: 'System');
                                        ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Module Info -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Module Information</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed" style="margin-bottom: 0;">
                        <tr>
                            <th>Version:</th>
                            <td><?php echo NICSRS_ADMIN_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th>Author:</th>
                            <td><a href="https://hvn.vn" target="_blank">HVN GROUP</a></td>
                        </tr>
                        <tr>
                            <th>Support:</th>
                            <td><a href="mailto:support@hvn.vn">support@hvn.vn</a></td>
                        </tr>
                        <tr>
                            <th>Documentation:</th>
                            <td><a href="https://hvn.vn/docs/nicsrs-ssl" target="_blank">View Docs</a></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Clear Activity Logs</h4>
            </div>
            <div class="modal-body">
                <p>Delete activity logs older than:</p>
                <select class="form-control" id="clearLogsDays">
                    <option value="7">7 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="90">90 days</option>
                    <option value="0">All logs</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmClearLogs">Delete Logs</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * NicSRS SSL Admin - Settings Page JavaScript
 * 
 * IMPORTANT: All AJAX requests use the 'modulelink' variable for proper URL routing
 * This ensures compatibility with WHMCS admin module URL structure
 */
(function($) {
    'use strict';

    // Module link - passed from PHP
    var modulelink = '<?php echo $modulelink; ?>';

    /**
     * Show toast notification
     * Falls back to alert if NicsrsAdmin.toast is not available
     */
    function showToast(message, type) {
        type = type || 'info';
        if (typeof NicsrsAdmin !== 'undefined' && typeof NicsrsAdmin.toast === 'function') {
            NicsrsAdmin.toast(message, type);
        } else {
            alert(message);
        }
    }

    /**
     * Make AJAX request to settings controller
     * @param {string} ajaxAction - The ajax_action parameter
     * @param {object} data - Additional data to send
     * @param {function} successCallback - Success callback
     * @param {function} errorCallback - Error callback (optional)
     */
    function ajaxRequest(ajaxAction, data, successCallback, errorCallback) {
        data = data || {};
        data.ajax_action = ajaxAction;

        $.ajax({
            url: modulelink + '&action=settings',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr, status, error) {
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                } else {
                    showToast('Request failed: ' + error, 'error');
                }
            }
        });
    }

    // =========================================================================
    // Document Ready - Initialize all event handlers
    // =========================================================================
    $(document).ready(function() {

        // Load sync status on page load
        loadSyncStatus();

        // -----------------------------------------------------------------
        // Test API Connection
        // -----------------------------------------------------------------
        $('#btnTestApi').on('click', function() {
            var btn = $(this);
            var originalHtml = btn.html();
            
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

            ajaxRequest('test_api', {}, function(response) {
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            }, function() {
                alert('Request failed. Please try again.');
            });

            // Always restore button (with delay for UX)
            setTimeout(function() {
                btn.prop('disabled', false).html(originalHtml);
            }, 1000);
        });

        // -----------------------------------------------------------------
        // Save Settings
        // -----------------------------------------------------------------
        $('#btnSaveSettings').on('click', function() {
            var btn = $(this);
            var originalHtml = btn.html();
            
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            var formData = $('#settingsForm').serialize();
            formData += '&ajax_action=save_settings';

            $.ajax({
                url: modulelink + '&action=settings',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Settings saved successfully!', 'success');
                    } else {
                        showToast('Error: ' + response.message, 'error');
                    }
                },
                error: function() {
                    showToast('Request failed. Please try again.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // -----------------------------------------------------------------
        // Clear Logs Modal
        // -----------------------------------------------------------------
        $('#btnClearLogs').on('click', function() {
            $('#clearLogsModal').modal('show');
        });

        // Confirm Clear Logs
        $('#confirmClearLogs').on('click', function() {
            var btn = $(this);
            var days = $('#clearLogsDays').val();
            
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

            ajaxRequest('clear_logs', { days: days }, function(response) {
                $('#clearLogsModal').modal('hide');
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            }, function() {
                $('#clearLogsModal').modal('hide');
                alert('Request failed. Please try again.');
            });

            // Restore button after delay
            setTimeout(function() {
                btn.prop('disabled', false).html('Delete Logs');
            }, 2000);
        });

        // -----------------------------------------------------------------
        // Export Logs
        // -----------------------------------------------------------------
        $('#btnExportLogs').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true);

            ajaxRequest('export_logs', {}, function(response) {
                if (response.success && response.csv) {
                    // Create download link
                    var blob = new Blob([atob(response.csv)], { type: 'text/csv' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.filename || 'activity_log.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                } else {
                    alert('Error: ' + (response.message || 'Export failed'));
                }
            }, function() {
                alert('Request failed. Please try again.');
            });

            setTimeout(function() {
                btn.prop('disabled', false);
            }, 1000);
        });

        // -----------------------------------------------------------------
        // Update Exchange Rate from API
        // -----------------------------------------------------------------
        $('#btnUpdateExchangeRate').on('click', function() {
            var btn = $(this);
            var originalHtml = btn.html();
            
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
            
            ajaxRequest('update_exchange_rate', {}, function(response) {
                if (response.success) {
                    // Update the input field with new rate
                    if (response.rate) {
                        $('#usd_vnd_rate').val(response.rate);
                    }
                    showToast('Success: ' + response.message + (response.rate_formatted ? '\n' + response.rate_formatted : ''), 'success');
                } else {
                    showToast('Error: ' + (response.message || 'Failed to update exchange rate'), 'error');
                }
            }, function(xhr, status, error) {
                showToast('Request failed: ' + error, 'error');
            });

            setTimeout(function() {
                btn.prop('disabled', false).html(originalHtml);
            }, 2000);
        });

        // -----------------------------------------------------------------
        // Manual Sync Buttons
        // -----------------------------------------------------------------
        $('#btn-sync-status').on('click', function() {
            runManualSync('status', $(this));
        });

        $('#btn-sync-products').on('click', function() {
            runManualSync('products', $(this));
        });

        $('#btn-check-expiring').on('click', function() {
            checkExpiringCerts($(this));
        });

    }); // End document ready

    // =========================================================================
    // Sync Functions
    // =========================================================================

    /**
     * Load and display sync status
     */
    function loadSyncStatus() {
        ajaxRequest('get_sync_status', {}, function(response) {
            if (response.success && response.data) {
                updateSyncStatusDisplay(response.data);
            }
        }, function() {
            $('#sync-status-badge')
                .removeClass('label-default label-success label-warning')
                .addClass('label-danger')
                .html('<i class="fa fa-exclamation-circle"></i> Error');
        });
    }

    /**
     * Update sync status display
     */
    function updateSyncStatusDisplay(data) {
        // Update badge
        if (data.enabled) {
            $('#sync-status-badge')
                .removeClass('label-default label-danger label-warning')
                .addClass('label-success')
                .html('<i class="fa fa-check"></i> Active');
        } else {
            $('#sync-status-badge')
                .removeClass('label-default label-success label-danger')
                .addClass('label-warning')
                .html('<i class="fa fa-pause"></i> Disabled');
        }

        // Show status info
        $('#sync-status-info').show();

        // Status sync info
        $('#last-status-sync').text(data.status_sync.last_sync || 'Never');
        $('#next-status-sync').text(data.status_sync.next_sync || 'On next cron');
        $('#pending-certs-count').text(data.status_sync.pending_certificates || 0);

        // Product sync info
        $('#last-product-sync').text(data.product_sync.last_sync || 'Never');
        $('#next-product-sync').text(data.product_sync.next_sync || 'On next cron');

        // Error alert
        if (data.error_count >= 3) {
            $('#sync-error-message').text('Auto-sync has encountered ' + data.error_count + ' consecutive errors.');
            $('#sync-error-alert').show();
        } else {
            $('#sync-error-alert').hide();
        }
    }

    /**
     * Run manual sync
     * @param {string} type - 'status' or 'products'
     * @param {jQuery} btn - Button element
     */
    function runManualSync(type, btn) {
        var originalHtml = btn.html();

        btn.html('<i class="fa fa-spinner fa-spin"></i> Syncing...').prop('disabled', true);

        ajaxRequest('manual_sync', { sync_type: type }, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                loadSyncStatus(); // Refresh status display
                addSyncLogEntry(type, response.message, 'success');
            } else {
                showToast(response.message || 'Sync failed', 'error');
                addSyncLogEntry(type, response.message || 'Sync failed', 'error');
            }
        }, function(xhr, status, error) {
            showToast('Sync request failed: ' + error, 'error');
            addSyncLogEntry(type, 'Request failed: ' + error, 'error');
        });

        // Restore button after delay
        setTimeout(function() {
            btn.html(originalHtml).prop('disabled', false);
        }, 3000);
    }

    /**
     * Check expiring certificates
     * @param {jQuery} btn - Button element
     */
    function checkExpiringCerts(btn) {
        var originalHtml = btn.html();

        btn.html('<i class="fa fa-spinner fa-spin"></i> Checking...').prop('disabled', true);

        ajaxRequest('check_expiring', {}, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                addSyncLogEntry('expiry_check', response.message, 'success');
            } else {
                showToast(response.message || 'Check failed', 'error');
            }
        }, function() {
            showToast('Request failed', 'error');
        });

        setTimeout(function() {
            btn.html(originalHtml).prop('disabled', false);
        }, 2000);
    }

    /**
     * Add entry to sync log display
     * @param {string} type - Sync type
     * @param {string} message - Log message
     * @param {string} status - 'success' or 'error'
     */
    function addSyncLogEntry(type, message, status) {
        var logSection = $('#sync-log-section');
        var logList = $('#sync-log-list');
        
        logSection.show();

        var icon = status === 'success' ? 'fa-check text-success' : 'fa-times text-danger';
        var timestamp = new Date().toLocaleTimeString();
        
        var entry = '<div class="sync-log-entry">' +
            '<i class="fa ' + icon + '"></i> ' +
            '<strong>' + timestamp + '</strong> - ' +
            '<span class="text-muted">[' + type + ']</span> ' +
            message +
            '</div>';

        logList.prepend(entry);

        // Keep only last 5 entries
        var entries = logList.find('.sync-log-entry');
        if (entries.length > 5) {
            entries.slice(5).remove();
        }
    }

})(jQuery);
</script>