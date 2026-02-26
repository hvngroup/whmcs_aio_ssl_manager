/**
 * HVN - AIO SSL Manager — Admin JavaScript
 * AJAX helpers, notification toasts, confirmation dialogs.
 *
 * @author  HVN GROUP <dev@hvn.vn>
 * @version 1.0.0
 */
(function($) {
    'use strict';

    // Namespace
    window.AioSSL = window.AioSSL || {};

    // Module link (set by PHP template)
    var moduleLink = window.aioModuleLink || 'addonmodules.php?module=aio_ssl_admin';

    // ─── Toast Notifications ───────────────────────────────────

    /**
     * Show toast notification
     * @param {string} msg   Message text
     * @param {string} type  success|error|warning|info
     * @param {int}    dur   Duration in ms (default 3500)
     */
    AioSSL.toast = function(msg, type, dur) {
        type = type || 'info';
        dur = dur || 3500;

        var icons = {
            success: 'fa-check-circle',
            error:   'fa-times-circle',
            warning: 'fa-exclamation-circle',
            info:    'fa-info-circle'
        };

        var $t = $('<div class="aio-toast ' + type + '">' +
            '<i class="fas ' + (icons[type] || icons.info) + '"></i>' +
            '<span>' + msg + '</span>' +
            '</div>');

        $('body').append($t);

        setTimeout(function() {
            $t.css({ animation: 'aio-slideOut 0.3s ease forwards' });
            setTimeout(function() { $t.remove(); }, 300);
        }, dur);
    };

    // ─── Loading Overlay ───────────────────────────────────────

    AioSSL.showLoading = function(msg) {
        if ($('#aio-loading').length) return;
        $('body').append(
            '<div id="aio-loading" class="aio-loading-overlay">' +
            '<div style="text-align:center">' +
            '<div class="aio-loading-spinner"></div>' +
            (msg ? '<div style="margin-top:12px;font-size:13px;color:#595959">' + msg + '</div>' : '') +
            '</div></div>'
        );
    };

    AioSSL.hideLoading = function() {
        $('#aio-loading').remove();
    };

    // ─── Confirm Dialog ────────────────────────────────────────

    /**
     * Show confirmation modal
     * @param {string}   msg        Message text
     * @param {function} onConfirm  Callback on Yes
     * @param {object}   opts       { title, confirmText, cancelText, type }
     */
    AioSSL.confirm = function(msg, onConfirm, opts) {
        opts = opts || {};
        var title = opts.title || 'Confirm Action';
        var yesText = opts.confirmText || 'Yes, Proceed';
        var noText = opts.cancelText || 'Cancel';
        var btnClass = opts.type === 'danger' ? 'aio-btn-danger' : 'aio-btn-primary';

        // Remove existing
        $('#aio-confirm-modal').remove();

        var html =
            '<div id="aio-confirm-modal" class="aio-modal-backdrop">' +
            '<div class="aio-modal">' +
            '<div class="aio-modal-header">' +
            '<span>' + title + '</span>' +
            '<button class="aio-modal-close" data-action="close">&times;</button>' +
            '</div>' +
            '<div class="aio-modal-body">' + msg + '</div>' +
            '<div class="aio-modal-footer">' +
            '<button class="aio-btn" data-action="close">' + noText + '</button>' +
            '<button class="aio-btn ' + btnClass + '" data-action="confirm">' + yesText + '</button>' +
            '</div></div></div>';

        $('body').append(html);

        var $m = $('#aio-confirm-modal');
        $m.find('[data-action="close"]').on('click', function() { $m.remove(); });
        $m.find('[data-action="confirm"]').on('click', function() {
            $m.remove();
            if (typeof onConfirm === 'function') onConfirm();
        });

        // Close on backdrop click
        $m.on('click', function(e) {
            if ($(e.target).is('.aio-modal-backdrop')) $m.remove();
        });
    };

    // ─── AJAX Request Wrapper ──────────────────────────────────

    /**
     * Make AJAX request to module endpoint
     * @param {object} opts  { page, action, data, loading, loadingMsg,
     *                         onSuccess, onError, successMessage }
     */
    AioSSL.ajax = function(opts) {
        opts = opts || {};

        var url = moduleLink;
        if (opts.page) url += '&page=' + opts.page;
        if (opts.action) url += '&action=' + opts.action;
        url += '&ajax=1';

        var ajaxOpts = {
            url: url,
            method: opts.method || 'POST',
            dataType: 'json',
            data: opts.data || {},
            beforeSend: function() {
                if (opts.loading !== false) {
                    AioSSL.showLoading(opts.loadingMsg || 'Processing...');
                }
            },
            complete: function() {
                AioSSL.hideLoading();
            },
            success: function(resp) {
                if (resp.success) {
                    if (opts.successMessage !== false) {
                        AioSSL.toast(resp.message || opts.successMessage || 'Success', 'success');
                    }
                    if (typeof opts.onSuccess === 'function') opts.onSuccess(resp);
                } else {
                    AioSSL.toast(resp.message || 'An error occurred.', 'error');
                    if (typeof opts.onError === 'function') opts.onError(resp);
                }
            },
            error: function(xhr, status, err) {
                AioSSL.toast('Request failed: ' + (err || status), 'error');
                if (typeof opts.onError === 'function') opts.onError({ success: false, message: err });
            }
        };

        $.ajax(ajaxOpts);
    };

    // ─── Provider Actions ──────────────────────────────────────

    /**
     * Test provider connection
     * @param {string} slug Provider slug
     */
    AioSSL.testProvider = function(slug) {
        AioSSL.ajax({
            page: 'providers',
            action: 'test',
            data: { slug: slug },
            loadingMsg: 'Testing connection...',
            onSuccess: function(resp) {
                var $el = $('#test-result-' + slug);
                if ($el.length) {
                    $el.removeClass('success error')
                       .addClass(resp.success ? 'success' : 'error')
                       .html('<i class="fas ' + (resp.success ? 'fa-check-circle' : 'fa-times-circle') + '"></i> ' + (resp.message || ''))
                       .show();
                }
            }
        });
    };

    /**
     * Toggle provider enable/disable
     * @param {int}    id   Provider DB id
     * @param {string} name Provider name
     */
    AioSSL.toggleProvider = function(id, name) {
        AioSSL.ajax({
            page: 'providers',
            action: 'toggle',
            data: { id: id },
            onSuccess: function() {
                location.reload();
            }
        });
    };

    /**
     * Delete provider with confirmation
     * @param {int}    id   Provider DB id
     * @param {string} name Provider name
     */
    AioSSL.deleteProvider = function(id, name) {
        AioSSL.confirm(
            'Are you sure you want to delete <strong>' + name + '</strong>?<br>' +
            '<small class="text-muted">This cannot be undone. Provider must have zero active orders.</small>',
            function() {
                AioSSL.ajax({
                    page: 'providers',
                    action: 'delete',
                    data: { id: id },
                    onSuccess: function() { location.reload(); }
                });
            },
            { title: 'Delete Provider', type: 'danger', confirmText: 'Delete' }
        );
    };

    // ─── Product Sync ──────────────────────────────────────────

    /**
     * Trigger product sync for a provider or all
     * @param {string|null} slug  Provider slug or null for all
     */
    AioSSL.syncProducts = function(slug) {
        AioSSL.ajax({
            page: 'products',
            action: 'sync',
            data: { slug: slug || '' },
            loadingMsg: 'Syncing products...',
            onSuccess: function(resp) {
                if (resp.redirect) {
                    location.href = resp.redirect;
                } else {
                    location.reload();
                }
            }
        });
    };

    // ─── Order Actions ─────────────────────────────────────────

    AioSSL.refreshOrder = function(id) {
        AioSSL.ajax({
            page: 'orders',
            action: 'refresh',
            data: { id: id },
            loadingMsg: 'Refreshing status...',
            onSuccess: function(resp) {
                if (resp.status) {
                    AioSSL.toast('Status updated: ' + resp.status, 'success');
                }
                location.reload();
            }
        });
    };

    AioSSL.resendDcv = function(id) {
        AioSSL.ajax({
            page: 'orders',
            action: 'resend_dcv',
            data: { id: id },
            loadingMsg: 'Resending DCV...'
        });
    };

    AioSSL.revokeOrder = function(id) {
        AioSSL.confirm(
            'Are you sure you want to revoke this certificate?<br>' +
            '<small>This action is irreversible.</small>',
            function() {
                AioSSL.ajax({
                    page: 'orders',
                    action: 'revoke',
                    data: { id: id },
                    onSuccess: function() { location.reload(); }
                });
            },
            { title: 'Revoke Certificate', type: 'danger', confirmText: 'Revoke' }
        );
    };

    // ─── Settings ──────────────────────────────────────────────

    AioSSL.saveSettings = function(formId) {
        var $form = $(formId || '#aio-settings-form');
        AioSSL.ajax({
            page: 'settings',
            action: 'save',
            data: $form.serialize(),
            loadingMsg: 'Saving settings...'
        });
    };

    AioSSL.manualSync = function(type) {
        AioSSL.ajax({
            page: 'settings',
            action: 'manual_sync',
            data: { type: type || 'all' },
            loadingMsg: 'Running sync...',
            onSuccess: function(resp) {
                AioSSL.toast(resp.message || 'Sync completed.', 'success', 5000);
            }
        });
    };

    // ─── Dynamic Credential Fields ─────────────────────────────

    /**
     * Load credential fields for provider slug in add/edit form
     * @param {string} slug
     */
    AioSSL.loadCredentialFields = function(slug) {
        if (!slug) {
            $('#aio-credential-fields').html('');
            return;
        }
        AioSSL.ajax({
            page: 'providers',
            action: 'credential_fields',
            data: { slug: slug },
            loading: false,
            successMessage: false,
            onSuccess: function(resp) {
                var html = '';
                if (resp.fields && resp.fields.length) {
                    $.each(resp.fields, function(i, f) {
                        html += '<div class="aio-form-group">';
                        html += '<label>' + f.label;
                        if (f.required) html += ' <span class="required">*</span>';
                        html += '</label>';
                        html += '<input type="' + (f.type || 'text') + '" name="credentials[' + f.key + ']" ';
                        html += 'class="aio-form-control" ' + (f.required ? 'required' : '') + ' />';
                        html += '</div>';
                    });
                }
                if (resp.tier) {
                    html += '<div class="aio-alert aio-alert-info" style="margin-top:8px">';
                    html += '<i class="fas fa-info-circle"></i> ';
                    html += 'Provider tier: <strong>' + resp.tier.toUpperCase() + '</strong>';
                    if (resp.tier === 'limited') {
                        html += ' — Some operations (reissue, revoke, download) are not available via API.';
                    }
                    html += '</div>';
                }
                $('#aio-credential-fields').html(html);
            }
        });
    };

    // ─── Import ────────────────────────────────────────────────

    /**
     * Import single certificate (simplified — delegates to template inline JS)
     * Kept for backward compatibility; template/import.php now handles this directly.
     */
    AioSSL.importSingle = function() {
        var $form = $('#aio-single-import-form');
        AioSSL.ajax({
            page: 'import',
            action: 'single',
            data: $form.serialize(),
            loadingMsg: 'Importing certificate...',
            onSuccess: function(resp) {
                AioSSL.toast(resp.message || 'Certificate imported.', 'success');
                if (resp.order_id) {
                    location.href = moduleLink + '&page=orders&action=detail&id=' + resp.order_id;
                }
            }
        });
    };
    
    // ─── Utility ───────────────────────────────────────────────

    /**
     * Format number with commas
     * @param {number} n
     * @returns {string}
     */
    AioSSL.formatNumber = function(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    /**
     * Copy text to clipboard
     * @param {string} text
     */
    AioSSL.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                AioSSL.toast('Copied to clipboard', 'success', 2000);
            });
        } else {
            var $tmp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            AioSSL.toast('Copied to clipboard', 'success', 2000);
        }
    };

        /**
     * Escape HTML to prevent XSS
     * @param {string} str
     * @returns {string}
     */
    AioSSL.escHtml = function(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    /**
     * Format balance with currency symbol
     * @param {number} amount
     * @param {string} currency  USD|EUR|GBP|VND
     * @returns {string}
     */
    AioSSL.formatBalance = function(amount, currency) {
        var symbols = { USD: '$', EUR: '€', GBP: '£', VND: '₫' };
        var symbol = symbols[currency] || (currency + ' ');
        var num = parseFloat(amount);

        if (currency === 'VND') {
            return symbol + num.toLocaleString('vi-VN', { maximumFractionDigits: 0 });
        }
        return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // ─── Provider Balance ──────────────────────────────────────

    /**
     * Render balance HTML into a cell
     * @param {jQuery} $cell  Target <td> element
     * @param {object} data   { balance, currency, error, supported }
     * @param {string} slug   Provider slug (for refresh button)
     */
    AioSSL._renderBalanceCell = function($cell, data, slug) {
        if (!data.supported) {
            $cell.html('<span class="text-muted" title="Not supported by this provider">&mdash;</span>');
            return;
        }

        if (data.error || data.balance === null || data.balance === undefined) {
            $cell.html(
                '<span class="text-danger" title="' + AioSSL.escHtml(data.error || 'Failed to load') + '">' +
                '<i class="fas fa-exclamation-triangle"></i> Error</span>'
            );
            return;
        }

        var bal = parseFloat(data.balance);
        var colorClass = bal < 50 ? 'text-danger' : (bal < 200 ? 'text-warning' : 'text-success');
        var formatted = AioSSL.formatBalance(bal, data.currency || 'USD');

        $cell.html(
            '<span class="' + colorClass + '" style="font-weight:600">' + formatted + '</span>' +
            ' <a href="#" class="aio-refresh-balance" data-slug="' + AioSSL.escHtml(slug) + '" ' +
            'style="color:#bbb;font-size:10px" title="Refresh balance">' +
            '<i class="fas fa-sync-alt"></i></a>'
        );
    };

    /**
     * Load balances for ALL providers on Providers page
     * Called once on page load — populates all balance cells
     */
    AioSSL.loadAllBalances = function() {
        // Only run if balance cells exist
        if (!$('[id^="balance-"]').length) return;

        AioSSL.ajax({
            page: 'providers',
            action: 'get_all_balances',
            loading: false,            // no overlay — silent background fetch
            successMessage: false,     // no toast
            onSuccess: function(resp) {
                if (!resp.balances) return;

                $.each(resp.balances, function(slug, data) {
                    var $cell = $('#balance-' + slug);
                    if ($cell.length) {
                        AioSSL._renderBalanceCell($cell, data, slug);
                    }
                });
            },
            onError: function() {
                // Silent fail — replace spinners with dash
                $('[id^="balance-"] .aio-balance-loading').closest('td')
                    .html('<span class="text-muted">&mdash;</span>');
            }
        });
    };

    /**
     * Refresh balance for a single provider
     * @param {string} slug Provider slug
     */
    AioSSL.refreshBalance = function(slug) {
        var $cell = $('#balance-' + slug);
        if (!$cell.length) return;

        $cell.html('<i class="fas fa-spinner fa-spin text-muted"></i>');

        AioSSL.ajax({
            page: 'providers',
            action: 'get_balance',
            data: { slug: slug },
            loading: false,
            successMessage: false,
            onSuccess: function(resp) {
                AioSSL._renderBalanceCell($cell, {
                    supported: true,
                    balance: resp.balance,
                    currency: resp.currency || 'USD',
                    error: resp.success ? null : resp.message
                }, slug);
            },
            onError: function() {
                AioSSL._renderBalanceCell($cell, {
                    supported: true, balance: null, error: 'Request failed'
                }, slug);
            }
        });
    };

    // ─── Event Delegates (balance refresh click) ───────────────

    $(document).on('click', '.aio-refresh-balance', function(e) {
        e.preventDefault();
        AioSSL.refreshBalance($(this).data('slug'));
    });

    // ─── Init ──────────────────────────────────────────────────

    $(document).ready(function() {
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.aio-alert[data-autodismiss]').fadeOut();
        }, 5000);

        // Checkbox select-all
        $(document).on('change', '.aio-check-all', function() {
            var checked = $(this).prop('checked');
            $(this).closest('table').find('.aio-check-item').prop('checked', checked);
        });

        // Auto-load balances if on providers page        
        if ($('[id^="balance-"]').length) {
            AioSSL.loadAllBalances();
        }
    });

    /**
     * Fetch exchange rate from API and update
     */
    AioSSL.fetchExchangeRate = function() {
        AioSSL.ajax({
            page: 'settings',
            action: 'fetch_rate',
            loadingMsg: 'Fetching exchange rate...',
            onSuccess: function(resp) {
                // Update the rate input field
                if (resp.rate) {
                    $('#currency_usd_vnd_rate').val(Math.round(resp.rate));
                }
                // Show result
                var html = '<div class="aio-alert aio-alert-success" style="font-size:12px;">'
                    + '<i class="fas fa-check-circle"></i> ' + resp.message;
                if (resp.change && resp.change !== 0) {
                    html += '<br><small>Previous: ' + (resp.old_rate ? Number(resp.old_rate).toLocaleString() : 'N/A')
                        + ' VND | Change: ' + (resp.change > 0 ? '+' : '') + resp.change + '%</small>';
                }
                html += '</div>';
                $('#rate-test-result').html(html).show();
            },
            onError: function(resp) {
                $('#rate-test-result').html(
                    '<div class="aio-alert aio-alert-danger" style="font-size:12px;">'
                    + '<i class="fas fa-times-circle"></i> ' + (resp.message || 'Failed to fetch rate.')
                    + '</div>'
                ).show();
            }
        });
    };

    /**
     * Test exchange rate API key
     */
    AioSSL.testRateApi = function() {
        var apiKey = $('#exchangerate_api_key').val();
        if (!apiKey) {
            AioSSL.toast('Please enter an API key first.', 'warning');
            return;
        }
        AioSSL.ajax({
            page: 'settings',
            action: 'test_rate_api',
            data: { exchangerate_api_key: apiKey },
            loadingMsg: 'Testing API key...',
            onSuccess: function(resp) {
                $('#rate-test-result').html(
                    '<div class="aio-alert aio-alert-success" style="font-size:12px;">'
                    + '<i class="fas fa-check-circle"></i> ' + resp.message
                    + '</div>'
                ).show();
            },
            onError: function(resp) {
                $('#rate-test-result').html(
                    '<div class="aio-alert aio-alert-danger" style="font-size:12px;">'
                    + '<i class="fas fa-times-circle"></i> ' + (resp.message || 'API test failed.')
                    + '</div>'
                ).show();
            }
        });
    };
})(jQuery);