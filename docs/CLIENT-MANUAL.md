# Hướng dẫn sử dụng SSL cho Khách hàng (Client Manual)

> **Module:** NicSRS SSL cho WHMCS  
> **Phiên bản:** 2.1.0  
> **Đối tượng:** Khách hàng cuối (End-user / Client)  
> **Cập nhật:** 2026-02-09

---

## Mục lục

1. [Giới thiệu](#1-giới-thiệu)
2. [Đặt chứng chỉ SSL mới](#2-đặt-chứng-chỉ-ssl-mới)
3. [Xác thực tên miền (DCV)](#3-xác-thực-tên-miền-dcv)
4. [Tải chứng chỉ đã cấp](#4-tải-chứng-chỉ-đã-cấp)
5. [Cấp lại chứng chỉ (Reissue)](#5-cấp-lại-chứng-chỉ-reissue)
6. [Gia hạn chứng chỉ (Renew)](#6-gia-hạn-chứng-chỉ-renew)
7. [Hủy và Thu hồi](#7-hủy-và-thu-hồi)
8. [Trạng thái chứng chỉ](#8-trạng-thái-chứng-chỉ)
9. [Câu hỏi thường gặp (FAQ)](#9-câu-hỏi-thường-gặp-faq)

---

## 1. Giới thiệu

Khi bạn mua sản phẩm SSL thông qua hệ thống WHMCS, bạn sẽ quản lý chứng chỉ SSL trực tiếp từ **Client Area** (Khu vực khách hàng).

### Truy cập

1. Đăng nhập vào Client Area
2. Vào **Services → My Services**
3. Click vào sản phẩm SSL đã mua
4. Trang quản lý SSL sẽ hiển thị tương ứng với trạng thái hiện tại

---

## 2. Đặt chứng chỉ SSL mới

Sau khi mua sản phẩm SSL, bạn sẽ thấy trang **"Configure Certificate"** với giao diện nhiều bước.

### Bước 1: Thông tin tên miền

**Nhập tên miền cần bảo vệ:**
- Nhập tên miền chính (ví dụ: `example.com`)
- Nếu sản phẩm hỗ trợ Multi-Domain (SAN), bạn có thể thêm domain phụ bằng nút **"Add Domain"**
- Mỗi domain cần chọn **phương thức xác thực (DCV Method)**:
  - **HTTP File Validation**: Upload file lên web server
  - **HTTPS File Validation**: Tương tự nhưng qua HTTPS
  - **DNS CNAME Validation**: Tạo bản ghi DNS CNAME
  - **Email Validation**: Nhận email xác thực (chọn email từ danh sách)

### Bước 2: CSR (Certificate Signing Request)

CSR là yêu cầu ký chứng chỉ chứa thông tin domain và tổ chức của bạn.

**Tùy chọn 1 — Tự động tạo CSR** (Khuyến nghị):
1. Chọn **"Auto-generate CSR"**
2. Hệ thống tự động tạo CSR và Private Key
3. **Quan trọng**: Private Key sẽ được lưu tự động — bạn cần bảo quản nó an toàn

**Tùy chọn 2 — Nhập CSR có sẵn:**
1. Chọn **"Enter CSR manually"**
2. Dán CSR vào ô textarea (bắt đầu bằng `-----BEGIN CERTIFICATE REQUEST-----`)
3. Click **"Decode CSR"** để kiểm tra thông tin

### Bước 3: Thông tin liên hệ Administrator

Điền đầy đủ thông tin bắt buộc:

| Trường | Bắt buộc | Ví dụ |
|---|---|---|
| First Name | ✅ | John |
| Last Name | ✅ | Doe |
| Email | ✅ | admin@example.com |
| Phone | ✅ | +84.123456789 |
| Organization | ✅ | Acme Corporation |
| Job Title | ✅ | IT Manager |
| Address | ✅ | 123 Main Street |
| City | ✅ | Ho Chi Minh |
| Postal Code | ✅ | 700000 |
| Country | ✅ | Vietnam |

**Lưu ý**: Email administrator sẽ nhận thông báo quan trọng về chứng chỉ.

### Bước 4: Thông tin tổ chức (chỉ OV/EV)

Nếu sản phẩm SSL là **OV (Organization Validation)** hoặc **EV (Extended Validation)**, bạn cần cung cấp thêm thông tin tổ chức:

| Trường | Ví dụ |
|---|---|
| Organization Name | Acme Corporation |
| Address | 123 Main Street, Suite 100 |
| City | Ho Chi Minh |
| State/Province | — |
| Postal Code | 700000 |
| Country | VN |
| Phone | +84.28.12345678 |

### Lưu bản nháp

Bạn có thể **lưu bản nháp** bất kỳ lúc nào bằng nút **"Save Draft"**. Khi quay lại, mọi thông tin đã điền sẽ được khôi phục. Trạng thái đơn hàng chuyển thành "Draft".

### Gửi đơn hàng

Khi đã điền đầy đủ, click nút **"Submit"**:
- Hệ thống gửi yêu cầu đến Certificate Authority (CA)
- Trạng thái chuyển thành **"Pending"**
- Bạn cần hoàn tất xác thực tên miền (DCV) để chứng chỉ được cấp

---

## 3. Xác thực tên miền (DCV)

Sau khi gửi đơn, bạn cần xác thực quyền sở hữu tên miền. Trang **Pending** hiển thị thông tin DCV chi tiết.

### 3.1. HTTP File Validation

1. Trang hiển thị **đường dẫn file** và **nội dung file**
2. Tạo file với nội dung được cung cấp
3. Upload lên web server tại đường dẫn: `http://yourdomain.com/.well-known/pki-validation/filename.txt`
4. Đảm bảo file truy cập được từ internet (HTTP 200)

**Ví dụ:**
```
Đường dẫn: /.well-known/pki-validation/fileauth.txt
Nội dung: abc123def456ghi789...
```

### 3.2. DNS CNAME Validation

1. Trang hiển thị **DNS Host**, **DNS Value** và **DNS Type**
2. Đăng nhập vào nơi quản lý DNS của domain
3. Tạo bản ghi CNAME:
   - **Host**: `_dnsauth.yourdomain.com`
   - **Value**: `abc123.verify.sectigo.com`
   - **Type**: CNAME
4. Chờ DNS propagate (thường 5–30 phút, tối đa 24–48 giờ)

### 3.3. Email Validation

1. Trang hiển thị danh sách email có thể dùng:
   - `admin@yourdomain.com`
   - `administrator@yourdomain.com`
   - `hostmaster@yourdomain.com`
   - `postmaster@yourdomain.com`
   - `webmaster@yourdomain.com`
2. CA sẽ gửi email xác thực đến email bạn đã chọn
3. Mở email và click link xác nhận
4. Nếu chưa nhận email, click nút **"Resend DCV Email"**

### 3.4. Kiểm tra trạng thái

- Click nút **"Refresh Status"** để kiểm tra trạng thái xác thực mới nhất
- Mỗi domain hiển thị: ✅ Verified hoặc ⏳ Pending
- Khi tất cả domain đã verified, chứng chỉ sẽ được cấp tự động (thường trong vài phút đến vài giờ)

---

## 4. Tải chứng chỉ đã cấp

Khi chứng chỉ được cấp (status = **Complete**), trang hiển thị đầy đủ thông tin và các tùy chọn tải về.

### 4.1. Thông tin chứng chỉ

- **Certificate ID**: Mã chứng chỉ từ NicSRS
- **Begin Date**: Ngày bắt đầu hiệu lực
- **End Date**: Ngày hết hạn
- **Days Remaining**: Số ngày còn lại

### 4.2. Định dạng tải về

| Định dạng | Dùng cho | Mô tả |
|---|---|---|
| **PEM** | Apache, Nginx, cPanel | File certificate + CA bundle (text) |
| **PKCS#12 (.pfx)** | IIS, Windows Server | File nhị phân chứa cert + key (có password) |
| **JKS** | Tomcat, Java | Java KeyStore format (có password) |
| **Private Key** | Mọi server | Key riêng (nếu đã lưu khi tạo CSR) |

### 4.3. Tải chứng chỉ PEM

1. Click nút **"Download PEM"**
2. File ZIP chứa:
   - `certificate.crt` — Chứng chỉ SSL
   - `ca-bundle.crt` — CA Certificate Chain
3. Cài đặt trên server theo hướng dẫn của hosting provider

### 4.4. Tải PKCS#12 / JKS

1. Click nút **"Download PKCS#12"** hoặc **"Download JKS"**
2. Một cửa sổ popup hiển thị **mật khẩu** của file
3. **Sao chép mật khẩu** (nút Copy) — bạn sẽ cần nó khi import vào server
4. File được tải về tự động

### 4.5. Copy chứng chỉ

Bạn cũng có thể **copy trực tiếp** nội dung certificate và CA bundle bằng các nút **"Copy"** trên trang.

---

## 5. Cấp lại chứng chỉ (Reissue)

Cấp lại (reissue) khi bạn cần thay đổi CSR, domain, hoặc private key bị lộ.

### Khi nào cần Reissue

- Private key bị lộ/mất
- Chuyển sang server mới
- Thay đổi tên miền
- Cần CSR mới

### Cách thực hiện

1. Từ trang chứng chỉ đã cấp, click nút **"Reissue Certificate"**
2. Chọn lý do reissue:
   - Private Key Compromised
   - Domain Name Change
   - Server Migration
   - Lost Private Key
   - Need New CSR
   - Other
3. Điền lại form (tương tự khi đặt mới):
   - **Bước 1**: Domain + DCV method
   - **Bước 2**: CSR mới (bắt buộc)
   - **Bước 3**: Thông tin liên hệ
   - **Bước 4**: Thông tin tổ chức (OV/EV)
4. Click **"Submit Reissue"**
5. Hoàn tất DCV cho CSR mới
6. Chứng chỉ mới sẽ được cấp (chứng chỉ cũ vẫn hoạt động cho đến khi cert mới được cấp)

**Lưu ý quan trọng**: Nếu lý do là "Private Key Compromised", bạn nên thu hồi (revoke) chứng chỉ cũ sau khi nhận được chứng chỉ mới.

---

## 6. Gia hạn chứng chỉ (Renew)

### Cách gia hạn

1. Khi chứng chỉ sắp hết hạn, bạn sẽ thấy cảnh báo trên trang quản lý
2. Click nút **"Renew"** (nếu hiển thị)
3. Hệ thống reset đơn hàng về trạng thái "Awaiting Configuration"
4. Bạn điền lại form cấu hình (CSR mới, domain, contacts)
5. Submit → Pending → DCV → Complete (giống quy trình đặt mới)

### Khác biệt với đặt mới

- Đơn gia hạn được đánh dấu flag `isRenew = 1`
- NicSRS API nhận flag này để áp dụng giá gia hạn (nếu khác)
- Thông tin cũ (domain, contacts) được pre-fill để tiết kiệm thời gian

---

## 7. Hủy và Thu hồi

### 7.1. Hủy đơn hàng (Cancel)

- **Điều kiện**: Chỉ khi status = Pending (chưa cấp)
- **Thao tác**: Click nút **"Cancel Order"** → Xác nhận
- **Kết quả**: Đơn hàng chuyển thành "Cancelled"
- **Lưu ý**: Không thể hoàn tác

### 7.2. Thu hồi chứng chỉ (Revoke)

- **Điều kiện**: Chỉ khi status = Complete (đã cấp)
- **Thao tác**: Click nút **"Revoke Certificate"** → Xác nhận
- **Kết quả**: Chứng chỉ bị thu hồi, trình duyệt sẽ không còn tin tưởng
- **⚠️ CẢNH BÁO**: Hành động này **KHÔNG THỂ HOÀN TÁC**. Chỉ revoke khi private key bị lộ hoặc chứng chỉ không còn cần thiết

---

## 8. Trạng thái chứng chỉ

| Trạng thái | Biểu tượng | Ý nghĩa | Hành động tiếp theo |
|---|---|---|---|
| **Awaiting Configuration** | ⚪ | Chưa cấu hình | Điền form và submit |
| **Draft** | 🔵 | Đã lưu bản nháp | Tiếp tục điền form |
| **Pending** | 🟡 | Đã gửi, chờ xác thực domain | Hoàn tất DCV |
| **Processing** | 🟡 | CA đang xử lý | Chờ — thường 5 phút đến vài giờ |
| **Complete / Issued** | 🟢 | Chứng chỉ đã cấp | Tải về và cài đặt |
| **Reissue** | 🔵 | Đang cấp lại | Chờ DCV + processing |
| **Cancelled** | 🔴 | Đã hủy | Liên hệ admin nếu cần đặt lại |
| **Revoked** | 🔴 | Đã thu hồi | Cần đặt chứng chỉ mới |
| **Expired** | 🔴 | Đã hết hạn | Gia hạn hoặc đặt mới |

### Thanh tiến trình (Progress Bar)

Trang quản lý hiển thị thanh tiến trình 4 bước trực quan:

```
[Configure] → [Submit] → [Validation] → [Issued]
    ●            ●           ○             ○       ← Ví dụ: đang ở bước Validation
```

---

## 9. Câu hỏi thường gặp (FAQ)

### Q: CSR là gì? Tại sao cần CSR?

**A**: CSR (Certificate Signing Request) là file chứa thông tin tên miền và tổ chức của bạn, dùng để yêu cầu CA cấp chứng chỉ. Bạn có thể tự động tạo CSR hoặc sử dụng CSR có sẵn từ server.

### Q: Private Key là gì? Tại sao quan trọng?

**A**: Private Key là "chìa khóa bí mật" ghép cặp với chứng chỉ SSL. Nếu mất Private Key, bạn cần cấp lại (reissue) chứng chỉ. **Không bao giờ chia sẻ Private Key** cho bất kỳ ai.

### Q: DCV mất bao lâu?

**A**: Tùy phương thức:
- **Email**: Ngay lập tức (khi click link trong email)
- **HTTP File**: 5–30 phút (sau khi upload file)
- **DNS CNAME**: 5 phút – 48 giờ (tùy DNS propagation)

### Q: Chứng chỉ mất bao lâu để được cấp?

**A**: Sau khi DCV hoàn tất:
- **DV**: 5–30 phút
- **OV**: 1–3 ngày (cần xác minh tổ chức)
- **EV**: 3–7 ngày (xác minh mở rộng)

### Q: Tôi có thể thay đổi domain sau khi submit không?

**A**: Không thể thay đổi trực tiếp. Bạn cần:
1. Hủy đơn hiện tại (nếu còn Pending)
2. Hoặc Reissue nếu chứng chỉ đã được cấp

### Q: Wildcard SSL bảo vệ những gì?

**A**: Wildcard SSL (ví dụ: `*.example.com`) bảo vệ domain chính và **tất cả subdomain cấp 1**:
- ✅ `www.example.com`, `mail.example.com`, `shop.example.com`
- ❌ `sub.shop.example.com` (cấp 2 — không được bảo vệ)

### Q: Multi-Domain (SAN) SSL là gì?

**A**: Cho phép bảo vệ nhiều tên miền khác nhau trong cùng một chứng chỉ. Ví dụ: `example.com`, `example.net`, `shop.example.org`. Số domain tối đa tùy thuộc sản phẩm (hiển thị trên trang cấu hình).

### Q: Tôi quên lưu Private Key, phải làm sao?

**A**: Nếu bạn chọn "Auto-generate CSR", Private Key được lưu trong hệ thống và có thể tải lại từ trang chứng chỉ đã cấp. Nếu bạn nhập CSR thủ công, Private Key không được lưu — bạn cần reissue chứng chỉ với CSR mới.

### Q: Chứng chỉ sắp hết hạn, tôi cần làm gì?

**A**: Bạn sẽ nhận email cảnh báo trước 30 ngày (mặc định). Truy cập trang quản lý chứng chỉ và click **"Renew"** để bắt đầu quy trình gia hạn.

---

## Hỗ trợ

Nếu bạn cần trợ giúp, vui lòng liên hệ:

- **Mở ticket hỗ trợ** tại Client Area → Support → Open New Ticket
- **Email**: support@hvn.vn
- **Website**: [hvn.vn](https://hvn.vn)

---

**© HVN GROUP** — Powered by NicSRS SSL Management System