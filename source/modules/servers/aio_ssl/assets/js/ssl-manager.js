/**
 * AIO SSL Manager — Client Area JavaScript
 * Adapted from NicSRS ref: single-page form, AJAX actions
 */
(function() {
    'use strict';

    var svcId = (document.getElementById('h-serviceid') || {}).value || '';
    var base = window.location.pathname; // clientarea.php

    // ══════════════════════════════════════════
    // CORE AJAX
    // ══════════════════════════════════════════

    function ajax(step, data, cb, opts) {
        opts = opts || {};
        var url = base + '?action=productdetails&id=' + svcId
            + '&modop=custom&a=manage&step=' + step;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            hideLoading();
            try {
                var r = JSON.parse(xhr.responseText);
                if (cb) cb(r);
            } catch(e) {
                showError('Invalid server response.');
                console.error(e, xhr.responseText);
            }
        };
        xhr.onerror = function() { hideLoading(); showError('Network error.'); };
        if (!opts.silent) showLoading(opts.loadingMsg);
        xhr.send(serialize(data));
    }

    function serialize(obj, pfx) {
        var p = [];
        for (var k in obj) {
            if (!obj.hasOwnProperty(k)) continue;
            var key = pfx ? pfx + '[' + k + ']' : k;
            var v = obj[k];
            if (v !== null && typeof v === 'object' && !(v instanceof File))
                p.push(serialize(v, key));
            else
                p.push(encodeURIComponent(key) + '=' + encodeURIComponent(v == null ? '' : v));
        }
        return p.join('&');
    }

    // ══════════════════════════════════════════
    // UI HELPERS
    // ══════════════════════════════════════════

    function showLoading(msg) {
        var el = document.getElementById('loading-overlay');
        if (el) el.style.display = 'flex';
        var t = document.getElementById('loading-text');
        if (t && msg) t.textContent = msg;
    }
    function hideLoading() {
        var el = document.getElementById('loading-overlay');
        if (el) el.style.display = 'none';
    }
    function showError(msg) {
        var el = document.getElementById('global-error'),
            m = document.getElementById('global-error-msg');
        if (el && m) { m.textContent = msg; el.style.display = 'flex'; }
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    function hideError() {
        var el = document.getElementById('global-error');
        if (el) el.style.display = 'none';
    }
    function toast(msg, type) {
        type = type || 'success';
        var d = document.createElement('div');
        d.className = 'sslm-alert sslm-alert-' + type;
        d.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;max-width:400px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        d.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + esc(msg);
        document.body.appendChild(d);
        setTimeout(function() { d.style.opacity = '0'; d.style.transition = 'opacity .3s'; setTimeout(function() { d.remove(); }, 300); }, 4000);
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    // ══════════════════════════════════════════
    // APPLYCERT: CSR Toggle + Generate + Decode
    // ══════════════════════════════════════════

    // CSR manual/auto toggle
    var csrToggle = document.getElementById('isManualCsr');
    if (csrToggle) {
        csrToggle.addEventListener('change', function() {
            document.getElementById('csrSection').style.display = this.checked ? '' : 'none';
            document.getElementById('autoGenSection').style.display = this.checked ? 'none' : '';
        });
    }

    // Generate CSR button
    var genBtn = document.getElementById('generateCsrBtn');
    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var domain = (document.getElementById('h-domain') || {}).value || '';
            ajax('generateCSR', { domain: domain }, function(r) {
                if (r.success) {
                    var csrEl = document.getElementById('csr');
                    if (csrEl) csrEl.value = r.csr;
                    var pkEl = document.getElementById('privateKey');
                    if (pkEl) pkEl.value = r.privateKey || '';
                    // Switch to manual with CSR filled
                    var toggle = document.getElementById('isManualCsr');
                    if (toggle) { toggle.checked = true; toggle.dispatchEvent(new Event('change')); }
                    decodeCsr();
                    toast('CSR generated successfully.');
                } else showError(r.message);
            });
        });
    }

    // Decode CSR button
    var decodeBtn = document.getElementById('decodeCsrBtn');
    if (decodeBtn) {
        decodeBtn.addEventListener('click', decodeCsr);
    }

    function decodeCsr() {
        var csr = (document.getElementById('csr') || {}).value || '';
        if (!csr || csr.indexOf('BEGIN') === -1) { showError('Please enter a valid CSR.'); return; }
        ajax('decodeCsr', { csr: csr }, function(r) {
            if (r.success) {
                var res = document.getElementById('csrDecodeResult');
                if (res) res.style.display = '';
                setText('csrCN', r.CN || '-');
                setText('csrO', r.O || '-');
                setText('csrC', r.C || '-');
                setText('csrST', r.ST || '-');
                setText('csrL', r.L || '-');
                setText('csrKeySize', r.keySize || r.bits || '-');
                hideError();
            } else showError(r.message);
        });
    }

    function setText(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }

    // ══════════════════════════════════════════
    // APPLYCERT: DCV Email Loading
    // ══════════════════════════════════════════

    var dcvSelect = document.getElementById('dcvMethodSelect');
    if (dcvSelect) {
        loadDcvEmails();
        dcvSelect.addEventListener('change', function() {
            // Already handled by inline onchange="toggleDcvEmail()"
        });
    }

    function loadDcvEmails() {
        var domain = (document.getElementById('h-domain') || {}).value || '';
        if (!domain) return;
        ajax('getDcvEmails', { domain: domain }, function(r) {
            var sel = document.getElementById('dcvEmailSelect');
            if (!sel) return;
            sel.innerHTML = '';
            var emails = (r.success && r.emails) ? r.emails : [];
            if (emails.length === 0) {
                var defaults = ['admin@', 'administrator@', 'postmaster@', 'webmaster@', 'hostmaster@'];
                for (var i = 0; i < defaults.length; i++)
                    emails.push(defaults[i] + domain);
            }
            for (var j = 0; j < emails.length; j++) {
                var opt = document.createElement('option');
                opt.value = emails[j]; opt.textContent = emails[j];
                sel.appendChild(opt);
            }
        }, { silent: true });
    }

    // ══════════════════════════════════════════
    // APPLYCERT: SAN Domains
    // ══════════════════════════════════════════

    window.addSanDomain = function() {
        var max = parseInt((document.getElementById('h-max-domains') || {}).value) || 1;
        var list = document.getElementById('san-domains-list');
        if (!list) return;
        var count = list.querySelectorAll('.sslm-san-row').length;
        if (count >= max - 1) { showError('Maximum ' + max + ' domains.'); return; }
        var row = document.createElement('div');
        row.className = 'sslm-san-row';
        row.innerHTML = '<input type="text" name="sanDomains[]" class="sslm-input" placeholder="additional-domain.com" />'
            + '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-danger sslm-btn-outline" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
        list.appendChild(row);
    };

    // ══════════════════════════════════════════
    // APPLYCERT: DCV toggle + Submit + Save
    // ══════════════════════════════════════════

    window.toggleDcvEmail = function() {
        var method = (document.getElementById('dcvMethodSelect') || {}).value || 'email';
        var sec = document.getElementById('dcvEmailSection');
        if (sec) sec.style.display = (method === 'email') ? '' : 'none';
    };

    window.saveDraft = function() {
        var data = collectFormData();
        ajax('saveDraft', { data: data }, function(r) {
            if (r.success) toast('Draft saved.'); else showError(r.message);
        });
    };

    window.submitApply = function() {
        hideError();
        var data = collectFormData();
        // Basic validation
        var csrToggle = document.getElementById('isManualCsr');
        if (csrToggle && csrToggle.checked) {
            if (!data.csr || data.csr.indexOf('BEGIN') === -1) {
                showError('Please enter or generate a valid CSR.'); return;
            }
        }
        ajax('submitApply', { data: data }, function(r) {
            if (r.success) {
                toast('Certificate order submitted successfully!');
                setTimeout(function() { location.reload(); }, 1500);
            } else showError(r.message);
        }, { loadingMsg: 'Submitting order...' });
    };

    function collectFormData() {
        var form = document.getElementById('ssl-apply-form');
        if (!form) return {};
        var data = {};
        // CSR
        data.csr = (document.getElementById('csr') || {}).value || '';
        data.private_key = (document.getElementById('privateKey') || {}).value || '';
        // DCV
        data.dcv_method = (document.getElementById('dcvMethodSelect') || {}).value || 'email';
        data.approver_email = (document.getElementById('dcvEmailSelect') || {}).value || '';
        // SAN domains
        var sanInputs = document.querySelectorAll('input[name="sanDomains[]"]');
        var domains = [];
        var primary = (document.getElementById('h-domain') || {}).value || '';
        if (primary) domains.push({ domainName: primary, dcvMethod: data.dcv_method });
        sanInputs.forEach(function(el) {
            var d = el.value.trim();
            if (d) domains.push({ domainName: d, dcvMethod: data.dcv_method });
        });
        data.domainInfo = domains;
        // Renewal flag
        var renewRadio = document.querySelector('input[name="isRenew"]:checked');
        data.isRenew = renewRadio ? renewRadio.value : '0';
        // Contacts
        data.Administrator = {
            firstName: val('adminFirstName'), lastName: val('adminLastName'),
            email: val('adminEmail'), phone: val('adminPhone'),
            jobTitle: val('adminJobTitle'), organization: val('adminOrganization')
        };
        // OV/EV org
        var valType = (document.getElementById('h-validation-type') || {}).value || 'dv';
        if (valType !== 'dv') {
            data.organizationInfo = {
                organizationName: val('organizationName'), organizationDivision: val('organizationDivision'),
                organizationAddress: val('organizationAddress'), organizationCity: val('organizationCity'),
                organizationState: val('organizationState'), organizationPostCode: val('organizationPostCode'),
                organizationCountry: val('organizationCountry'), organizationPhone: val('organizationPhone'),
                organizationRegNumber: val('organizationRegNumber')
            };
        }
        return data;
    }

    function val(name) {
        var el = document.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    }

    // ══════════════════════════════════════════
    // SSLManager — Actions for issued/pending
    // ══════════════════════════════════════════

    var SSLManager = {
        refreshStatus: function() {
            ajax('refreshStatus', {}, function(r) {
                if (r.success) { toast('Status: ' + (r.status || 'Updated')); setTimeout(function() { location.reload(); }, 1000); }
                else showError(r.message);
            });
        },

        download: function(fmt) {
            window.location.href = base + '?action=productdetails&id=' + svcId
                + '&modop=custom&a=manage&step=downloadCert&format=' + (fmt || 'all');
        },

        resendDcv: function(domain) {
            var d = domain ? { domain: domain } : {};
            ajax('resendDcvEmail', d, function(r) {
                if (r.success) toast(r.message || 'Validation email resent.');
                else showError(r.message);
            });
        },

        confirmRevoke: function() {
            if (!confirm('Are you sure you want to revoke this certificate? This action cannot be undone.')) return;
            ajax('revoke', {}, function(r) {
                if (r.success) { toast('Certificate revoked.'); setTimeout(function() { location.reload(); }, 1000); }
                else showError(r.message);
            });
        },

        confirmCancel: function() {
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) return;
            ajax('cancelOrder', {}, function(r) {
                if (r.success) { toast('Order cancelled.'); setTimeout(function() { location.reload(); }, 1000); }
                else showError(r.message);
            });
        },

        renew: function() {
            ajax('renew', {}, function(r) {
                if (r.success) { toast('Renewal submitted.'); setTimeout(function() { location.reload(); }, 1000); }
                else showError(r.message);
            });
        },

        submitReissue: function() {
            var csr = (document.getElementById('reissue-csr') || {}).value || '';
            var dcv = (document.getElementById('reissue-dcv') || {}).value || 'email';
            var pk = (document.getElementById('reissuePrivateKey') || {}).value || '';
            if (!csr) { showError('New CSR is required.'); return; }
            ajax('submitReissue', { csr: csr, dcv_method: dcv, private_key: pk }, function(r) {
                if (r.success) { toast('Reissue submitted.'); setTimeout(function() { location.reload(); }, 1000); }
                else showError(r.message);
            });
        },

        generateCsrForReissue: function() {
            var domain = (document.getElementById('h-domain') || {}).value || '';
            ajax('generateCSR', { domain: domain }, function(r) {
                if (r.success) {
                    var el = document.getElementById('reissue-csr');
                    if (el) el.value = r.csr;
                    var pk = document.getElementById('reissuePrivateKey');
                    if (pk) pk.value = r.privateKey || '';
                    var toggle = document.getElementById('reissueManualCsr');
                    if (toggle) { toggle.checked = true; toggle.dispatchEvent(new Event('change')); }
                    toast('CSR generated.');
                } else showError(r.message);
            });
        },

        getConfigLink: function() {
            ajax('getConfigLink', {}, function(r) {
                if (r.success && r.config_link) window.open(r.config_link, '_blank');
                else showError(r.message || 'Failed to get configuration link.');
            });
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() { toast('Copied.'); });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
                toast('Copied.');
            }
        }
    };

    window.SSLManager = SSLManager;
})();