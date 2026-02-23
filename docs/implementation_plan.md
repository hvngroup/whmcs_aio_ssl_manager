# HVN - AIO SSL Manager — Implementation Plan

> **Version:** 1.1.0 (aligned with PDR v1.1.0)  
> **Total Estimated Hours:** 360h  
> **Phases:** 4 + Testing/Deployment  
> **Author:** HVN GROUP  
> **Created:** 2026-02-23

---

## Status Legend

| Icon | Status | Icon | Status |
|------|--------|------|--------|
| 📋 | Planned | 🔨 | In Progress |
| 🔍 | In Review | ✅ | Complete |
| ⏸️ | Blocked | ❌ | Cancelled |

## Dependency Notation

- `DEP:X.Y.Z` — Hard dependency, must complete first
- `SOFT:X.Y.Z` — Soft dependency, can start before completion
- `PAR:X.Y.Z` — Can run in parallel

---

## Critical Architecture Constraints (from PDR v1.1.0)

These constraints affect EVERY task. Reference this table before implementation.

| # | Constraint | Detail |
|---|-----------|--------|
| C1 | **Admin Addon templates = PHP** | `.php` files via `includeTemplate()` + `extract()`. **NO Smarty**. |
| C2 | **Server Module client area = Smarty** | `.tpl` files returned via `['templatefile' => ..., 'vars' => ...]` |
| C3 | **Admin service tab = inline PHP** | `AdminServicesTabFields()` returns array of field => HTML string |
| C4 | **Dual-table read, single-table write** | Write → `mod_aio_ssl_orders`. Read → also `nicsrs_sslorders` + `tblsslorders` |
| C5 | **NicSRS custom table** | Legacy NicSRS orders in `nicsrs_sslorders`, NOT `tblsslorders` |
| C6 | **GoGetSSL session auth** | Must POST `/auth/` → cache token → refresh on 401 |
| C7 | **TheSSLStore renew = new order** | No `/renew` endpoint. Use `/order/neworder` with `isRenewalOrder=true` |
| C8 | **SSL2Buy brand-specific routing** | Query endpoints differ by CA brand (comodo/globalsign/symantec/prime) |
| C9 | **UI = Ant Design-inspired** | CSS variables `--aio-primary: #1890ff` etc. Match existing NicSRS module. |
| C10 | **configdata dual-format** | Legacy: `json_decode()` first → fallback `unserialize()` (WHMCS < 7.3) |

---

## Phase 1 — Foundation & Core Architecture (85h)

**Goal:** Working admin addon with provider CRUD, encryption, NicSRS as first provider.  
**Duration:** 2–3 weeks  
**Milestone:** Admin can add/edit/test providers; NicSRS product sync functional.

### 1.1 Project Scaffolding (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.1.1 | Create directory structure `modules/addons/aio_ssl_admin/` per PDR §7.1 | 📋 | Crit | 1h | — | `lib/`, `templates/`, `assets/`, `lang/` |
| 1.1.2 | Create directory structure `modules/servers/aio_ssl/` per PDR §7.1 | 📋 | Crit | 1h | — | `src/`, `view/`, `assets/`, `lang/` |
| 1.1.3 | Implement PSR-4 autoloader via `spl_autoload_register` | 📋 | Crit | 2h | 1.1.1 | Namespace: `AioSSL\\` for addon, `aioSSL\\` for server |
| 1.1.4 | `aio_ssl_admin.php` — `_config()`, `_activate()`, `_deactivate()`, `_upgrade()`, `_output()` stubs | 📋 | Crit | 3h | 1.1.1 | **C1**: `_output()` uses PHP rendering |
| 1.1.5 | `aio_ssl.php` — `_MetaData()`, `_ConfigOptions()`, `_CreateAccount()`, `_ClientArea()` stubs | 📋 | Crit | 2h | 1.1.2 | **C2**: ClientArea returns Smarty template path |
| 1.1.6 | Module constants: `AIO_SSL_VERSION`, `AIO_SSL_ADMIN_PATH`, `AIO_SSL_PATH` | 📋 | Crit | 0.5h | 1.1.4 | |
| 1.1.7 | `hooks.php` — `DailyCronJob`, `AfterCronJob`, `AdminAreaHeaderOutput` stubs | 📋 | High | 1h | 1.1.4 | |
| 1.1.8 | Language file stubs: `lang/english.php` (~200 keys), `lang/vietnamese.php` | 📋 | Med | 1.5h | 1.1.1 | |

### 1.2 Database Schema (10h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.2.1 | `_activate()`: Create `mod_aio_ssl_providers` table | 📋 | Crit | 1.5h | 1.1.4 | PDR §6.3 |
| 1.2.2 | `_activate()`: Create `mod_aio_ssl_products` table | 📋 | Crit | 1.5h | 1.1.4 | PDR §6.4 |
| 1.2.3 | `_activate()`: Create `mod_aio_ssl_product_map` table | 📋 | Crit | 1h | 1.1.4 | PDR §6.5 |
| 1.2.4 | `_activate()`: Create `mod_aio_ssl_orders` table | 📋 | Crit | 1.5h | 1.1.4 | PDR §6.2 — **C4**: new AIO orders go here |
| 1.2.5 | `_activate()`: Create `mod_aio_ssl_settings` table + default settings | 📋 | Crit | 1h | 1.1.4 | Key-value store, sync intervals, notification flags |
| 1.2.6 | `_activate()`: Create `mod_aio_ssl_activity_log` table | 📋 | High | 1h | 1.1.4 | |
| 1.2.7 | `_upgrade($vars)` — Version-based migration handler | 📋 | High | 1h | 1.2.1 | Future-proof for schema changes |
| 1.2.8 | Seed SQL: `mod_aio_ssl_product_map` with ~40 canonical mappings | 📋 | High | 1.5h | 1.2.3 | PDR §5.2 table data. Separate `.sql` file. |

### 1.3 Core Infrastructure (16h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.3.1 | `EncryptionService.php` — AES-256-CBC + HMAC integrity verification | 📋 | Crit | 4h | 1.1.3 | PDR §12.1. Key derived from `cc_encryption_hash`. |
| 1.3.2 | `ProviderInterface.php` — Full contract: identity, connection, catalog, lifecycle, DCV, capabilities | 📋 | Crit | 2h | 1.1.3 | PDR §3.2 — 20+ method signatures |
| 1.3.3 | `AbstractProvider.php` — Base HTTP client (cURL), logging via `logModuleCall()`, error handling, retry | 📋 | Crit | 4h | 1.3.2 | **Two content-types**: form-urlencoded (NicSRS/GoGetSSL) vs JSON (TheSSLStore/SSL2Buy) |
| 1.3.4 | `ProviderFactory.php` — Instantiate by slug, load + decrypt credentials from `mod_aio_ssl_providers` | 📋 | Crit | 2h | 1.3.3, 1.3.1 | |
| 1.3.5 | `ProviderRegistry.php` — Static map `slug → class`, `getAllEnabled()`, `get(slug)` | 📋 | Crit | 2h | 1.3.4 | |
| 1.3.6 | `NormalizedProduct.php` — Value object: `code, name, vendor, validation_type, type, wildcard, san, max_domains, max_years, price_data` | 📋 | High | 1h | 1.1.3 | |
| 1.3.7 | `ActivityLogger.php` — Log to `mod_aio_ssl_activity_log` with `action, entity_type, entity_id, old_value, new_value` | 📋 | High | 1h | 1.2.6 | |

### 1.4 Admin UI Framework (10h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.4.1 | `BaseController.php` — Abstract: `includeTemplate()`, JSON response, pagination, settings, lang | 📋 | Crit | 3h | 1.1.3 | **C1**: PHP templates via `extract()` + `include`. Pattern from `ref/nicsrs BaseController`. |
| 1.4.2 | `_output()` routing: detect AJAX → `handleAjax()` / page → controller dispatch | 📋 | Crit | 2h | 1.4.1 | `$controllerMap` array, `ob_end_clean()` for AJAX |
| 1.4.3 | Navigation renderer: tabs (Dashboard, Providers, Products, Price Compare, Orders, Import, Reports, Settings) | 📋 | Crit | 2h | 1.1.4 | **C9**: Ant Design nav with `--aio-primary` CSS var |
| 1.4.4 | `assets/css/admin.css` — Ant Design-inspired styles (copy+adapt from NicSRS `admin.css`) | 📋 | High | 2h | 1.4.3 | CSS variables: `--aio-primary`, `--aio-success`, etc. |
| 1.4.5 | `assets/js/admin.js` — AJAX helpers, notification toasts, confirmation dialogs | 📋 | High | 1h | 1.4.4 | |

### 1.5 Provider CRUD (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.5.1 | `ProviderController.php` — `render()` list, `handleAjax()` for add/edit/test/delete/toggle | 📋 | Crit | 4h | 1.4.1 | |
| 1.5.2 | Add provider: form with slug, name, tier, credentials, sandbox toggle | 📋 | Crit | 2h | 1.5.1, 1.3.1 | Encrypt credentials on save |
| 1.5.3 | Test connection: AJAX → `ProviderFactory::get(slug)->testConnection()` → show result | 📋 | Crit | 1.5h | 1.5.1, 1.3.4 | |
| 1.5.4 | Edit provider: load, decrypt, show masked credentials, save | 📋 | Crit | 2h | 1.5.2 | |
| 1.5.5 | Enable/disable toggle (soft-disable) + delete (hard, only if 0 active orders) | 📋 | High | 1.5h | 1.5.1 | |
| 1.5.6 | `templates/providers.php` — List template | 📋 | High | 0.5h | 1.5.1 | **C1**: PHP template |
| 1.5.7 | `templates/provider_edit.php` — Add/Edit form template | 📋 | High | 0.5h | 1.5.2 | **C1**: PHP template |

### 1.6 NicSRS Provider Plugin (16h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.6.1 | `NicsrsProvider.php` — Constructor, `getSlug()`, `getName()`, `getTier()='full'`, `getApiBaseUrl()` | 📋 | Crit | 1h | 1.3.3 | |
| 1.6.2 | `testConnection()` — POST `/productList` with `api_token` → check `code == 1` | 📋 | Crit | 1h | 1.6.1 | Auth: `api_token` as form field |
| 1.6.3 | `fetchProducts()` — POST `/productList` per vendor → normalize to `NormalizedProduct[]` | 📋 | Crit | 3h | 1.6.1 | 10 vendors, 500ms delay between. Port from NicSRS `SyncService`. |
| 1.6.4 | `placeOrder()` — POST `/place` with CSR, domainInfo, contacts, period | 📋 | Crit | 3h | 1.6.1 | Port from `ActionController::submitApply()` |
| 1.6.5 | `getOrderStatus()` — POST `/collect` with certId | 📋 | Crit | 1.5h | 1.6.1 | Merge status + cert data into configdata |
| 1.6.6 | `downloadCertificate()` — POST `/collect` → extract crt, ca, pkcs12, jks from response | 📋 | Crit | 1.5h | 1.6.5 | |
| 1.6.7 | `reissueCertificate()`, `renewCertificate()`, `revokeCertificate()`, `cancelOrder()` | 📋 | Crit | 3h | 1.6.1 | `/reissue`, `/renew`, `/revoke`, `/cancel` |
| 1.6.8 | DCV methods: `getDcvEmails()` → `/DCVemail`, `resendDcvEmail()`, `changeDcvMethod()` → `/updateDCV` | 📋 | High | 1.5h | 1.6.1 | |
| 1.6.9 | `getCapabilities()` — Return full capability list + `csr_decode`, `caa_check` | 📋 | High | 0.5h | 1.6.1 | |

### 1.7 Settings Controller (4h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 1.7.1 | `SettingsController.php` — Load/save from `mod_aio_ssl_settings` | 📋 | High | 2h | 1.4.1 | |
| 1.7.2 | `templates/settings.php` — Sync config, notifications, currency, admin email | 📋 | High | 2h | 1.7.1 | **C1**: PHP template |

### Phase 1 Checklist

- [ ] `_activate()` creates all 6 tables without errors
- [ ] Provider CRUD: add NicSRS provider, test connection → success
- [ ] NicSRS `fetchProducts()` returns normalized product list
- [ ] Settings save/load correctly
- [ ] Navigation renders all 8 tabs
- [ ] CSS matches Ant Design variables from NicSRS module
- [ ] Admin templates render without Smarty errors (pure PHP)
- [ ] `_deactivate()` is no-op (preserves data)

---

## Phase 2 — Provider Plugins & Product Engine (105h)

**Goal:** All 4 providers integrated, product sync, auto-mapping, price comparison.  
**Duration:** 3–4 weeks  
**Milestone:** Admin can sync products, map across providers, compare prices.

### 2.1 GoGetSSL Provider Plugin (24h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.1.1 | `GoGetSSLProvider.php` — **C6**: Session auth: POST `/auth/` → cache `key`, refresh on 401 | 📋 | Crit | 3h | 1.3.3 | Private `$authToken`, `authenticate()` method. Token cached in memory only. |
| 2.1.2 | `testConnection()` — Auth + `/account/balance/` | 📋 | Crit | 1h | 2.1.1 | |
| 2.1.3 | `fetchProducts()` — `/products/ssl/` → normalize. **Products use NUMERIC IDs**, not string codes. | 📋 | Crit | 3h | 2.1.1 | Map `id` → `product_code` in `mod_aio_ssl_products` |
| 2.1.4 | `fetchPricing()` — `/products/price/{id}` + `/products/all_prices/` for bulk | 📋 | Crit | 2h | 2.1.3 | |
| 2.1.5 | `placeOrder()` — `/orders/add_ssl_order/` with brand-specific `webserver_type` | 📋 | Crit | 3h | 2.1.1 | `18` for GeoTrust/RapidSSL/DigiCert/Thawte, `-1` otherwise |
| 2.1.6 | `getOrderStatus()` — `/orders/status/{id}` | 📋 | Crit | 1.5h | 2.1.1 | |
| 2.1.7 | `downloadCertificate()` — `/orders/ssl/download/{id}` | 📋 | Crit | 1.5h | 2.1.1 | |
| 2.1.8 | `reissueCertificate()` — `/orders/ssl/reissue/{id}` | 📋 | Crit | 2h | 2.1.1 | |
| 2.1.9 | `renewCertificate()` — `/orders/add_ssl_renew_order/` | 📋 | Crit | 2h | 2.1.1 | Different endpoint from place |
| 2.1.10 | `revokeCertificate()` — `/orders/ssl/revoke/{id}` | 📋 | High | 1h | 2.1.1 | |
| 2.1.11 | `cancelOrder()` — `/orders/cancel_ssl_order/{id}` | 📋 | High | 1h | 2.1.1 | |
| 2.1.12 | DCV: `getDcvEmails()` → `/tools/domain/emails/`, `resendDcvEmail()`, `changeDcvMethod()` | 📋 | High | 2h | 2.1.1 | |
| 2.1.13 | `getBalance()` → `/account/balance/` | 📋 | Med | 0.5h | 2.1.1 | |
| 2.1.14 | `csrDecode()` → `/tools/csr/decode/` | 📋 | Med | 0.5h | 2.1.1 | |

### 2.2 TheSSLStore Provider Plugin (24h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.2.1 | `TheSSLStoreProvider.php` — JSON auth: `AuthRequest { PartnerCode, AuthToken }` in every request body | 📋 | Crit | 2h | 1.3.3 | `buildAuthBody()` helper. Content-Type: `application/json` |
| 2.2.2 | `testConnection()` — `/health/status` or `/product/query` (1 product) | 📋 | Crit | 1h | 2.2.1 | |
| 2.2.3 | `fetchProducts()` — POST `/product/query` → normalize `ProductCode`, `ProductName`, `ProductType`, `PricingInfo` | 📋 | Crit | 3h | 2.2.1 | Pricing nested: `PricingInfo[].Price, PricePerAdditionalSAN` |
| 2.2.4 | `placeOrder()` — POST `/order/neworder` with complex body: `CSR, ProductCode, AdminContact, TechnicalContact, OrganizationInfo, DNSNames, WebServerType` etc. | 📋 | Crit | 4h | 2.2.1 | Ref: `PHPSDK/order_neworder.php` |
| 2.2.5 | `getOrderStatus()` — POST `/order/status` with `TheSSLStoreOrderID` or `CustomOrderID` | 📋 | Crit | 1.5h | 2.2.1 | |
| 2.2.6 | `downloadCertificate()` — `/order/download` + `/order/downloadaszip` | 📋 | Crit | 2h | 2.2.1 | |
| 2.2.7 | `reissueCertificate()` — POST `/order/reissue` | 📋 | Crit | 2h | 2.2.1 | |
| 2.2.8 | `renewCertificate()` — **C7**: POST `/order/neworder` with `isRenewalOrder=true, RelatedTheSSLStoreOrderID` | 📋 | Crit | 2h | 2.2.4 | NOT a separate endpoint! |
| 2.2.9 | `revokeCertificate()` — POST `/order/certificaterevokerequest` | 📋 | High | 1h | 2.2.1 | |
| 2.2.10 | `cancelOrder()` — POST `/order/refundrequest` | 📋 | High | 1h | 2.2.1 | Refund ≠ cancel, different flow |
| 2.2.11 | DCV: `getDcvEmails()` → `/order/approverlist`, `resendDcvEmail()` → `/order/resend`, `changeDcvMethod()` → `/order/changeapproveremail` | 📋 | High | 2h | 2.2.1 | |
| 2.2.12 | Sandbox mode: switch `baseUrl` to `sandbox-wbapi.thesslstore.com` when `api_mode=sandbox` | 📋 | Med | 1h | 2.2.1 | |
| 2.2.13 | Invite order support: `/order/inviteorder` (optional, low priority) | 📋 | Low | 1.5h | 2.2.4 | |

### 2.3 SSL2Buy Provider Plugin (20h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.3.1 | `SSL2BuyProvider.php` — JSON auth: `PartnerEmail + ApiKey` in body. Tier = `limited`. | 📋 | Crit | 2h | 1.3.3 | |
| 2.3.2 | `testConnection()` — `/orderservice/order/getbalance` | 📋 | Crit | 1h | 2.3.1 | |
| 2.3.3 | `fetchProducts()` — Static product list from `SSL2BuyProducts` class (no API endpoint for bulk list). Per-product pricing via `/orderservice/order/getproductprice`. | 📋 | Crit | 3h | 2.3.1 | Hardcoded product catalog (~80 products). Pricing fetched per product. |
| 2.3.4 | `placeOrder()` — POST `/orderservice/order/placeorder` | 📋 | Crit | 3h | 2.3.1 | |
| 2.3.5 | `validateOrder()` — POST `/orderservice/order/validateorder` | 📋 | Crit | 1h | 2.3.1 | |
| 2.3.6 | `getOrderStatus()` — **C8**: Brand-specific routing via `getBrandRoute()` | 📋 | Crit | 3h | 2.3.1 | Comodo → `/queryservice/comodo/getorderdetails`, GlobalSign → `/queryservice/globalsign/...`, Symantec → `/queryservice/symantec/...`, Prime → `/queryservice/prime/...` |
| 2.3.7 | `getConfigurationLink()` — `/orderservice/order/getsslconfigurationlink` | 📋 | Crit | 1.5h | 2.3.1 | Primary management method for limited tier |
| 2.3.8 | `resendApprovalEmail()` — Brand-routed: `/queryservice/{brand}/resendapprovalemail` | 📋 | High | 1h | 2.3.6 | |
| 2.3.9 | `getBalance()` — `/orderservice/order/getbalance` | 📋 | Med | 0.5h | 2.3.1 | |
| 2.3.10 | Implement `UnsupportedOperationException` for: `reissue, renew, revoke, cancel, download, getDcvEmails, changeDcvMethod` | 📋 | Crit | 1h | 2.3.1 | Message: "This operation is not supported by SSL2Buy. Please use the provider portal." |
| 2.3.11 | Test mode toggle: `$config['test_mode']` flag changes behavior | 📋 | Med | 1h | 2.3.1 | |
| 2.3.12 | Subscription order support: `orderSubscriptionDetail()` per brand | 📋 | Low | 2h | 2.3.6 | Comodo/GlobalSign/Symantec/PrimeSSL each have different response structures |

### 2.4 Product Catalog Sync Service (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.4.1 | `SyncService.php` — Orchestrator: loop enabled providers, call `fetchProducts()`, upsert `mod_aio_ssl_products` | 📋 | Crit | 4h | 1.6.3, 2.1.3, 2.2.3, 2.3.3 | |
| 2.4.2 | Price normalization: each provider returns different price structures → normalize to `{ base: { "12": X, "24": Y }, san: {...} }` | 📋 | Crit | 2h | 2.4.1 | NicSRS: `price.basePrice.price012`, GoGetSSL: per-product call, TheSSLStore: `PricingInfo[]`, SSL2Buy: per-product call |
| 2.4.3 | Price change detection: compare old vs new `price_data`, log differences | 📋 | High | 1.5h | 2.4.1 | |
| 2.4.4 | Sync scheduling: configurable intervals per type (status sync hours, product sync hours) | 📋 | High | 1.5h | 2.4.1 | |
| 2.4.5 | Error tracking: `sync_error_count` per provider in settings, alert at ≥ 3 | 📋 | High | 1h | 2.4.1 | |
| 2.4.6 | Manual sync trigger: AJAX from admin UI (per provider or all) | 📋 | High | 1h | 2.4.1 | |
| 2.4.7 | `ProductController.php` — Product list with filters (provider, vendor, validation type, search) | 📋 | High | 1h | 1.4.1 | |

### 2.5 Product Mapping Service (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.5.1 | `ProductMapService.php` — Auto-mapping: exact code → name normalization → fuzzy match (Levenshtein < 3) | 📋 | Crit | 4h | 2.4.1, 1.2.3 | |
| 2.5.2 | Name normalization: strip "Certificate", "SSL", trim, lowercase, handle DV/OV/EV/SAN/UCC abbreviations | 📋 | Crit | 2h | 2.5.1 | |
| 2.5.3 | Admin mapping UI: canonical products table, dropdown per provider column, unmapped alerts | 📋 | Crit | 3h | 2.5.1 | |
| 2.5.4 | Bulk create canonical entries from unmatched provider products | 📋 | High | 2h | 2.5.3 | |
| 2.5.5 | `templates/products.php` + `templates/product_mapping.php` | 📋 | High | 1h | 2.5.3 | **C1**: PHP templates |

### 2.6 Price Comparison Engine (8h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 2.6.1 | `PriceCompareService.php` — canonical_id → fetch from all providers → best per period → margin vs WHMCS sell price | 📋 | Crit | 3h | 2.5.1 | |
| 2.6.2 | `PriceCompareController.php` — Search by WHMCS product or canonical_id, AJAX | 📋 | Crit | 2h | 2.6.1 | |
| 2.6.3 | `templates/price_compare.php` — Comparison table with best-price highlighting | 📋 | High | 2h | 2.6.2 | **C1**: PHP template. PDR §13.3 mockup. |
| 2.6.4 | CSV export: all mapped products with cross-provider pricing | 📋 | Med | 1h | 2.6.1 | |

### Phase 2 Checklist

- [ ] All 4 providers `testConnection()` → success
- [ ] Product sync fetches + stores products from all 4 providers
- [ ] GoGetSSL auth token refresh works (invalidate → re-auth automatically)
- [ ] TheSSLStore sandbox mode works
- [ ] SSL2Buy `UnsupportedOperationException` thrown correctly
- [ ] Auto-mapping resolves ≥ 80% of products to canonical entries
- [ ] Price comparison shows correct pricing for mapped products
- [ ] Admin UI: PHP templates render correctly (no Smarty)
- [ ] All templates use Ant Design CSS variables

---

## Phase 3 — Server Module & Client Area (85h)

**Goal:** Full certificate lifecycle from client area across all providers.  
**Duration:** 2–3 weeks  
**Milestone:** Client can order, configure, download, reissue via unified interface.

### 3.1 Server Module Core (16h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.1.1 | `aio_ssl_MetaData()` — DisplayName="AIO SSL", APIVersion="1.1", RequiresServer=false | 📋 | Crit | 0.5h | 1.1.5 | |
| 3.1.2 | `aio_ssl_ConfigOptions()` — Dropdown: canonical products from `mod_aio_ssl_product_map`. Provider selector (auto/specific). API token override. | 📋 | Crit | 3h | 2.5.1 | Show cached product count + link to sync |
| 3.1.3 | `ProviderBridge.php` — Resolve provider: check order configdata → tblproducts configoption2 → auto cheapest → first enabled | 📋 | Crit | 4h | 1.3.5, 2.6.1 | Core routing component |
| 3.1.4 | `aio_ssl_CreateAccount()` — Legacy check (search `nicsrs_sslorders` + `tblsslorders`) → resolve provider → create `mod_aio_ssl_orders` record | 📋 | Crit | 4h | 3.1.3 | **C4**: Write to `mod_aio_ssl_orders`. **C5**: Check BOTH legacy tables. |
| 3.1.5 | `aio_ssl_SuspendAccount()`, `aio_ssl_TerminateAccount()` — Update `mod_aio_ssl_orders.status` | 📋 | High | 1.5h | 3.1.4 | |
| 3.1.6 | `AdminServicesTabFields()` — Order info OR vendor migration warning | 📋 | High | 2h | 3.1.4 | **C3**: Returns `['Field' => 'HTML string']`. Inline PHP. |
| 3.1.7 | `AdminCustomButtonArray()` — Manage Order, Refresh Status, Resend DCV, Allow New Certificate | 📋 | High | 1h | 3.1.6 | |

### 3.2 Dispatchers & Routing (8h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.2.1 | `ActionDispatcher.php` — AJAX routing: validate access, map step→action, call ActionController, JSON response | 📋 | Crit | 3h | 3.1.1 | |
| 3.2.2 | `PageDispatcher.php` — Page routing: validate ownership, determine template by order status | 📋 | Crit | 3h | 3.2.1 | Status → template mapping |
| 3.2.3 | Step-to-action map with legacy aliases (25+ mappings for backward compat) | 📋 | High | 1h | 3.2.1 | `applyssl`, `cancleOrder`, `downcert` etc. |
| 3.2.4 | `aio_ssl_ClientArea()` — Main entry: AJAX → ActionDispatcher / page → PageDispatcher | 📋 | Crit | 1h | 3.2.1, 3.2.2 | **C2**: Returns Smarty template |

### 3.3 Client Area — Certificate Application (20h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.3.1 | `ActionController::submitApply()` — Orchestrate: validate → `provider->validateOrder()` → `provider->placeOrder()` → update order | 📋 | Crit | 4h | 3.2.1, 3.1.3 | |
| 3.3.2 | Step 1 — CSR: paste or auto-generate (OpenSSL). Decode CSR to extract domains. | 📋 | Crit | 4h | 3.3.1 | `openssl_csr_new()` for generation |
| 3.3.3 | Step 2 — DCV: fetch email options via `provider->getDcvEmails()`. Select method per domain (EMAIL/HTTP/CNAME/HTTPS). | 📋 | Crit | 3h | 3.3.2 | |
| 3.3.4 | Step 3 — Contacts (OV/EV only): admin + tech contact, org info. Pre-fill from `tblclients`. Skip for DV. | 📋 | Crit | 3h | 3.3.3 | |
| 3.3.5 | Step 4 — Confirm & Submit: validate → call provider → update `mod_aio_ssl_orders` (remoteid, status=Pending) | 📋 | Crit | 3h | 3.3.4 | |
| 3.3.6 | Draft save/resume: save partial data to `configdata` at each step | 📋 | High | 2h | 3.3.1 | |
| 3.3.7 | `view/applycert.tpl` — Multi-step wizard UI with progress tabs | 📋 | High | 1h | 3.3.1 | **C2**: Smarty template, Ant Design CSS |

### 3.4 Client Area — Certificate Actions (16h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.4.1 | `refreshStatus()` — `provider->getOrderStatus()` → update `mod_aio_ssl_orders` | 📋 | Crit | 2h | 3.1.3 | |
| 3.4.2 | `downloadCertificate()` — `provider->downloadCertificate()` → serve PEM/ZIP | 📋 | Crit | 3h | 3.1.3 | Capability-checked |
| 3.4.3 | `submitReissue()` — New CSR + DCV → `provider->reissueCertificate()` | 📋 | Crit | 3h | 3.1.3 | |
| 3.4.4 | `renew()` — `provider->renewCertificate()`. **C7**: TheSSLStore calls `placeOrder` with renewal flag. | 📋 | Crit | 2h | 3.1.3 | |
| 3.4.5 | `revoke()` — Confirmation → `provider->revokeCertificate()` | 📋 | High | 2h | 3.1.3 | |
| 3.4.6 | `cancelOrder()` — Confirmation → `provider->cancelOrder()` | 📋 | High | 1.5h | 3.1.3 | |
| 3.4.7 | `resendDCVEmail()` — `provider->resendDcvEmail()` | 📋 | High | 1h | 3.1.3 | |
| 3.4.8 | Capability-aware UI: hide buttons per `provider->getCapabilities()` | 📋 | High | 1.5h | 3.4.1 | SSL2Buy: no Download/Reissue/Renew/Revoke/Cancel buttons |

### 3.5 SSL2Buy Limited-Tier Client Area (8h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.5.1 | Detect SSL2Buy orders → render `limited_provider.tpl` | 📋 | Crit | 2h | 3.2.2, 2.3.7 | |
| 3.5.2 | "Manage at Provider" button → `provider->getConfigurationLink()` → display link + PIN | 📋 | Crit | 2h | 2.3.7 | |
| 3.5.3 | Status display: parse brand-specific response structures | 📋 | High | 2h | 2.3.6 | Comodo/GlobalSign/Symantec each return different JSON |
| 3.5.4 | `view/limited_provider.tpl` — Info view with external link + PIN display | 📋 | High | 1h | 3.5.1 | **C2**: Smarty |
| 3.5.5 | Admin: show "Limited API" badge in service tab for SSL2Buy orders | 📋 | Med | 1h | 3.1.6 | |

### 3.6 Client Area Smarty Templates (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.6.1 | `view/applycert.tpl` — Multi-step wizard, AJAX, validation feedback | 📋 | Crit | 3h | 3.3.7 | **C2**: Smarty. **C9**: Ant Design CSS. |
| 3.6.2 | `view/pending.tpl` — DCV status per domain, refresh, resend DCV | 📋 | Crit | 2h | | |
| 3.6.3 | `view/complete.tpl` — Cert details, download (PEM/ZIP), reissue/renew/revoke | 📋 | Crit | 2h | | Capability-aware buttons |
| 3.6.4 | `view/reissue.tpl` — New CSR form, DCV selection | 📋 | High | 1.5h | | |
| 3.6.5 | `view/migrated.tpl` — Read-only legacy cert view: vendor badge, details, expiry | 📋 | High | 2h | | Vendor-aware formatting |
| 3.6.6 | `view/error.tpl` + `view/message.tpl` — Error/info display | 📋 | High | 0.5h | | |
| 3.6.7 | `assets/css/ssl-manager.css` — Client area Ant Design CSS (adapt from NicSRS) | 📋 | High | 1h | | **C9**: CSS var prefix `--sslm-` |

### 3.7 OrderService for `mod_aio_ssl_orders` (5h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 3.7.1 | `OrderService.php` — CRUD: `create(), getById(), getByServiceId(), update(), getByStatus()` on `mod_aio_ssl_orders` | 📋 | Crit | 3h | 1.2.4 | **C4**: Write only to this table |
| 3.7.2 | `ensureTableExists()` — Auto-create if missing (safety net) | 📋 | High | 1h | 3.7.1 | |
| 3.7.3 | configdata JSON encode/decode helpers with `json_last_error()` check | 📋 | High | 1h | 3.7.1 | |

### Phase 3 Checklist

- [ ] New order (`CreateAccount`) works with all 4 providers
- [ ] CreateAccount checks BOTH `nicsrs_sslorders` AND `tblsslorders` for legacy certs
- [ ] Multi-step application (CSR → DCV → Contacts → Submit) functional
- [ ] Certificate download works (NicSRS, GoGetSSL, TheSSLStore)
- [ ] SSL2Buy shows config link instead of download
- [ ] Reissue/Renew/Revoke functional for Full-tier providers
- [ ] TheSSLStore renew creates new order with `isRenewalOrder=true`
- [ ] Provider selector (auto/specific) routes correctly
- [ ] Admin service tab shows correct info (inline PHP, not Smarty)
- [ ] Client area templates are Smarty `.tpl` files
- [ ] Legacy orders display in `migrated.tpl`

---

## Phase 4 — Dashboard, Reports, Migration & Polish (85h)

**Goal:** Production-ready with unified dashboard, migration tools, reporting.  
**Duration:** 3–4 weeks  
**Milestone:** Full production deployment.

### 4.1 UnifiedOrderService (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.1.1 | `UnifiedOrderService.php` — Read from 3 tables: `mod_aio_ssl_orders` + `nicsrs_sslorders` + `tblsslorders` | 📋 | Crit | 4h | 1.2.4 | **C4, C5**: Core of unified view |
| 4.1.2 | NicSRS legacy reader: query `nicsrs_sslorders`, normalize configdata, mark `source='legacy_nicsrs'` | 📋 | Crit | 2h | 4.1.1 | **C5**: Separate table! |
| 4.1.3 | tblsslorders legacy reader: `WHERE module IN ('SSLCENTERWHMCS','thesslstore_ssl','thesslstore','ssl2buy')`, map module→provider | 📋 | Crit | 2h | 4.1.1 | **C10**: json_decode → unserialize fallback |
| 4.1.4 | Unified sorting + pagination across merged results | 📋 | High | 2h | 4.1.1 | Sort by date, status, provider |
| 4.1.5 | Filters: provider, status, client, domain, date range | 📋 | High | 2h | 4.1.4 | |

### 4.2 Dashboard (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.2.1 | `DashboardController.php` — Aggregate stats from `UnifiedOrderService` | 📋 | High | 3h | 4.1.1 | |
| 4.2.2 | Stat cards: Total Orders (per provider), Pending, Issued, Expiring Soon | 📋 | High | 2h | 4.2.1 | |
| 4.2.3 | Chart.js: Orders by Provider (stacked bar), Status (doughnut), Monthly Trends (line) | 📋 | High | 3h | 4.2.1 | |
| 4.2.4 | API Health widget: `testConnection()` per provider | 📋 | Med | 1.5h | 4.2.1 | |
| 4.2.5 | Provider balance (GoGetSSL + SSL2Buy only) | 📋 | Med | 0.5h | 4.2.1 | |
| 4.2.6 | `templates/dashboard.php` | 📋 | High | 2h | 4.2.1 | **C1**: PHP template |

### 4.3 Order Management (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.3.1 | `OrderController.php` — List: unified order table, provider badge, source indicator (AIO/Legacy) | 📋 | Crit | 4h | 4.1.1 | |
| 4.3.2 | Order detail: full cert data, DCV status, activity log | 📋 | Crit | 3h | 4.3.1 | |
| 4.3.3 | Admin actions: Refresh, Resend DCV, Revoke, Cancel (capability-aware) | 📋 | Crit | 2h | 4.3.2 | |
| 4.3.4 | "Claim" button for legacy orders → create `mod_aio_ssl_orders` record with `legacy_table` + `legacy_order_id` | 📋 | Crit | 2h | 4.3.1, 4.5.1 | **C4**: Non-destructive claim |
| 4.3.5 | `templates/orders.php` + `templates/order_detail.php` | 📋 | High | 1h | 4.3.1 | **C1**: PHP templates |

### 4.4 Auto-Sync Engine (10h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.4.1 | Certificate status sync: loop pending/processing orders per provider, call `getOrderStatus()`, update `mod_aio_ssl_orders` | 📋 | Crit | 3h | 2.4.1 | |
| 4.4.2 | Product catalog sync via cron | 📋 | Crit | 2h | 2.4.1 | |
| 4.4.3 | Expiry check: scan active certs, detect within N days | 📋 | High | 2h | 4.4.1 | |
| 4.4.4 | WHMCS hooks: `DailyCronJob` + `AfterCronJob` → `SyncService::runScheduledSync()` | 📋 | Crit | 1.5h | 4.4.1 | |
| 4.4.5 | File-based lock to prevent concurrent sync | 📋 | High | 0.5h | 4.4.4 | |
| 4.4.6 | Sync status in Settings (last sync, error count per provider) | 📋 | High | 1h | 4.4.1 | |

### 4.5 Migration Service (14h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.5.1 | `MigrationService.php` — Core `normalizeConfigdata()` dispatcher | 📋 | Crit | 2h | 1.2.4 | PDR §11.2 |
| 4.5.2 | NicSRS normalizer: `nicsrs_sslorders` configdata → AIO format. Map: `applyReturn.beginDate/endDate`, `domainInfo`, `csr/crt/ca/private_key`. | 📋 | Crit | 2h | 4.5.1 | **C5**: Different table + different JSON structure |
| 4.5.3 | GoGetSSL normalizer: `tblsslorders WHERE module='SSLCENTERWHMCS'`. **C10**: Try `json_decode` → fallback `unserialize`. Map: `csr, crt, ca, approver_email`. | 📋 | Crit | 3h | 4.5.1 | |
| 4.5.4 | TheSSLStore normalizer: `WHERE module IN ('thesslstore_ssl','thesslstore')`. Map: `TheSSLStoreOrderID, crt_code, ca_code`. | 📋 | Crit | 2h | 4.5.1 | |
| 4.5.5 | SSL2Buy normalizer: `WHERE module='ssl2buy'`. **C8**: Brand-specific configdata varies (Comodo/GlobalSign/Symantec/PrimeSSL). | 📋 | Crit | 2h | 4.5.1 | |
| 4.5.6 | "Claim" function: create `mod_aio_ssl_orders` with `legacy_table`, `legacy_order_id`, `legacy_module` populated | 📋 | Crit | 1.5h | 4.5.1 | Non-destructive: original record untouched |
| 4.5.7 | Bulk claim: select multiple → batch process | 📋 | High | 1.5h | 4.5.6 | |

### 4.6 Notification Service (8h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.6.1 | `NotificationService.php` — Send via WHMCS `SendAdminEmail` Local API (NOT `mail()`) | 📋 | High | 2h | 4.4.1 | |
| 4.6.2 | Certificate issuance email (HTML with cert details, provider badge) | 📋 | High | 1.5h | 4.6.1 | |
| 4.6.3 | Expiry warning (urgency: 🚨 ≤7d, ⚠️ ≤30d) | 📋 | High | 1.5h | 4.6.1 | |
| 4.6.4 | Sync error alert (when error_count ≥ 3) | 📋 | High | 1h | 4.6.1 | |
| 4.6.5 | Price change notification (comparison table) | 📋 | Med | 1h | 4.6.1 | |
| 4.6.6 | `AdminAreaHeaderOutput` hook: warning banner for sync errors | 📋 | Med | 1h | 4.6.1 | |

### 4.7 Import & Reports (12h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.7.1 | `ImportController.php` — Single cert: provider + remote ID → `provider->getOrderStatus()` → create `mod_aio_ssl_orders` | 📋 | High | 3h | 3.1.3 | |
| 4.7.2 | Link certificate to existing WHMCS service (validate servertype = aio_ssl) | 📋 | High | 1.5h | 4.7.1 | |
| 4.7.3 | Bulk import: CSV upload (provider, remote_id, service_id) | 📋 | Med | 2h | 4.7.1 | |
| 4.7.4 | `ReportService.php` — Revenue by Provider, Product Performance, Expiry Forecast | 📋 | Med | 3h | 4.1.1 | |
| 4.7.5 | CSV export for reports | 📋 | Med | 1h | 4.7.4 | |
| 4.7.6 | `templates/import.php` + `templates/reports.php` | 📋 | Med | 1.5h | 4.7.1 | **C1**: PHP templates |

### 4.8 Localization & Polish (5h)

| # | Task | Status | Pri. | Est. | Dep. | Notes |
|---|------|--------|------|------|------|-------|
| 4.8.1 | Complete `lang/english.php` — All ~200 translation keys (admin + server) | 📋 | Med | 1.5h | — | |
| 4.8.2 | Complete `lang/vietnamese.php` | 📋 | Med | 2h | 4.8.1 | |
| 4.8.3 | Client area languages: EN, VI, Chinese (Trad + Simp) | 📋 | Med | 1h | 4.8.1 | Port from NicSRS |
| 4.8.4 | UI polish: loading spinners, error handling, responsive tables | 📋 | Med | 0.5h | — | |

### Phase 4 Checklist

- [ ] Dashboard shows data from ALL 3 tables (unified)
- [ ] NicSRS legacy orders from `nicsrs_sslorders` appear correctly
- [ ] GoGetSSL/TheSSLStore/SSL2Buy legacy orders from `tblsslorders` appear correctly
- [ ] Legacy configdata normalized correctly (JSON + serialized formats)
- [ ] "Claim" creates new `mod_aio_ssl_orders` without touching legacy record
- [ ] Auto-sync runs via cron, updates `mod_aio_ssl_orders` only
- [ ] Notifications sent via WHMCS `SendAdminEmail` (not `mail()`)
- [ ] All admin templates are PHP (no `.tpl`)
- [ ] All client area templates are Smarty (`.tpl`)

---

## Testing Matrix

### Provider Integration Tests

| Test | NicSRS | GoGetSSL | TheSSLStore | SSL2Buy |
|------|--------|----------|-------------|---------|
| Connection test | 📋 | 📋 | 📋 | 📋 |
| Product sync | 📋 | 📋 | 📋 | 📋 |
| Place DV order | 📋 | 📋 | 📋 | 📋 |
| Place OV order | 📋 | 📋 | 📋 | 📋 |
| Status refresh | 📋 | 📋 | 📋 | 📋 |
| Download cert | 📋 | 📋 | 📋 | N/A (config link) |
| Reissue | 📋 | 📋 | 📋 | N/A |
| Renew | 📋 | 📋 | 📋 (via neworder) | N/A |
| Revoke | 📋 | 📋 | 📋 | N/A |
| Cancel | 📋 | 📋 | 📋 (refund) | N/A |
| DCV management | 📋 | 📋 | 📋 | Partial (resend only) |
| Config link | N/A | N/A | N/A | 📋 |
| Auth token refresh | N/A | 📋 (session expiry) | N/A | N/A |

### Migration Tests

| Test | Status | Notes |
|------|--------|-------|
| Read `nicsrs_sslorders` (NicSRS legacy) | 📋 | **C5**: Custom table |
| Read `tblsslorders` WHERE module=`SSLCENTERWHMCS` | 📋 | GoGetSSL legacy |
| Read `tblsslorders` WHERE module=`thesslstore_ssl` | 📋 | TheSSLStore legacy |
| Read `tblsslorders` WHERE module=`ssl2buy` | 📋 | SSL2Buy legacy |
| Normalize JSON configdata | 📋 | |
| Normalize serialized configdata | 📋 | **C10**: WHMCS < 7.3 |
| Claim single legacy order | 📋 | Non-destructive |
| Bulk claim | 📋 | |
| Client view of legacy order | 📋 | `migrated.tpl` (Smarty) |

### Template Engine Tests

| Test | Status | Notes |
|------|--------|-------|
| Admin templates render as PHP (no Smarty errors) | 📋 | **C1** |
| Client area templates render as Smarty | 📋 | **C2** |
| Admin service tab returns HTML strings | 📋 | **C3** |
| CSS variables apply correctly (Ant Design theme) | 📋 | **C9** |

### WHMCS Compatibility

| WHMCS | PHP | Status |
|-------|-----|--------|
| 7.10 | 7.4 | 📋 |
| 8.0 | 7.4 | 📋 |
| 8.5+ | 8.0 | 📋 |
| 8.8+ | 8.1 | 📋 |

---

## Deployment Checklist

### Pre-Deployment

- [ ] All Phase 1–4 tasks ✅
- [ ] All provider integration tests pass
- [ ] All migration tests pass (with real legacy data)
- [ ] Template engine tests pass (PHP admin, Smarty client)
- [ ] WHMCS 8.x + PHP 8.0 verified
- [ ] Security: encryption, input validation, access control reviewed
- [ ] Performance: pagination with 10K+ orders tested
- [ ] Languages: EN + VI complete

### Deployment Steps

1. [ ] Backup all databases (`tblsslorders`, `nicsrs_sslorders`)
2. [ ] Upload `modules/addons/aio_ssl_admin/`
3. [ ] Upload `modules/servers/aio_ssl/`
4. [ ] Activate addon: WHMCS Admin → Setup → Addon Modules
5. [ ] Configure providers (add credentials)
6. [ ] Test connection for each provider
7. [ ] Run initial product sync
8. [ ] Verify seed data in `mod_aio_ssl_product_map`
9. [ ] Review auto-mapped products, fix any mismatches
10. [ ] Create first test WHMCS product with `servertype=aio_ssl`
11. [ ] Place test order → verify full lifecycle
12. [ ] Verify dashboard shows legacy orders from all 4 tables
13. [ ] Enable cron sync

### Post-Deployment (Gradual Migration)

1. [ ] Create new WHMCS products with `servertype=aio_ssl`
2. [ ] Gradually change existing products from legacy `servertype` to `aio_ssl`
3. [ ] Monitor: verify legacy orders still visible
4. [ ] Admin "Claims" legacy orders (one-by-one or bulk)
5. [ ] Once all orders claimed → deactivate legacy modules
6. [ ] Keep legacy tables for audit (never delete)

---

## Summary

| Phase | Hours | Duration | Key Deliverable |
|-------|-------|----------|-----------------|
| Phase 1 | 85h | 2–3 weeks | Foundation + NicSRS provider + admin UI |
| Phase 2 | 105h | 3–4 weeks | All 4 providers + product sync + price compare |
| Phase 3 | 85h | 2–3 weeks | Server module + client area + full lifecycle |
| Phase 4 | 85h | 3–4 weeks | Dashboard + migration + reports + polish |
| **Total** | **360h** | **10–14 weeks** | **Production-ready AIO SSL Manager** |

---

**© HVN GROUP** — All rights reserved.  
**Document Version:** 1.1.0 | **Aligned with PDR v1.1.0**