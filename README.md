# NicSRS SSL Management System for WHMCS

> **Version:** Admin Addon v1.3.1 | Server Module v2.1.0  
> **Author:** [HVN GROUP](https://hvn.vn)  
> **License:** Proprietary  
> **Last Updated:** 2026-02-09

## Overview

A comprehensive, production-grade SSL certificate management solution for WHMCS consisting of two tightly integrated modules:

| Module | Location | Role |
|---|---|---|
| **Admin Addon Module** | `modules/addons/nicsrs_ssl_admin/` | Centralized back-office management — dashboard, product catalog sync, order management, reporting, auto-sync engine, settings, certificate import |
| **Server Provision Module** | `modules/servers/nicsrs_ssl/` | Customer-facing certificate lifecycle — apply, validate, issue, download, reissue, renew, revoke; vendor migration support |

Both modules share database tables and communicate with the **NicSRS REST API** at `https://portal.nicsrs.com/ssl` for all certificate authority operations.

## Key Features

**Certificate Management**
- Full lifecycle: Order → Configure → Validate → Issue → Download → Reissue → Renew → Revoke
- Multi-step client area with CSR auto-generation, draft save/resume, DCV method selection
- Multi-vendor: Sectigo, DigiCert, GlobalSign, GeoTrust, Entrust, sslTrus, BaiduTrust, RapidSSL, Thawte
- DV, OV, EV certificates; Wildcard and Multi-domain (SAN) support
- Vendor migration flow — detects certificates from other WHMCS SSL modules, admin override to allow new NicSRS order

**Admin Features**
- Real-time dashboard with Chart.js visualizations (status distribution, monthly trends)
- Product catalog cached locally with automatic pricing sync
- Comprehensive order management with detail view, status refresh, DCV management
- Reporting: Profit (USD/VND), Product Performance, Revenue by Brand — all with CSV export
- Certificate import: single, bulk, and link-to-existing-service
- Activity audit log with export capability

**Automation & Integration**
- Auto-sync engine via WHMCS cron: certificate status sync + product catalog sync
- Configurable intervals (status: 1–24h, products: 1–168h, batch size: 10–200)
- HTML email notifications: certificate issuance, expiry warnings, sync errors, price change alerts
- Centralized API token with priority-based fallback chain
- Vietnamese Dong (VND) currency support with configurable USD/VND rate

**Multilingual**
- Admin interface: English, Vietnamese
- Client area: English, Chinese (Traditional), Chinese (Simplified)

---

## System Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| WHMCS | 7.10+ | 8.x |
| PHP | 7.4+ | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ | 8.0+ / 10.6+ |
| PHP Extensions | cURL, JSON, OpenSSL, mbstring | + intl |
| NicSRS Account | Active reseller at [portal.nicsrs.com](https://portal.nicsrs.com) | — |
| Server | cURL outbound HTTPS allowed | — |

---

## Installation

### Step 1: Upload Files

Upload the following directories to your WHMCS installation root:

```
/your-whmcs/modules/addons/nicsrs_ssl_admin/   ← Admin Addon
/your-whmcs/modules/servers/nicsrs_ssl/         ← Server Module
```

Ensure file permissions are readable by the web server (typically `644` for files, `755` for directories).

### Step 2: Activate Admin Addon

1. Log in to WHMCS Admin → **Setup → Addon Modules**
2. Find **"HVN - NicSRS SSL Admin"** → click **Activate**
3. Configure module settings:
   - **NicSRS API Token**: Your API token from [portal.nicsrs.com](https://portal.nicsrs.com)
   - **Items Per Page**: Display preference (10/25/50/100)
4. Click **Save Changes**

Upon activation, the module automatically creates these database tables:
- `mod_nicsrs_products` — Product catalog cache
- `mod_nicsrs_activity_log` — Admin activity audit log
- `mod_nicsrs_settings` — Module configuration key-value store

### Step 3: Sync Product Catalog

1. Navigate to **Addons → NicSRS SSL Admin → Products**
2. Click **"Sync All Products"**
3. Wait for sync to complete — products from all supported vendors will be cached locally

### Step 4: Create WHMCS Products

1. Go to **Setup → Products/Services → Products/Services**
2. Create a new product or edit an existing one
3. On the **Module Settings** tab:
   - **Module Name**: Select `nicsrs_ssl`
   - **Certificate Type**: Choose from the synced product list
   - **API Token (Override)**: Leave empty to use the shared token from Admin Addon; only set if this product needs a different token
4. Save and configure pricing as needed

### Step 5: Configure Cron (Auto-Sync)

The module hooks into WHMCS's built-in cron (`DailyCronJob` and `AfterCronJob` hooks). If your WHMCS cron runs every 5–15 minutes, auto-sync will work automatically.

Optionally, for standalone cron execution:
```bash
# Add to crontab (every 15 minutes)
*/15 * * * * php /path/to/whmcs/modules/addons/nicsrs_ssl_admin/cron.php
```

### Step 6: Configure Settings

Navigate to **Addons → NicSRS SSL Admin → Settings** to configure:

- **Notification Settings**: Email on issuance, expiry warnings, admin email override
- **Auto-Sync Settings**: Enable/disable, intervals, batch size
- **Display Settings**: Date format
- **Currency Settings**: USD/VND exchange rate, display mode (USD/VND/both)

---

## Directory Structure

```
modules/
├── addons/nicsrs_ssl_admin/              # ──── ADMIN ADDON MODULE ────
│   ├── nicsrs_ssl_admin.php              # Entry point: config(), activate(), output(), upgrade()
│   ├── hooks.php                         # WHMCS hooks: DailyCronJob, AfterCronJob, AdminAreaHeader
│   ├── cron.php                          # Standalone cron endpoint
│   ├── lang/
│   │   ├── english.php                   # English translations (~120 keys)
│   │   └── vietnamese.php                # Vietnamese translations
│   ├── lib/
│   │   ├── Controller/
│   │   │   ├── BaseController.php        # Abstract base: template, JSON, settings, pagination
│   │   │   ├── DashboardController.php   # Dashboard stats + charts
│   │   │   ├── ProductController.php     # Product catalog CRUD + sync
│   │   │   ├── OrderController.php       # Order list + detail + actions
│   │   │   ├── SettingsController.php    # Settings CRUD + manual sync + exchange rate
│   │   │   ├── ActivityController.php    # Activity log viewer
│   │   │   ├── ImportController.php      # Certificate import/link
│   │   │   └── ReportController.php      # Reports + CSV export
│   │   ├── Service/
│   │   │   ├── SyncService.php           # Auto-sync engine (status + products)
│   │   │   ├── NicsrsApiService.php      # API client wrapper (instance-based)
│   │   │   ├── NotificationService.php   # HTML email notifications via WHMCS Local API
│   │   │   ├── ReportService.php         # Report data aggregation
│   │   │   └── ActivityLogger.php        # Activity log writer
│   │   └── Helper/
│   │       ├── ViewHelper.php            # UI formatting: badges, dates, prices, truncate
│   │       └── CurrencyHelper.php        # USD/VND conversion + rate management
│   ├── templates/                        # PHP templates (Bootstrap 3 + WHMCS admin CSS)
│   │   ├── dashboard.php
│   │   ├── products.php
│   │   ├── orders.php
│   │   ├── order_detail.php
│   │   ├── settings.php
│   │   ├── import.php
│   │   └── reports.php
│   └── assets/
│       ├── css/nicsrs-admin.css
│       └── js/nicsrs-admin.js
│
└── servers/nicsrs_ssl/                   # ──── SERVER PROVISION MODULE ────
    ├── nicsrs_ssl.php                    # Entry point: all nicsrs_ssl_*() WHMCS functions
    ├── hooks.php                         # Client-side hooks: status refresh, expiry check
    ├── lang/
    │   ├── english.php
    │   ├── chinese.php                   # Traditional Chinese
    │   └── chinese-cn.php               # Simplified Chinese
    ├── src/
    │   ├── config/
    │   │   ├── const.php                 # Status constants, API URL, version
    │   │   └── country.json              # Country list for forms (250+ countries)
    │   └── model/
    │       ├── Controller/
    │       │   ├── PageController.php    # Page rendering: index, manage, reissue
    │       │   └── ActionController.php  # AJAX actions: submit, refresh, download, DCV
    │       ├── Dispatcher/
    │       │   ├── PageDispatcher.php    # Status-based page routing
    │       │   └── ActionDispatcher.php  # Action routing with alias support
    │       └── Service/
    │           ├── ApiService.php        # API client (static, with token fallback)
    │           ├── OrderRepository.php   # Database CRUD for nicsrs_sslorders
    │           ├── CertificateFunc.php   # Cert utilities: name↔code mapping, product data
    │           ├── ResponseFormatter.php # Standardized JSON response formatting
    │           ├── TemplateHelper.php    # Template rendering by order status
    │           ├── DcvHelper.php         # DCV method utilities
    │           ├── nicsrsAPI.php         # Legacy API client (static, with caching)
    │           ├── nicsrsSSLSql.php      # Legacy SQL operations
    │           └── nicsrsTemplate.php    # Legacy template rendering
    ├── view/                             # Smarty .tpl templates (client area)
    │   ├── applycert.tpl                 # Multi-step certificate application form
    │   ├── complete.tpl                  # Issued certificate: download, reissue, manage
    │   ├── message.tpl                   # Pending status: DCV info, refresh
    │   ├── reissue.tpl                   # Reissue/replace certificate form
    │   ├── replace.tpl                   # Legacy replace template
    │   ├── migrated.tpl                  # Vendor migration notice
    │   └── error.tpl                     # Error display
    └── assets/
        ├── css/ssl-manager.css           # Modern client area CSS (Ant Design inspired)
        └── js/ssl-manager.js             # Client area JavaScript
```

---

## Supported Certificate Authorities & Products

| Vendor | DV | OV | EV | Wildcard | Multi-Domain | Code Signing |
|---|---|---|---|---|---|---|
| **Sectigo (Comodo)** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **sslTrus** | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| **DigiCert** | — | ✅ | ✅ | ✅ | ✅ | ✅ |
| **GlobalSign** | ✅ | ✅ | ✅ | ✅ | — | — |
| **GeoTrust** | ✅ | — | — | — | — | — |
| **Entrust** | — | ✅ | ✅ | ✅ | — | — |
| **BaiduTrust** | ✅ | ✅ | ✅ | — | — | — |
| **Thawte** | ✅ | — | — | — | — | — |
| **RapidSSL** | ✅ | — | — | — | — | — |
| **PositiveSSL** | ✅ | ✅ | ✅ | ✅ | ✅ | — |

---

## API Token Configuration

The system uses a **priority-based token resolution chain**:

| Priority | Source | Scope |
|---|---|---|
| 1 | Product-level `configoption2` | Per-product override |
| 2 | Product config via `serviceid → tblhosting → tblproducts` | Per-product |
| 3 | Admin Addon `tbladdonmodules` (`api_token` setting) | Global shared |
| 4 | `mod_nicsrs_settings` table (`api_token` key) | Global fallback |

Most installations only need to set the token once in the Admin Addon configuration (Priority 3). Product-level overrides are only needed for multi-account scenarios.

---

## Troubleshooting

### Common Issues

| Issue | Possible Cause | Resolution |
|---|---|---|
| "API token not configured" | No token at any priority level | Set token in Addons → NicSRS SSL Admin → Settings |
| Certificate stuck in Pending | DCV not completed | Check DCV method; verify DNS/file/email validation |
| Products not showing in dropdown | Product catalog not synced | Run "Sync All Products" from Products page |
| Auto-sync not running | WHMCS cron not configured | Verify WHMCS cron runs every 5–15 minutes |
| Email notifications not sent | Using deprecated `mail()` | Module uses WHMCS `SendAdminEmail` Local API |
| Vendor migration blocked | Active cert from other provider | Admin clicks "Allow New Certificate" button |

### Debug Logging

1. Go to **Utilities → Logs → Module Log**
2. Enable logging for `nicsrs_ssl` and `nicsrs_ssl_admin`
3. All API requests/responses are logged automatically
4. Sensitive data (API tokens) are masked in logs

### Sync Error Alerts

When auto-sync encounters 3+ consecutive errors:
- Warning banner appears in Admin Addon pages
- Email alert sent to configured admin email
- Check **Settings → Sync Status** for details

---

## Changelog

### Admin Addon Module

| Version | Changes |
|---|---|
| **1.3.1** | Currency settings migration in upgrade(); error count tracking |
| **1.3.0** | Report module with Chart.js; CSV export; currency helper |
| **1.2.1** | Auto-sync improvements; expiry checking for active certs |
| **1.2.0** | Auto-sync engine; SyncService; NotificationService; HTML emails |
| **1.1.0** | Import/Link controller; Order detail improvements |
| **1.0.0** | Initial release: dashboard, products, orders, settings |

### Server Provision Module

| Version | Changes |
|---|---|
| **2.1.0** | Modern multi-step UI; CSR auto-generation; draft save/resume |
| **2.0.1** | Improved POST data handling; AJAX routing fixes |
| **2.0.0** | Complete rewrite: Dispatcher pattern; ApiService; OrderRepository |
| **1.1** | sslTrus support; multi-domain improvements; IP SSL support |
| **1.0** | Initial release: basic certificate lifecycle |

---

## Support

| Channel | Contact |
|---|---|
| **Portal** | [portal.nicsrs.com](https://portal.nicsrs.com) |
| **Documentation** | [docs.nicsrs.com](https://docs.nicsrs.com) |
| **Email** | support@hvn.vn |
| **Website** | [hvn.vn](https://hvn.vn) |

---

**© HVN GROUP** — All rights reserved. Unauthorized distribution or modification is prohibited