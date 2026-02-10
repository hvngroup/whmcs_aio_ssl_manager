# Hướng dẫn sử dụng cho Quản trị viên (Admin User Manual)

> **Module:** NicSRS SSL Admin cho WHMCS  
> **Phiên bản:** 1.3.1 / 2.1.0  
> **Đối tượng:** Quản trị viên WHMCS (Admin)  
> **Cập nhật:** 2026-02-09

---

## Mục lục

1. [Giới thiệu](#1-giới-thiệu)
2. [Truy cập Module](#2-truy-cập-module)
3. [Dashboard — Bảng điều khiển](#3-dashboard--bảng-điều-khiển)
4. [Products — Quản lý sản phẩm SSL](#4-products--quản-lý-sản-phẩm-ssl)
5. [Orders — Quản lý đơn hàng](#5-orders--quản-lý-đơn-hàng)
6. [Import — Nhập chứng chỉ](#6-import--nhập-chứng-chỉ)
7. [Reports — Báo cáo](#7-reports--báo-cáo)
8. [Settings — Cài đặt](#8-settings--cài-đặt)
9. [Quản lý sản phẩm WHMCS](#9-quản-lý-sản-phẩm-whmcs)
10. [Quản lý dịch vụ khách hàng](#10-quản-lý-dịch-vụ-khách-hàng)
11. [Xử lý Vendor Migration](#11-xử-lý-vendor-migration)
12. [Xử lý sự cố thường gặp](#12-xử-lý-sự-cố-thường-gặp)

---

## 1. Giới thiệu

NicSRS SSL Admin là module mở rộng cho WHMCS giúp quản trị viên:

- Quản lý tập trung toàn bộ chứng chỉ SSL từ NicSRS
- Đồng bộ tự động danh mục sản phẩm và trạng thái chứng chỉ
- Theo dõi doanh thu, lợi nhuận với báo cáo chi tiết (USD/VND)
- Nhận thông báo email khi chứng chỉ được cấp, sắp hết hạn, hoặc đồng bộ lỗi
- Nhập và liên kết chứng chỉ đã mua trực tiếp từ NicSRS

---

## 2. Truy cập Module

### Đường dẫn chính

**WHMCS Admin → Addons → NicSRS SSL Admin**

Module hiển thị thanh điều hướng 6 tab:

| Tab | Biểu tượng | Chức năng |
|---|---|---|
| **Dashboard** | 📊 | Tổng quan thống kê, biểu đồ, đơn hàng gần đây |
| **Products** | 📦 | Danh mục sản phẩm SSL, đồng bộ từ NicSRS |
| **Orders** | 🛒 | Danh sách và chi tiết đơn hàng chứng chỉ |
| **Import** | 📥 | Nhập chứng chỉ từ NicSRS portal |
| **Reports** | 📈 | Báo cáo doanh thu, lợi nhuận, hiệu suất |
| **Settings** | ⚙️ | Cài đặt thông báo, đồng bộ, tiền tệ |

---

## 3. Dashboard — Bảng điều khiển

Trang chính hiển thị tổng quan hệ thống SSL.

### 3.1. Thẻ thống kê

4 thẻ thông tin ở đầu trang:

- **Total Orders**: Tổng số đơn hàng SSL trong hệ thống
- **Pending Orders**: Đơn hàng đang chờ xử lý (Awaiting, Draft, Pending)
- **Issued Certificates**: Chứng chỉ đã được cấp thành công
- **Expiring Soon (30d)**: Chứng chỉ sẽ hết hạn trong 30 ngày tới

### 3.2. Biểu đồ

- **Status Distribution** (Biểu đồ tròn): Phân bố trạng thái đơn hàng theo màu sắc
- **Monthly Orders** (Biểu đồ cột): Xu hướng đơn hàng 6 tháng gần nhất

### 3.3. Bảng dữ liệu

- **Recent Orders** (10 đơn gần nhất): Order ID, Domain, Product, Client, Status, Actions
- **Expiring Certificates** (20 chứng chỉ): Domain, ngày hết hạn, số ngày còn lại

### 3.4. Cảnh báo API

Nếu API chưa được kết nối, một banner cảnh báo vàng sẽ hiển thị với link đến trang Settings.

---

## 4. Products — Quản lý sản phẩm SSL

### 4.1. Danh sách sản phẩm

Hiển thị tất cả sản phẩm SSL từ NicSRS được cache trong database.

**Bộ lọc:**
- **Vendor**: Sectigo, DigiCert, GlobalSign, GeoTrust, Entrust, sslTrus, BaiduTrust, RapidSSL, Thawte, Positive
- **Validation Type**: DV (Domain Validation), OV (Organization), EV (Extended)
- **Search**: Tìm theo tên hoặc mã sản phẩm
- **Linked**: Chỉ hiển thị sản phẩm đã/chưa liên kết với WHMCS product

**Thông tin cột**: Product Code, Product Name, Vendor, Type (badge màu), Wildcard (✓/✗), SAN (✓/✗), Max Domains, Price (1 năm), Linked Status, Last Sync

### 4.2. Đồng bộ sản phẩm

**Cách đồng bộ:**
1. Click nút **"Sync All Products"** để đồng bộ từ tất cả nhà cung cấp
2. Hoặc chọn vendor cụ thể từ dropdown rồi click **"Sync Vendor"**
3. Quá trình đồng bộ mất 10–30 giây (tùy số lượng vendor)
4. Sau khi hoàn tất, danh sách sẽ tự động refresh

**Lưu ý**: Đồng bộ sản phẩm cũng được chạy tự động theo lịch cron (mặc định mỗi 24 giờ). Khi phát hiện thay đổi giá, admin sẽ nhận email thông báo.

---

## 5. Orders — Quản lý đơn hàng

### 5.1. Danh sách đơn hàng

**Bộ lọc:**
- **Status**: Awaiting, Draft, Pending, Complete, Cancelled, Revoked, Expired, hoặc **Expiring** (đặc biệt — lọc chứng chỉ sắp hết hạn 30 ngày)
- **Search**: Tìm theo domain, Certificate ID, tên khách hàng

**Thông tin cột**: Order ID, Domain, Product, Client (tên + email), Service ID, Status, Created, Expires, Days Left, Actions

### 5.2. Chi tiết đơn hàng

Click vào Order ID để xem chi tiết. Trang chi tiết bao gồm:

**Panel "Order Info":**
- Order ID, Remote ID (NicSRS Certificate ID), Status badge
- Domain, Product Code, Product Name
- Client (link đến trang client WHMCS), Service (link đến trang service)
- Provision Date, Completion Date, Last Refresh

**Panel "Certificate Details"** (khi đã cấp):
- Begin Date, End Date, Vendor ID
- DCV Status per domain

**Panel "DCV Information"** (khi pending):
- Per domain: DCV method, verification status
- File validation: path + content (có nút copy)
- DNS validation: host + value + type (có nút copy)
- Email validation: email address

**Panel "Activity Log"**: Lịch sử thao tác cho đơn hàng này

### 5.3. Thao tác trên đơn hàng

| Nút | Điều kiện | Mô tả |
|---|---|---|
| **Refresh Status** | Có Certificate ID | Cập nhật trạng thái mới nhất từ NicSRS API |
| **Resend DCV** | Status = Pending | Gửi lại email xác thực domain |
| **Cancel Order** | Status = Pending | Hủy đơn hàng (yêu cầu xác nhận) |
| **Revoke Certificate** | Status = Complete | Thu hồi chứng chỉ (**không thể hoàn tác**) |
| **Delete Order** | Mọi trạng thái | Xóa bản ghi khỏi database (yêu cầu xác nhận) |

---

## 6. Import — Nhập chứng chỉ

Dùng để nhập chứng chỉ đã mua trực tiếp từ NicSRS portal vào hệ thống WHMCS.

### 6.1. Tra cứu chứng chỉ

1. Nhập **Certificate ID** (lấy từ NicSRS portal → Orders → SSL Orders → Instance ID)
2. Click **"Lookup"**
3. Hệ thống hiển thị thông tin: domain, status, ngày hết hạn, DCV list

### 6.2. Nhập + Liên kết dịch vụ

1. Sau khi lookup, nhập **Service ID** (WHMCS hosting service ID)
2. Click **"Import & Link Certificate"**
3. Hệ thống tạo bản ghi `nicsrs_sslorders` với đầy đủ dữ liệu từ API
4. Chứng chỉ xuất hiện trong Orders list và quản lý được từ admin

**Tìm Service ID**: WHMCS Admin → Clients → Products/Services → mở service → URL chứa `id=XXX`

### 6.3. Nhập không liên kết

- Tick checkbox **"Import only"**
- Chứng chỉ được nhập với `userid=0`, `serviceid=0`
- Có thể liên kết sau từ trang Orders

### 6.4. Nhập hàng loạt (Bulk Import)

1. Nhập nhiều Certificate ID (mỗi ID một dòng) vào textarea
2. Click **"Bulk Import"**
3. Hệ thống xử lý từng cert: bỏ qua trùng lặp, báo lỗi per cert
4. Kết quả: "X of Y certificates imported"

---

## 7. Reports — Báo cáo

### 7.1. Tổng quan Reports

Trang Reports Index hiển thị:
- 4 thẻ thống kê nhanh: Doanh thu tháng (VND/USD), Đơn hàng tháng, Chứng chỉ active, Sắp hết hạn
- Link đến 3 loại báo cáo chi tiết

### 7.2. Profit Report (Báo cáo lợi nhuận)

- **Bộ lọc**: Khoảng thời gian, Vendor, Validation type
- **Bảng**: Per-order: Sale Amount, Cost, Profit (USD + VND), Margin %
- **Biểu đồ**: Xu hướng lợi nhuận theo thời gian (line chart)
- **Tóm tắt**: Total Revenue, Total Cost, Total Profit, Overall Margin
- **Xuất CSV**: Nút "Export CSV" để tải file

### 7.3. Product Performance (Hiệu suất sản phẩm)

- **Bảng**: Per-product: Total Orders, Active, Cancelled, Revenue, Avg Order, Completion Rate, Renewal Rate
- **Biểu đồ**: Top Products (bar chart), Validation Type (pie chart)
- **Xuất CSV**

### 7.4. Revenue by Brand (Doanh thu theo thương hiệu)

- **Bảng**: Per-vendor: Orders, Active, Revenue, Avg Order, Revenue Share %, Order Share %
- **Biểu đồ**: Brand trend over time
- **Xuất CSV**

### 7.5. Tiền tệ

Tất cả báo cáo hỗ trợ hiển thị USD, VND, hoặc cả hai. Cấu hình tại Settings → Currency Settings hoặc trực tiếp trong trang Reports.

---

## 8. Settings — Cài đặt

### 8.1. Notification Settings (Thông báo)

| Cài đặt | Mô tả |
|---|---|
| Email on issuance | Gửi email cho admin khi chứng chỉ được cấp |
| Email on expiry | Gửi email cảnh báo trước khi chứng chỉ hết hạn |
| Expiry warning days | Số ngày trước hết hạn để gửi cảnh báo (mặc định: 30) |
| Admin email | Email nhận thông báo (để trống = email hệ thống WHMCS) |

### 8.2. Auto-Sync Settings (Đồng bộ tự động)

| Cài đặt | Mô tả | Mặc định |
|---|---|---|
| Enable auto-sync | Bật/tắt đồng bộ tự động | ✅ Bật |
| Status sync interval | Tần suất kiểm tra trạng thái chứng chỉ | 6 giờ |
| Product sync interval | Tần suất đồng bộ danh mục sản phẩm | 24 giờ |
| Batch size | Số chứng chỉ xử lý mỗi lần sync | 50 |

**Trạng thái đồng bộ**: Hiển thị realtime — Last Sync, Next Sync, Pending Count

**Nút đồng bộ thủ công:**
- **Sync Certificate Status**: Kiểm tra trạng thái tất cả cert pending
- **Sync Products**: Cập nhật danh mục sản phẩm từ NicSRS
- **Check Expiring**: Kiểm tra chứng chỉ sắp hết hạn

### 8.3. Display Settings (Hiển thị)

- **Date Format**: `Y-m-d` / `d/m/Y` / `m/d/Y` / `d.m.Y`

### 8.4. Currency Settings (Tiền tệ)

- **USD/VND Rate**: Tỷ giá quy đổi (mặc định: 25,000)
- **Display Mode**: USD only / VND only / Both
- **Update from API**: Lấy tỷ giá tự động từ API bên ngoài

### 8.5. Activity Logs (Nhật ký)

- Xem 20 log gần nhất
- **Clear Logs**: Xóa log cũ hơn 7/30/90 ngày hoặc tất cả
- **Export CSV**: Xuất file CSV

---

## 9. Quản lý sản phẩm WHMCS

### Tạo sản phẩm SSL mới

1. **Setup → Products/Services → Products/Services**
2. Tạo sản phẩm mới → Tab **Module Settings**
3. **Module Name**: Chọn `nicsrs_ssl`
4. **Certificate Type**: Dropdown chứa tất cả sản phẩm đã sync (hiển thị product code)
5. **API Token (Override)**: Để trống để dùng token chung từ Admin Addon. Chỉ nhập nếu sản phẩm này cần token khác
6. Cấu hình giá tại tab Pricing

### Kiểm tra kết nối API

Trong trang Module Settings, dòng API Token hiển thị trạng thái:
- `✅ Connected` — API hoạt động bình thường
- `❌ Connection failed` — Kiểm tra token hoặc kết nối mạng

### Số lượng sản phẩm trong cache

Dòng Certificate Type hiển thị: *"XX products in cache. [Sync Products]"*. Click link để đồng bộ.

---

## 10. Quản lý dịch vụ khách hàng

### Admin Service Tab

Khi mở một dịch vụ SSL trong admin (Clients → Products/Services → Service Detail), tab hiển thị:

**Thông tin đơn hàng NicSRS:**
- Order ID (với nút "Manage" link đến Admin Addon)
- Certificate ID, Status, Domain, Certificate Type
- Issued Date, Expiry Date, Vendor ID, Last Refresh

**Nút Admin Actions** (Module Commands):
| Nút | Chức năng |
|---|---|
| **Manage Order** | Mở trang chi tiết đơn hàng trong Admin Addon |
| **Refresh Status** | Cập nhật trạng thái từ NicSRS API |
| **Resend DCV Email** | Gửi lại email xác thực domain |
| **Allow New Certificate** | Cho phép cấp cert mới (dùng khi vendor migration) |

---

## 11. Xử lý Vendor Migration

Khi chuyển sản phẩm SSL từ nhà cung cấp khác (cPanel SSL, GoGetSSL...) sang NicSRS:

### Hiện tượng

- Admin tab hiển thị cảnh báo vàng: **"Vendor Migration Detected"**
- Thông tin: Provider cũ, Cert ID cũ, Status, Expiry
- Khách hàng thấy trang read-only thông báo liên hệ admin

### Cách xử lý

1. Xác nhận chứng chỉ cũ đã/sắp hết hạn hoặc cần thay thế
2. Click nút **"Allow New Certificate"** trong Module Commands
3. Hệ thống tạo đơn hàng NicSRS mới với trạng thái "Awaiting Configuration"
4. Khách hàng giờ có thể cấu hình và đặt chứng chỉ NicSRS mới
5. Khi gửi đơn lên NicSRS API, flag `originalfromOthers=1` được gửi kèm

---

## 12. Xử lý sự cố thường gặp

### API không kết nối

| Triệu chứng | Kiểm tra |
|---|---|
| Dashboard hiện "API Not Connected" | Settings → nhập đúng API Token → Test Connection |
| ConfigOptions hiện "❌ Connection failed" | Kiểm tra token, firewall outbound HTTPS, cURL extension |

### Đồng bộ không chạy

| Triệu chứng | Kiểm tra |
|---|---|
| Last Sync không cập nhật | WHMCS cron đang chạy? (`Utilities → System → Cron Status`) |
| Banner cảnh báo "Sync Warning" | Settings → kiểm tra API Token + test connection |
| Error count tăng liên tục | Xem Module Log (`Utilities → Logs → Module Log`) |

### Đơn hàng kẹt Pending

| Triệu chứng | Kiểm tra |
|---|---|
| Status không chuyển Complete | Click Refresh Status trong Order Detail |
| DCV chưa verify | Kiểm tra DCV method: file có accessible? DNS đã propagate? Email đã nhận? |
| DCV email không nhận | Resend DCV Email; kiểm tra email address chính xác |

### Email thông báo không nhận

| Triệu chứng | Kiểm tra |
|---|---|
| Không nhận email từ module | Module dùng WHMCS `SendAdminEmail` — kiểm tra WHMCS email logs |
| Email không hiển thị đúng | Kiểm tra WHMCS mail config (SMTP settings) |

### Xem log chi tiết

1. Vào **Utilities → Logs → Module Log**
2. Tìm module `nicsrs_ssl` hoặc `nicsrs_ssl_admin`
3. Mỗi API request/response được log đầy đủ (token đã được mask)