<?php
/**
 * NicSRS SSL Module - Vietnamese Language File (COMPLETE)
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
$_LANG['ssl_management'] = 'Quản lý SSL';
$_LANG['ssl_certificate'] = 'Chứng chỉ SSL';
$_LANG['configure_certificate'] = 'Cấu hình Chứng chỉ';
$_LANG['certificate_management'] = 'Quản lý Chứng chỉ';
$_LANG['certificate_status'] = 'Trạng thái Chứng chỉ';
$_LANG['module_version'] = 'Phiên bản Module';

// =============================================
// STATUS
// =============================================
$_LANG['status'] = 'Trạng thái';
$_LANG['awaiting_configuration'] = 'Chờ cấu hình';
$_LANG['draft'] = 'Bản nháp';
$_LANG['pending'] = 'Đang chờ xử lý';
$_LANG['processing'] = 'Đang xử lý';
$_LANG['complete'] = 'Hoàn thành';
$_LANG['issued'] = 'Đã cấp phát';
$_LANG['cancelled'] = 'Đã hủy';
$_LANG['revoked'] = 'Đã thu hồi';
$_LANG['expired'] = 'Đã hết hạn';
$_LANG['reissue_pending'] = 'Đang chờ cấp lại';
$_LANG['verified'] = 'Đã xác minh';
$_LANG['un_verified'] = 'Chưa xác minh';
$_LANG['active'] = 'Đang hoạt động';
$_LANG['pending_validation'] = 'Đang chờ Xác minh';
$_LANG['awaiting_validation'] = 'Đang Chờ Xác minh Tên miền';

// =============================================
// PROGRESS STEPS (applycert.tpl)
// =============================================
$_LANG['step'] = 'Bước';
$_LANG['step_configure'] = 'Cấu hình';
$_LANG['step_submit'] = 'Gửi';
$_LANG['step_validation'] = 'Xác minh';
$_LANG['step_issued'] = 'Đã cấp phát';

// PROGRESS STEPS (message.tpl / manage.tpl)
$_LANG['step_ordered'] = 'Đã đặt hàng';
$_LANG['step_submitted'] = 'Đã gửi';

// PROGRESS STEPS (reissue.tpl)
$_LANG['step_reissue'] = 'Cấp lại';
$_LANG['step_new_cert'] = 'Chứng chỉ Mới';

// PROGRESS STEPS (applycert.tpl - original)
$_LANG['step_csr'] = 'CSR';
$_LANG['step_domain'] = 'Xác minh Tên miền';
$_LANG['step_contacts'] = 'Thông tin Liên hệ';
$_LANG['step_organization'] = 'Thông tin Tổ chức';
$_LANG['step_review'] = 'Xem lại';

// =============================================
// CSR SECTION (applycert.tpl, reissue.tpl)
// =============================================
$_LANG['csr'] = 'CSR';
$_LANG['csr_configuration'] = 'Cấu hình CSR';
$_LANG['csr_des'] = 'Yêu cầu ký chứng chỉ (CSR) là bắt buộc để đăng ký chứng chỉ SSL. Bạn có thể tự động tạo hoặc dán CSR có sẵn.';
$_LANG['csr_section_guide'] = 'CSR (Yêu cầu ký chứng chỉ) chứa thông tin tên miền và tổ chức của bạn. Bạn có thể tự động tạo hoặc dán CSR sẵn có.';
$_LANG['auto_generate_csr'] = 'Tự động tạo CSR';
$_LANG['manual_csr'] = 'Nhập CSR thủ công';
$_LANG['is_manual_csr'] = 'Tôi có CSR riêng';
$_LANG['paste_csr'] = 'Dán CSR của bạn vào đây';
$_LANG['generate_csr'] = 'Tạo CSR';
$_LANG['decode_csr'] = 'Giải mã CSR';
$_LANG['csr_decoded'] = 'Giải mã CSR thành công';
$_LANG['invalid_csr'] = 'Định dạng CSR không hợp lệ';
$_LANG['generating_csr'] = 'Đang tạo CSR...';
$_LANG['csr_generated'] = 'Tạo CSR thành công';
$_LANG['csr_info'] = 'Thông tin CSR';
$_LANG['enter_csr'] = 'Vui lòng nhập CSR';
$_LANG['key_size'] = 'Kích thước khóa';
$_LANG['key_type'] = 'Loại khóa';

// =============================================
// CSR FIELDS
// =============================================
$_LANG['common_name'] = 'Tên miền chính (Common Name)';
$_LANG['organization'] = 'Tổ chức';
$_LANG['organizational_unit'] = 'Đơn vị/Phòng ban';
$_LANG['city'] = 'Thành phố';
$_LANG['locality'] = 'Thành phố/Địa phương';
$_LANG['state'] = 'Tỉnh/Thành';
$_LANG['province'] = 'Tỉnh';
$_LANG['country'] = 'Quốc gia';
$_LANG['email'] = 'Email';
$_LANG['email_address'] = 'Địa chỉ Email';

// =============================================
// DOMAIN SECTION
// =============================================
$_LANG['domain'] = 'Tên miền';
$_LANG['domain_info'] = 'Thông tin Tên miền';
$_LANG['domain_name'] = 'Tên miền';
$_LANG['primary_domain'] = 'Tên miền chính';
$_LANG['additional_domains'] = 'Tên miền bổ sung';
$_LANG['add_domain'] = 'Thêm tên miền';
$_LANG['remove_domain'] = 'Xóa';
$_LANG['max_domain'] = 'Số lượng tên miền tối đa';
$_LANG['add'] = 'Thêm';
$_LANG['set_for_all'] = 'Áp dụng cho tất cả';
$_LANG['must_same_pmain'] = 'Phải giống với tên miền chính';
$_LANG['overplus'] = 'Bạn đã vượt quá số lượng tên miền cho phép';
$_LANG['domain_section_guide'] = 'Nhập (các) tên miền bạn muốn bảo vệ và chọn phương thức xác minh. Đối với xác minh qua Email, các tùy chọn sẽ hiển thị dựa trên tên miền.';
$_LANG['domain_required'] = 'Vui lòng nhập tên miền trước';
$_LANG['at_least_one_domain'] = 'Cần ít nhất một tên miền';
$_LANG['max_domains_reached'] = 'Đã đạt số lượng tên miền tối đa';

// =============================================
// DCV - Domain Control Validation
// =============================================
$_LANG['dcv'] = 'Xác minh DCV';
$_LANG['dcv_method'] = 'Phương thức DCV';
$_LANG['dcv_status'] = 'Trạng thái DCV';
$_LANG['domain_validation'] = 'Xác minh Tên miền';
$_LANG['domain_validation_required'] = 'Yêu cầu Xác minh Tên miền';
$_LANG['please_choose'] = '-- Chọn Phương thức DCV --';
$_LANG['http_csr_hash'] = 'Xác minh qua File HTTP';
$_LANG['https_csr_hash'] = 'Xác minh qua File HTTPS';
$_LANG['cname_csr_hash'] = 'Xác minh qua DNS CNAME';
$_LANG['dns_csr_hash'] = 'Xác minh qua DNS TXT';
$_LANG['email_validation'] = 'Xác minh qua Email';
$_LANG['file_validation'] = 'Xác minh File/DNS';
$_LANG['update_dcv'] = 'Cập nhật DCV';
$_LANG['resend_dcv'] = 'Gửi lại DCV';
$_LANG['dcv_instructions'] = 'Hướng dẫn DCV';
$_LANG['select_method'] = 'Vui lòng chọn phương thức xác minh';

// DCV Instructions (message.tpl / manage.tpl)
$_LANG['action_required'] = 'Yêu cầu Thực hiện: Xác minh Tên miền';
$_LANG['important'] = 'Quan trọng';
$_LANG['dcv_instruction_main'] = 'Hoàn thành xác minh tên miền để nhận chứng chỉ SSL. Thực hiện theo hướng dẫn bên dưới cho từng tên miền.';
$_LANG['dns_instruction'] = 'Thêm bản ghi CNAME sau vào cài đặt DNS của bạn:';
$_LANG['dns_txt_instruction'] = 'Thêm bản ghi TXT sau vào cài đặt DNS của bạn:';
$_LANG['http_instruction'] = 'Tạo file tại URL sau với nội dung bên dưới:';
$_LANG['email_instruction'] = 'Email xác minh đã được gửi đến:';
$_LANG['record_type'] = 'Loại';
$_LANG['host_name'] = 'Host/Tên';
$_LANG['points_to'] = 'Trỏ đến';
$_LANG['txt_value'] = 'Giá trị TXT';
$_LANG['file_url'] = 'URL File';
$_LANG['file_content_label'] = 'Nội dung File';
$_LANG['dns_propagation'] = 'Thay đổi DNS có thể mất 5-30 phút để cập nhật.';
$_LANG['dns_propagation_note'] = 'Thay đổi DNS có thể mất 5-30 phút để cập nhật.';
$_LANG['http_note'] = 'File phải truy cập được qua HTTP/HTTPS. Content-Type phải là text/plain.';
$_LANG['email_note'] = 'Kiểm tra hộp thư đến và thư rác. Nhấp vào liên kết xác minh trong email.';
$_LANG['resend_email'] = 'Gửi lại Email';
$_LANG['change'] = 'Thay đổi';
$_LANG['validation_status'] = 'Trạng thái Xác minh';
$_LANG['validation_desc'] = 'Yêu cầu chứng chỉ đã được gửi. Hoàn thành xác minh tên miền bên dưới để nhận chứng chỉ SSL.';

// DCV legacy keys
$_LANG['http_instructions'] = 'Tạo file tại đường dẫn sau trên máy chủ:';
$_LANG['https_instructions'] = 'Tạo file tại đường dẫn HTTPS sau trên máy chủ:';
$_LANG['dns_cname_instructions'] = 'Thêm bản ghi CNAME sau vào DNS:';
$_LANG['dns_txt_instructions'] = 'Thêm bản ghi TXT sau vào DNS:';
$_LANG['email_instructions'] = 'Email xác minh sẽ được gửi đến địa chỉ đã chọn.';
$_LANG['file_path'] = 'Đường dẫn File';
$_LANG['file_content'] = 'Nội dung File';
$_LANG['dns_host'] = 'Host DNS';
$_LANG['dns_value'] = 'Giá trị DNS';
$_LANG['dns_type'] = 'Loại DNS';
$_LANG['dcv_email'] = 'Email Xác minh';
$_LANG['down_txt'] = 'Tải file xác minh';

// Change DCV Modal
$_LANG['change_dcv_title'] = 'Đổi Phương thức Xác minh';
$_LANG['change_dcv_desc'] = 'Chọn phương thức xác minh mới cho tên miền này:';
$_LANG['select_new_method'] = 'Chọn Phương thức Mới';
$_LANG['dcv_change_success'] = 'Cập nhật phương thức xác minh thành công';
$_LANG['dcv_change_failed'] = 'Không thể cập nhật phương thức xác minh';

// =============================================
// CONTACT INFORMATION
// =============================================
$_LANG['contacts'] = 'Thông tin Liên hệ';
$_LANG['admin_contact'] = 'Liên hệ Quản trị';
$_LANG['tech_contact'] = 'Liên hệ Kỹ thuật';
$_LANG['contact_section_guide'] = 'Thông tin này sẽ hiển thị trên chứng chỉ. Email quản trị sẽ nhận thông báo quan trọng về chứng chỉ SSL. Tất cả trường có dấu * là bắt buộc bởi Tổ chức Chứng nhận.';
$_LANG['admin_email_note'] = 'Thông báo chứng chỉ sẽ được gửi đến email này.';
$_LANG['title'] = 'Chức danh';
$_LANG['job_title'] = 'Chức danh công việc';
$_LANG['first_name'] = 'Tên';
$_LANG['last_name'] = 'Họ';
$_LANG['phone'] = 'Số điện thoại';
$_LANG['organization_name'] = 'Tên Tổ chức';
$_LANG['address'] = 'Địa chỉ';
$_LANG['post_code'] = 'Mã bưu điện';
$_LANG['select_country'] = '-- Chọn Quốc gia --';

// =============================================
// ORGANIZATION
// =============================================
$_LANG['organization_info'] = 'Thông tin Tổ chức';
$_LANG['org_section_guide'] = 'Đối với chứng chỉ OV/EV, thông tin tổ chức sẽ được xác minh và hiển thị trên chứng chỉ. Vui lòng đảm bảo tất cả thông tin chính xác.';
$_LANG['org_name'] = 'Tên Tổ chức';
$_LANG['org_address'] = 'Địa chỉ';
$_LANG['org_city'] = 'Thành phố';
$_LANG['org_state'] = 'Tỉnh/Thành';
$_LANG['org_country'] = 'Quốc gia';
$_LANG['org_postal'] = 'Mã bưu điện';
$_LANG['org_phone'] = 'Điện thoại';
$_LANG['org_division'] = 'Phòng/Ban';
$_LANG['idType'] = 'Loại giấy tờ';
$_LANG['organizationCode'] = 'Mã số thuế / ĐKKD';
$_LANG['organizationRegNumber'] = 'Số đăng ký';
$_LANG['registration_number'] = 'Số đăng ký';
$_LANG['other'] = 'Khác';

// =============================================
// RENEWAL
// =============================================
$_LANG['is_renew'] = 'Đây là gia hạn?';
$_LANG['is_renew_des'] = 'Chọn "Có" nếu gia hạn chứng chỉ hiện có để nhận thêm thời gian bonus.';
$_LANG['is_renew_option_new'] = 'Không, chứng chỉ mới';
$_LANG['is_renew_option_renew'] = 'Có, gia hạn';

// =============================================
// ORDER DETAILS (message.tpl, manage.tpl)
// =============================================
$_LANG['order_details'] = 'Chi tiết Đơn hàng';
$_LANG['order_id'] = 'Mã Đơn hàng';
$_LANG['order_date'] = 'Ngày đặt hàng';
$_LANG['certificate_pending'] = 'Chứng chỉ đang Chờ Xác minh';
$_LANG['message_desc'] = 'Yêu cầu chứng chỉ đã được gửi. Vui lòng hoàn thành xác minh tên miền bên dưới để nhận chứng chỉ SSL.';

// =============================================
// ACTIONS
// =============================================
$_LANG['actions'] = 'Thao tác';
$_LANG['submit'] = 'Gửi';
$_LANG['submit_request'] = 'Gửi Yêu cầu';
$_LANG['submit_reissue'] = 'Gửi Yêu cầu Cấp lại';
$_LANG['save_draft'] = 'Lưu Bản nháp';
$_LANG['cancel'] = 'Hủy';
$_LANG['back'] = 'Quay lại';
$_LANG['next'] = 'Tiếp theo';
$_LANG['refresh'] = 'Làm mới';
$_LANG['refresh_status'] = 'Làm mới Trạng thái';
$_LANG['download'] = 'Tải xuống';
$_LANG['download_certificate'] = 'Tải Chứng chỉ';
$_LANG['down_cert'] = 'Tải Chứng chỉ';
$_LANG['down_key'] = 'Tải Private Key';
$_LANG['reissue'] = 'Cấp lại Chứng chỉ';
$_LANG['repalce'] = 'Cấp lại';
$_LANG['reissue_certificate'] = 'Cấp lại Chứng chỉ';
$_LANG['renew'] = 'Gia hạn';
$_LANG['renew_certificate'] = 'Gia hạn Chứng chỉ';
$_LANG['revoke'] = 'Thu hồi Chứng chỉ';
$_LANG['revoke_certificate'] = 'Thu hồi Chứng chỉ';
$_LANG['cancel_order'] = 'Hủy Đơn hàng';
$_LANG['submitting'] = 'Đang gửi...';
$_LANG['updating'] = 'Đang cập nhật...';
$_LANG['refreshing'] = 'Đang làm mới...';
$_LANG['revoking'] = 'Đang thu hồi...';
$_LANG['downloading'] = 'Đang tải xuống...';

// =============================================
// DOWNLOAD FORMATS (complete.tpl)
// =============================================
$_LANG['select_download_format'] = 'Chọn Định dạng Tải xuống';
$_LANG['format_all'] = 'Tất cả Định dạng (ZIP)';
$_LANG['format_apache'] = 'Apache (.crt + .ca-bundle)';
$_LANG['format_nginx'] = 'Nginx (.pem)';
$_LANG['format_iis'] = 'IIS (.p12)';
$_LANG['format_tomcat'] = 'Tomcat (.jks)';
$_LANG['all_formats'] = 'Tất cả Định dạng';
$_LANG['complete_package'] = 'Gói ZIP đầy đủ';
$_LANG['download_all'] = 'Tải tất cả';
$_LANG['download_info'] = 'Chọn định dạng phù hợp với máy chủ web. Nếu không chắc chắn, hãy tải "Tất cả Định dạng".';

// =============================================
// CERTIFICATE INFO (complete.tpl)
// =============================================
$_LANG['certificate'] = 'Chứng chỉ';
$_LANG['certificate_info'] = 'Thông tin Chứng chỉ';
$_LANG['certificate_id'] = 'Mã Chứng chỉ';
$_LANG['certificate_type'] = 'Loại Chứng chỉ';
$_LANG['certificate_issued'] = 'Chứng chỉ đã Cấp phát';
$_LANG['cert_status'] = 'Trạng thái Chứng chỉ';
$_LANG['cert_begin'] = 'Ngày cấp';
$_LANG['cert_end'] = 'Ngày hết hạn';
$_LANG['days_remaining'] = 'Số ngày còn lại';
$_LANG['expiring_soon'] = 'Sắp hết hạn';
$_LANG['vendor_id'] = 'Mã Nhà cung cấp';
$_LANG['last_refresh'] = 'Cập nhật lần cuối';
$_LANG['certificate_content'] = 'Nội dung Chứng chỉ';
$_LANG['certificate_actions'] = 'Thao tác Chứng chỉ';
$_LANG['ca_bundle'] = 'CA Bundle';
$_LANG['private_key'] = 'Khóa riêng tư';
$_LANG['validity_period'] = 'Thời hạn Hiệu lực';
$_LANG['valid_from'] = 'Có hiệu lực từ';
$_LANG['valid_until'] = 'Có hiệu lực đến';
$_LANG['secured_domains'] = 'Tên miền được Bảo vệ';
$_LANG['validation_type'] = 'Loại Xác minh';

// Private Key
$_LANG['private_key_notice'] = 'Không có Private Key';
$_LANG['private_key_notice_desc'] = 'Private key không được lưu trữ trên hệ thống. Nếu bạn tạo CSR ở nơi khác, hãy sử dụng private key gốc.';
$_LANG['private_key_password'] = 'Mật khẩu Private Key';
$_LANG['password_notice'] = 'Vui lòng lưu mật khẩu này cẩn thận. Bạn sẽ cần nó khi cài đặt chứng chỉ.';
$_LANG['password_for_format'] = 'Mật khẩu cho định dạng %s';
$_LANG['all_passwords'] = 'Tất cả Mật khẩu';

// Installation Help
$_LANG['installation_help'] = 'Hướng dẫn Cài đặt';
$_LANG['apache_help'] = 'Apache';
$_LANG['apache_help_desc'] = 'Tải lên file .crt, .ca-bundle và .key. Cập nhật cấu hình VirtualHost.';
$_LANG['nginx_help'] = 'Nginx';
$_LANG['nginx_help_desc'] = 'Sử dụng file .pem (chứng chỉ kết hợp) cùng file .key trong khối server.';
$_LANG['iis_help'] = 'IIS';
$_LANG['iis_help_desc'] = 'Nhập file .p12 (PKCS#12) thông qua IIS Manager hoặc công cụ MMC.';
$_LANG['cpanel_help'] = 'cPanel';
$_LANG['cpanel_help_desc'] = 'Vào SSL/TLS trong cPanel và dán chứng chỉ cùng khóa vào phần Cài đặt.';
$_LANG['tomcat_help'] = 'Tomcat';
$_LANG['tomcat_help_desc'] = 'Nhập file .jks (Java KeyStore) vào cấu hình server.xml của Tomcat.';

// =============================================
// REISSUE (reissue.tpl)
// =============================================
$_LANG['reissue_reason'] = 'Lý do Cấp lại';
$_LANG['reissue_reason_guide'] = 'Vui lòng chọn lý do cấp lại chứng chỉ. Điều này giúp chúng tôi xử lý yêu cầu phù hợp.';
$_LANG['select_reason'] = 'Tại sao bạn cấp lại chứng chỉ này?';
$_LANG['select_reason_placeholder'] = '-- Chọn Lý do --';
$_LANG['reason_key_compromise'] = 'Private Key bị Lộ';
$_LANG['reason_domain_change'] = 'Thay đổi Tên miền';
$_LANG['reason_server_change'] = 'Chuyển Máy chủ';
$_LANG['reason_lost_key'] = 'Mất Private Key';
$_LANG['reason_csr_change'] = 'Cần CSR Mới';
$_LANG['reason_other'] = 'Lý do Khác';
$_LANG['reissue_reason_help'] = 'Nếu private key bị lộ, nên thu hồi chứng chỉ hiện tại sau khi chứng chỉ mới được cấp.';
$_LANG['security_notice'] = 'Thông báo Bảo mật';
$_LANG['key_compromise_warning'] = 'Nếu private key bị lộ, bạn nên thu hồi chứng chỉ hiện tại sau khi chứng chỉ mới được cấp.';
$_LANG['reissue_warning_title'] = 'Thông tin Quan trọng';
$_LANG['reissue_warning'] = 'Cấp lại sẽ tạo chứng chỉ mới. Chứng chỉ trước đó vẫn có hiệu lực cho đến khi hết hạn hoặc bị thu hồi.';
$_LANG['current_certificate'] = 'Chứng chỉ Hiện tại';
$_LANG['original_domains'] = 'Tên miền Gốc';
$_LANG['reissue_domain_guide'] = 'Bạn có thể giữ nguyên hoặc thay đổi các tên miền. Chọn phương thức xác minh cho từng tên miền.';

// =============================================
// CANCELLED / REVOKED / EXPIRED (cancelled.tpl)
// =============================================
$_LANG['order_cancelled'] = 'Đơn hàng đã Hủy';
$_LANG['certificate_revoked'] = 'Chứng chỉ đã Thu hồi';
$_LANG['certificate_expired'] = 'Chứng chỉ đã Hết hạn';
$_LANG['certificate_inactive'] = 'Chứng chỉ Không hoạt động';
$_LANG['cancelled_desc'] = 'Đơn đặt chứng chỉ đã bị hủy. Nếu cần bảo mật SSL cho website, vui lòng đặt đơn mới.';
$_LANG['revoked_desc'] = 'Chứng chỉ đã bị thu hồi và không còn hợp lệ. Trình duyệt sẽ hiển thị cảnh báo bảo mật nếu tiếp tục sử dụng.';
$_LANG['expired_desc'] = 'Chứng chỉ đã hết hạn. Vui lòng gia hạn để duy trì bảo mật SSL cho website.';
$_LANG['inactive_desc'] = 'Chứng chỉ này không còn hoạt động.';
$_LANG['certificate_history'] = 'Lịch sử Chứng chỉ';
$_LANG['order_placed'] = 'Đã đặt Đơn hàng';
$_LANG['whats_next'] = 'Bước Tiếp theo?';
$_LANG['order_new'] = 'Đặt Chứng chỉ Mới';
$_LANG['order_new_desc'] = 'Đặt chứng chỉ SSL mới để bảo vệ website và giữ an toàn cho khách truy cập.';
$_LANG['revocation_notice'] = 'Thông báo Thu hồi';
$_LANG['revoked_warning'] = 'Chứng chỉ đã được thêm vào Danh sách Thu hồi (CRL). Trình duyệt sẽ hiển thị cảnh báo bảo mật. Bạn cần cài đặt chứng chỉ mới ngay.';
$_LANG['expired_warning_title'] = 'Cảnh báo Bảo mật Website';
$_LANG['expired_warning_desc'] = 'Khách truy cập có thể thấy lỗi "Kết nối không riêng tư". Vui lòng gia hạn hoặc đặt chứng chỉ mới sớm nhất.';
$_LANG['revoked_reason'] = 'Thu hồi theo yêu cầu';
$_LANG['browse_ssl'] = 'Xem Chứng chỉ SSL';
$_LANG['contact_support_btn'] = 'Liên hệ Hỗ trợ';

// =============================================
// MESSAGES
// =============================================
$_LANG['apply_des'] = 'Vui lòng hoàn thành biểu mẫu bên dưới để yêu cầu chứng chỉ SSL.';
$_LANG['apply_welcome'] = 'Vui lòng điền thông tin bên dưới để yêu cầu chứng chỉ SSL. Các trường có dấu * là bắt buộc.';
$_LANG['message_des'] = 'Yêu cầu chứng chỉ đã được gửi. Vui lòng hoàn thành xác minh tên miền.';
$_LANG['complete_des'] = 'Chứng chỉ đã được cấp phát thành công. Bạn có thể tải xuống hoặc cấp lại bên dưới.';
$_LANG['replace_des'] = 'Vui lòng cung cấp thông tin mới để cấp lại chứng chỉ.';
$_LANG['cancelled_des'] = 'Chứng chỉ này đã bị hủy hoặc thu hồi.';
$_LANG['email_info'] = 'Thông tin Email';
$_LANG['email_wait_info'] = 'Yêu cầu đã được gửi. Vui lòng kiểm tra email để hoàn thành xác minh.';
$_LANG['draft_saved'] = 'Đã Lưu Bản nháp';
$_LANG['draft_desc'] = 'Tiến trình của bạn đã được lưu. Bạn có thể tiếp tục từ lần trước.';
$_LANG['draft_continue'] = 'Bạn có bản nháp đã lưu. Bạn có thể tiếp tục từ lần trước.';
$_LANG['last_saved'] = 'Lưu lần cuối';

// =============================================
// CONFIRMATIONS
// =============================================
$_LANG['sure_to_cancel'] = 'Bạn có chắc muốn hủy chứng chỉ này?';
$_LANG['sure_to_replace'] = 'Bạn có chắc muốn cấp lại chứng chỉ này?';
$_LANG['sure_to_revoke'] = 'Bạn có chắc muốn thu hồi chứng chỉ này? Hành động này không thể hoàn tác.';
$_LANG['confirm_action'] = 'Xác nhận Thao tác';
$_LANG['confirm_revoke'] = 'Bạn có chắc muốn thu hồi chứng chỉ? Hành động này không thể hoàn tác.';
$_LANG['cancel_reason'] = 'Lý do Hủy';
$_LANG['revoke_reason'] = 'Lý do Thu hồi';

// =============================================
// SUCCESS MESSAGES
// =============================================
$_LANG['success'] = 'Thành công';
$_LANG['submit_success'] = 'Gửi yêu cầu thành công';
$_LANG['status_refreshed'] = 'Đã cập nhật trạng thái';
$_LANG['refresh_success'] = 'Làm mới trạng thái thành công';
$_LANG['dcv_updated'] = 'Đã cập nhật phương thức DCV';
$_LANG['cancelled_success'] = 'Hủy đơn hàng thành công';
$_LANG['revoked_success'] = 'Thu hồi chứng chỉ thành công';
$_LANG['reissue_success'] = 'Gửi yêu cầu cấp lại thành công';
$_LANG['revoke_success'] = 'Đã thu hồi chứng chỉ';
$_LANG['download_success'] = 'Bắt đầu tải xuống thành công';
$_LANG['copy_success'] = 'Đã sao chép vào bộ nhớ tạm';

// =============================================
// ERROR MESSAGES
// =============================================
$_LANG['error'] = 'Lỗi';
$_LANG['params_error'] = 'Lỗi tham số';
$_LANG['action_not_found'] = 'Thao tác không được hỗ trợ';
$_LANG['pay_order_first'] = 'Vui lòng thanh toán đơn hàng trước';
$_LANG['product_offline'] = 'Sản phẩm không khả dụng, vui lòng liên hệ hỗ trợ';
$_LANG['service_error'] = 'Không tìm thấy dịch vụ, vui lòng thử lại';
$_LANG['status_error'] = 'Trạng thái đơn hàng không hợp lệ';
$_LANG['sys_error'] = 'Lỗi hệ thống, vui lòng liên hệ hỗ trợ';
$_LANG['cert_not_found'] = 'Không tìm thấy chứng chỉ';
$_LANG['cert_not_issued'] = 'Chứng chỉ chưa được cấp phát';
$_LANG['api_error'] = 'Lỗi API';
$_LANG['validation_error'] = 'Vui lòng điền đầy đủ các trường bắt buộc';
$_LANG['network_error'] = 'Lỗi mạng';
$_LANG['revoke_failed'] = 'Thu hồi thất bại';
$_LANG['refresh_failed'] = 'Không thể làm mới trạng thái';
$_LANG['download_failed'] = 'Tải xuống thất bại';
$_LANG['copy_failed'] = 'Không thể sao chép';
$_LANG['request_failed'] = 'Yêu cầu thất bại. Vui lòng thử lại.';

// =============================================
// ERROR PAGE (error.tpl)
// =============================================
$_LANG['error_title'] = 'Đã xảy ra Lỗi';
$_LANG['error_default_message'] = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
$_LANG['error_go_back'] = 'Quay lại';
$_LANG['error_try_again'] = 'Thử lại';
$_LANG['error_contact_support'] = 'Liên hệ Hỗ trợ';

// =============================================
// SERVER TYPES
// =============================================
$_LANG['server_type'] = 'Loại Máy chủ';
$_LANG['server_other'] = 'Khác';
$_LANG['server_apache'] = 'Apache';
$_LANG['server_nginx'] = 'Nginx';
$_LANG['server_iis'] = 'Microsoft IIS';
$_LANG['server_tomcat'] = 'Tomcat';
$_LANG['server_cpanel'] = 'cPanel';
$_LANG['server_plesk'] = 'Plesk';

// =============================================
// HELP SECTION
// =============================================
$_LANG['need_help'] = 'Cần Trợ giúp?';
$_LANG['help_guide_title'] = 'Hướng dẫn SSL';
$_LANG['help_guide_desc'] = 'Tìm hiểu về chứng chỉ SSL, các loại xác minh và cách cài đặt trên máy chủ.';
$_LANG['help_reissue_guide_desc'] = 'Tìm hiểu về quy trình cấp lại chứng chỉ và khi nào nên cấp lại SSL.';
$_LANG['help_support_title'] = 'Liên hệ Hỗ trợ';
$_LANG['help_support_desc'] = 'Cần hỗ trợ? Đội ngũ hỗ trợ sẵn sàng 24/7 để giúp bạn với chứng chỉ SSL.';
$_LANG['help_faq_title'] = 'Câu hỏi Thường gặp';
$_LANG['help_faq_desc'] = 'Tìm câu trả lời cho các câu hỏi thường gặp về chứng chỉ SSL.';
$_LANG['what_is_dcv'] = 'DCV là gì?';
$_LANG['dcv_explanation'] = 'Xác minh Quyền Quản lý Tên miền (DCV) chứng minh bạn sở hữu tên miền. Chọn xác minh qua file HTTP, bản ghi DNS hoặc email.';
$_LANG['what_is_csr'] = 'CSR là gì?';
$_LANG['csr_explanation'] = 'Yêu cầu Ký Chứng chỉ chứa thông tin tên miền. Nhấn "Tạo CSR" để tạo tự động, hoặc dán CSR sẵn có.';
$_LANG['installation_service'] = 'Dịch vụ Cài đặt SSL';
$_LANG['installation_service_desc'] = 'Không muốn tự cài SSL? Chuyên gia của chúng tôi sẽ cài đặt và cấu hình chứng chỉ SSL cho bạn. Nhanh chóng, an toàn!';
$_LANG['order_installation'] = 'Đặt Dịch vụ Cài đặt';

// Help - message.tpl / manage.tpl
$_LANG['how_long'] = 'Mất bao lâu?';
$_LANG['time_note'] = 'DNS: 5-30 phút sau khi cập nhật. HTTP: Thường tức thì. Email: Tùy thuộc vào khi bạn nhấp liên kết.';
$_LANG['check_status'] = 'Kiểm tra Trạng thái';
$_LANG['status_note'] = 'Nhấn nút "Làm mới" ở trên để kiểm tra xem xác minh đã hoàn tất và chứng chỉ đã được cấp chưa.';
$_LANG['change_method'] = 'Đổi Phương thức';
$_LANG['change_note'] = 'Nhấn "Thay đổi" bên cạnh từng tên miền để chuyển sang phương thức xác minh khác.';

// =============================================
// PRODUCT INFO
// =============================================
$_LANG['product'] = 'Sản phẩm';
$_LANG['product_name'] = 'Tên Sản phẩm';
$_LANG['product_type'] = 'Loại Sản phẩm';
$_LANG['expires'] = 'Hết hạn';

// =============================================
// MISC
// =============================================
$_LANG['loading'] = 'Đang tải...';
$_LANG['please_wait'] = 'Vui lòng đợi...';
$_LANG['required'] = 'Bắt buộc';
$_LANG['optional'] = 'Tùy chọn';
$_LANG['yes'] = 'Có';
$_LANG['no'] = 'Không';
$_LANG['na'] = 'N/A';
$_LANG['copy'] = 'Sao chép';
$_LANG['copied'] = 'Đã sao chép!';
$_LANG['close'] = 'Đóng';
$_LANG['help'] = 'Trợ giúp';
$_LANG['more_info'] = 'Thông tin thêm';
$_LANG['powered_by'] = 'Cung cấp bởi';
$_LANG['version'] = 'Phiên bản';
$_LANG['select'] = 'Chọn';
$_LANG['save'] = 'Lưu';
$_LANG['confirm'] = 'Xác nhận';
$_LANG['ok'] = 'OK';
$_LANG['details'] = 'Chi tiết';
$_LANG['view_details'] = 'Xem Chi tiết';
$_LANG['show_more'] = 'Xem thêm';
$_LANG['show_less'] = 'Thu gọn';
$_LANG['search'] = 'Tìm kiếm';
$_LANG['filter'] = 'Lọc';
$_LANG['reset'] = 'Đặt lại';
$_LANG['apply'] = 'Áp dụng';
$_LANG['date'] = 'Ngày';
$_LANG['from'] = 'Từ';
$_LANG['to'] = 'Đến';
$_LANG['total'] = 'Tổng';
$_LANG['none'] = 'Không có';
$_LANG['unknown'] = 'Không xác định';
$_LANG['not_available'] = 'Không khả dụng';
$_LANG['pending_verification'] = 'Đang chờ Xác minh';
$_LANG['contact_support'] = 'Liên hệ Hỗ trợ';
$_LANG['contact_support_desc'] = 'Cần trợ giúp? Đội ngũ hỗ trợ sẵn sàng hỗ trợ bạn.';

// --- message.tpl + manage.tpl: JS config ---
$_LANG['certificate_issued'] = 'Chứng chỉ đã được cấp phát!';
$_LANG['still_pending'] = 'Vẫn đang chờ xác minh';
$_LANG['dcv_changed'] = 'Đã thay đổi phương thức xác minh';
$_LANG['dcv_email_sent'] = 'Đã gửi email xác minh';

// --- manage.tpl: JS config + inline ---
$_LANG['cancel_success'] = 'Đã hủy đơn hàng';
$_LANG['cancel_failed'] = 'Hủy đơn hàng thất bại';
$_LANG['cancelling'] = 'Đang hủy...';

// --- manage.tpl: Cancel modal HTML ---
$_LANG['confirm_cancel'] = 'Xác nhận Hủy Đơn hàng';
$_LANG['warning'] = 'Cảnh báo';
$_LANG['cancel_warning'] = 'Hủy đơn hàng này sẽ dừng quá trình cấp phát chứng chỉ. Hành động này không thể hoàn tác.';
$_LANG['keep_order'] = 'Giữ Đơn hàng';

// --- complete.tpl: Progress Steps ---
$_LANG['step_validated'] = 'Đã xác minh';

// --- complete.tpl: Status Card ---
$_LANG['certificate_ready'] = 'Chứng chỉ của Bạn đã Sẵn sàng!';
$_LANG['certificate_ready_desc'] = 'Chứng chỉ SSL đã được cấp phát và sẵn sàng để cài đặt. Tải xuống các file chứng chỉ bên dưới.';

// --- complete.tpl: Certificate Content ---
$_LANG['ssl_certificate'] = 'Chứng chỉ SSL';

// --- complete.tpl: Revoke modal ---
$_LANG['confirm_revoke'] = 'Xác nhận Thu hồi';
$_LANG['revoke_warning'] = 'Thu hồi chứng chỉ là vĩnh viễn và không thể hoàn tác. Chứng chỉ sẽ mất hiệu lực ngay lập tức.';
$_LANG['revoke_confirm_question'] = 'Bạn có chắc muốn thu hồi chứng chỉ này?';

// --- complete.tpl: JS config ---
$_LANG['download_started'] = 'Đã bắt đầu tải xuống';
$_LANG['revoke_success'] = 'Đã thu hồi chứng chỉ';
$_LANG['revoke_failed'] = 'Thu hồi thất bại';

// --- complete.tpl: Revoke modal HTML ---
$_LANG['confirm_revoke'] = 'Xác nhận Thu hồi';
$_LANG['revoke_confirm_question'] = 'Bạn có chắc muốn thu hồi chứng chỉ này?';

// --- reissue.tpl: JS config ---
$_LANG['submit_failed'] = 'Gửi yêu cầu thất bại. Vui lòng thử lại.';

// --- reissue.tpl: template HTML ---
$_LANG['domains_used'] = 'Tên miền';
$_LANG['csr_config'] = 'Cấu hình CSR';
$_LANG['reissue_csr_guide'] = 'Cần CSR mới để cấp lại chứng chỉ. Bạn có thể tự động tạo hoặc dán CSR sẵn có.';
$_LANG['open_ticket'] = 'Gửi Yêu cầu Hỗ trợ';
$_LANG['help_installation_title'] = 'Dịch vụ Cài đặt SSL';
$_LANG['help_installation_desc'] = 'Không muốn tự cài đặt? Chuyên gia của chúng tôi sẽ cài đặt chứng chỉ SSL cho bạn nhanh chóng và an toàn.';

// --- manage.tpl + message.tpl: Change DCV modal HTML ---
$_LANG['change_dcv_method'] = 'Đổi Phương thức Xác minh';
$_LANG['new_dcv_method'] = 'Phương thức Xác minh Mới';
$_LANG['file_dns_validation'] = 'Xác minh File/DNS';
$_LANG['http_file'] = 'Xác minh qua File HTTP';
$_LANG['https_file'] = 'Xác minh qua File HTTPS';
$_LANG['dns_cname'] = 'Xác minh qua DNS CNAME';
$_LANG['select_email'] = '-- Chọn Email --';
$_LANG['dcv_change_note'] = 'Sau khi thay đổi phương thức xác minh, bạn cần hoàn thành quy trình xác minh mới.';
$_LANG['confirm_change'] = 'Xác nhận Thay đổi';