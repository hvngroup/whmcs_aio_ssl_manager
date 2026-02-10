/**
 * NicSRS SSL Admin - Main JavaScript
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

(function($) {
    'use strict';

    // Namespace
    window.NicsrsAdmin = window.NicsrsAdmin || {};

    /**
     * Show loading overlay
     * @param {string} message Loading message
     */
    NicsrsAdmin.showLoading = function(message) {
        message = message || 'Loading...';
        
        if ($('#nicsrs-loading').length === 0) {
            $('body').append(
                '<div id="nicsrs-loading" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;">' +
                '<div style="text-align:center;">' +
                '<i class="fa fa-spinner fa-spin fa-3x text-primary"></i>' +
                '<p style="margin-top:15px;" class="loading-message">' + message + '</p>' +
                '</div></div>'
            );
        } else {
            $('#nicsrs-loading .loading-message').text(message);
            $('#nicsrs-loading').show();
        }
    };

    /**
     * Hide loading overlay
     */
    NicsrsAdmin.hideLoading = function() {
        $('#nicsrs-loading').hide();
    };

    /**
     * Show toast notification
     * @param {string} message Message to display
     * @param {string} type Type: success, error, warning, info
     * @param {number} duration Duration in ms
     */
    NicsrsAdmin.toast = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 3000;

        var icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        var colors = {
            success: '#52c41a',
            error: '#ff4d4f',
            warning: '#faad14',
            info: '#1890ff'
        };

        var $toast = $(
            '<div class="nicsrs-toast" style="position:fixed;top:20px;right:20px;z-index:9999;background:#fff;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.15);padding:16px 20px;display:flex;align-items:center;max-width:400px;animation:slideIn 0.3s ease;">' +
            '<i class="fa ' + icons[type] + '" style="color:' + colors[type] + ';font-size:20px;margin-right:12px;"></i>' +
            '<span>' + message + '</span>' +
            '</div>'
        );

        // Add animation style
        if ($('#nicsrs-toast-style').length === 0) {
            $('head').append(
                '<style id="nicsrs-toast-style">' +
                '@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }' +
                '</style>'
            );
        }

        $('body').append($toast);

        setTimeout(function() {
            $toast.css({
                animation: 'slideIn 0.3s ease reverse',
                animationFillMode: 'forwards'
            });
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, duration);
    };

    /**
     * Confirm dialog
     * @param {string} message Confirmation message
     * @param {function} onConfirm Callback on confirm
     * @param {function} onCancel Callback on cancel
     */
    NicsrsAdmin.confirm = function(message, onConfirm, onCancel) {
        if ($('#nicsrs-confirm-modal').length === 0) {
            $('body').append(
                '<div class="modal fade" id="nicsrs-confirm-modal" tabindex="-1">' +
                '<div class="modal-dialog modal-sm">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
                '<h4 class="modal-title">Confirm</h4>' +
                '</div>' +
                '<div class="modal-body"><p class="confirm-message"></p></div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
                '<button type="button" class="btn btn-primary confirm-yes">Yes</button>' +
                '</div></div></div></div>'
            );
        }

        var $modal = $('#nicsrs-confirm-modal');
        $modal.find('.confirm-message').text(message);
        
        $modal.find('.confirm-yes').off('click').on('click', function() {
            $modal.modal('hide');
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        $modal.off('hidden.bs.modal').on('hidden.bs.modal', function() {
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });

        $modal.modal('show');
    };

    /**
     * AJAX request wrapper
     * @param {object} options Request options
     */
    NicsrsAdmin.ajax = function(options) {
        var defaults = {
            method: 'POST',
            dataType: 'json',
            beforeSend: function() {
                if (options.loading !== false) {
                    NicsrsAdmin.showLoading(options.loadingMessage || 'Processing...');
                }
            },
            complete: function() {
                NicsrsAdmin.hideLoading();
            },
            success: function(response) {
                if (response.success) {
                    if (options.successMessage !== false) {
                        NicsrsAdmin.toast(response.message || options.successMessage || 'Success', 'success');
                    }
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess(response);
                    }
                } else {
                    NicsrsAdmin.toast(response.message || 'An error occurred', 'error');
                    if (typeof options.onError === 'function') {
                        options.onError(response);
                    }
                }
            },
            error: function(xhr, status, error) {
                NicsrsAdmin.toast('Request failed: ' + error, 'error');
                if (typeof options.onError === 'function') {
                    options.onError({ success: false, message: error });
                }
            }
        };

        $.ajax($.extend({}, defaults, options));
    };

    /**
     * Format number with commas
     * @param {number} num Number to format
     * @returns {string} Formatted number
     */
    NicsrsAdmin.formatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    /**
     * Copy text to clipboard
     * @param {string} text Text to copy
     */
    NicsrsAdmin.copyToClipboard = function(text) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        NicsrsAdmin.toast('Copied to clipboard', 'success', 1500);
    };

    /**
     * Initialize tooltips
     */
    NicsrsAdmin.initTooltips = function() {
        $('[data-toggle="tooltip"]').tooltip();
    };

    /**
     * Initialize popovers
     */
    NicsrsAdmin.initPopovers = function() {
        $('[data-toggle="popover"]').popover();
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        NicsrsAdmin.initTooltips();
        NicsrsAdmin.initPopovers();

        // Auto-hide alerts after 5 seconds
        $('.nicsrs-content .alert').not('.alert-permanent').delay(5000).fadeOut(500);

        // Confirm for dangerous actions
        $(document).on('click', '[data-confirm]', function(e) {
            var message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Copy to clipboard
        $(document).on('click', '[data-copy]', function() {
            var text = $(this).data('copy');
            NicsrsAdmin.copyToClipboard(text);
        });

        // Auto-submit form on filter change
        $(document).on('change', '.auto-submit', function() {
            $(this).closest('form').submit();
        });

        // Select all checkboxes
        $(document).on('change', '.select-all', function() {
            var isChecked = $(this).prop('checked');
            $(this).closest('table').find('tbody input[type="checkbox"]').prop('checked', isChecked);
        });

        // Disable submit button on form submit to prevent double submit
        $(document).on('submit', 'form', function() {
            $(this).find('button[type="submit"]').prop('disabled', true);
        });
    });

})(jQuery);