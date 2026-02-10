<?php
/**
 * Import SSL Orders Template
 * 
 * File: templates/import.php
 * 
 * @var array $availableServices Services available for linking
 * @var array $linkedOrders Recently linked orders
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */
?>

<div class="nicsrs-import">
    
    <!-- Header -->
    <div class="page-header">
        <h3>
            <i class="fa fa-download"></i> Import SSL Certificates
            <small class="text-muted">Link NicSRS certificates to WHMCS</small>
        </h3>
    </div>

    <div class="row">
        <!-- Left: Import Form -->
        <div class="col-md-6">
            
            <!-- Lookup Certificate -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-search"></i> Step 1: Lookup Certificate</h3>
                </div>
                <div class="panel-body">
                    <form id="lookupForm">
                        <div class="form-group">
                            <label>Certificate ID from NicSRS:</label>
                            <input type="text" class="form-control" id="certId" name="cert_id" 
                                   placeholder="Enter NicSRS Certificate ID (e.g., 12345678)" required>
                            <p class="help-block">You can find this ID in your NicSRS portal > Instance ID</p>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i> Lookup Certificate
                        </button>
                    </form>

                    <!-- Lookup Result -->
                    <div id="lookupResult" style="display: none; margin-top: 20px;">
                        <hr>
                        <h4><i class="fa fa-certificate"></i> Certificate Details</h4>
                        <table class="table table-condensed">
                            <tr>
                                <th style="width: 120px;">Cert ID:</th>
                                <td><code id="resultCertId"></code></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><span id="resultStatus"></span></td>
                            </tr>
                            <tr>
                                <th>Domain(s):</th>
                                <td id="resultDomains"></td>
                            </tr>
                            <tr>
                                <th>Valid From:</th>
                                <td id="resultBeginDate"></td>
                            </tr>
                            <tr>
                                <th>Valid Until:</th>
                                <td id="resultEndDate"></td>
                            </tr>
                            <tr>
                                <th>Certificate:</th>
                                <td id="resultHasCert"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Link to Service -->
            <div class="panel panel-success" id="linkPanel" style="display: none;">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-link"></i> Step 2: Link to WHMCS Service</h3>
                </div>
                <div class="panel-body">
                    <form id="linkForm">
                        <input type="hidden" id="linkCertId" name="cert_id">
                        
                        <!-- Option 1: Link to existing service by ID -->
                        <div class="form-group">
                            <label>
                                <input type="radio" name="link_option" value="existing" id="optionExisting" checked>
                                Link to existing WHMCS Service
                            </label>
                        </div>
                        
                        <div id="existingServiceFields">
                            <div class="form-group">
                                <label>Service ID:</label>
                                <input type="text" class="form-control" id="serviceId" name="service_id" 
                                       placeholder="Enter WHMCS Service ID (e.g., 123)">
                                <p class="help-block">
                                    <i class="fa fa-info-circle"></i> 
                                    <strong>How to find Service ID:</strong><br>
                                    Go to <strong>Clients → Products/Services</strong>, find the SSL product, 
                                    and look at the URL - the <code>id=XXX</code> parameter is the Service ID.<br>
                                    Example: <code>clientsservices.php?userid=1&<strong>id=123</strong></code> → Service ID is <strong>123</strong>
                                </p>
                            </div>
                        </div>

                        <hr>

                        <!-- Option 2: Import without linking -->
                        <div class="form-group">
                            <label>
                                <input type="radio" name="link_option" value="import_only" id="optionImportOnly">
                                Import without linking (can link later)
                            </label>
                            <p class="help-block text-muted" style="margin-left: 20px;">
                                Certificate will be imported to Orders list. You can link it to a WHMCS service later.
                            </p>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                            <i class="fa fa-check"></i> <span id="submitBtnText">Import & Link Certificate</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right: Info & History -->
        <div class="col-md-6">
            
            <!-- Instructions -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> How to Import</h3>
                </div>
                <div class="panel-body">
                    <ol>
                        <li><strong>Get Certificate ID</strong> from your NicSRS portal (Orders → SSL Orders → Instance ID)</li>
                        <li><strong>Lookup</strong> the certificate using the form</li>
                        <li><strong>Link</strong> to an existing WHMCS service OR import without linking</li>
                        <li>The certificate will appear in Orders list and be manageable from admin</li>
                    </ol>
                    
                    <hr>
                    
                    <h5><i class="fa fa-lightbulb-o"></i> Use Cases:</h5>
                    <ul>
                        <li>Import certificates purchased directly from NicSRS</li>
                        <li>Migrate existing certificates to WHMCS management</li>
                        <li>Link certificates issued outside WHMCS workflow</li>
                    </ul>

                    <hr>

                    <h5><i class="fa fa-question-circle"></i> Finding Service ID:</h5>
                    <ol>
                        <li>Go to <strong>Clients → Products/Services</strong></li>
                        <li>Search for the client or SSL product</li>
                        <li>Click on the service to open it</li>
                        <li>Look at the URL in your browser</li>
                        <li>Find <code>id=XXX</code> - that number is the Service ID</li>
                    </ol>
                </div>
            </div>

            <!-- Bulk Import -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-list"></i> Bulk Import</h3>
                </div>
                <div class="panel-body">
                    <form id="bulkImportForm">
                        <div class="form-group">
                            <label>Enter Certificate IDs (one per line):</label>
                            <textarea class="form-control" id="bulkCertIds" name="cert_ids" rows="5" 
                                      placeholder="12345678&#10;23456789&#10;34567890"></textarea>
                        </div>
                        <button type="submit" class="btn btn-default">
                            <i class="fa fa-upload"></i> Bulk Import (without linking)
                        </button>
                        <p class="help-block">
                            <small>Certificates will be imported without linking to services. You can link them later.</small>
                        </p>
                    </form>
                </div>
            </div>

            <!-- Recently Imported -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-history"></i> Recently Imported</h3>
                </div>
                <div class="panel-body" style="padding: 0; max-height: 300px; overflow-y: auto;">
                    <table class="table table-striped table-condensed" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($linkedOrders)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No imported certificates yet</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($linkedOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $modulelink; ?>&action=order&id=<?php echo $order->id; ?>">
                                        #<?php echo $order->id; ?>
                                    </a>
                                </td>
                                <td><?php echo $helper->truncate($order->domain, 25); ?></td>
                                <td><?php echo $helper->statusBadge($order->status); ?></td>
                                <td>
                                    <?php if ($order->end_date): ?>
                                    <?php echo $helper->daysLeftBadge($order->end_date); ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 40px;">
                <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                <h4 style="margin-top: 20px;" id="loadingMessage">Processing...</h4>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modulelink = '<?php echo $modulelink; ?>';
    var certData = null;

    // Handle radio button changes
    document.querySelectorAll('input[name="link_option"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var existingFields = document.getElementById('existingServiceFields');
            var submitBtnText = document.getElementById('submitBtnText');
            var serviceIdInput = document.getElementById('serviceId');
            
            if (this.value === 'existing') {
                existingFields.style.display = 'block';
                submitBtnText.textContent = 'Import & Link Certificate';
                serviceIdInput.required = false; // Not required, will be validated on submit
            } else {
                existingFields.style.display = 'none';
                submitBtnText.textContent = 'Import Certificate';
                serviceIdInput.required = false;
            }
        });
    });

    // Lookup Certificate
    document.getElementById('lookupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var certId = document.getElementById('certId').value.trim();
        if (!certId) return;

        showLoading('Looking up certificate...');

        $.ajax({
            url: modulelink + '&action=import',
            method: 'POST',
            data: { ajax_action: 'lookup_cert', cert_id: certId },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    certData = response.certificate;
                    displayCertificate(certData);
                } else {
                    alert('Error: ' + response.message);
                    document.getElementById('lookupResult').style.display = 'none';
                    document.getElementById('linkPanel').style.display = 'none';
                }
            },
            error: function() {
                hideLoading();
                alert('Request failed. Please try again.');
            }
        });
    });

    // Display certificate info
    function displayCertificate(cert) {
        document.getElementById('resultCertId').textContent = cert.certId;
        document.getElementById('resultStatus').innerHTML = getStatusBadge(cert.status);
        document.getElementById('resultBeginDate').textContent = cert.beginDate || 'N/A';
        document.getElementById('resultEndDate').textContent = cert.endDate || 'N/A';
        document.getElementById('resultHasCert').innerHTML = cert.hasCertificate 
            ? '<span class="text-success"><i class="fa fa-check"></i> Available</span>'
            : '<span class="text-muted">Not yet issued</span>';

        // Display domains
        var domainsHtml = '';
        if (cert.domains && cert.domains.length) {
            cert.domains.forEach(function(d) {
                domainsHtml += '<div>' + d.domain;
                if (d.verified) {
                    domainsHtml += ' <span class="label label-success">Verified</span>';
                }
                domainsHtml += '</div>';
            });
        } else {
            domainsHtml = cert.domain || 'N/A';
        }
        document.getElementById('resultDomains').innerHTML = domainsHtml;

        document.getElementById('lookupResult').style.display = 'block';
        document.getElementById('linkPanel').style.display = 'block';
        document.getElementById('linkCertId').value = cert.certId;
    }

    function getStatusBadge(status) {
        var badges = {
            'complete': '<span class="label label-success">Complete</span>',
            'pending': '<span class="label label-warning">Pending</span>',
            'cancelled': '<span class="label label-danger">Cancelled</span>'
        };
        return badges[status.toLowerCase()] || '<span class="label label-default">' + status + '</span>';
    }

    // Link Form Submit
    document.getElementById('linkForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var certId = document.getElementById('linkCertId').value;
        if (!certId) {
            alert('Please lookup a certificate first');
            return;
        }

        var linkOption = document.querySelector('input[name="link_option"]:checked').value;
        var serviceId = document.getElementById('serviceId').value.trim();

        // Validate service ID if linking to existing
        if (linkOption === 'existing') {
            if (!serviceId) {
                alert('Please enter a Service ID to link the certificate');
                return;
            }
            // Validate it's a number
            if (!/^\d+$/.test(serviceId)) {
                alert('Service ID must be a number');
                return;
            }
        }

        var action = (linkOption === 'existing' && serviceId) ? 'link_existing' : 'import_cert';
        var data = {
            ajax_action: action,
            cert_id: certId,
            service_id: serviceId || 0
        };

        var confirmMsg = (action === 'link_existing') 
            ? 'Import certificate and link to Service #' + serviceId + '?'
            : 'Import certificate without linking to a service?';

        if (!confirm(confirmMsg)) {
            return;
        }

        showLoading('Importing certificate...');

        $.ajax({
            url: modulelink + '&action=import',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('AJAX Error:', status, error);
                alert('Request failed. Please try again.');
            }
        });
    });

    // Bulk Import
    document.getElementById('bulkImportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var textarea = document.getElementById('bulkCertIds').value.trim();
        if (!textarea) {
            alert('Please enter at least one certificate ID');
            return;
        }

        var certIds = textarea.split('\n').map(function(id) {
            return id.trim();
        }).filter(function(id) {
            return id.length > 0;
        });

        if (!certIds.length) {
            alert('No valid certificate IDs found');
            return;
        }

        if (!confirm('Import ' + certIds.length + ' certificates?')) {
            return;
        }

        showLoading('Importing ' + certIds.length + ' certificates...');

        $.ajax({
            url: modulelink + '&action=import',
            method: 'POST',
            data: { ajax_action: 'bulk_import', cert_ids: certIds },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                var msg = response.message;
                if (response.errors && response.errors.length) {
                    msg += '\n\nErrors:\n' + response.errors.join('\n');
                }
                alert(msg);
                if (response.imported > 0) {
                    location.reload();
                }
            },
            error: function() {
                hideLoading();
                alert('Request failed. Please try again.');
            }
        });
    });

    function showLoading(message) {
        document.getElementById('loadingMessage').textContent = message || 'Processing...';
        $('#loadingModal').modal('show');
    }

    function hideLoading() {
        $('#loadingModal').modal('hide');
    }
});
</script>