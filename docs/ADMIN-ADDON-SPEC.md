# Admin Addon Module Specification

> **Module:** `nicsrs_ssl_admin` | **Version:** 1.3.1  
> **Location:** `modules/addons/nicsrs_ssl_admin/`  
> **Last Updated:** 2026-02-09

---

## 1. Module Entry Point

**File:** `nicsrs_ssl_admin.php`

### Required WHMCS Functions

| Function | Purpose |
|---|---|
| `nicsrs_ssl_admin_config()` | Returns module metadata + config fields (`api_token`, `items_per_page`) |
| `nicsrs_ssl_admin_activate()` | Creates 3 database tables + inserts default settings |
| `nicsrs_ssl_admin_deactivate()` | No-op (tables preserved) |
| `nicsrs_ssl_admin_upgrade($vars)` | Version-based schema migrations (e.g., currency settings in v1.3.0) |
| `nicsrs_ssl_admin_output($vars)` | Main routing: AJAX → `handleAjaxRequest()` / Page → Controller |

### AJAX Handling

All AJAX requests send `POST[ajax_action]`. Detected in `output()`:
1. Clear output buffers (`ob_end_clean`)
2. Set `Content-Type: application/json`
3. Resolve controller from `$controllerMap`
4. Call `$controller->handleAjax($_POST)`
5. Echo response + `exit`

### Page Rendering Pipeline

```
outputAssets()        → CSS + JS includes
renderNavigation()    → Tab bar (Dashboard, Products, Orders, Import, Reports, Settings)
$controller->render() → PHP template via includeTemplate()
renderFooter()        → Version + HVN GROUP link
```

---

## 2. Dashboard (`DashboardController`)

### Page Content

**Statistics Cards** (4 cards):
- Total Orders: `COUNT(nicsrs_sslorders)`
- Pending Orders: `WHERE status IN (awaiting, draft, pending)`
- Issued Certificates: `WHERE status IN (complete, issued, active)`
- Expiring Soon (30d): certificates with `endDate` within 30 days

**Charts** (Chart.js):
- Status Distribution: Doughnut chart, color-coded by status
- Monthly Orders: Bar chart, last 6 months trend

**Tables**:
- Recent Orders (10): Order ID, domain, product, client, status, date, action link
- Expiring Certificates (20): domain, expiry date, days left

**API Status Alert**: Warning banner if `testApiConnection()` returns false.

### AJAX Actions

None — dashboard is read-only.

---

## 3. Products (`ProductController`)

### Page Content

**Filters**: Vendor dropdown, validation type (DV/OV/EV), search text, linked/unlinked toggle

**Product Table** with columns: Product Code, Product Name, Vendor, Type (badge), Wildcard (✓/✗), SAN (✓/✗), Max Domains, Price (1yr), Linked (to WHMCS product), Last Sync, Actions

**Pagination**: Configurable `items_per_page` from settings.

### AJAX Actions

| Action | Method | Description |
|---|---|---|
| `sync_all` | `handleAjax()` | Sync products from all 10 vendors via `SyncService` |
| `sync_vendor` | `handleAjax()` | Sync products from a specific vendor |
| `search` | `handleAjax()` | Server-side search with filters |

### Product-WHMCS Linking

The "Linked" column shows whether `mod_nicsrs_products.product_code` matches any `tblproducts.configoption1` where `servertype = 'nicsrs_ssl'`. This is read-only display; actual linking is done in WHMCS product configuration.

---

## 4. Orders (`OrderController`)

### Order List

**Filters**: Status dropdown (including special "Expiring" filter), search (domain, cert ID, client name)

**Order Table** columns: Order ID, Domain, Product, Client (name + email), Service ID, Status (badge), Created, Expires, Days Left, Actions (view)

**Special "Expiring" filter**: Queries active certs where `applyReturn.endDate` is within 30 days. Implemented as post-query JSON parsing since endDate is inside `configdata` JSON.

### Order Detail View

Triggered when `action=order&id=N`. Displays:

**Order Info Panel**: Order ID, Remote ID, Status (badge), Domain, Product Code, Product Name, Client (link to WHMCS client), Service (link to WHMCS service), Provision Date, Completion Date, Last Refresh

**Certificate Details Panel**: Begin Date, End Date, Vendor ID, Vendor Cert ID, DCV Status

**DCV Information Panel** (when pending): per-domain DCV method, verification status, file/DNS/email details

**Action Buttons**:
| Button | AJAX Action | Condition |
|---|---|---|
| Refresh Status | `refresh_status` | Has `remoteid` |
| Resend DCV | `resend_dcv` | Status = pending |
| Cancel Order | `cancel` | Status = pending |
| Revoke Certificate | `revoke` | Status = complete |
| Delete Order | `delete` | Any (with confirmation) |

**Activity Log**: Recent activity entries for this order from `mod_nicsrs_activity_log` where `entity_type='order' AND entity_id=$orderId`.

### AJAX Actions

| Action | Description | API Call |
|---|---|---|
| `refresh_status` | Fetch latest from NicSRS API | `/collect` |
| `cancel` | Cancel pending order | `/cancel` |
| `revoke` | Revoke issued certificate | `/revoke` |
| `resend_dcv` | Resend DCV validation email | `/DCVemail` |
| `delete` | Remove order from database | None (DB only) |
| `edit` | Update order fields | None (DB only) |

---

## 5. Settings (`SettingsController`)

### Settings Panels

**Notification Settings** (`<form id="settingsForm">`):
- Email on issuance (checkbox)
- Email on expiry (checkbox)
- Expiry warning days (number, 1–90)
- Admin email override (email input)

**Auto-Sync Settings**:
- Enable auto-sync (checkbox)
- Status sync interval (1–24 hours)
- Product sync interval (1–168 hours)
- Batch size (10–200 certs)
- Sync status display: last sync, next sync, pending count (AJAX loaded)
- Manual sync buttons: Sync Status / Sync Products / Check Expiring
- Sync log display (last 5 entries, JS-populated)

**Display Settings**:
- Date format dropdown (Y-m-d / d/m/Y / m/d/Y / d.m.Y)

**Currency Settings**:
- USD/VND exchange rate (number input)
- Display mode (USD / VND / Both)
- Update from API button
- Rate last updated display

**Activity Log Panel**:
- Last 20 log entries table
- Clear logs modal (7/30/90 days or all)
- Export logs CSV button

**Module Info Panel**: Version, Author, Support email, Docs link

### AJAX Actions

| Action | Description |
|---|---|
| `save_settings` | Save all settings to `mod_nicsrs_settings` |
| `manual_sync` | Trigger `SyncService::forceSyncNow($type)` with `set_time_limit(300)` |
| `get_sync_status` | Return current sync state from settings |
| `check_expiring` | Count expiring certs within threshold |
| `update_exchange_rate` | Fetch rate from external API via `CurrencyHelper` |
| `test_api` | Test API connection via `NicsrsApiService::testConnection()` |
| `clear_logs` | Delete activity logs older than N days |
| `export_logs` | Generate CSV base64 for download |

---

## 6. Import (`ImportController`)

### Page Content

**Lookup Form**: Enter Certificate ID → lookup via API `/collect` → display cert info (domain, status, dates, DCV list)

**Import Options** (after lookup):
- Import only (no service link): creates order with `userid=0`, `serviceid=0`
- Import + Link to Service: requires Service ID, validates `servertype=nicsrs_ssl`

**Bulk Import Form**: Textarea for multiple cert IDs (one per line) → bulk import without service linking

**Recently Imported Table**: Last 20 imported orders with domain, status, expiry

### AJAX Actions

| Action | Description | Validations |
|---|---|---|
| `lookup_cert` | Fetch cert data from API | Cert ID required |
| `import_cert` | Create unlinked order record | Not already imported |
| `link_existing` | Create order linked to WHMCS service | Service exists, servertype match, not already linked |
| `bulk_import` | Import multiple certs | Skip duplicates, report errors per cert |

---

## 7. Reports (`ReportController`)

### Report Types

**Profit Report** (`reports/profit` template):
- Summary: Total Revenue USD, Total Cost USD, Total Profit USD/VND, Overall Margin %
- Table: per-order profit breakdown (sale amount, cost, profit, margin)
- Chart: profit trend over time (line chart)
- Filters: date range, vendor, validation type
- CSV export

**Product Performance** (`reports/performance` template):
- Summary: Total Products, Total Orders, Total Revenue USD
- Table: per-product metrics (orders, active, cancelled, revenue, avg order, completion rate, renewal rate)
- Charts: Top Products bar chart (dual Y-axis: orders + revenue), Validation Type pie chart
- Filters: date range, vendor
- CSV export

**Revenue by Brand** (`reports/brand` template):
- Summary: Total Brands, Total Orders, Total Revenue
- Table: per-vendor metrics (orders, active, revenue, avg order, revenue share %, order share %)
- Chart: brand trend over time
- Filters: date range, period (month/quarter)
- CSV export

### AJAX Actions

| Action | Description |
|---|---|
| `get_sales_data` / `get_sales_chart` | Sales report data + chart |
| `get_profit_data` / `get_profit_chart` | Profit report data + chart |
| `get_performance_data` | Product performance data |
| `get_brand_data` / `get_brand_trend` | Brand analytics data + trend |
| `update_exchange_rate` | Update VND rate |
| `save_currency_settings` | Save rate + display mode |
| `export_csv` | Generate CSV by report type |

### Currency in Reports

All monetary values stored in USD. `CurrencyHelper` converts to VND at runtime using the configured exchange rate. Display mode (`usd`/`vnd`/`both`) controls which columns appear.

---

## 8. Auto-Sync Engine (`SyncService`)

### Sync Flow

**Scheduled** (via cron hooks): `runScheduledSync()` checks intervals → executes if elapsed.

**Manual** (via Settings UI): `forceSyncNow($type)` bypasses interval check.

### Certificate Status Sync

1. Query `nicsrs_sslorders` where status in `PENDING_STATUSES`
2. Process in batches of `sync_batch_size`
3. For each: API `/collect` → merge data → update status
4. If new status = complete: set `completiondate`, send notification
5. Then: check `ACTIVE_STATUSES` for expiry
6. For expired: update status, notify admin

### Product Catalog Sync

1. Iterate `VENDORS[]` array (10 vendors)
2. For each: API `/productList?vendor=X`
3. `saveProducts()`: UPSERT into `mod_nicsrs_products`
4. Detect price changes (compare old vs new `price_data`)
5. Send price change notification if any
6. 500ms delay between vendors

### Error Handling

- Success: reset `sync_error_count` to 0
- Failure: increment `sync_error_count`
- At ≥ 3: `sendSyncErrorNotification()` via `NotificationService`
- Admin area banner via `AdminAreaHeaderOutput` hook

---

## 9. Notification Service (`NotificationService`)

### Email Types

| Type | Trigger | Template |
|---|---|---|
| Certificate Issued | Auto-sync detects complete | Green header, cert details, manage link |
| Expiry Warning | Cron expiry check | Orange/red header, urgency level, renew link |
| Sync Error | 3+ consecutive errors | Red header, error list, settings link |
| Price Change | Product sync detects changes | Blue header, comparison table, arrows |

### Delivery Method

All emails use WHMCS Local API `SendAdminEmail`:
```php
localAPI('SendAdminEmail', [
    'customsubject' => $subject,
    'custommessage' => $htmlBody,
    'type' => 'system',
]);
```

**Recipient**: `admin_email` setting (if set), otherwise WHMCS system admin email.

### Email Template Features
- Inline CSS (email client compatibility)
- Responsive container (max-width: 600px)
- Gradient header with emoji icons
- Action buttons with links to admin pages
- HVN GROUP branding in footer