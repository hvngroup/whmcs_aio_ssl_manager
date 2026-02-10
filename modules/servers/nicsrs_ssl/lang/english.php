<?php
/**
 * NicSRS SSL Module - English Language File (COMPLETE)
 * Covers ALL $_LANG keys from: applycert.tpl, message.tpl, manage.tpl,
 * complete.tpl, reissue.tpl, cancelled.tpl, error.tpl + JS config blocks
 *
 * @package    nicsrs_ssl
 * @version    2.1.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

// =============================================
// GENERAL
// =============================================
$_LANG['ssl_management'] = 'SSL Management';
$_LANG['ssl_certificate'] = 'SSL Certificate';
$_LANG['configure_certificate'] = 'Configure Certificate';
$_LANG['certificate_management'] = 'Certificate Management';
$_LANG['certificate_status'] = 'Certificate Status';
$_LANG['module_version'] = 'Module Version';

// =============================================
// STATUS
// =============================================
$_LANG['status'] = 'Status';
$_LANG['awaiting_configuration'] = 'Awaiting Configuration';
$_LANG['draft'] = 'Draft';
$_LANG['pending'] = 'Pending';
$_LANG['processing'] = 'Processing';
$_LANG['complete'] = 'Complete';
$_LANG['issued'] = 'Issued';
$_LANG['cancelled'] = 'Cancelled';
$_LANG['revoked'] = 'Revoked';
$_LANG['expired'] = 'Expired';
$_LANG['reissue_pending'] = 'Reissue Pending';
$_LANG['verified'] = 'Verified';
$_LANG['un_verified'] = 'Unverified';
$_LANG['active'] = 'Active';
$_LANG['pending_validation'] = 'Pending Validation';
$_LANG['awaiting_validation'] = 'Awaiting Domain Validation';

// =============================================
// PROGRESS STEPS (applycert.tpl)
// =============================================
$_LANG['step'] = 'Step';
$_LANG['step_configure'] = 'Configure';
$_LANG['step_submit'] = 'Submit';
$_LANG['step_validation'] = 'Validation';
$_LANG['step_issued'] = 'Issued';
$_LANG['step_validated'] = 'Validated';

// PROGRESS STEPS (message.tpl / manage.tpl)
$_LANG['step_ordered'] = 'Ordered';
$_LANG['step_submitted'] = 'Submitted';

// PROGRESS STEPS (reissue.tpl)
$_LANG['step_reissue'] = 'Reissue';
$_LANG['step_new_cert'] = 'New Certificate';

// PROGRESS STEPS (applycert.tpl - original)
$_LANG['step_csr'] = 'CSR';
$_LANG['step_domain'] = 'Domain Validation';
$_LANG['step_contacts'] = 'Contact Information';
$_LANG['step_organization'] = 'Organization';
$_LANG['step_review'] = 'Review';

// =============================================
// CSR SECTION (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['csr'] = 'CSR';
$_LANG['csr_configuration'] = 'CSR Configuration';
$_LANG['csr_des'] = 'A Certificate Signing Request (CSR) is required to apply for an SSL certificate. You can auto-generate one or paste an existing CSR.';
$_LANG['csr_section_guide'] = 'A CSR (Certificate Signing Request) contains your domain and organization info. You can auto-generate one or paste your own if you already have it.';
$_LANG['auto_generate_csr'] = 'Auto-generate CSR';
$_LANG['manual_csr'] = 'Enter CSR manually';
$_LANG['is_manual_csr'] = 'I have my own CSR';
$_LANG['paste_csr'] = 'Paste your CSR here';
$_LANG['generate_csr'] = 'Generate CSR';
$_LANG['decode_csr'] = 'Decode CSR';
$_LANG['csr_decoded'] = 'CSR decoded successfully';
$_LANG['invalid_csr'] = 'Invalid CSR format';
$_LANG['generating_csr'] = 'Generating CSR...';
$_LANG['csr_generated'] = 'CSR generated successfully';
$_LANG['csr_info'] = 'CSR Information';
$_LANG['enter_csr'] = 'Please enter a CSR';
$_LANG['key_size'] = 'Key Size';
$_LANG['key_type'] = 'Key Type';

// =============================================
// CSR FIELDS (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['common_name'] = 'Common Name (Domain)';
$_LANG['organization'] = 'Organization';
$_LANG['organizational_unit'] = 'Organizational Unit';
$_LANG['city'] = 'City';
$_LANG['locality'] = 'City/Locality';
$_LANG['state'] = 'State/Province';
$_LANG['province'] = 'Province';
$_LANG['country'] = 'Country';
$_LANG['email'] = 'Email';
$_LANG['email_address'] = 'Email Address';

// =============================================
// DOMAIN SECTION (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['domain'] = 'Domain';
$_LANG['domain_info'] = 'Domain Information';
$_LANG['domain_name'] = 'Domain Name';
$_LANG['primary_domain'] = 'Primary Domain';
$_LANG['additional_domains'] = 'Additional Domains';
$_LANG['add_domain'] = 'Add Domain';
$_LANG['remove_domain'] = 'Remove';
$_LANG['max_domain'] = 'Maximum Domains';
$_LANG['add'] = 'Add';
$_LANG['set_for_all'] = 'Set for all';
$_LANG['must_same_pmain'] = 'Must be the same as the main domain';
$_LANG['overplus'] = 'You have exceeded the maximum number of domains';
$_LANG['domain_section_guide'] = 'Enter the domain name(s) you want to protect and select a validation method. For Email validation, options will appear based on your domain.';
$_LANG['domain_required'] = 'Please enter a domain name first';
$_LANG['at_least_one_domain'] = 'At least one domain is required';
$_LANG['max_domains_reached'] = 'Maximum domains reached';

// =============================================
// DCV - Domain Control Validation (all templates)
// =============================================
$_LANG['dcv'] = 'DCV';
$_LANG['dcv_method'] = 'DCV Method';
$_LANG['dcv_status'] = 'DCV Status';
$_LANG['domain_validation'] = 'Domain Validation';
$_LANG['domain_validation_required'] = 'Domain Validation Required';
$_LANG['please_choose'] = '-- Select DCV Method --';
$_LANG['http_csr_hash'] = 'HTTP File Validation';
$_LANG['https_csr_hash'] = 'HTTPS File Validation';
$_LANG['cname_csr_hash'] = 'DNS CNAME Validation';
$_LANG['dns_csr_hash'] = 'DNS TXT Validation';
$_LANG['email_validation'] = 'Email Validation';
$_LANG['file_validation'] = 'File/DNS Validation';
$_LANG['update_dcv'] = 'Update DCV';
$_LANG['resend_dcv'] = 'Resend DCV';
$_LANG['dcv_instructions'] = 'DCV Instructions';
$_LANG['select_method'] = 'Please select a validation method';

// DCV Instructions (message.tpl / manage.tpl)
$_LANG['action_required'] = 'Action Required: Domain Validation';
$_LANG['important'] = 'Important';
$_LANG['dcv_instruction_main'] = 'Complete domain validation to receive your SSL certificate. Follow the instructions below for each domain.';
$_LANG['dns_instruction'] = 'Add the following CNAME record to your DNS settings:';
$_LANG['dns_txt_instruction'] = 'Add the following TXT record to your DNS settings:';
$_LANG['http_instruction'] = 'Create a file at the following URL with the content shown below:';
$_LANG['email_instruction'] = 'A validation email has been sent to:';
$_LANG['record_type'] = 'Type';
$_LANG['host_name'] = 'Host/Name';
$_LANG['points_to'] = 'Points To';
$_LANG['txt_value'] = 'TXT Value';
$_LANG['file_url'] = 'File URL';
$_LANG['file_content_label'] = 'File Content';
$_LANG['dns_propagation'] = 'DNS changes may take 5-30 minutes to propagate.';
$_LANG['dns_propagation_note'] = 'DNS changes may take 5-30 minutes to propagate.';
$_LANG['http_note'] = 'The file must be accessible via HTTP/HTTPS. Content-Type should be text/plain.';
$_LANG['email_note'] = 'Check your inbox and spam folder. Click the validation link in the email.';
$_LANG['resend_email'] = 'Resend Email';
$_LANG['change'] = 'Change';
$_LANG['validation_status'] = 'Validation Status';
$_LANG['validation_desc'] = 'Your certificate request has been submitted. Complete the domain validation below to receive your SSL certificate.';

// DCV legacy keys
$_LANG['http_instructions'] = 'Create a file at the following path on your server:';
$_LANG['https_instructions'] = 'Create a file at the following HTTPS path on your server:';
$_LANG['dns_cname_instructions'] = 'Add the following CNAME record to your DNS:';
$_LANG['dns_txt_instructions'] = 'Add the following TXT record to your DNS:';
$_LANG['email_instructions'] = 'A verification email will be sent to the selected address.';
$_LANG['file_path'] = 'File Path';
$_LANG['file_content'] = 'File Content';
$_LANG['dns_host'] = 'DNS Host';
$_LANG['dns_value'] = 'DNS Value';
$_LANG['dns_type'] = 'DNS Type';
$_LANG['dcv_email'] = 'Verification Email';
$_LANG['down_txt'] = 'Download validation file';

// Change DCV Modal
$_LANG['change_dcv_title'] = 'Change Validation Method';
$_LANG['change_dcv_desc'] = 'Select a new validation method for this domain:';
$_LANG['select_new_method'] = 'Select New Method';
$_LANG['dcv_change_success'] = 'Validation method updated successfully';
$_LANG['dcv_change_failed'] = 'Failed to update validation method';

// =============================================
// CONTACT INFORMATION (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['contacts'] = 'Contact Information';
$_LANG['admin_contact'] = 'Administrator Contact';
$_LANG['tech_contact'] = 'Technical Contact';
$_LANG['contact_section_guide'] = 'This information will appear on your certificate. The admin email will receive important notifications about your SSL certificate. All fields marked with * are required by the Certificate Authority.';
$_LANG['admin_email_note'] = 'Certificate notifications will be sent to this email.';
$_LANG['title'] = 'Title/Job Position';
$_LANG['job_title'] = 'Job Title';
$_LANG['first_name'] = 'First Name';
$_LANG['last_name'] = 'Last Name';
$_LANG['phone'] = 'Phone Number';
$_LANG['organization_name'] = 'Organization Name';
$_LANG['address'] = 'Address';
$_LANG['post_code'] = 'Postal Code';
$_LANG['select_country'] = '-- Select Country --';

// =============================================
// ORGANIZATION (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['organization_info'] = 'Organization Information';
$_LANG['org_section_guide'] = 'For OV/EV certificates, your organization details will be verified and displayed in the certificate. Please ensure all information is accurate.';
$_LANG['org_name'] = 'Organization Name';
$_LANG['org_address'] = 'Address';
$_LANG['org_city'] = 'City';
$_LANG['org_state'] = 'State/Province';
$_LANG['org_country'] = 'Country';
$_LANG['org_postal'] = 'Postal Code';
$_LANG['org_phone'] = 'Phone';
$_LANG['org_division'] = 'Division/Department';
$_LANG['idType'] = 'ID Type';
$_LANG['organizationCode'] = 'Tax ID / Business Registration';
$_LANG['organizationRegNumber'] = 'Registration Number';
$_LANG['registration_number'] = 'Registration Number';
$_LANG['other'] = 'Other';

// =============================================
// RENEWAL (applycert.tpl)
// =============================================
$_LANG['is_renew'] = 'Is this a renewal?';
$_LANG['is_renew_des'] = 'Select "Yes" if renewing an existing certificate to receive bonus time.';
$_LANG['is_renew_option_new'] = 'No, new certificate';
$_LANG['is_renew_option_renew'] = 'Yes, renewal';

// =============================================
// ORDER DETAILS (message.tpl, manage.tpl)
// =============================================
$_LANG['order_details'] = 'Order Details';
$_LANG['order_id'] = 'Order ID';
$_LANG['order_date'] = 'Order Date';
$_LANG['certificate_pending'] = 'Certificate Pending Validation';
$_LANG['message_desc'] = 'Your certificate request has been submitted. Please complete domain validation below to receive your SSL certificate.';

// =============================================
// ACTIONS (all templates)
// =============================================
$_LANG['actions'] = 'Actions';
$_LANG['submit'] = 'Submit';
$_LANG['submit_request'] = 'Submit Request';
$_LANG['submit_reissue'] = 'Submit Reissue Request';
$_LANG['save_draft'] = 'Save Draft';
$_LANG['cancel'] = 'Cancel';
$_LANG['back'] = 'Back';
$_LANG['next'] = 'Next';
$_LANG['refresh'] = 'Refresh';
$_LANG['refresh_status'] = 'Refresh Status';
$_LANG['download'] = 'Download';
$_LANG['download_certificate'] = 'Download Certificate';
$_LANG['down_cert'] = 'Download Certificate';
$_LANG['down_key'] = 'Download Private Key';
$_LANG['reissue'] = 'Reissue Certificate';
$_LANG['repalce'] = 'Reissue';
$_LANG['reissue_certificate'] = 'Reissue Certificate';
$_LANG['renew'] = 'Renew';
$_LANG['renew_certificate'] = 'Renew Certificate';
$_LANG['revoke'] = 'Revoke Certificate';
$_LANG['revoke_certificate'] = 'Revoke Certificate';
$_LANG['cancel_order'] = 'Cancel Order';
$_LANG['submitting'] = 'Submitting...';
$_LANG['updating'] = 'Updating...';
$_LANG['refreshing'] = 'Refreshing...';
$_LANG['revoking'] = 'Revoking...';
$_LANG['downloading'] = 'Downloading...';

// =============================================
// DOWNLOAD FORMATS (complete.tpl)
// =============================================
$_LANG['select_download_format'] = 'Select Download Format';
$_LANG['format_all'] = 'All Formats (ZIP)';
$_LANG['format_apache'] = 'Apache (.crt + .ca-bundle)';
$_LANG['format_nginx'] = 'Nginx (.pem)';
$_LANG['format_iis'] = 'IIS (.p12)';
$_LANG['format_tomcat'] = 'Tomcat (.jks)';
$_LANG['all_formats'] = 'All Formats';
$_LANG['complete_package'] = 'Complete ZIP package';
$_LANG['download_all'] = 'Download All';
$_LANG['download_info'] = 'Select the format that matches your web server. If unsure, download "All Formats" to get everything.';

// =============================================
// CERTIFICATE INFO (complete.tpl)
// =============================================
$_LANG['certificate'] = 'Certificate';
$_LANG['certificate_info'] = 'Certificate Information';
$_LANG['certificate_id'] = 'Certificate ID';
$_LANG['certificate_type'] = 'Certificate Type';
$_LANG['certificate_issued'] = 'Certificate Issued';
$_LANG['certificate_pending'] = 'Certificate Pending';
$_LANG['cert_status'] = 'Certificate Status';
$_LANG['cert_begin'] = 'Issue Date';
$_LANG['cert_end'] = 'Expiry Date';
$_LANG['days_remaining'] = 'Days Remaining';
$_LANG['expiring_soon'] = 'Expiring Soon';
$_LANG['vendor_id'] = 'Vendor ID';
$_LANG['last_refresh'] = 'Last Refresh';
$_LANG['certificate_content'] = 'Certificate Content';
$_LANG['certificate_actions'] = 'Certificate Actions';
$_LANG['ca_bundle'] = 'CA Bundle';
$_LANG['private_key'] = 'Private Key';
$_LANG['validity_period'] = 'Validity Period';
$_LANG['valid_from'] = 'Valid From';
$_LANG['valid_until'] = 'Valid Until';
$_LANG['secured_domains'] = 'Secured Domains';
$_LANG['validation_type'] = 'Validation Type';

// Private Key (complete.tpl)
$_LANG['private_key_notice'] = 'Private Key Not Available';
$_LANG['private_key_notice_desc'] = 'The private key is not stored in our system. If you generated the CSR elsewhere, use your original private key.';
$_LANG['private_key_password'] = 'Private Key Password';
$_LANG['password_notice'] = 'Please save this password securely. You will need it when installing the certificate.';
$_LANG['password_for_format'] = 'Password for %s format';
$_LANG['all_passwords'] = 'All Passwords';

// Installation Help (complete.tpl)
$_LANG['installation_help'] = 'Installation Help';
$_LANG['apache_help'] = 'Apache';
$_LANG['apache_help_desc'] = 'Upload .crt, .ca-bundle, and .key files. Update your VirtualHost configuration.';
$_LANG['nginx_help'] = 'Nginx';
$_LANG['nginx_help_desc'] = 'Use the .pem file (combined cert) with your .key file in server block.';
$_LANG['iis_help'] = 'IIS';
$_LANG['iis_help_desc'] = 'Import the .p12 (PKCS#12) file through IIS Manager or MMC snap-in.';
$_LANG['cpanel_help'] = 'cPanel';
$_LANG['cpanel_help_desc'] = 'Go to SSL/TLS in cPanel and paste your certificate and key in the Install section.';
$_LANG['tomcat_help'] = 'Tomcat';
$_LANG['tomcat_help_desc'] = 'Import the .jks (Java KeyStore) file into your Tomcat server.xml configuration.';

// =============================================
// REISSUE (reissue.tpl)
// =============================================
$_LANG['reissue_reason'] = 'Reason for Reissue';
$_LANG['reissue_reason_guide'] = 'Please select the reason for reissuing your certificate. This helps us process your request appropriately.';
$_LANG['select_reason'] = 'Why are you reissuing this certificate?';
$_LANG['select_reason_placeholder'] = '-- Select Reason --';
$_LANG['reason_key_compromise'] = 'Private Key Compromised';
$_LANG['reason_domain_change'] = 'Domain Name Change';
$_LANG['reason_server_change'] = 'Server Migration';
$_LANG['reason_lost_key'] = 'Lost Private Key';
$_LANG['reason_csr_change'] = 'Need New CSR';
$_LANG['reason_other'] = 'Other';
$_LANG['reissue_reason_help'] = 'If your private key was compromised, we recommend also revoking the current certificate after the new one is issued.';
$_LANG['security_notice'] = 'Security Notice';
$_LANG['key_compromise_warning'] = 'If your private key has been compromised, you should also revoke the current certificate after the new one is issued.';
$_LANG['reissue_warning_title'] = 'Important Information';
$_LANG['reissue_warning'] = 'Reissuing will generate a new certificate. The previous certificate will remain valid until it expires or you choose to revoke it.';
$_LANG['current_certificate'] = 'Current Certificate';
$_LANG['original_domains'] = 'Original Domains';
$_LANG['reissue_domain_guide'] = 'You can keep the same domains or modify them. Select a validation method for each domain.';

// =============================================
// CANCELLED / REVOKED / EXPIRED (cancelled.tpl)
// =============================================
$_LANG['order_cancelled'] = 'Order Cancelled';
$_LANG['certificate_revoked'] = 'Certificate Revoked';
$_LANG['certificate_expired'] = 'Certificate Expired';
$_LANG['certificate_inactive'] = 'Certificate Inactive';
$_LANG['cancelled_desc'] = 'This certificate order has been cancelled. If you need SSL protection for your website, please place a new order.';
$_LANG['revoked_desc'] = 'This certificate has been revoked and is no longer valid. Browsers will show security warnings if you continue using it.';
$_LANG['expired_desc'] = 'This certificate has expired. Please renew your certificate to maintain SSL protection for your website.';
$_LANG['inactive_desc'] = 'This certificate is no longer active.';
$_LANG['certificate_history'] = 'Certificate History';
$_LANG['order_placed'] = 'Order Placed';
$_LANG['whats_next'] = "What's Next?";
$_LANG['order_new'] = 'Order New Certificate';
$_LANG['order_new_desc'] = 'Get a new SSL certificate to protect your website and keep your visitors safe.';
$_LANG['revocation_notice'] = 'Revocation Notice';
$_LANG['revoked_warning'] = 'This certificate has been added to Certificate Revocation Lists (CRL). Browsers will show security warnings when visiting your site. You must install a new certificate immediately.';
$_LANG['expired_warning_title'] = 'Website Security Warning';
$_LANG['expired_warning_desc'] = 'Visitors may see "Your connection is not private" errors. Please renew or order a new certificate as soon as possible to maintain trust with your users.';
$_LANG['revoked_reason'] = 'Revoked by request';
$_LANG['browse_ssl'] = 'Browse SSL Certificates';
$_LANG['contact_support_btn'] = 'Contact Support';

// =============================================
// MESSAGES (applycert.tpl status cards)
// =============================================
$_LANG['apply_des'] = 'Please complete the form below to request your SSL certificate.';
$_LANG['apply_welcome'] = 'Please fill in the information below to request your SSL certificate. Fields marked with * are required.';
$_LANG['message_des'] = 'Your certificate request has been submitted. Please complete domain validation.';
$_LANG['complete_des'] = 'Your certificate has been issued successfully. You can download or reissue below.';
$_LANG['replace_des'] = 'Please provide new information to reissue the certificate.';
$_LANG['cancelled_des'] = 'This certificate has been cancelled or revoked.';
$_LANG['email_info'] = 'Email Information';
$_LANG['email_wait_info'] = 'Your request has been submitted. Please check your email to complete verification.';
$_LANG['draft_saved'] = 'Draft Saved';
$_LANG['draft_desc'] = 'Your progress has been saved. You can continue where you left off.';
$_LANG['draft_continue'] = 'You have a saved draft. You can continue where you left off.';
$_LANG['last_saved'] = 'Last saved';

// =============================================
// CONFIRMATIONS
// =============================================
$_LANG['sure_to_cancel'] = 'Are you sure you want to cancel this certificate?';
$_LANG['sure_to_replace'] = 'Are you sure you want to reissue this certificate?';
$_LANG['sure_to_revoke'] = 'Are you sure you want to revoke this certificate? This action cannot be undone.';
$_LANG['confirm_action'] = 'Confirm Action';
$_LANG['confirm_revoke'] = 'Are you sure you want to revoke this certificate? This action cannot be undone.';
$_LANG['cancel_reason'] = 'Cancellation Reason';
$_LANG['revoke_reason'] = 'Revocation Reason';

// =============================================
// SUCCESS MESSAGES
// =============================================
$_LANG['success'] = 'Success';
$_LANG['submit_success'] = 'Request submitted successfully';
$_LANG['status_refreshed'] = 'Status refreshed';
$_LANG['refresh_success'] = 'Status refreshed successfully';
$_LANG['dcv_updated'] = 'DCV method updated';
$_LANG['cancelled_success'] = 'Order cancelled successfully';
$_LANG['revoked_success'] = 'Certificate revoked successfully';
$_LANG['reissue_success'] = 'Reissue request submitted';
$_LANG['revoke_success'] = 'Certificate revoked';
$_LANG['download_success'] = 'Download started successfully';
$_LANG['copy_success'] = 'Copied to clipboard';

// =============================================
// ERROR MESSAGES
// =============================================
$_LANG['error'] = 'Error';
$_LANG['params_error'] = 'Parameter error';
$_LANG['action_not_found'] = 'Action not supported';
$_LANG['pay_order_first'] = 'Please pay the order first';
$_LANG['product_offline'] = 'Product is not available, please contact support';
$_LANG['service_error'] = 'Service not found, please try again';
$_LANG['status_error'] = 'Invalid order status';
$_LANG['sys_error'] = 'System error, please contact support';
$_LANG['cert_not_found'] = 'Certificate not found';
$_LANG['cert_not_issued'] = 'Certificate not yet issued';
$_LANG['api_error'] = 'API Error';
$_LANG['validation_error'] = 'Please fill in all required fields';
$_LANG['network_error'] = 'Network error';
$_LANG['revoke_failed'] = 'Revocation failed';
$_LANG['refresh_failed'] = 'Failed to refresh status';
$_LANG['download_failed'] = 'Download failed';
$_LANG['copy_failed'] = 'Failed to copy';
$_LANG['request_failed'] = 'Request failed. Please try again.';

// =============================================
// ERROR PAGE (error.tpl)
// =============================================
$_LANG['error_title'] = 'An Error Occurred';
$_LANG['error_default_message'] = 'Something went wrong. Please try again.';
$_LANG['error_go_back'] = 'Go Back';
$_LANG['error_try_again'] = 'Try Again';
$_LANG['error_contact_support'] = 'Contact Support';

// =============================================
// SERVER TYPES
// =============================================
$_LANG['server_type'] = 'Server Type';
$_LANG['server_other'] = 'Other';
$_LANG['server_apache'] = 'Apache';
$_LANG['server_nginx'] = 'Nginx';
$_LANG['server_iis'] = 'Microsoft IIS';
$_LANG['server_tomcat'] = 'Tomcat';
$_LANG['server_cpanel'] = 'cPanel';
$_LANG['server_plesk'] = 'Plesk';

// =============================================
// HELP SECTION (applycert.tpl, reissue.tpl, message.tpl, manage.tpl)
// =============================================
$_LANG['need_help'] = 'Need Help?';
$_LANG['help_guide_title'] = 'SSL Guide';
$_LANG['help_guide_desc'] = 'Learn about SSL certificates, validation types, and how to install them on your server.';
$_LANG['help_reissue_guide_desc'] = 'Learn about the certificate reissue process and when you should reissue your SSL certificate.';
$_LANG['help_support_title'] = 'Contact Support';
$_LANG['help_support_desc'] = 'Need assistance? Our support team is available 24/7 to help you with your SSL certificate.';
$_LANG['help_faq_title'] = 'FAQ';
$_LANG['help_faq_desc'] = 'Find answers to commonly asked questions about SSL certificates.';
$_LANG['what_is_dcv'] = 'What is DCV?';
$_LANG['dcv_explanation'] = 'Domain Control Validation (DCV) proves you own the domain. Choose HTTP file, DNS record, or email validation based on your preference.';
$_LANG['what_is_csr'] = 'What is CSR?';
$_LANG['csr_explanation'] = 'A Certificate Signing Request contains your domain info. Click "Generate CSR" to create one automatically, or paste your own if you have it.';
$_LANG['installation_service'] = 'SSL Installation Service';
$_LANG['installation_service_desc'] = "Don't want to install SSL yourself? Our experts can install and configure your SSL certificate for you. Fast, secure, and hassle-free!";
$_LANG['order_installation'] = 'Order Installation Service';

// Help - message.tpl / manage.tpl specific
$_LANG['how_long'] = 'How long does it take?';
$_LANG['time_note'] = 'DNS: 5-30 minutes after propagation. HTTP: Usually instant. Email: Depends on when you click the link.';
$_LANG['check_status'] = 'Check Status';
$_LANG['status_note'] = 'Click "Refresh" button above to check if validation is complete and certificate is issued.';
$_LANG['change_method'] = 'Change Method';
$_LANG['change_note'] = 'Click "Change" next to each domain to switch to a different validation method.';

// =============================================
// PRODUCT INFO
// =============================================
$_LANG['product'] = 'Product';
$_LANG['product_name'] = 'Product Name';
$_LANG['product_type'] = 'Product Type';
$_LANG['expires'] = 'Expires';

// =============================================
// MISC
// =============================================
$_LANG['loading'] = 'Loading...';
$_LANG['please_wait'] = 'Please wait...';
$_LANG['required'] = 'Required';
$_LANG['optional'] = 'Optional';
$_LANG['yes'] = 'Yes';
$_LANG['no'] = 'No';
$_LANG['na'] = 'N/A';
$_LANG['copy'] = 'Copy';
$_LANG['copied'] = 'Copied!';
$_LANG['close'] = 'Close';
$_LANG['help'] = 'Help';
$_LANG['more_info'] = 'More Information';
$_LANG['powered_by'] = 'Powered by';
$_LANG['version'] = 'Version';
$_LANG['select'] = 'Select';
$_LANG['save'] = 'Save';
$_LANG['confirm'] = 'Confirm';
$_LANG['ok'] = 'OK';
$_LANG['details'] = 'Details';
$_LANG['view_details'] = 'View Details';
$_LANG['show_more'] = 'Show More';
$_LANG['show_less'] = 'Show Less';
$_LANG['search'] = 'Search';
$_LANG['filter'] = 'Filter';
$_LANG['reset'] = 'Reset';
$_LANG['apply'] = 'Apply';
$_LANG['date'] = 'Date';
$_LANG['from'] = 'From';
$_LANG['to'] = 'To';
$_LANG['total'] = 'Total';
$_LANG['none'] = 'None';
$_LANG['unknown'] = 'Unknown';
$_LANG['not_available'] = 'Not Available';
$_LANG['pending_verification'] = 'Pending Verification';
$_LANG['contact_support'] = 'Contact Support';
$_LANG['contact_support_desc'] = 'Need help? Our support team is ready to assist you.';

$_LANG['certificate_issued'] = 'Certificate has been issued!';
$_LANG['still_pending'] = 'Still pending validation';
$_LANG['dcv_changed'] = 'Validation method changed';
$_LANG['dcv_email_sent'] = 'Validation email sent';

// --- manage.tpl: JS config + inline ---
$_LANG['cancel_success'] = 'Order cancelled';
$_LANG['cancel_failed'] = 'Cancellation failed';
$_LANG['cancelling'] = 'Cancelling...';

// --- manage.tpl: Cancel modal HTML ---
$_LANG['confirm_cancel'] = 'Confirm Cancellation';
$_LANG['warning'] = 'Warning';
$_LANG['cancel_warning'] = 'Cancelling this order will stop the certificate issuance process. This action cannot be undone.';
$_LANG['keep_order'] = 'Keep Order';

// --- complete.tpl: Status Card ---
$_LANG['certificate_ready'] = 'Your Certificate is Ready!';
$_LANG['certificate_ready_desc'] = 'Your SSL certificate has been issued and is ready for installation. Download the certificate files below.';

// --- complete.tpl: Certificate Content ---
$_LANG['ssl_certificate'] = 'SSL Certificate';

// --- complete.tpl: Revoke modal ---
$_LANG['confirm_revoke'] = 'Confirm Revocation';
$_LANG['revoke_warning'] = 'Revoking this certificate is permanent and cannot be undone. The certificate will become invalid immediately.';
$_LANG['revoke_confirm_question'] = 'Are you sure you want to revoke this certificate?';

// --- complete.tpl: JS config ---
$_LANG['download_started'] = 'Download started';
$_LANG['revoke_success'] = 'Certificate revoked';
$_LANG['revoke_failed'] = 'Revocation failed';

// --- complete.tpl: Revoke modal HTML ---
$_LANG['confirm_revoke'] = 'Confirm Revocation';
$_LANG['revoke_confirm_question'] = 'Are you sure you want to revoke this certificate?';

// --- reissue.tpl: JS config ---
$_LANG['submit_failed'] = 'Submission failed. Please try again.';

// --- reissue.tpl: template HTML ---
$_LANG['domains_used'] = 'Domains';
$_LANG['csr_config'] = 'CSR Configuration';
$_LANG['reissue_csr_guide'] = 'A new CSR is required for reissue. You can auto-generate one or paste your own if you already have it.';
$_LANG['open_ticket'] = 'Open a Ticket';
$_LANG['help_installation_title'] = 'SSL Installation Service';
$_LANG['help_installation_desc'] = "Don't want to install it yourself? Our experts can install your SSL certificate for you quickly and securely.";

// --- manage.tpl + message.tpl: Change DCV modal HTML ---
$_LANG['change_dcv_method'] = 'Change Validation Method';
$_LANG['new_dcv_method'] = 'New Validation Method';
$_LANG['file_dns_validation'] = 'File/DNS Validation';
$_LANG['http_file'] = 'HTTP File Validation';
$_LANG['https_file'] = 'HTTPS File Validation';
$_LANG['dns_cname'] = 'DNS CNAME Validation';
$_LANG['select_email'] = '-- Select Email --';
$_LANG['dcv_change_note'] = 'After changing the validation method, you will need to complete the new validation process.';
$_LANG['confirm_change'] = 'Confirm Change';