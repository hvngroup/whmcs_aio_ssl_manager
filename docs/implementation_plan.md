# HVN - AIO SSL Manager — Implementation Plan

> **Version:** 1.0.0  
> **Total Estimated Hours:** 350h  
> **Phases:** 4  
> **Author:** HVN GROUP  
> **Created:** 2026-02-11

---

## Status Legend

| Icon | Status |
|------|--------|
| 📋 | Planned |
| 🔨 | In Progress |
| 🔍 | In Review |
| ✅ | Complete |
| ⏸️ | Blocked |
| ❌ | Cancelled |

## Dependency Legend

| Code | Meaning |
|------|---------|
| `DEP:X.Y` | Depends on task X.Y completion |
| `SOFT:X.Y` | Soft dependency (can start before X.Y completes) |
| `PAR:X.Y` | Can run in parallel with X.Y |

---

## Phase 1 — Foundation & Core Architecture (80h)

**Goal:** Working admin addon with provider CRUD, encryption, NicSRS integrated as first provider.  
**Duration:** 2–3 weeks  
**Milestone:** Admin can add/edit/test providers; NicSRS product sync functional.

### 1.1 Project Scaffolding (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.1.1 | Create directory structure for `modules/addons/aio_ssl_admin/` | 📋 | Critical | 1h | — | See PDR §7.1 |
| 1.1.2 | Create directory structure for `modules/servers/aio_ssl/` | 📋 | Critical | 1h | — | |
| 1.1.3 | Implement PSR-4 compatible autoloader (`spl_autoload_register`) | 📋 | Critical | 2h | 1.1.1 | Namespace: `AioSSL\` |
| 1.1.4 | Create `aio_ssl_admin.php` entry point with `_config()`, `_activate()`, `_deactivate()`, `_upgrade()`, `_output()` stubs | 📋 | Critical | 3h | 1.1.1 | |
| 1.1.5 | Create `aio_ssl.php` server module entry point with `_MetaData()`, `_ConfigOptions()`, `_CreateAccount()` stubs | 📋 | Critical | 2h | 1.1.2 | |
| 1.1.6 | Define module constants (`AIO_SSL_VERSION`, `AIO_SSL_PATH`, etc.) | 📋 | Critical | 0.5h | 1.1.4 | |
| 1.1.7 | Setup `hooks.php` with `DailyCronJob`, `AfterCronJob` stubs | 📋 | High | 1h | 1.1.4 | |
| 1.1.8 | Create language file stubs `lang/english.php`, `lang/vietnamese.php` | 📋 | Medium | 1.5h | 1.1.1 | ~150 keys |

### 1.2 Database Schema & Migration (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.2.1 | Implement `_activate()`: Create `mod_aio_ssl_providers` table | 📋 | Critical | 1.5h | 1.1.4 | See PDR §6.2 |
| 1.2.2 | Implement `_activate()`: Create `mod_aio_ssl_products` table | 📋 | Critical | 1.5h | 1.1.4 | See PDR §6.3 |
| 1.2.3 | Implement `_activate()`: Create `mod_aio_ssl_product_map` table | 📋 | Critical | 1.5h | 1.1.4 | See PDR §6.4 |
| 1.2.4 | Implement `_activate()`: Create `mod_aio_ssl_settings` table | 📋 | Critical | 1h | 1.1.4 | Key-value store |
| 1.2.5 | Implement `_activate()`: Create `mod_aio_ssl_activity_log` table | 📋 | High | 1h | 1.1.4 | |
| 1.2.6 | Insert default settings (sync intervals, notification flags, etc.) | 📋 | High | 0.5h | 1.2.4 | |
| 1.2.7 | Implement `_upgrade($vars)` version-based migration handler | 📋 | High | 1h | 1.2.1 | Future-proof |
| 1.2.8 | Seed `mod_aio_ssl_product_map` with initial canonical mappings (~40 products) | 📋 | High | — | 1.2.3 | SQL seed file |

### 1.3 Core Infrastructure (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.3.1 | `EncryptionService` — AES-256-CBC encrypt/decrypt using WHMCS `cc_encryption_hash` | 📋 | Critical | 4h | 1.1.3 | See PDR §12.1 |
| 1.3.2 | `ProviderInterface.php` — Full contract definition (all methods) | 📋 | Critical | 2h | 1.1.3 | See PDR §3.2 |
| 1.3.3 | `AbstractProvider.php` — Base implementation with HTTP client, logging, error handling | 📋 | Critical | 4h | 1.3.2 | cURL wrapper, retry logic |
| 1.3.4 | `ProviderFactory.php` — Instantiate provider by slug, inject credentials | 📋 | Critical | 2h | DEP:1.3.3, 1.3.1 | Decrypts credentials |
| 1.3.5 | `ProviderRegistry.php` — Static registry, `getAllEnabled()`, `get(slug)` | 📋 | Critical | 2h | 1.3.4 | |
| 1.3.6 | `NormalizedProduct.php` — Value object for cross-provider product data | 📋 | High | 1h | 1.1.3 | |
| 1.3.7 | `ActivityLogger.php` — Log actions to `mod_aio_ssl_activity_log` | 📋 | High | 1h | 1.2.5 | |

### 1.4 Admin UI Framework (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.4.1 | `BaseController.php` — Abstract base: template rendering, JSON response, pagination, settings access | 📋 | Critical | 3h | 1.1.3 | |
| 1.4.2 | Admin navigation renderer (tabs: Dashboard, Providers, Products, Price Compare, Orders, Import, Reports, Settings) | 📋 | Critical | 2h | 1.1.4 | Bootstrap 3 (WHMCS native) |
| 1.4.3 | `_output()` routing: AJAX detection + page controller dispatch | 📋 | Critical | 2h | DEP:1.4.1, 1.4.2 | |
| 1.4.4 | CSS/JS asset loader + base template with footer branding | 📋 | High | 1h | 1.4.2 | HVN GROUP footer |

### 1.5 Provider CRUD Controller (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.5.1 | `ProviderController.php` — List all providers (table with status, tier, test result) | 📋 | Critical | 2h | DEP:1.4.1, 1.2.1 | |
| 1.5.2 | Add Provider form: name, slug, tier selection, API credentials (dynamic fields per provider type), sandbox toggle | 📋 | Critical | 3h | 1.5.1 | Credential fields change based on provider type |
| 1.5.3 | Edit Provider: load existing config, update credentials (re-encrypt), toggle enable/disable | 📋 | Critical | 2h | 1.5.2 | |
| 1.5.4 | Test Connection: AJAX call → `ProviderFactory::get(slug)->testConnection()` → display result | 📋 | Critical | 2h | DEP:1.3.5 | |
| 1.5.5 | Delete Provider: confirmation modal, check for active orders, hard delete | 📋 | High | 1.5h | 1.5.1 | Block if active orders > 0 |
| 1.5.6 | `providers.tpl` template: provider list table + add/edit modal form | 📋 | High | 1.5h | 1.5.1 | |

### 1.6 NicSRS Provider Plugin (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.6.1 | `NicsrsProvider.php` — Constructor, auth setup, HTTP client config | 📋 | Critical | 1h | DEP:1.3.3 | Port from existing `nicsrs_ssl` |
| 1.6.2 | `testConnection()` — Call `/productList` with minimal params | 📋 | Critical | 1h | 1.6.1 | |
| 1.6.3 | `fetchProducts()` — Call `/productList` per vendor, normalize to `NormalizedProduct[]` | 📋 | Critical | 3h | 1.6.1 | 10 vendors, 500ms delay |
| 1.6.4 | `fetchPricing()` — Extract pricing from product list response | 📋 | Critical | 1h | 1.6.3 | |
| 1.6.5 | `placeOrder()` — Build params, call `/place`, parse response | 📋 | Critical | 2h | 1.6.1 | Complex param building |
| 1.6.6 | `getOrderStatus()` — Call `/collect`, normalize response | 📋 | Critical | 1.5h | 1.6.1 | |
| 1.6.7 | `downloadCertificate()` — Extract cert from `/collect` response | 📋 | Critical | 1h | 1.6.6 | cert, ca, private_key |
| 1.6.8 | `reissueCertificate()` — Call `/reissue` | 📋 | Critical | 1h | 1.6.1 | |
| 1.6.9 | `renewCertificate()` — Call `/renew` | 📋 | Critical | 1h | 1.6.1 | |
| 1.6.10 | `revokeCertificate()` — Call `/revoke` | 📋 | Critical | 1h | 1.6.1 | |
| 1.6.11 | `cancelOrder()` — Call `/cancel` | 📋 | High | 0.5h | 1.6.1 | |
| 1.6.12 | `getDcvEmails()`, `resendDcvEmail()`, `changeDcvMethod()` | 📋 | High | 1.5h | 1.6.1 | |
| 1.6.13 | `validateOrder()` — Call `/validate` | 📋 | High | 0.5h | 1.6.1 | |

### 1.7 Settings Controller (4h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 1.7.1 | `SettingsController.php` — Load/save settings from `mod_aio_ssl_settings` | 📋 | High | 2h | DEP:1.4.1 | |
| 1.7.2 | `settings.tpl` — Sync config, notification toggles, currency settings, admin email | 📋 | High | 2h | 1.7.1 | |

### Phase 1 Checklist

- [ ] `_activate()` creates all 5 tables without errors
- [ ] Provider CRUD works (add NicSRS, test connection, save)
- [ ] NicSRS `fetchProducts()` returns normalized product list
- [ ] Settings save/load correctly
- [ ] Admin navigation renders all tabs
- [ ] `_deactivate()` preserves data (no-op)

---

## Phase 2 — Provider Plugins & Product Engine (100h)

**Goal:** All 4 providers fully integrated, product catalog sync working, price comparison operational.  
**Duration:** 3–4 weeks  
**Milestone:** Admin can sync products from all providers, view cross-provider price comparison.

### 2.1 GoGetSSL Provider Plugin (24h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.1.1 | `GoGetSSLProvider.php` — Constructor, session-based auth (`/auth/` endpoint → token caching) | 📋 | Critical | 2h | DEP:1.3.3 | Token expires, needs refresh logic |
| 2.1.2 | `testConnection()` — Auth + `/account/balance/` | 📋 | Critical | 1h | 2.1.1 | |
| 2.1.3 | `fetchProducts()` — `/products/ssl/` → normalize (numeric IDs to product objects) | 📋 | Critical | 3h | 2.1.1 | Products use numeric IDs |
| 2.1.4 | `fetchPricing()` — `/products/price/{id}` per product | 📋 | Critical | 2h | 2.1.3 | |
| 2.1.5 | `placeOrder()` — `/orders/add_ssl_order/` with brand-specific `webserver_type` (18 for GeoTrust/RapidSSL/DigiCert/Thawte, -1 for others) | 📋 | Critical | 3h | 2.1.1 | Complex brand logic |
| 2.1.6 | `getOrderStatus()` — `/orders/status/{id}` | 📋 | Critical | 1.5h | 2.1.1 | |
| 2.1.7 | `downloadCertificate()` — `/orders/ssl/download/{id}` | 📋 | Critical | 1.5h | 2.1.1 | |
| 2.1.8 | `reissueCertificate()` — `/orders/ssl/reissue/{id}` | 📋 | Critical | 2h | 2.1.1 | |
| 2.1.9 | `renewCertificate()` — `/orders/add_ssl_renew_order/` | 📋 | Critical | 2h | 2.1.1 | |
| 2.1.10 | `revokeCertificate()` — `/orders/ssl/revoke/{id}` | 📋 | High | 1h | 2.1.1 | |
| 2.1.11 | `cancelOrder()` — `/orders/cancel_ssl_order/{id}` | 📋 | High | 1h | 2.1.1 | |
| 2.1.12 | DCV methods: `getDcvEmails()`, `resendDcvEmail()`, `changeDcvMethod()` | 📋 | High | 2h | 2.1.1 | |
| 2.1.13 | `getBalance()` — `/account/balance/` | 📋 | Medium | 1h | 2.1.1 | |
| 2.1.14 | `csrDecode()` — `/tools/csr/decode/` | 📋 | Medium | 1h | 2.1.1 | |

### 2.2 TheSSLStore Provider Plugin (24h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.2.1 | `TheSSLStoreProvider.php` — Constructor, JSON body auth (`AuthRequest` object), sandbox URL support | 📋 | Critical | 2h | DEP:1.3.3 | Content-Type: application/json |
| 2.2.2 | `testConnection()` — `/health/status` or `/product/query` with minimal params | 📋 | Critical | 1h | 2.2.1 | |
| 2.2.3 | `fetchProducts()` — `/product/query` → normalize `ProductResponse[]` | 📋 | Critical | 3h | 2.2.1 | Returns `ProductCode`, `ProductName`, `ProductType`, `PricingInfo` |
| 2.2.4 | `fetchPricing()` — Extract from product query `PricingInfo.ProductPricing[]` | 📋 | Critical | 2h | 2.2.3 | `NumberOfMonths`, `Price`, `PricePerAdditionalSAN` |
| 2.2.5 | `placeOrder()` — `/order/neworder` with full `order_neworder_request` structure | 📋 | Critical | 4h | 2.2.1 | Complex: OrganizationInfo, AdminContact, TechnicalContact, DNSNames, SignatureHashAlgorithm, CertTransparencyIndicator |
| 2.2.6 | `getOrderStatus()` — `/order/status` with `TheSSLStoreOrderID` or `CustomOrderID` | 📋 | Critical | 2h | 2.2.1 | |
| 2.2.7 | `downloadCertificate()` — `/order/download` or `/order/downloadaszip` | 📋 | Critical | 2h | 2.2.1 | Support both formats |
| 2.2.8 | `reissueCertificate()` — `/order/reissue` | 📋 | Critical | 2h | 2.2.1 | |
| 2.2.9 | `revokeCertificate()` — `/order/certificaterevokerequest` | 📋 | High | 1.5h | 2.2.1 | |
| 2.2.10 | `refundOrder()` — `/order/refundrequest` + `/order/refundstatus` | 📋 | High | 1.5h | 2.2.1 | |
| 2.2.11 | DCV: `getDcvEmails()` → `/order/approverlist`, `resendDcvEmail()` → `/order/resend`, `changeDcvMethod()` → `/order/changeapproveremail` | 📋 | High | 2h | 2.2.1 | |
| 2.2.12 | `inviteOrder()` — `/order/inviteorder` (email-based provisioning) | 📋 | Medium | 1h | 2.2.1 | Optional feature |

### 2.3 SSL2Buy Provider Plugin — Limited Tier (20h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.3.1 | `SSL2BuyProvider.php` — Constructor, JSON auth (`PartnerEmail` + `ApiKey`), test mode toggle | 📋 | Critical | 2h | DEP:1.3.3 | |
| 2.3.2 | `testConnection()` — Call `GetBalance` endpoint | 📋 | Critical | 1h | 2.3.1 | |
| 2.3.3 | `fetchProducts()` — Use static product list from `SSL2BuyProducts::$products` + `GetProductPrice` for live pricing | 📋 | Critical | 3h | 2.3.1 | Products are hardcoded in module, prices fetched via API |
| 2.3.4 | `fetchPricing()` — `/orderservice/order/getproductprice` per product | 📋 | Critical | 2h | 2.3.1 | |
| 2.3.5 | `placeOrder()` — `/orderservice/order/placeorder` | 📋 | Critical | 3h | 2.3.1 | |
| 2.3.6 | `validateOrder()` — `/orderservice/order/validateorder` | 📋 | Critical | 1.5h | 2.3.1 | |
| 2.3.7 | `getOrderStatus()` — Brand-routing: determine CA brand → call `/queryservice/{brand}/getorderdetails` | 📋 | Critical | 3h | 2.3.1 | Comodo, GlobalSign, Symantec, PrimeSSL routes. See PDR §4.2 |
| 2.3.8 | `getConfigurationLink()` — `/orderservice/order/getsslconfigurationlink` | 📋 | Critical | 1.5h | 2.3.1 | Primary management method for limited tier |
| 2.3.9 | `resendApprovalEmail()` — `/queryservice/{brand}/resendapprovalemail` | 📋 | High | 1h | 2.3.7 | Brand-specific routing |
| 2.3.10 | `getBalance()` — `/orderservice/order/getbalance` | 📋 | Medium | 1h | 2.3.1 | |
| 2.3.11 | Implement `UnsupportedOperationException` for: `reissue`, `renew`, `revoke`, `cancel`, `download`, `getDcvEmails`, `changeDcvMethod` | 📋 | Critical | 1h | 2.3.1 | Throw with helpful message directing to provider portal |

### 2.4 Product Catalog Sync (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.4.1 | `SyncService.php` — Orchestrator: loop enabled providers, call `fetchProducts()`, upsert to `mod_aio_ssl_products` | 📋 | Critical | 4h | DEP:1.6.3, 2.1.3, 2.2.3, 2.3.3 | |
| 2.4.2 | Price change detection: compare old vs new `price_data`, track changes | 📋 | High | 2h | 2.4.1 | |
| 2.4.3 | Sync scheduling: configurable intervals per provider (status sync vs product sync) | 📋 | High | 2h | 2.4.1 | |
| 2.4.4 | Sync error tracking: `sync_error_count` per provider, alert at ≥3 | 📋 | High | 1.5h | 2.4.1 | |
| 2.4.5 | Manual sync trigger from admin UI (per provider or all) | 📋 | High | 1.5h | 2.4.1 | |
| 2.4.6 | `ProductController.php` — Product list with filters (provider, vendor, validation type, search) | 📋 | High | 1h | DEP:1.4.1 | |

### 2.5 Product Mapping Service (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.5.1 | `ProductMapService.php` — Auto-mapping algorithm: exact code → name normalization → fuzzy match | 📋 | Critical | 4h | DEP:2.4.1, 1.2.3 | |
| 2.5.2 | Name normalization: strip "Certificate", "SSL", trim, lowercase, handle abbreviations (DV/OV/EV/SAN/UCC) | 📋 | Critical | 2h | 2.5.1 | |
| 2.5.3 | Admin mapping UI: table of canonical products, dropdowns per provider column, unmapped alerts | 📋 | Critical | 3h | 2.5.1 | |
| 2.5.4 | Bulk operations: auto-create canonical entries from unmatched provider products, bulk assign | 📋 | High | 2h | 2.5.3 | |
| 2.5.5 | `products.tpl` + `product_mapping.tpl` templates | 📋 | High | 1h | 2.5.3 | |

### 2.6 Price Comparison Engine (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 2.6.1 | `PriceCompareService.php` — Given canonical_id, fetch prices from all providers, determine best price per period | 📋 | Critical | 3h | DEP:2.5.1 | |
| 2.6.2 | `PriceCompareController.php` — Search by WHMCS product or canonical product, AJAX-powered | 📋 | Critical | 2h | 2.6.1 | |
| 2.6.3 | `price_compare.tpl` — Comparison table with best-price highlighting, margin calculation | 📋 | High | 2h | 2.6.2 | See PDR §13.3 mockup |
| 2.6.4 | CSV export: all products with cross-provider pricing | 📋 | Medium | 1h | 2.6.1 | |

### Phase 2 Checklist

- [ ] All 4 providers can `testConnection()` successfully
- [ ] Product sync fetches and stores products from all 4 providers
- [ ] Auto-mapping resolves ≥80% of products to canonical entries
- [ ] Price comparison shows correct pricing for mapped products
- [ ] SSL2Buy limited-tier correctly throws `UnsupportedOperationException` for unsupported methods
- [ ] GoGetSSL auth token refresh works correctly
- [ ] TheSSLStore sandbox mode functional

---

## Phase 3 — Server Module & Client Area (80h)

**Goal:** Full certificate lifecycle from client area across all providers.  
**Duration:** 2–3 weeks  
**Milestone:** Client can order, configure, download, reissue certificates via unified interface.

### 3.1 Server Module Core (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.1.1 | `aio_ssl_MetaData()` — Module metadata (DisplayName, APIVersion, SSO labels) | 📋 | Critical | 0.5h | DEP:1.1.5 | |
| 3.1.2 | `aio_ssl_ConfigOptions()` — Dropdown: canonical products from `mod_aio_ssl_product_map`; Provider selector (auto/specific); API token override | 📋 | Critical | 3h | DEP:2.5.1 | |
| 3.1.3 | `ProviderBridge.php` — Resolve provider from service: check configdata → tblproducts configoption2 → auto-select cheapest | 📋 | Critical | 4h | DEP:1.3.5, 2.6.1 | |
| 3.1.4 | `aio_ssl_CreateAccount()` — Vendor migration check → resolve provider → create `tblsslorders` record | 📋 | Critical | 4h | DEP:3.1.3, 1.6.5 | |
| 3.1.5 | `aio_ssl_SuspendAccount()`, `aio_ssl_TerminateAccount()` — Update order status | 📋 | High | 1.5h | 3.1.4 | |
| 3.1.6 | `aio_ssl_AdminServicesTabFields()` — Order info display + vendor migration warning | 📋 | High | 2h | 3.1.4 | |
| 3.1.7 | `aio_ssl_AdminCustomButtonArray()` — Manage Order, Refresh Status, Resend DCV, Allow New Certificate | 📋 | High | 1h | 3.1.6 | |

### 3.2 Dispatchers & Routing (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.2.1 | `ActionDispatcher.php` — AJAX routing: validate access, map step→action, call ActionController method, return JSON | 📋 | Critical | 3h | DEP:3.1.1 | |
| 3.2.2 | `PageDispatcher.php` — Page routing: validate ownership, determine template by order status, render | 📋 | Critical | 3h | 3.2.1 | |
| 3.2.3 | Step-to-action mapping with legacy aliases (support old module step names) | 📋 | High | 1h | 3.2.1 | 25+ aliases |
| 3.2.4 | `aio_ssl_ClientArea()` — Main routing: AJAX vs page, dispatch accordingly | 📋 | Critical | 1h | DEP:3.2.1, 3.2.2 | |

### 3.3 Client Area — Certificate Application (20h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.3.1 | `ActionController::submitApply()` — Orchestrate full application flow | 📋 | Critical | 4h | DEP:3.2.1, 3.1.3 | |
| 3.3.2 | Step 1 — CSR: paste or auto-generate CSR + private key; decode CSR to extract domains | 📋 | Critical | 4h | 3.3.1 | OpenSSL integration |
| 3.3.3 | Step 2 — DCV: fetch DCV email options from provider; select method per domain (EMAIL/HTTP/CNAME/HTTPS) | 📋 | Critical | 3h | 3.3.2 | |
| 3.3.4 | Step 3 — Contacts (OV/EV): admin contact, tech contact, org info; pre-fill from client profile | 📋 | Critical | 3h | 3.3.3 | Skip for DV |
| 3.3.5 | Step 4 — Confirm & Submit: validate all data → `provider->validateOrder()` → `provider->placeOrder()` → update tblsslorders | 📋 | Critical | 3h | 3.3.4 | |
| 3.3.6 | Draft save/resume: save partial application; resume from last step | 📋 | High | 2h | 3.3.1 | Store in configdata |
| 3.3.7 | `apply.tpl` — Multi-step UI template with progress indicator, AJAX form submission, validation | 📋 | High | 1h | 3.3.1 | |

### 3.4 Client Area — Certificate Actions (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.4.1 | `ActionController::refreshStatus()` — Call `provider->getOrderStatus()`, update tblsslorders | 📋 | Critical | 2h | DEP:3.1.3 | |
| 3.4.2 | `ActionController::downloadCertificate()` — Call `provider->downloadCertificate()`, serve as file/display | 📋 | Critical | 3h | 3.1.3 | ZIP option, individual file option |
| 3.4.3 | `ActionController::submitReissue()` — New CSR + DCV → `provider->reissueCertificate()` | 📋 | Critical | 3h | 3.1.3 | |
| 3.4.4 | `ActionController::renew()` — `provider->renewCertificate()` | 📋 | Critical | 2h | 3.1.3 | |
| 3.4.5 | `ActionController::revoke()` — Confirmation → `provider->revokeCertificate()` | 📋 | High | 2h | 3.1.3 | |
| 3.4.6 | `ActionController::cancelOrder()` — Confirmation → `provider->cancelOrder()` | 📋 | High | 1.5h | 3.1.3 | |
| 3.4.7 | `ActionController::resendDCVEmail()` — `provider->resendDcvEmail()` | 📋 | High | 1h | 3.1.3 | |
| 3.4.8 | Capability-aware UI: hide buttons for unsupported provider actions (e.g., no Revoke for SSL2Buy) | 📋 | High | 1.5h | 3.4.1 | Check `provider->getCapabilities()` |

### 3.5 SSL2Buy Limited-Tier Client Area (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.5.1 | Detect SSL2Buy orders → render `limited_provider.tpl` instead of standard actions | 📋 | Critical | 2h | DEP:3.2.2, 2.3.8 | |
| 3.5.2 | "Manage at Provider" button: call `provider->getConfigurationLink()` → redirect or display link | 📋 | Critical | 2h | 2.3.8 | |
| 3.5.3 | PIN display: show configuration PIN from configdata if available | 📋 | High | 1h | 3.5.1 | |
| 3.5.4 | Status display: parse brand-specific order details (Comodo, GlobalSign, Symantec formats) | 📋 | High | 2h | 2.3.7 | Different response structures per CA |
| 3.5.5 | `limited_provider.tpl` template: informational view with provider link + PIN | 📋 | High | 1h | 3.5.1 | |

### 3.6 Client Area Templates (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 3.6.1 | `apply.tpl` — Multi-step with tabs/wizard UI, AJAX, validation feedback | 📋 | Critical | 3h | DEP:3.3.7 | |
| 3.6.2 | `pending.tpl` — DCV status per domain, refresh button, resend DCV | 📋 | Critical | 2h | | |
| 3.6.3 | `complete.tpl` — Certificate details, download options (PEM/ZIP/individual), reissue/renew/revoke buttons | 📋 | Critical | 2h | | |
| 3.6.4 | `reissue.tpl` — New CSR form, DCV selection (reuse complete.tpl patterns) | 📋 | High | 1.5h | | |
| 3.6.5 | `migrated.tpl` — Read-only legacy cert display: vendor badge, cert details, domains, expiry | 📋 | High | 2h | | Vendor-aware formatting |
| 3.6.6 | `limited_provider.tpl` — SSL2Buy management: status + config link + PIN | 📋 | High | 1.5h | | |

### Phase 3 Checklist

- [ ] New order (CreateAccount) works with all 4 providers
- [ ] Multi-step application (CSR → DCV → Contacts → Submit) functional
- [ ] Certificate download works (NicSRS, GoGetSSL, TheSSLStore)
- [ ] SSL2Buy shows config link instead of download
- [ ] Reissue/Renew/Revoke functional for Full-tier providers
- [ ] Provider selector (auto/specific) routes correctly
- [ ] Admin service tab shows correct order info
- [ ] Legacy orders display in migrated template

---

## Phase 4 — Dashboard, Reports, Migration & Polish (90h)

**Goal:** Production-ready with unified dashboard, migration tools, reporting, notifications.  
**Duration:** 3–4 weeks  
**Milestone:** Full production deployment ready.

### 4.1 Unified Dashboard (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.1.1 | `DashboardController.php` — Aggregate stats across all providers from `tblsslorders` | 📋 | High | 3h | DEP:1.4.1 | |
| 4.1.2 | Statistics cards: Total Orders (per provider), Pending, Issued, Expiring Soon | 📋 | High | 2h | 4.1.1 | |
| 4.1.3 | Chart.js: Orders by Provider (stacked bar), Status Distribution (doughnut), Monthly Trends (line, per provider) | 📋 | High | 3h | 4.1.1 | |
| 4.1.4 | API Health widget: test each provider, show status indicator | 📋 | Medium | 1.5h | 4.1.1 | |
| 4.1.5 | Provider balance display (GoGetSSL, SSL2Buy) | 📋 | Medium | 1h | 4.1.1 | |
| 4.1.6 | `dashboard.tpl` template | 📋 | High | 1.5h | 4.1.1 | |

### 4.2 Order Management (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.2.1 | `OrderController.php` — List: query `tblsslorders` for ALL module types, with filters (provider, status, client, domain, date) | 📋 | Critical | 4h | DEP:1.4.1 | See PDR §10.3 |
| 4.2.2 | Provider badge renderer: color-coded provider labels | 📋 | High | 1h | 4.2.1 | NicSRS=blue, GoGetSSL=green, TheSSLStore=orange, SSL2Buy=purple |
| 4.2.3 | Order detail page: full cert data, provider-specific metadata, DCV status, activity log | 📋 | Critical | 4h | 4.2.1 | |
| 4.2.4 | Admin order actions: Refresh Status, Resend DCV, Revoke, Cancel (provider-capability-aware) | 📋 | Critical | 3h | 4.2.3 | |
| 4.2.5 | Order search: domain, remote ID, client name, cert type | 📋 | High | 2h | 4.2.1 | |
| 4.2.6 | `orders.tpl` + `order_detail.tpl` templates | 📋 | High | 2h | 4.2.1 | |

### 4.3 Auto-Sync Engine (12h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.3.1 | Certificate status sync: loop pending/processing orders per provider, call `getOrderStatus()`, update `tblsslorders` | 📋 | Critical | 4h | DEP:2.4.1 | |
| 4.3.2 | Product catalog sync via cron: scheduled `fetchProducts()` per provider | 📋 | Critical | 2h | 2.4.1 | |
| 4.3.3 | Expiry check: scan active certs, detect expiring within N days, trigger warnings | 📋 | High | 2h | 4.3.1 | |
| 4.3.4 | WHMCS hooks integration: `DailyCronJob` + `AfterCronJob` → `SyncService::runScheduledSync()` | 📋 | Critical | 2h | 4.3.1 | |
| 4.3.5 | File-based lock to prevent concurrent sync runs | 📋 | High | 1h | 4.3.4 | |
| 4.3.6 | Sync status display in Settings (last sync, next sync, error count per provider) | 📋 | High | 1h | 4.3.1 | |

### 4.4 Notification Service (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.4.1 | `NotificationService.php` — Base: send via WHMCS `SendAdminEmail` Local API | 📋 | High | 2h | DEP:4.3.1 | |
| 4.4.2 | Certificate issuance notification (HTML email with cert details) | 📋 | High | 1.5h | 4.4.1 | |
| 4.4.3 | Expiry warning notification (urgency levels: 🚨 ≤7d, ⚠️ ≤30d) | 📋 | High | 1.5h | 4.4.1 | |
| 4.4.4 | Sync error notification (sent when error_count ≥ 3) | 📋 | High | 1h | 4.4.1 | |
| 4.4.5 | Price change notification (comparison table in email) | 📋 | Medium | 1h | 4.4.1 | |
| 4.4.6 | `AdminAreaHeaderOutput` hook: warning banner for sync errors | 📋 | Medium | 1h | 4.4.1 | |

### 4.5 Legacy Module Migration (16h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.5.1 | `MigrationService.php` — Core: detect legacy orders, normalize configdata | 📋 | Critical | 4h | DEP:1.2.1 | See PDR §11 |
| 4.5.2 | NicSRS migration: `nicsrs_ssl` → `aio_ssl` configdata normalization | 📋 | Critical | 2h | 4.5.1 | Map `nicsrs_sslorders` data patterns |
| 4.5.3 | GoGetSSL migration: `SSLCENTERWHMCS` → `aio_ssl` configdata normalization (handle JSON + serialized) | 📋 | Critical | 3h | 4.5.1 | Dual format: json_decode + unserialize |
| 4.5.4 | TheSSLStore migration: `thesslstore_ssl` → `aio_ssl` configdata normalization | 📋 | Critical | 2h | 4.5.1 | |
| 4.5.5 | SSL2Buy migration: `ssl2buy` → `aio_ssl` configdata normalization (brand-specific structures) | 📋 | Critical | 2h | 4.5.1 | Comodo/GlobalSign/Symantec/PrimeSSL formats differ |
| 4.5.6 | "Claim Order" function: admin clicks → update `tblsslorders.module` to `aio_ssl`, enrich configdata | 📋 | Critical | 1.5h | 4.5.1 | |
| 4.5.7 | Bulk claim: select multiple legacy orders → batch claim | 📋 | High | 1.5h | 4.5.6 | |

### 4.6 Import Controller (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.6.1 | `ImportController.php` — Single cert import: enter provider + remote ID → fetch data → create `tblsslorders` | 📋 | High | 3h | DEP:3.1.3 | |
| 4.6.2 | Link certificate to existing WHMCS service | 📋 | High | 2h | 4.6.1 | Validate servertype = aio_ssl |
| 4.6.3 | Bulk import: CSV upload (provider, remote_id, service_id) → batch process | 📋 | Medium | 2h | 4.6.1 | |
| 4.6.4 | `import.tpl` template | 📋 | High | 1h | 4.6.1 | |

### 4.7 Report Service (10h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.7.1 | `ReportService.php` — Revenue by Provider report | 📋 | Medium | 3h | DEP:4.2.1 | Cross-reference tblhosting pricing |
| 4.7.2 | Product Performance report: orders per product, per provider | 📋 | Medium | 2h | 4.7.1 | |
| 4.7.3 | Expiry Forecast: certificates expiring in 30/60/90 days, grouped by provider | 📋 | Medium | 2h | 4.7.1 | |
| 4.7.4 | CSV export for all reports | 📋 | Medium | 1.5h | 4.7.1 | |
| 4.7.5 | `reports.tpl` template with Chart.js visualizations | 📋 | Medium | 1.5h | 4.7.1 | |

### 4.8 Localization & Polish (8h)

| # | Task | Status | Priority | Est. | Dep. | Notes |
|---|------|--------|----------|------|------|-------|
| 4.8.1 | Complete `lang/english.php` — All ~200 translation keys | 📋 | Medium | 2h | — | |
| 4.8.2 | Complete `lang/vietnamese.php` — Full Vietnamese translation | 📋 | Medium | 3h | 4.8.1 | |
| 4.8.3 | Client area language files: English, Vietnamese, Chinese (Traditional + Simplified) | 📋 | Medium | 2h | 4.8.1 | Port from existing NicSRS module |
| 4.8.4 | UI polish: consistent styling, responsive tables, loading spinners, error handling | 📋 | Medium | 1h | — | |

### Phase 4 Checklist

- [ ] Dashboard shows aggregated data across all 4 providers
- [ ] Orders from all legacy modules appear in unified order list
- [ ] Auto-sync runs on cron without errors
- [ ] Migration: claim legacy orders → module updated to `aio_ssl`
- [ ] Import: single + bulk import functional
- [ ] Notifications: issuance, expiry, sync errors, price changes
- [ ] Reports: revenue by provider, product performance, expiry forecast
- [ ] Vietnamese translation complete

---

## Testing Matrix

### Provider Integration Tests

| Test | NicSRS | GoGetSSL | TheSSLStore | SSL2Buy |
|------|--------|----------|-------------|---------|
| Connection test | 📋 | 📋 | 📋 | 📋 |
| Product sync | 📋 | 📋 | 📋 | 📋 |
| Place DV order | 📋 | 📋 | 📋 | 📋 |
| Place OV order | 📋 | 📋 | 📋 | 📋 |
| Place EV order | 📋 | 📋 | 📋 | 📋 |
| Status refresh | 📋 | 📋 | 📋 | 📋 |
| Download cert | 📋 | 📋 | 📋 | N/A |
| Reissue | 📋 | 📋 | 📋 | N/A |
| Renew | 📋 | 📋 | 📋 | N/A |
| Revoke | 📋 | 📋 | 📋 | N/A |
| Cancel | 📋 | 📋 | 📋 | N/A |
| DCV management | 📋 | 📋 | 📋 | Partial |
| Config link | N/A | N/A | N/A | 📋 |

### Migration Tests

| Test | Status |
|------|--------|
| Read `nicsrs_ssl` legacy orders | 📋 |
| Read `SSLCENTERWHMCS` legacy orders | 📋 |
| Read `thesslstore_ssl` legacy orders | 📋 |
| Read `ssl2buy` legacy orders | 📋 |
| Claim single legacy order | 📋 |
| Bulk claim legacy orders | 📋 |
| Legacy configdata normalization (JSON) | 📋 |
| Legacy configdata normalization (serialized) | 📋 |
| Client view of legacy order (migrated.tpl) | 📋 |

### WHMCS Compatibility

| WHMCS Version | PHP Version | Status |
|---------------|-------------|--------|
| 7.10 | 7.4 | 📋 |
| 8.0 | 7.4 | 📋 |
| 8.5 | 8.0 | 📋 |
| 8.8+ | 8.1 | 📋 |

---

## Deployment Checklist

### Pre-Deployment

- [ ] All Phase 1–4 tasks marked ✅
- [ ] All provider integration tests pass
- [ ] Migration tests pass with real legacy data
- [ ] WHMCS 8.x compatibility verified
- [ ] PHP 7.4+ and 8.0+ tested
- [ ] Security review: encryption, input validation, access control
- [ ] Performance: pagination works with 10K+ orders
- [ ] Language files complete (EN + VI)

### Deployment Steps

1. [ ] Backup `tblsslorders` table
2. [ ] Upload `modules/addons/aio_ssl_admin/`
3. [ ] Upload `modules/servers/aio_ssl/`
4. [ ] Activate addon in WHMCS Admin → Setup → Addon Modules
5. [ ] Configure providers (add credentials for each)
6. [ ] Test connection for each provider
7. [ ] Run initial product sync
8. [ ] Verify product mapping coverage
9. [ ] Create first WHMCS product with `servertype=aio_ssl`
10. [ ] Test order lifecycle (place → configure → validate → issue)
11. [ ] Verify legacy orders visible in order management
12. [ ] Enable auto-sync (configure cron settings)
13. [ ] Gradually migrate WHMCS products from legacy modules to AIO

### Post-Deployment

- [ ] Monitor sync logs for 48h
- [ ] Verify notifications work (test expiry warning)
- [ ] Confirm client area renders correctly for existing services
- [ ] Deactivate legacy modules (after all orders claimed)

---

**© HVN GROUP** — All rights reserved.