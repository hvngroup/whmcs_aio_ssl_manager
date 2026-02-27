/**
 * AIO SSL Manager — Client Area JavaScript
 * Handles: AJAX calls, multi-step wizard, download, DCV management
 *
 * @package AioSSL
 * @author  HVN GROUP
 */

(function () {
    'use strict';

    var serviceId = document.getElementById('h-serviceid')
        ? document.getElementById('h-serviceid').value : '';
    var baseUrl = window.location.pathname; // clientarea.php

    // ═══════════════════════════════════════════════════════════
    // CORE AJAX
    // ═══════════════════════════════════════════════════════════

    function ajax(step, data, callback, method) {
        method = method || 'POST';
        var url = baseUrl + '?action=productdetails&id=' + serviceId + '&modop=custom&a=manage&step=' + step;
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            hideLoading();
            try {
                var resp = JSON.parse(xhr.responseText);
                if (callback) callback(resp);
            } catch (e) {
                showError('Invalid server response. Please try again.');
                console.error('Parse error:', e, xhr.responseText);
            }
        };
        xhr.onerror = function () {
            hideLoading();
            showError('Network error. Please check your connection.');
        };

        showLoading();
        xhr.send(serialize(data));
    }

    function serialize(obj, prefix) {
        var parts = [];
        for (var key in obj) {
            if (!obj.hasOwnProperty(key)) continue;
            var k = prefix ? prefix + '[' + key + ']' : key;
            var v = obj[key];
            if (v !== null && typeof v === 'object' && !(v instanceof File)) {
                parts.push(serialize(v, k));
            } else {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v == null ? '' : v));
            }
        }
        return parts.join('&');
    }

    // ═══════════════════════════════════════════════════════════
    // UI HELPERS
    // ═══════════════════════════════════════════════════════════

    function showLoading(msg) {
        var el = document.getElementById('loading-overlay');
        if (el) { el.style.display = 'flex'; }
        var txt = document.getElementById('loading-text');
        if (txt && msg) txt.textContent = msg;
    }

    function hideLoading() {
        var el = document.getElementById('loading-overlay');
        if (el) el.style.display = 'none';
    }

    function showError(msg) {
        var el = document.getElementById('global-error');
        var msgEl = document.getElementById('global-error-msg');
        if (el && msgEl) { msgEl.textContent = msg; el.style.display = 'flex'; }
    }

    function hideError() {
        var el = document.getElementById('global-error');
        if (el) el.style.display = 'none';
    }

    function toast(msg, type) {
        type = type || 'success';
        var div = document.createElement('div');
        div.className = 'sslm-alert sslm-alert-' + type;
        div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;max-width:400px;animation:sslm-fadeIn 0.3s;';
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
        document.body.appendChild(div);
        setTimeout(function () { div.style.opacity = '0'; setTimeout(function () { div.remove(); }, 300); }, 4000);
    }

    // ═══════════════════════════════════════════════════════════
    // SSL WIZARD (Multi-step application form)
    // ═══════════════════════════════════════════════════════════

    var SSLWizard = {
        currentStep: 1,
        csrData: null,
        privateKey: '',

        switchCsrTab: function (tab) {
            document.querySelectorAll('.sslm-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.sslm-tab-panel').forEach(function (p) { p.classList.remove('active'); });
            document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
            document.getElementById('panel-' + tab).classList.add('active');
        },

        generateCsr: function () {
            var domain = document.getElementById('gen-domain').value.trim();
            if (!domain) { showError('Domain name is required.'); return; }

            ajax('generateCSR', {
                domain: domain,
                organization: document.getElementById('gen-org').value,
                email: document.getElementById('gen-email').value,
                city: document.getElementById('gen-city').value,
                state: document.getElementById('gen-state').value,
                country: document.getElementById('gen-country').value
            }, function (resp) {
                if (resp.success) {
                    document.getElementById('csr-input').value = resp.csr;
                    SSLWizard.privateKey = resp.privateKey || '';
                    // Show private key
                    if (SSLWizard.privateKey) {
                        document.getElementById('private-key-display').value = SSLWizard.privateKey;
                        document.getElementById('private-key-box').style.display = 'flex';
                    }
                    // Switch to paste tab with generated CSR
                    SSLWizard.switchCsrTab('paste');
                    SSLWizard.decodeCsr();
                    toast('CSR generated successfully.');
                } else {
                    showError(resp.message || 'CSR generation failed.');
                }
            });
        },

        decodeCsr: function () {
            var csr = document.getElementById('csr-input').value.trim();
            if (!csr) { showError('Please enter or generate a CSR first.'); return; }

            ajax('decodeCsr', { csr: csr }, function (resp) {
                if (resp.success) {
                    SSLWizard.csrData = resp;
                    var grid = document.getElementById('csr-info-grid');
                    grid.innerHTML = '';
                    var fields = { 'CN': resp.CN || '', 'O': resp.O || '', 'L': resp.L || '', 'ST': resp.ST || '', 'C': resp.C || '' };
                    for (var k in fields) {
                        if (fields[k]) {
                            grid.innerHTML += '<div class="sslm-info-item"><span class="sslm-info-label">' + k + '</span><span class="sslm-info-value">' + escHtml(fields[k]) + '</span></div>';
                        }
                    }
                    document.getElementById('csr-decoded').style.display = 'block';
                    hideError();
                } else {
                    showError(resp.message || 'CSR decode failed.');
                }
            });
        },

        copyKey: function () {
            var el = document.getElementById('private-key-display');
            el.select(); el.setSelectionRange(0, 99999);
            document.execCommand('copy');
            toast('Private key copied to clipboard.');
        },

        addSanDomain: function () {
            var max = parseInt(document.getElementById('h-max-domains').value) || 1;
            var list = document.getElementById('san-domains-list');
            var count = list.querySelectorAll('.san-domain-row').length;
            if (count >= max - 1) { showError('Maximum ' + max + ' domains allowed.'); return; }

            var row = document.createElement('div');
            row.className = 'san-domain-row';
            row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;align-items:center;';
            row.innerHTML = '<input type="text" class="sslm-input san-domain-input" placeholder="additional-domain.com" style="flex:1;" />'
                + '<button type="button" class="sslm-btn sslm-btn-sm sslm-btn-danger sslm-btn-outline" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
            list.appendChild(row);
        },

        nextStep: function (step) {
            hideError();

            if (step === 'submit' || step === 'confirm') {
                SSLWizard.submitOrder();
                return;
            }

            // Validate current step before advancing
            if (SSLWizard.currentStep === 1) {
                var csr = document.getElementById('csr-input').value.trim();
                if (!csr || csr.indexOf('BEGIN CERTIFICATE REQUEST') === -1) {
                    showError('Please enter or generate a valid CSR.');
                    return;
                }
                // Populate DCV step
                SSLWizard.populateDcvStep();
            }

            SSLWizard.goToStep(step);
        },

        prevStep: function (step) {
            hideError();
            SSLWizard.goToStep(step);
        },

        goToStep: function (num) {
            document.querySelectorAll('.sslm-step').forEach(function (s) { s.style.display = 'none'; });
            var target = document.getElementById('step-' + num);
            if (target) target.style.display = 'block';

            document.querySelectorAll('.sslm-progress-step').forEach(function (s) {
                var sn = parseInt(s.getAttribute('data-step'));
                s.classList.remove('active', 'completed');
                if (sn < num) s.classList.add('completed');
                if (sn === num) s.classList.add('active');
            });

            SSLWizard.currentStep = num;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        populateDcvStep: function () {
            var container = document.getElementById('dcv-domains-container');
            if (!container) return;

            var domain = (document.getElementById('h-domain').value || '').trim();
            var domains = [domain];

            // Add SAN domains
            document.querySelectorAll('.san-domain-input').forEach(function (el) {
                var d = el.value.trim();
                if (d) domains.push(d);
            });

            var html = '';
            domains.forEach(function (d, i) {
                html += '<div class="sslm-form-group" style="display:flex;gap:12px;align-items:center;padding:12px;background:var(--sslm-bg);border-radius:4px;margin-bottom:8px;">'
                    + '<code style="flex:1;font-size:13px;">' + escHtml(d) + '</code>'
                    + '<select class="sslm-select dcv-method-select" data-domain="' + escHtml(d) + '" style="width:180px;">'
                    + '<option value="email">Email</option>'
                    + '<option value="http">HTTP File</option>'
                    + '<option value="https">HTTPS File</option>'
                    + '<option value="dns">DNS (CNAME)</option>'
                    + '</select>';

                // Email selector (shown when email method selected)
                html += '<select class="sslm-select dcv-email-select" data-domain="' + escHtml(d) + '" style="width:220px;display:none;">'
                    + '<option value="">Loading emails...</option></select>';
                html += '</div>';
            });

            container.innerHTML = html;

            // Load DCV emails
            container.querySelectorAll('.dcv-method-select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var emailSel = container.querySelector('.dcv-email-select[data-domain="' + sel.dataset.domain + '"]');
                    emailSel.style.display = sel.value === 'email' ? 'inline-block' : 'none';
                    if (sel.value === 'email') SSLWizard.loadDcvEmails(sel.dataset.domain, emailSel);
                });
                // Trigger for default email method
                var emailSel = container.querySelector('.dcv-email-select[data-domain="' + sel.dataset.domain + '"]');
                emailSel.style.display = 'inline-block';
                SSLWizard.loadDcvEmails(sel.dataset.domain, emailSel);
            });
        },

        loadDcvEmails: function (domain, selectEl) {
            ajax('getDcvEmails', { domain: domain }, function (resp) {
                var opts = '';
                if (resp.success && resp.emails) {
                    resp.emails.forEach(function (e) {
                        opts += '<option value="' + escHtml(e) + '">' + escHtml(e) + '</option>';
                    });
                } else {
                    // Default email patterns
                    var defaults = ['admin@', 'administrator@', 'postmaster@', 'webmaster@', 'hostmaster@'];
                    defaults.forEach(function (prefix) {
                        opts += '<option value="' + prefix + domain + '">' + prefix + domain + '</option>';
                    });
                }
                selectEl.innerHTML = opts;
            });
        },

        saveDraft: function () {
            var data = SSLWizard.collectFormData();
            data.step = SSLWizard.currentStep;

            ajax('saveDraft', { data: data }, function (resp) {
                if (resp.success) toast('Draft saved.'); else showError(resp.message);
            });
        },

        resumeDraft: function () {
            var draftEl = document.getElementById('h-draft-data');
            if (draftEl && draftEl.value) {
                try {
                    var draft = JSON.parse(draftEl.value);
                    if (draft.csr) document.getElementById('csr-input').value = draft.csr;
                    if (draft.step) SSLWizard.goToStep(draft.step);
                    document.getElementById('draft-notice').style.display = 'none';
                } catch (e) { console.warn('Draft parse error:', e); }
            }
        },

        startFresh: function () {
            document.getElementById('draft-notice').style.display = 'none';
        },

        collectFormData: function () {
            var data = {
                csr: (document.getElementById('csr-input') || {}).value || '',
                private_key: SSLWizard.privateKey || '',
                dcv_method: 'email',
                domains: [],
            };

            // Collect DCV per domain
            document.querySelectorAll('.dcv-method-select').forEach(function (sel) {
                var domain = sel.dataset.domain;
                var method = sel.value;
                var email = '';
                if (method === 'email') {
                    var emailSel = document.querySelector('.dcv-email-select[data-domain="' + domain + '"]');
                    email = emailSel ? emailSel.value : '';
                }
                data.domains.push({ domainName: domain, dcvMethod: method, approverEmail: email });
                if (!data.dcv_method || data.dcv_method === 'email') data.dcv_method = method;
            });

            // First domain approver email
            if (data.domains.length > 0 && data.domains[0].approverEmail) {
                data.approver_email = data.domains[0].approverEmail;
            }

            // OV/EV contacts
            var validType = (document.getElementById('h-validation-type') || {}).value || 'dv';
            if (validType !== 'dv') {
                data.organizationInfo = {
                    name: (document.getElementById('org-name') || {}).value || '',
                    division: (document.getElementById('org-division') || {}).value || '',
                    phone: (document.getElementById('org-phone') || {}).value || '',
                    address: (document.getElementById('org-address') || {}).value || '',
                    city: (document.getElementById('org-city') || {}).value || '',
                    state: (document.getElementById('org-state') || {}).value || '',
                    country: (document.getElementById('org-country') || {}).value || '',
                    zip: (document.getElementById('org-zip') || {}).value || ''
                };
                data.Administrator = {
                    FirstName: (document.getElementById('admin-firstname') || {}).value || '',
                    LastName: (document.getElementById('admin-lastname') || {}).value || '',
                    Email: (document.getElementById('admin-email') || {}).value || '',
                    Phone: (document.getElementById('admin-phone') || {}).value || '',
                    Title: (document.getElementById('admin-title') || {}).value || ''
                };
            }

            return data;
        },

        submitOrder: function () {
            var data = SSLWizard.collectFormData();
            if (!data.csr) { showError('CSR is required.'); return; }

            ajax('submitApply', { data: data }, function (resp) {
                if (resp.success) {
                    toast('Order submitted successfully!');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    showError(resp.message || 'Submit failed.');
                }
            });
        }
    };

    // ═══════════════════════════════════════════════════════════
    // SSL MANAGER (Actions for issued/pending certs)
    // ═══════════════════════════════════════════════════════════

    var SSLManager = {
        refreshStatus: function () {
            ajax('refreshStatus', {}, function (resp) {
                if (resp.success) {
                    toast('Status: ' + (resp.status || 'Updated'));
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    showError(resp.message);
                }
            });
        },

        download: function (format) {
            window.location.href = baseUrl + '?action=productdetails&id=' + serviceId
                + '&modop=custom&a=manage&step=downloadCert&format=' + (format || 'all');
        },

        resendDcv: function (domain) {
            var data = {};
            if (domain) data.domain = domain;
            ajax('resendDcvEmail', data, function (resp) {
                if (resp.success) toast('Validation email resent.'); else showError(resp.message);
            });
        },

        confirmRevoke: function () {
            if (!confirm('Are you sure you want to revoke this certificate? This action cannot be undone.')) return;
            ajax('revoke', {}, function (resp) {
                if (resp.success) { toast('Certificate revoked.'); setTimeout(function () { location.reload(); }, 1000); }
                else showError(resp.message);
            });
        },

        renew: function () {
            ajax('renew', {}, function (resp) {
                if (resp.success) { toast('Renewal submitted.'); setTimeout(function () { location.reload(); }, 1000); }
                else showError(resp.message);
            });
        },

        submitReissue: function () {
            var csr = (document.getElementById('reissue-csr') || {}).value || '';
            var dcv = (document.getElementById('reissue-dcv') || {}).value || 'email';
            if (!csr) { showError('New CSR is required.'); return; }
            ajax('submitReissue', { csr: csr, dcv_method: dcv }, function (resp) {
                if (resp.success) { toast('Reissue submitted.'); setTimeout(function () { location.reload(); }, 1000); }
                else showError(resp.message);
            });
        },

        generateCsrForReissue: function () {
            var domain = (document.getElementById('h-domain') || {}).value || '';
            ajax('generateCSR', { domain: domain }, function (resp) {
                if (resp.success) {
                    document.getElementById('reissue-csr').value = resp.csr;
                    toast('CSR generated.');
                } else showError(resp.message);
            });
        },

        getConfigLink: function () {
            ajax('getConfigLink', {}, function (resp) {
                if (resp.success && resp.config_link) {
                    window.open(resp.config_link, '_blank');
                } else showError(resp.message || 'Failed to get configuration link.');
            });
        },

        copyToClipboard: function (text) {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta);
            toast('Copied to clipboard.');
        }
    };

    function escHtml(s) {
        var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML;
    }

    // Export to global
    window.SSLWizard = SSLWizard;
    window.SSLManager = SSLManager;
})();