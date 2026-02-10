# Implementation Plan & Roadmap

> **Project:** NicSRS SSL Management System for WHMCS  
> **Last Updated:** 2026-02-09  
> **Status Legend:** ✅ Done | 🔄 In Progress | 📋 Planned | ❌ Blocked

---

## Phase 1 — Core Foundation (v1.0.0) ✅ COMPLETE

### Admin Addon Module — Initial Release

| # | Task | Status | Notes |
|---|---|---|---|
| 1.1 | Module entry point (`config`, `activate`, `deactivate`, `output`) | ✅ | `nicsrs_ssl_admin.php` |
| 1.2 | Database schema: `mod_nicsrs_products`, `mod_nicsrs_activity_log`, `mod_nicsrs_settings` | ✅ | Auto-created on activate |
| 1.3 | BaseController with template, JSON, settings, pagination | ✅ | Abstract class, all controllers inherit |
| 1.4 | DashboardController — stats cards, recent orders, expiring certs | ✅ | Chart.js doughnut + bar charts |
| 1.5 | ProductController — list, search, filter by vendor/type | ✅ | Linked filter for WHMCS products |
| 1.6 | OrderController — list with search/filter, detail view | ✅ | JOIN with tblclients, tblhosting |
| 1.7 | SettingsController — notification, display, API config forms | ✅ | AJAX save via `handleAjax()` |
| 1.8 | ActivityController — paginated audit log viewer | ✅ | Export CSV |
| 1.9 | ViewHelper — badges, dates, prices, truncation | ✅ | |
| 1.10 | Navigation tabs + footer rendering | ✅ | 6-tab layout |
| 1.11 | English language file (~120 translation keys) | ✅ | `lang/english.php` |
| 1.12 | Admin CSS (`assets/css/admin.css`) | ✅ | Bootstrap 3 compatible |

### Server Provision Module — v2.0.0 Rewrite

| # | Task | Status | Notes |
|---|---|---|---|
| 1.13 | Dispatcher pattern: `PageDispatcher` + `ActionDispatcher` | ✅ | With alias support for backward compat |
| 1.14 | `PageController` — status-based routing | ✅ | `normalizeStatus()` handles 15+ variants |
| 1.15 | `ActionController` — all AJAX handlers | ✅ | submitApply, saveDraft, refreshStatus, etc. |
| 1.16 | `ApiService` — modern static API client with 4-level token fallback | ✅ | |
| 1.17 | `OrderRepository` — CRUD with `ensureTableExists()` | ✅ | Capsule ORM |
| 1.18 | `CertificateFunc` — name↔code mapping, dynamic DB retrieval | ✅ | Replaces hardcoded values |
| 1.19 | `TemplateHelper` — template rendering by status | ✅ | 7 template types |
| 1.20 | `ResponseFormatter` — standardized JSON responses | ✅ | |
| 1.21 | Legacy compatibility layer (`nicsrsAPI`, `nicsrsSSLSql`) | ✅ | Maintained for old data |
| 1.22 | WHMCS module functions: `CreateAccount`, `ConfigOptions`, `AdminServicesTabFields`, `ClientArea` | ✅ | |
| 1.23 | Step-to-action mapping with old module aliases | ✅ | 25+ aliases in `nicsrs_ssl.php` |

---

## Phase 2 — Enhanced Features (v1.1.0 – v1.2.0) ✅ COMPLETE

### Import & Link

| # | Task | Status | Notes |
|---|---|---|---|
| 2.1 | ImportController — single cert lookup + import | ✅ | API `/collect` to fetch data |
| 2.2 | Link certificate to existing WHMCS service | ✅ | Validates servertype = nicsrs_ssl |
| 2.3 | Bulk import (multiple cert IDs) | ✅ | Error reporting per cert |
| 2.4 | Recently imported list display | ✅ | Last 20 orders |

### Auto-Sync Engine

| # | Task | Status | Notes |
|---|---|---|---|
| 2.5 | `SyncService` — scheduled sync orchestrator | ✅ | Cron-based via WHMCS hooks |
| 2.6 | Certificate status sync (pending → complete) | ✅ | Configurable batch size 10–200 |
| 2.7 | Product catalog sync from all vendors | ✅ | 10 vendors, 500ms delay |
| 2.8 | Price change detection + notification | ✅ | HTML email with comparison table |
| 2.9 | Error tracking with `sync_error_count` | ✅ | Alert at ≥3 consecutive errors |
| 2.10 | WHMCS hooks: `DailyCronJob`, `AfterCronJob` | ✅ | `hooks.php` |
| 2.11 | Manual sync trigger from Settings UI | ✅ | Status + Products + Check Expiring |
| 2.12 | Sync status display (last sync, next sync, pending count) | ✅ | AJAX-loaded in Settings |
| 2.13 | `AdminAreaHeaderOutput` hook — sync error warning banner | ✅ | Shows when error_count ≥ 3 |

### Notifications

| # | Task | Status | Notes |
|---|---|---|---|
| 2.14 | `NotificationService` — certificate issuance email | ✅ | HTML template |
| 2.15 | Expiry warning email (configurable days threshold) | ✅ | Urgency levels: 🚨 ≤7d, ⚠️ other |
| 2.16 | Sync error alert email | ✅ | Settings link in email |
| 2.17 | Switch from `mail()` to WHMCS `SendAdminEmail` Local API | ✅ | **Critical fix** |
| 2.18 | `checkAndSendExpiryWarnings()` — full scan for cron | ✅ | |

### Vendor Migration

| # | Task | Status | Notes |
|---|---|---|---|
| 2.19 | `hasActiveVendorCert()` detection in `CreateAccount` | ✅ | Checks `tblsslorders` |
| 2.20 | `buildVendorMigrationWarning()` in admin tab | ✅ | Shows provider, cert ID, expiry |
| 2.21 | `AdminAllowNewCert` button + handler | ✅ | Migration flags in configdata |
| 2.22 | `migrated.tpl` — read-only vendor cert display | ✅ | Client area |
| 2.23 | `originalfromOthers` / `isRenew` flags to API | ✅ | Sent on `/place` |

---

## Phase 3 — Reporting & Currency (v1.3.0 – v1.3.1) ✅ COMPLETE

| # | Task | Status | Notes |
|---|---|---|---|
| 3.1 | `ReportService` — profit, performance, brand analytics | ✅ | |
| 3.2 | `ReportController` — 3 report types with Chart.js | ✅ | Bar, line, pie, doughnut |
| 3.3 | CSV export for all report types | ✅ | Base64-encoded download |
| 3.4 | `CurrencyHelper` — USD/VND conversion | ✅ | Configurable rate |
| 3.5 | VND display in reports | ✅ | `both` / `usd` / `vnd` mode |
| 3.6 | Exchange rate auto-update from external API | ✅ | Via Settings AJAX |
| 3.7 | Currency settings migration in `upgrade()` | ✅ | v1.2.x → v1.3.x |
| 3.8 | Vietnamese language file | ✅ | `lang/vietnamese.php` |

---

## Phase 4 — Client Area Modernization (v2.1.0) ✅ COMPLETE

| # | Task | Status | Notes |
|---|---|---|---|
| 4.1 | Multi-step `applycert.tpl` with progress indicator | ✅ | 4 steps + visual progress bar |
| 4.2 | CSR auto-generation (JavaScript + OpenSSL) | ✅ | Client-side key generation option |
| 4.3 | Draft save/resume functionality | ✅ | `saveDraft` action + `isDraft` flag |
| 4.4 | DCV method selection with email optgroup | ✅ | Dynamic email loading |
| 4.5 | Modern CSS — Ant Design inspired (`ssl-manager.css`) | ✅ | Responsive, themed |
| 4.6 | `reissue.tpl` — same multi-step structure as apply | ✅ | Pre-filled with existing data |
| 4.7 | `complete.tpl` — download (PEM/PKCS12/JKS), reissue, manage | ✅ | Password modal for PKCS/JKS |
| 4.8 | `message.tpl` — DCV status, file/DNS/email info | ✅ | Auto-refresh capability |
| 4.9 | Smarty template variables via `TemplateHelper::getBaseVars()` | ✅ | WEB_ROOT, countries, support options |

---

## Phase 5 — Current / Planned 🔄

### In Progress

| # | Task | Status | Priority | Est. Hours |
|---|---|---|---|---|
| 5.1 | Consolidate CSS files (admin.css cleanup) | 🔄 | Medium | 4h |
| 5.2 | Remove vendor-specific branding from public templates | 🔄 | Medium | 2h |
| 5.3 | JavaScript file rename/consolidation | 🔄 | Low | 2h |
| 5.4 | Technical documentation completion (10 docs) | 🔄 | High | 20h |

### Planned — Short Term (v1.4.0)

| # | Task | Status | Priority | Est. Hours |
|---|---|---|---|---|
| 5.5 | Admin order edit: modify configdata fields | 📋 | Medium | 8h |
| 5.6 | Admin order delete: with confirmation + logging | 📋 | Medium | 4h |
| 5.7 | Product-to-WHMCS linking helper (auto-create products) | 📋 | Medium | 12h |
| 5.8 | Batch operations: bulk refresh, bulk cancel | 📋 | Low | 8h |
| 5.9 | Dashboard: API health check widget | 📋 | Low | 4h |
| 5.10 | Settings: API token test button with detailed response | 📋 | Low | 2h |

### Planned — Medium Term (v1.5.0)

| # | Task | Status | Priority | Est. Hours |
|---|---|---|---|---|
| 5.11 | Client area: multi-language Vietnamese translation | 📋 | Medium | 8h |
| 5.12 | Auto-renewal integration with WHMCS `ServiceRenewal` hook | 📋 | High | 16h |
| 5.13 | Webhook support for real-time certificate status updates | 📋 | Medium | 12h |
| 5.14 | Certificate download history tracking | 📋 | Low | 6h |
| 5.15 | Advanced reporting: time-series trends, custom date ranges | 📋 | Medium | 12h |

### Planned — Long Term (v2.0.0)

| # | Task | Status | Priority | Est. Hours |
|---|---|---|---|---|
| 5.16 | REST API for external integrations | 📋 | Low | 24h |
| 5.17 | Multi-server support (multiple NicSRS accounts) | 📋 | Low | 16h |
| 5.18 | WHMCS 9.x compatibility testing | 📋 | High | 8h |
| 5.19 | PHP 8.2+ strict types migration | 📋 | Medium | 12h |
| 5.20 | Unit test suite (PHPUnit) | 📋 | Medium | 24h |

---

## Backward Compatibility Checklist

| Area | Status | Notes |
|---|---|---|
| Old `nicsrsAPI` class still functional | ✅ | Parallel with new `ApiService` |
| `nicsrsSSLSql` legacy SQL operations | ✅ | Kept for old data access patterns |
| Old step names (`applyssl`, `cancleOrder`, `downcert`) | ✅ | Mapped in `$stepToAction` |
| `configoption2` token fallback | ✅ | Priority 1 in `ApiService` |
| Existing `nicsrs_sslorders` data format | ✅ | No schema breaking changes |
| `tblsslorders` vendor cert detection | ✅ | Read-only access |