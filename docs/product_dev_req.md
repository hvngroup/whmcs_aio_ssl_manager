# HVN - AIO SSL Manager
## Product Development Requirements (PDR)

> **Version:** 1.1.0  
> **Author:** HVN GROUP (https://hvn.vn)  
> **License:** Proprietary  
> **Created:** 2026-02-11 | **Revised:** 2026-02-23  
> **Module Type:** WHMCS Admin Addon + Server Provisioning Module

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Project Scope & Objectives](#2-project-scope--objectives)
3. [Architecture Overview](#3-architecture-overview)
4. [Provider API Capability Matrix](#4-provider-api-capability-matrix)
5. [Product Name Mapping & Price Comparison](#5-product-name-mapping--price-comparison)
6. [Database Design](#6-database-design)
7. [Module Structure](#7-module-structure)
8. [Feature Specifications](#8-feature-specifications)
9. [Provider Integration Details](#9-provider-integration-details)
10. [Data Flow Diagrams](#10-data-flow-diagrams)
11. [Backward Compatibility & Migration](#11-backward-compatibility--migration)
12. [Security Architecture](#12-security-architecture)
13. [UI/UX Design Specifications](#13-uiux-design-specifications)
14. [WHMCS Template Engine Rules](#14-whmcs-template-engine-rules)
15. [Implementation Plan](#15-implementation-plan)
16. [Risk Assessment](#16-risk-assessment)

---

## 1. Executive Summary

### 1.1 Problem Statement

HVN currently manages SSL certificates through **four separate WHMCS modules**, each with its own interface, database patterns, and management workflows:

| Module | Provider | Server Module Name | Order Storage Table | Module value in orders |
|--------|----------|-------------------|---------------------|----------------------|
| NicSRS SSL | NicSRS | `nicsrs_ssl` | `nicsrs_sslorders` (custom) | `nicsrs_ssl` |
| GoGetSSL (SSLCENTER) | GoGetSSL | `SSLCENTERWHMCS` | `tblsslorders` (native) | `SSLCENTERWHMCS` |
| TheSSLStore | TheSSLStore | `thesslstore_ssl` | `tblsslorders` (native) | `thesslstore_ssl` / `thesslstore` |
| SSL2Buy | SSL2Buy | `ssl2buy` | `tblsslorders` (native) | `ssl2buy` |

**Critical observation**: NicSRS uses a **custom `nicsrs_sslorders` table**, while the other three use WHMCS's native `tblsslorders`. The AIO module must handle both tables for backward compatibility.

This fragmentation creates operational overhead: no unified dashboard, no cross-provider price comparison, inconsistent client experiences, and duplicated maintenance effort.

### 1.2 Solution

Build a **single, unified AIO (All-In-One) SSL Manager** module for WHMCS that:
- Centralizes all SSL operations across NicSRS, GoGetSSL, TheSSLStore, and SSL2Buy
- Provides cross-provider price comparison with intelligent product name mapping
- Uses a plugin-based architecture for easy addition of future providers
- Maintains full backward compatibility with existing orders from all four legacy modules
- Reads from BOTH `tblsslorders` AND `nicsrs_sslorders` for unified order visibility

### 1.3 Key Architecture Decision: Dual-Table Read, Single-Table Write

For **new AIO orders**, the module writes to its own custom table `mod_aio_ssl_orders` to avoid conflicts with legacy modules still running. For **backward compatibility**, the module reads from:

| Table | Read | Write | Purpose |
|-------|------|-------|---------|
| `mod_aio_ssl_orders` | ✅ | ✅ | New AIO orders |
| `nicsrs_sslorders` | ✅ | ❌ | Legacy NicSRS orders (read-only) |
| `tblsslorders` | ✅ | ❌ | Legacy GoGetSSL/TheSSLStore/SSL2Buy orders (read-only) |

When admin "claims" a legacy order, a **new record** is created in `mod_aio_ssl_orders` with migration metadata linking to the original record. The original record in the legacy table is **not modified**.

---

## 2. Project Scope & Objectives

### 2.1 Core Objectives

| # | Objective | Priority |
|---|-----------|----------|
| O1 | Unified admin dashboard across all providers | Critical |
| O2 | Cross-provider price comparison engine | Critical |
| O3 | Plugin-based provider architecture (extensible) | Critical |
| O4 | Full certificate lifecycle automation (order, validate, issue, download, reissue, renew, revoke) | Critical |
| O5 | Backward compatibility with 4 existing modules | Critical |
| O6 | Provider CRUD (add, edit, disable, delete) | High |
| O7 | Unified client area experience | High |
| O8 | Auto-sync engine (status + products) | High |
| O9 | Reporting & analytics across providers | Medium |
| O10 | Multi-language support (EN, VI) | Medium |

### 2.2 Out of Scope (v1.0)

- Let's Encrypt / ACME automation
- cPanel AutoSSL integration
- REST API for external consumers
- Sub-reseller network support
- Automated certificate installation on servers

### 2.3 Success Criteria

1. All existing orders from 4 legacy modules viewable through AIO interface
2. New orders can be placed with any enabled provider
3. Admin can compare prices for the same certificate type across providers
4. Adding a new provider requires only creating a new provider plugin class (no core changes)
5. Zero downtime — legacy modules can run alongside AIO during transition

---

## 3. Architecture Overview

### 3.1 Two-Module Design

```
┌──────────────────────────────────────────────────────────────────┐
│                    WHMCS Installation                            │
│                                                                  │
│  ┌────────────────────────────┐  ┌────────────────────────────┐ │
│  │  Admin Addon Module        │  │  Server Provisioning Module │ │
│  │  aio_ssl_admin             │  │  aio_ssl                   │ │
│  │  Templates: PHP (.php)     │  │  Templates: Smarty (.tpl)  │ │
│  │                            │  │                            │ │
│  │  • Dashboard               │  │  • CreateAccount           │ │
│  │  • Provider Management     │  │  • ClientArea              │ │
│  │  • Product Catalog         │  │  • AdminServicesTab        │ │
│  │  • Price Comparison        │  │  • Certificate Lifecycle   │ │
│  │  • Order Management        │  │  • Multi-step Apply        │ │
│  │  • Import & Migration      │  │  • Download / Reissue      │ │
│  │  • Reporting               │  │  • Status Refresh          │ │
│  │  • Settings & Sync         │  │  • Vendor Migration        │ │
│  └──────────┬─────────────────┘  └──────────┬─────────────────┘ │
│             │                                │                   │
│             └────────────┬───────────────────┘                   │
│                          │                                       │
│              ┌───────────▼────────────┐                          │
│              │  Shared Library Layer   │                          │
│              │  (Provider plugins,     │                          │
│              │   Services, Helpers)    │                          │
│              ├────────────────────────┤                          │
│              │ NicSRS    │ GoGetSSL   │                          │
│              │ TheSSLSt  │ SSL2Buy    │                          │
│              │ [Future]  │ [Future]   │                          │
│              └───────────┬────────────┘                          │
│                          │                                       │
│              ┌───────────▼────────────┐                          │
│              │  Database Layer         │                          │
│              │  mod_aio_ssl_orders     │ ← New orders            │
│              │  nicsrs_sslorders       │ ← Legacy read-only      │
│              │  tblsslorders           │ ← Legacy read-only      │
│              │  mod_aio_ssl_*          │ ← Config/catalog        │
│              └────────────────────────┘                          │
└──────────────────────────────────────────────────────────────────┘
```

### 3.2 Plugin-Based Provider Architecture

Every SSL provider implements `ProviderInterface`:

```php
namespace AioSSL\Provider;

interface ProviderInterface
{
    // ── Identity ──
    public function getSlug(): string;         // e.g. 'nicsrs'
    public function getName(): string;         // e.g. 'NicSRS'
    public function getApiBaseUrl(): string;
    public function getTier(): string;         // 'full' | 'limited'

    // ── Connection ──
    public function testConnection(): array;   // ['success' => bool, 'message' => str]

    // ── Product Catalog ──
    public function fetchProducts(): array;    // Normalized product list
    public function fetchPricing(string $productCode, int $years = 1): array;

    // ── Certificate Lifecycle (Tier: Full) ──
    public function placeOrder(array $params): array;
    public function getOrderStatus(string $remoteId): array;
    public function downloadCertificate(string $remoteId): array;
    public function reissueCertificate(string $remoteId, array $params): array;
    public function renewCertificate(string $remoteId, array $params): array;
    public function revokeCertificate(string $remoteId, string $reason): array;
    public function cancelOrder(string $remoteId): array;

    // ── DCV Management ──
    public function getDcvEmails(string $domain): array;
    public function resendDcvEmail(string $remoteId, string $email): array;
    public function changeDcvMethod(string $remoteId, array $params): array;

    // ── Validation ──
    public function validateOrder(array $params): array;

    // ── Capability Declaration ──
    public function getCapabilities(): array;  // ['cancel', 'revoke', 'reissue', ...]
}
```

### 3.3 Two-Tier Provider System

Based on API capability analysis, providers fall into two tiers:

| Tier | Description | Providers | Capabilities |
|------|-------------|-----------|--------------|
| **Full** | Complete API lifecycle management | NicSRS, GoGetSSL, TheSSLStore | Order, validate, issue, download, reissue, renew, revoke, cancel, DCV management |
| **Limited** | Order + query only, manual management for rest | SSL2Buy | Order, query status, resend approval email. No cancel/revoke/reissue/renew via API |

**Limited-tier handling**: For SSL2Buy, the module provides:
- Configuration Link (SSL2Buy's `GetSSLConfigurationLink` API) for manual certificate management
- PIN-based management system through SSL2Buy's portal
- Admin UI shows "Manage at Provider" button with direct link
- Status sync via vendor-specific `GetOrderDetails` endpoints (branched by CA: Comodo, GlobalSign, Symantec/DigiCert)

---

## 4. Provider API Capability Matrix

### 4.1 Authentication Methods (CRITICAL DETAIL)

| Provider | Auth Method | Details |
|----------|-----------|---------|
| **NicSRS** | Static API Token | Token sent as `api_token` POST form parameter. Token obtained from portal.nicsrs.com. No session/expiry. |
| **GoGetSSL** | Session-based Token | **Step 1**: POST `/auth/` with `user` + `pass` → returns `{"key":"xxx"}`. **Step 2**: Pass `auth_key=xxx` in all subsequent requests. Token expires after inactivity. Must cache & refresh. |
| **TheSSLStore** | Partner Code + Auth Token | Both sent in JSON body as `AuthRequest` object: `{"PartnerCode":"xxx","AuthToken":"xxx"}`. Every request includes this. |
| **SSL2Buy** | Partner Email + API Key | Both sent in JSON body: `{"PartnerEmail":"xxx","ApiKey":"xxx"}`. Every request includes this. |

### 4.2 Full Capability Comparison

| Capability | NicSRS | GoGetSSL | TheSSLStore | SSL2Buy |
|------------|--------|----------|-------------|---------|
| **API Protocol** | REST POST, form-urlencoded | REST GET/POST, form-urlencoded | REST POST, **JSON** | REST POST, **JSON** |
| **API Base URL** | `portal.nicsrs.com/ssl` | `my.gogetssl.com/api` | `api.thesslstore.com/rest` | `api.ssl2buy.com` |
| **Sandbox URL** | ❌ None | ✅ `sandbox.gogetssl.com/api` | ✅ `sandbox-wbapi.thesslstore.com/rest` | ✅ (test mode flag) |
| **Product ID Type** | String code (`positivessl`) | **Numeric ID** (`71`) | String code (`positivessl`) | **Numeric code** (`351`) |
| **Get Products** | ✅ `/productList` | ✅ `/products/` or `/products/ssl/` | ✅ `/product/query` | ✅ `GetProductPrice` (per product) |
| **Get All Prices** | ✅ in productList response | ✅ `/products/all_prices/` | ✅ in product/query | ❌ No bulk pricing |
| **Validate Order** | ✅ `/validate` | ✅ (via order params) | ✅ `/order/validate` | ✅ `ValidateOrder` |
| **Place Order** | ✅ `/place` | ✅ `/orders/add_ssl_order` | ✅ `/order/neworder` | ✅ `PlaceOrder` |
| **Order Status** | ✅ `/collect` | ✅ `/orders/status/{id}` | ✅ `/order/status` | ✅ `GetOrderDetails` (per CA brand) |
| **Download Cert** | ✅ `/collect` (cert in response) | ✅ `/orders/ssl/download/{id}` | ✅ `/order/download` + `/order/downloadaszip` | ❌ (via config link) |
| **Reissue** | ✅ `/reissue` | ✅ `/orders/ssl/reissue/{id}` | ✅ `/order/reissue` | ❌ |
| **Renew** | ✅ `/renew` | ✅ `/orders/add_ssl_renew_order` | ⚠️ `/order/neworder` with `isRenewalOrder=true` | ❌ |
| **Revoke** | ✅ `/revoke` | ✅ `/orders/ssl/revoke/{id}` | ✅ `/order/certificaterevokerequest` | ❌ |
| **Cancel/Refund** | ✅ `/cancel` | ✅ `/orders/cancel_ssl_order/{id}` | ✅ `/order/refundrequest` | ❌ |
| **DCV Emails** | ✅ `/DCVemail` | ✅ `/tools/domain/emails/` | ✅ `/order/approverlist` | ❌ |
| **Resend DCV** | ✅ `/DCVemail` (with certId) | ✅ `/orders/ssl/resend_validation_email/{id}` | ✅ `/order/resend` | ⚠️ `ResendApprovalMail` (per CA brand) |
| **Change DCV** | ✅ `/updateDCV` | ✅ `/orders/ssl/change_dcv_method/{id}` | ✅ `/order/changeapproveremail` | ❌ |
| **Get Balance** | ❌ | ✅ `/account/balance/` | ❌ | ✅ `GetBalance` |
| **CSR Decode** | ✅ `/csrDecode` | ✅ `/tools/csr/decode/` | ❌ (client-side) | ❌ |
| **CAA Check** | ✅ `/caaCheck` | ❌ | ❌ | ❌ |
| **Config Link** | ❌ | ❌ | ❌ | ✅ `GetSSLConfigurationLink` |
| **Invite Order** | ❌ | ❌ | ✅ `/order/inviteorder` | ❌ |
| **Mid-Term Upgrade** | ❌ | ❌ | ✅ `/order/midtermupgrade` | ❌ |
| **Tier** | **Full** | **Full** | **Full** | **Limited** |

> **Note on TheSSLStore Renew**: TheSSLStore does NOT have a dedicated renew endpoint. Renewal is done by placing a new order via `/order/neworder` with `isRenewalOrder: true` and `RelatedTheSSLStoreOrderID` set to the previous order ID.

### 4.3 SSL2Buy Vendor-Specific Routing

SSL2Buy routes query API calls by Certificate Authority brand. The `brand_name` from `SSL2BuyProducts` determines the endpoint:

| CA Brand (in module) | brand_name | Query Route | Resend Approval Route |
|---------------------|------------|-------------|----------------------|
| Sectigo/Comodo | `Comodo` | `/queryservice/comodo/getorderdetails` | `/queryservice/comodo/resendapprovalemail` |
| GlobalSign/AlphaSSL | `GlobalSign` | `/queryservice/globalsign/getorderdetails` | `/queryservice/globalsign/resendapprovalemail` |
| DigiCert/Symantec/GeoTrust/Thawte/RapidSSL | `Symantec` | `/queryservice/symantec/getorderdetails` | `/queryservice/symantec/resendapprovalemail` |
| PrimeSSL | `Prime` | `/queryservice/prime/primesubscriptionorderdetail` | N/A |
| ACME (Sectigo) | `sectigo_acme` | `/queryservice/acme/GetAcmeOrderDetail` | N/A |

### 4.4 SSL2Buy ConfigOptions in Legacy Module

The legacy `ssl2buy` server module uses these WHMCS product config fields:

| Field | Usage |
|-------|-------|
| `configoption1` | Partner Email |
| `configoption2` | API Key |
| `configoption3` | Product Code (numeric, from `SSL2BuyProducts`) |
| `configoption4` | (unused) |
| `configoption5` | (unused) |
| `configoption6` | (unused) |
| `configoption7` | Test Mode (`on`/`off`) |

### 4.5 GoGetSSL ConfigOptions in Legacy Module

| Field | Usage |
|-------|-------|
| `configoption1` | API Product ID (numeric) |
| `configoption2` | Months (validity period) |
| `configoption3` | Enable SANs (`on`/off) |
| `configoption4` | Included SANs count |
| `configoption5` | Price Auto Download |
| `configoption6` | Commission % |
| `configoption7` | Month One-Time |
| `configoption8` | Included SANs Wildcard count |
| `configoption13` | Enable SAN Wildcard |

---

## 5. Product Name Mapping & Price Comparison

### 5.1 The Product Mapping Problem

Each provider uses different product names/codes for the **same certificate**. For price comparison, we need a canonical mapping:

**Example: "Sectigo PositiveSSL" (DV, Single Domain)**

| Provider | Product Code / ID | Product Name in Provider |
|----------|------------------|--------------------------|
| **WHMCS Product** | (configoption varies) | Sectigo PositiveSSL |
| **NicSRS** | `positivessl` (string) | PositiveSSL |
| **GoGetSSL** | `71` (numeric ID) | Sectigo PositiveSSL DV |
| **TheSSLStore** | `positivessl` (string) | Sectigo Positive SSL |
| **SSL2Buy** | `351` (numeric code) | Sectigo Positive SSL |

### 5.2 Canonical Product Mapping Table

A new database table `mod_aio_ssl_product_map` stores mappings between a canonical product identifier and each provider's product code. Key mappings:

| Canonical ID | Canonical Name | Type | Val. | NicSRS Code | GoGetSSL ID | TheSSLStore Code | SSL2Buy Code |
|-------------|---------------|------|------|-------------|-------------|-----------------|-------------|
| `sectigo-positivessl` | Sectigo PositiveSSL | SSL | DV | `positivessl` | `71` | `positivessl` | `351` |
| `sectigo-positivessl-wildcard` | Sectigo PositiveSSL Wildcard | WC | DV | `positivessl_wildcard` | `72` | `positivesslwildcard` | `352` |
| `sectigo-positivessl-multi` | Sectigo PositiveSSL Multi-Domain | MD | DV | `positivessl_multidomain` | `74` | `positivesslmultidomain` | `371` |
| `sectigo-essentialssl` | Sectigo EssentialSSL | SSL | DV | — | `65` | `essentialssl` | `362` |
| `sectigo-essentialssl-wildcard` | Sectigo EssentialSSL Wildcard | WC | DV | — | `66` | `essentialsslwildcard` | `363` |
| `sectigo-instantssl` | Sectigo InstantSSL | SSL | OV | — | `22` | `instantssl` | `354` |
| `sectigo-instantssl-pro` | Sectigo InstantSSL Pro | SSL | OV | — | `23` | `instantsslpro` | `355` |
| `sectigo-ov-ssl` | Sectigo OV SSL | SSL | OV | `sectigo_ov` | `198` | `sectigosslovi` | `384` |
| `sectigo-ov-wildcard` | Sectigo OV Wildcard | WC | OV | `sectigo_ov_wildcard` | `199` | `sectigosslwildcardov` | `385` |
| `sectigo-ev-ssl` | Sectigo EV SSL | SSL | EV | `sectigo_ev_ssl` | `21` | `sectigoevssl` | `360` |
| `sectigo-ev-multi` | Sectigo EV Multi-Domain | MD | EV | `sectigo_ev_multidomain` | `68` | `sectigoevmultidomain` | `370` |
| `sectigo-premium-ssl` | Sectigo Premium SSL | SSL | OV | — | — | `premiumssl` | `357` |
| `sectigo-premium-wildcard` | Sectigo Premium Wildcard | WC | OV | — | — | `premiumsslwildcard` | `358` |
| `geotrust-quickssl-premium` | GeoTrust QuickSSL Premium | SSL | DV | `geotrust_quickssl_premium` | `42` | `quicksslpremium` | `5` |
| `geotrust-truebiz-id` | GeoTrust True BusinessID | SSL | OV | `geotrust_truebusiness_id` | `45` | `truebusinessid` | `6` |
| `geotrust-truebiz-wildcard` | GeoTrust True BusinessID Wildcard | WC | OV | — | `46` | `truebusinessidwildcard` | `7` |
| `geotrust-truebiz-ev` | GeoTrust True BusinessID EV | SSL | EV | — | `47` | `truebusinessidev` | `8` |
| `rapidssl-standard` | RapidSSL Certificate | SSL | DV | — | `14` | `rapidssl` | `1` |
| `rapidssl-wildcard` | RapidSSL Wildcard | WC | DV | — | `15` | `rapidsslwildcard` | `2` |
| `thawte-ssl-webserver` | Thawte SSL Web Server | SSL | OV | — | `32` | `sslwebserver` | `11` |
| `thawte-ssl123` | Thawte SSL123 | SSL | DV | — | `30` | `ssl123` | `12` |
| `thawte-ev-ssl` | Thawte EV SSL | SSL | EV | — | `33` | `sslwebserverwithev` | `19` |
| `digicert-secure-site` | DigiCert Secure Site | SSL | OV | — | — | `securesite` | `13` |
| `digicert-secure-site-pro` | DigiCert Secure Site Pro | SSL | OV | — | — | `securesitepro` | `14` |
| `digicert-secure-site-ev` | DigiCert Secure Site EV | SSL | EV | — | — | `securesiteev` | `16` |
| `digicert-basic-ov` | DigiCert Basic OV | SSL | OV | — | — | `digicertov` | `528` |
| `digicert-basic-ev` | DigiCert Basic EV | SSL | EV | — | — | `digicertev` | `529` |
| `globalsign-domain-ssl` | GlobalSign DomainSSL | SSL | DV | — | `87` | — | `103` |
| `globalsign-org-ssl` | GlobalSign OrganizationSSL | SSL | OV | — | `88` | — | `105` |
| `globalsign-ev-ssl` | GlobalSign ExtendedSSL | SSL | EV | — | `89` | — | `109` |
| `alphassl-standard` | AlphaSSL Certificate | SSL | DV | — | `85` | — | `101` |
| `alphassl-wildcard` | AlphaSSL Wildcard | WC | DV | — | `86` | — | `102` |
| `sectigo-code-signing` | Sectigo Code Signing | CS | OV | `sectigo_code_signing` | `61` | `codesigning` | `364` |
| `sectigo-ev-code-signing` | Sectigo EV Code Signing | CS | EV | `sectigo_ev_code_signing` | `62` | `evcodesigning` | `386` |

> **Legend**: SSL = single domain, WC = wildcard, MD = multi-domain, CS = code signing. `—` means provider does not offer this product.

### 5.3 Auto-Mapping Strategy

The module implements a three-layer mapping resolution:

1. **Exact Code Match**: `mod_aio_ssl_product_map` direct lookup
2. **Name Similarity**: Normalized fuzzy matching (strip "Certificate", "SSL", whitespace, case)
3. **Admin Manual Map**: Admin UI to manually link unmapped products

### 5.4 Price Comparison Engine

```
Admin selects WHMCS Product "Sectigo PositiveSSL"
           │
           ▼
Lookup canonical_id from product_map → "sectigo-positivessl"
           │
           ▼
For each enabled provider:
  ├─ NicSRS:     code="positivessl"       → fetch price from mod_aio_ssl_products
  ├─ GoGetSSL:   id="71"                  → fetch price from mod_aio_ssl_products
  ├─ TheSSLStore: code="positivessl"      → fetch price from mod_aio_ssl_products
  └─ SSL2Buy:    code="351"               → fetch price from mod_aio_ssl_products
           │
           ▼
Display comparison table with "Best Price" highlighting
```

### 5.5 WHMCS Product Linking

Each WHMCS product (`tblproducts`) links to the AIO module via:

| Field | Usage |
|-------|-------|
| `servertype` | `aio_ssl` |
| `configoption1` | `canonical_id` (e.g., `sectigo-positivessl`) |
| `configoption2` | Preferred provider slug (e.g., `nicsrs`) — or `auto` for cheapest |
| `configoption3` | Provider-specific override token (optional) |
| `configoption4` | Fallback provider slug (if primary fails) |

When `configoption2` = `auto`, the module selects the cheapest enabled provider at order time.

---

## 6. Database Design

### 6.1 Tables Overview

| Table | Purpose | Owner | New? |
|-------|---------|-------|------|
| `mod_aio_ssl_orders` | New AIO orders | Server + Addon | ✅ New |
| `mod_aio_ssl_providers` | Provider configuration & credentials | Admin Addon | ✅ New |
| `mod_aio_ssl_products` | Cached product catalog from all providers | Admin Addon | ✅ New |
| `mod_aio_ssl_product_map` | Cross-provider product name mapping | Admin Addon | ✅ New |
| `mod_aio_ssl_settings` | Module configuration (key-value) | Admin Addon | ✅ New |
| `mod_aio_ssl_activity_log` | Audit trail | Admin Addon | ✅ New |
| `nicsrs_sslorders` | Legacy NicSRS orders | Read-only | Existing |
| `tblsslorders` | Legacy GoGetSSL/TheSSLStore/SSL2Buy orders | Read-only | Existing |

### 6.2 Schema: `mod_aio_ssl_orders`

```sql
CREATE TABLE `mod_aio_ssl_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL,
  `serviceid` int(10) unsigned NOT NULL,
  `provider_slug` varchar(50) NOT NULL,            -- 'nicsrs', 'gogetssl', etc.
  `remoteid` varchar(255) DEFAULT '',              -- Provider's order/cert ID
  `certtype` varchar(255) DEFAULT '',              -- Canonical product ID
  `provider_product_code` varchar(150) DEFAULT '', -- Provider-specific product code
  `status` varchar(50) NOT NULL DEFAULT 'Awaiting Configuration',
  `configdata` longtext,                           -- JSON blob (see 6.7)
  `provisiondate` date DEFAULT NULL,
  `completiondate` datetime DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Migration tracking
  `legacy_table` varchar(50) DEFAULT NULL,         -- 'nicsrs_sslorders' or 'tblsslorders'
  `legacy_order_id` int(10) DEFAULT NULL,          -- Original order ID in legacy table
  `legacy_module` varchar(100) DEFAULT NULL,       -- Original module name
  PRIMARY KEY (`id`),
  KEY `idx_userid` (`userid`),
  KEY `idx_serviceid` (`serviceid`),
  KEY `idx_provider` (`provider_slug`),
  KEY `idx_status` (`status`),
  KEY `idx_remoteid` (`remoteid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.3 Schema: `mod_aio_ssl_providers`

```sql
CREATE TABLE `mod_aio_ssl_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,                     -- 'nicsrs', 'gogetssl', 'thesslstore', 'ssl2buy'
  `name` varchar(100) NOT NULL,
  `tier` enum('full','limited') NOT NULL DEFAULT 'full',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `api_credentials` text,                          -- AES-256-CBC encrypted JSON
  `api_mode` enum('live','sandbox') NOT NULL DEFAULT 'live',
  `config` text,                                   -- JSON: extra provider-specific config
  `last_sync` datetime DEFAULT NULL,
  `last_test` datetime DEFAULT NULL,
  `test_result` tinyint(1) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`api_credentials` JSON structure** (encrypted at rest):

```json
// NicSRS
{ "api_token": "xxx" }

// GoGetSSL — NOTE: session token cached separately, not stored here
{ "username": "xxx", "password": "xxx" }

// TheSSLStore
{ "partner_code": "xxx", "auth_token": "xxx" }

// SSL2Buy
{ "partner_email": "xxx", "api_key": "xxx" }
```

### 6.4 Schema: `mod_aio_ssl_products`

```sql
CREATE TABLE `mod_aio_ssl_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `provider_slug` varchar(50) NOT NULL,
  `product_code` varchar(150) NOT NULL,            -- Provider-specific code (string or numeric)
  `product_name` varchar(255) NOT NULL,
  `vendor` varchar(50) NOT NULL,                   -- CA brand: Sectigo, DigiCert, etc.
  `validation_type` enum('dv','ov','ev') NOT NULL,
  `product_type` enum('ssl','wildcard','multi_domain','code_signing','email') NOT NULL DEFAULT 'ssl',
  `support_wildcard` tinyint(1) NOT NULL DEFAULT 0,
  `support_san` tinyint(1) NOT NULL DEFAULT 0,
  `max_domains` int NOT NULL DEFAULT 1,
  `max_years` int NOT NULL DEFAULT 1,
  `min_years` int NOT NULL DEFAULT 1,
  `price_data` text,                               -- JSON pricing (normalized format)
  `extra_data` text,                               -- JSON: provider-specific metadata
  `canonical_id` varchar(100) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_product` (`provider_slug`, `product_code`),
  KEY `idx_canonical` (`canonical_id`),
  KEY `idx_vendor` (`vendor`),
  KEY `idx_validation` (`validation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.5 Schema: `mod_aio_ssl_product_map`

```sql
CREATE TABLE `mod_aio_ssl_product_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `canonical_id` varchar(100) NOT NULL,
  `canonical_name` varchar(255) NOT NULL,
  `vendor` varchar(50) NOT NULL,
  `validation_type` enum('dv','ov','ev') NOT NULL,
  `product_type` enum('ssl','wildcard','multi_domain','code_signing','email') NOT NULL DEFAULT 'ssl',
  `nicsrs_code` varchar(150) DEFAULT NULL,
  `gogetssl_code` varchar(150) DEFAULT NULL,
  `thesslstore_code` varchar(150) DEFAULT NULL,
  `ssl2buy_code` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_canonical` (`canonical_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.6 Provider Config JSON Examples

```json
// NicSRS config
{
  "supported_vendors": ["Sectigo","DigiCert","GlobalSign","GeoTrust","Thawte","RapidSSL","sslTrus","Entrust","BaiduTrust"],
  "sync_vendors_separately": true,
  "api_timeout": 60
}

// GoGetSSL config — includes session token cache
{
  "auth_token_cache": null,
  "auth_token_expiry": null,
  "brand_webserver_override": { "geotrust": 18, "rapidssl": 18, "digicert": 18, "thawte": 18 },
  "default_webserver_type": -1
}

// TheSSLStore config
{
  "date_time_culture": "en-US",
  "signature_hash": "SHA2-256",
  "cert_transparency": true,
  "sandbox_partner_code": "",
  "sandbox_auth_token": ""
}

// SSL2Buy config
{
  "test_mode": false,
  "brand_routing": {
    "Comodo": "comodo", "Sectigo": "comodo",
    "GlobalSign": "globalsign", "AlphaSSL": "globalsign",
    "Symantec": "symantec", "DigiCert": "symantec",
    "GeoTrust": "symantec", "Thawte": "symantec", "RapidSSL": "symantec",
    "PrimeSSL": "prime"
  }
}
```

### 6.7 `configdata` JSON for AIO Orders

```json
{
  "provider": "nicsrs",
  "provider_product_code": "positivessl",
  "canonical_id": "sectigo-positivessl",
  "csr": "-----BEGIN CERTIFICATE REQUEST-----\n...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "crt": "-----BEGIN CERTIFICATE-----\n...",
  "ca": "-----BEGIN CERTIFICATE-----\n...",
  "domainInfo": [
    { "domainName": "example.com", "dcvMethod": "EMAIL", "dcvEmail": "admin@example.com", "dcvStatus": "validated" }
  ],
  "admin_contact": { "firstname": "", "lastname": "", "email": "", "phone": "" },
  "tech_contact": {},
  "org_info": {},
  "webserver_type": "Other",
  "beginDate": "2026-01-01",
  "endDate": "2027-01-01",
  "years": 1,
  "order_date": "2026-01-01 10:30:00",
  "migration": {
    "from_module": "nicsrs_ssl",
    "from_table": "nicsrs_sslorders",
    "original_remoteid": "12345",
    "original_order_id": 42,
    "migrated_at": "2026-02-01"
  }
}
```

---

## 7. Module Structure

### 7.1 File System Layout

```
modules/
├── addons/aio_ssl_admin/                        # ──── ADMIN ADDON ────
│   ├── aio_ssl_admin.php                        # Entry: config(), activate(), output(), upgrade()
│   ├── hooks.php                                # WHMCS hooks: DailyCronJob, AfterCronJob
│   ├── cron.php                                 # Standalone cron endpoint
│   ├── lang/
│   │   ├── english.php
│   │   └── vietnamese.php
│   ├── lib/
│   │   ├── Core/
│   │   │   ├── ProviderInterface.php
│   │   │   ├── AbstractProvider.php
│   │   │   ├── ProviderFactory.php
│   │   │   ├── ProviderRegistry.php
│   │   │   ├── EncryptionService.php
│   │   │   └── NormalizedProduct.php
│   │   ├── Provider/
│   │   │   ├── NicsrsProvider.php
│   │   │   ├── GoGetSSLProvider.php
│   │   │   ├── TheSSLStoreProvider.php
│   │   │   └── SSL2BuyProvider.php
│   │   ├── Controller/
│   │   │   ├── BaseController.php               # includeTemplate() for PHP templates
│   │   │   ├── DashboardController.php
│   │   │   ├── ProviderController.php
│   │   │   ├── ProductController.php
│   │   │   ├── PriceCompareController.php
│   │   │   ├── OrderController.php
│   │   │   ├── ImportController.php
│   │   │   ├── ReportController.php
│   │   │   └── SettingsController.php
│   │   ├── Service/
│   │   │   ├── SyncService.php
│   │   │   ├── ProductMapService.php
│   │   │   ├── PriceCompareService.php
│   │   │   ├── NotificationService.php
│   │   │   ├── MigrationService.php
│   │   │   ├── UnifiedOrderService.php          # Reads from all 3 tables
│   │   │   └── ReportService.php
│   │   └── Helper/
│   │       ├── ViewHelper.php
│   │       └── CurrencyHelper.php
│   ├── templates/                               # ⚠️ PHP templates (.php), NOT Smarty
│   │   ├── dashboard.php
│   │   ├── providers.php
│   │   ├── provider_edit.php
│   │   ├── products.php
│   │   ├── product_mapping.php
│   │   ├── price_compare.php
│   │   ├── orders.php
│   │   ├── order_detail.php
│   │   ├── import.php
│   │   ├── reports.php
│   │   └── settings.php
│   └── assets/
│       ├── css/admin.css                        # Ant Design-inspired CSS variables
│       └── js/admin.js
│
├── servers/aio_ssl/                             # ──── SERVER MODULE ────
│   ├── aio_ssl.php                              # Entry: ConfigOptions, CreateAccount, ClientArea
│   ├── src/
│   │   ├── Controller/
│   │   │   ├── ActionController.php
│   │   │   └── PageController.php
│   │   ├── Dispatcher/
│   │   │   ├── ActionDispatcher.php
│   │   │   └── PageDispatcher.php
│   │   ├── Service/
│   │   │   ├── OrderService.php                 # CRUD on mod_aio_ssl_orders
│   │   │   ├── CertificateService.php
│   │   │   └── ProviderBridge.php
│   │   └── compatibility.php                    # Legacy class aliases
│   ├── view/                                    # ⚠️ Smarty templates (.tpl) for CLIENT AREA
│   │   ├── applycert.tpl                        # Multi-step application
│   │   ├── pending.tpl                          # Pending/processing status
│   │   ├── complete.tpl                         # Issued cert: download, reissue
│   │   ├── reissue.tpl                          # Reissue form
│   │   ├── migrated.tpl                         # Legacy vendor cert view
│   │   ├── limited_provider.tpl                 # For SSL2Buy (config link)
│   │   ├── error.tpl
│   │   └── message.tpl
│   ├── lang/
│   │   ├── english.php
│   │   ├── vietnamese.php
│   │   └── chinese.php
│   └── assets/
│       ├── css/ssl-manager.css                  # Ant Design-inspired client CSS
│       └── js/ssl-manager.js
```

---

## 8. Feature Specifications

### 8.1 Admin Dashboard

**Unified statistics across all providers:**
- Total orders by provider (stacked bar chart — Chart.js)
- Order status distribution (doughnut chart)
- Monthly order trends (line chart, per provider)
- Revenue by provider (bar chart)
- Expiring certificates (sortable table, all providers)
- API health status for each provider (color-coded: 🟢/🔴)
- Provider balance (GoGetSSL and SSL2Buy only)

### 8.2 Provider Management (CRUD)

| Action | Description |
|--------|-------------|
| **List** | Table: name, tier, enabled, last sync, test result, balance |
| **Add** | Form: name, slug, tier, API credentials, sandbox toggle |
| **Edit** | Modify credentials, enable/disable, change config |
| **Test** | One-click API connection test (each provider's `testConnection()`) |
| **Disable** | Soft-disable: existing orders remain, no new orders routed |
| **Delete** | Hard-delete with confirmation (only if 0 active orders) |

### 8.3 Unified Order Management

**Order List** reads from **three sources** via `UnifiedOrderService`:

```php
class UnifiedOrderService
{
    public function getAllOrders(array $filters): array
    {
        $orders = [];

        // 1. AIO orders (new)
        $orders = array_merge($orders, $this->getAioOrders($filters));

        // 2. NicSRS legacy orders
        if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
            $orders = array_merge($orders, $this->getNicsrsLegacyOrders($filters));
        }

        // 3. GoGetSSL/TheSSLStore/SSL2Buy legacy orders
        if (Capsule::schema()->hasTable('tblsslorders')) {
            $orders = array_merge($orders, $this->getTblsslLegacyOrders($filters));
        }

        return $this->sortAndPaginate($orders, $filters);
    }
}
```

### 8.4 Provider-Aware Actions

Buttons dynamically show/hide based on `getCapabilities()`:
- Full tier: Reissue, Renew, Revoke, Cancel, Refresh, Resend DCV, Change DCV
- Limited tier (SSL2Buy): Refresh Status, Manage at Provider (external link), Resend Approval Email

### 8.5 Import & Migration

**Legacy Order Detection** — admin "Claim" workflow:
1. AIO displays legacy order as read-only with "Claim" button
2. Admin clicks "Claim" → AIO creates record in `mod_aio_ssl_orders` with `legacy_table` + `legacy_order_id` populated
3. Original record untouched (non-destructive)
4. Future interactions use AIO order

---

## 9. Provider Integration Details

### 9.1 NicSRS Provider

```php
class NicsrsProvider extends AbstractProvider
{
    protected string $slug = 'nicsrs';
    protected string $name = 'NicSRS';
    protected string $tier = 'full';
    protected string $baseUrl = 'https://portal.nicsrs.com/ssl';

    // Auth: api_token as POST form parameter
    // Content-Type: application/x-www-form-urlencoded
    // Response: JSON { code: 1, msg: "Success", data: {...} }
    // Products: /productList (filterable by vendor)
    // Supported CAs: Sectigo, DigiCert, GlobalSign, GeoTrust,
    //                Thawte, RapidSSL, sslTrus, Entrust, BaiduTrust
    // NO sandbox environment

    public function getCapabilities(): array
    {
        return ['order','validate','status','download','reissue',
                'renew','revoke','cancel','dcv_emails','resend_dcv',
                'change_dcv','csr_decode','caa_check'];
    }
}
```

### 9.2 GoGetSSL Provider

```php
class GoGetSSLProvider extends AbstractProvider
{
    protected string $slug = 'gogetssl';
    protected string $name = 'GoGetSSL';
    protected string $tier = 'full';
    protected string $baseUrl = 'https://my.gogetssl.com/api';

    // Auth: SESSION-BASED TOKEN
    //   Step 1: POST /auth/ {user, pass} → {"key":"session_token"}
    //   Step 2: All requests include auth_key=session_token
    //   Token cached in provider config, refreshed on expiry/401
    //
    // Products use NUMERIC IDs (not string codes)
    // Has sandbox: https://sandbox.gogetssl.com/api
    // Brand-specific webserver_type override

    private ?string $authToken = null;

    private function authenticate(): string
    {
        $response = $this->post('/auth/', [
            'user' => $this->credentials['username'],
            'pass' => $this->credentials['password'],
        ]);
        $this->authToken = $response['key'];
        return $this->authToken;
    }

    public function getCapabilities(): array
    {
        return ['order','validate','status','download','reissue',
                'renew','revoke','cancel','dcv_emails','resend_dcv',
                'change_dcv','balance','csr_decode'];
    }
}
```

### 9.3 TheSSLStore Provider

```php
class TheSSLStoreProvider extends AbstractProvider
{
    protected string $slug = 'thesslstore';
    protected string $name = 'TheSSLStore';
    protected string $tier = 'full';
    protected string $baseUrl = 'https://api.thesslstore.com/rest';
    protected string $sandboxUrl = 'https://sandbox-wbapi.thesslstore.com/rest';

    // Auth: PartnerCode + AuthToken in JSON body (AuthRequest object)
    // Content-Type: application/json; charset=utf-8
    //
    // ⚠️ RENEW: No dedicated /renew endpoint
    //    Uses /order/neworder with isRenewalOrder=true
    //    + RelatedTheSSLStoreOrderID for linking
    //
    // Has invite order: /order/inviteorder
    // Has mid-term upgrade: /order/midtermupgrade

    protected function buildAuthBody(array $params = []): array
    {
        return array_merge([
            'AuthRequest' => [
                'PartnerCode' => $this->credentials['partner_code'],
                'AuthToken'   => $this->credentials['auth_token'],
            ],
        ], $params);
    }

    public function renewCertificate(string $remoteId, array $params): array
    {
        // TheSSLStore renew = new order with renewal flag
        $params['isRenewalOrder'] = true;
        $params['RelatedTheSSLStoreOrderID'] = $remoteId;
        return $this->placeOrder($params);
    }

    public function getCapabilities(): array
    {
        return ['order','validate','status','download','reissue',
                'revoke','refund','resend_dcv','change_dcv',
                'invite_order','midterm_upgrade'];
    }
}
```

### 9.4 SSL2Buy Provider (Limited Tier)

```php
class SSL2BuyProvider extends AbstractProvider
{
    protected string $slug = 'ssl2buy';
    protected string $name = 'SSL2Buy';
    protected string $tier = 'limited';
    protected string $baseUrl = 'https://api.ssl2buy.com';

    // Auth: PartnerEmail + ApiKey in JSON body
    // Content-Type: application/json
    //
    // Order: /orderservice/order/placeorder
    // Query: /queryservice/{brand}/getorderdetails (brand-specific routing!)
    // Balance: /orderservice/order/getbalance
    // Config Link: /orderservice/order/getsslconfigurationlink
    //
    // ❌ NO: cancel, revoke, reissue, renew, download, DCV management
    // ✅ Fallback: Configuration Link + PIN for manual management

    private function getBrandRoute(string $brandName): string
    {
        $brand = strtolower($brandName);
        return match(true) {
            in_array($brand, ['comodo', 'sectigo']) => 'comodo',
            in_array($brand, ['globalsign', 'alphassl']) => 'globalsign',
            in_array($brand, ['symantec', 'digicert', 'geotrust', 'thawte', 'rapidssl']) => 'symantec',
            $brand === 'prime' || $brand === 'primessl' => 'prime',
            default => 'comodo',
        };
    }

    public function getCapabilities(): array
    {
        return ['order','validate','status','balance',
                'resend_approval','config_link'];
    }

    public function getConfigurationLink(string $orderId): string
    {
        // Calls /orderservice/order/getsslconfigurationlink
        // Returns URL for manual cert management at SSL2Buy portal
    }
}
```

---

## 10. Data Flow Diagrams

### 10.1 New Order Flow

```
Client purchases SSL product (servertype=aio_ssl)
│
└─ WHMCS triggers: aio_ssl_CreateAccount($params)
   │
   ├─ Check for legacy orders:
   │   ├─ Search nicsrs_sslorders WHERE serviceid = X
   │   ├─ Search tblsslorders WHERE serviceid = X
   │   ├─ FOUND → Show migration warning, offer "Allow New Certificate"
   │   └─ NOT FOUND → Continue
   │
   ├─ Resolve provider:
   │   ├─ configoption2 = specific slug → use that provider
   │   ├─ configoption2 = 'auto' → PriceCompareService::getCheapest()
   │   └─ configoption2 = empty → use first enabled provider
   │
   ├─ Create mod_aio_ssl_orders record:
   │   ├─ provider_slug = resolved slug
   │   ├─ certtype = canonical_id from configoption1
   │   ├─ status = 'Awaiting Configuration'
   │   └─ configdata = { provider: slug, canonical_id: ... }
   │
   └─ Return success → Client sees "Configure Certificate" in client area
```

### 10.2 Legacy Order Read Flow (Unified Order View)

```
Admin opens Order Management in AIO
│
├─ UnifiedOrderService::getAllOrders()
│   │
│   ├─ Query mod_aio_ssl_orders (AIO native orders)
│   │   → Mark each as source='aio', editable=true
│   │
│   ├─ Query nicsrs_sslorders (legacy NicSRS)
│   │   → Normalize configdata format
│   │   → Mark as source='legacy_nicsrs', editable=false, provider='nicsrs'
│   │
│   └─ Query tblsslorders WHERE module IN ('SSLCENTERWHMCS','thesslstore_ssl',
│       'thesslstore','ssl2buy')
│       → Normalize configdata format (json_decode or unserialize)
│       → Map module name to provider slug
│       → Mark as source='legacy_tblssl', editable=false
│
└─ Display unified table with:
   ├─ Provider badge (color-coded)
   ├─ Source indicator (AIO / Legacy)
   └─ "Claim" button for legacy orders
```

---

## 11. Backward Compatibility & Migration

### 11.1 Legacy Module Detection

| Legacy Module | Server Module Name | Table | configdata Format |
|--------------|-------------------|-------|-------------------|
| NicSRS | `nicsrs_ssl` | `nicsrs_sslorders` | JSON: `csr`, `crt`, `ca`, `private_key`, `domainInfo`, `applyReturn.beginDate/endDate` |
| GoGetSSL | `SSLCENTERWHMCS` | `tblsslorders` | JSON or serialized: `csr`, `crt`, `ca`, `approver_email`, `order_id` |
| TheSSLStore | `thesslstore_ssl` / `thesslstore` | `tblsslorders` | JSON: `csr`, `TheSSLStoreOrderID`, `crt_code`, `ca_code` |
| SSL2Buy | `ssl2buy` | `tblsslorders` | JSON: `orderId`, `csr`, `brand_name`, varies by CA |

### 11.2 configdata Normalization

```php
class MigrationService
{
    public function normalizeConfigdata(string $module, $rawConfigdata): array
    {
        // WHMCS 7.3+ uses json, older uses serialize
        if (is_string($rawConfigdata)) {
            $data = json_decode($rawConfigdata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = @unserialize($rawConfigdata);
            }
        }
        $data = (array) ($data ?? []);

        return match ($module) {
            'nicsrs_ssl'      => $this->normalizeNicsrs($data),
            'SSLCENTERWHMCS'  => $this->normalizeGoGetSSL($data),
            'thesslstore_ssl', 'thesslstore' => $this->normalizeTheSSLStore($data),
            'ssl2buy'         => $this->normalizeSSL2Buy($data),
            default           => $data,
        };
    }
}
```

### 11.3 Zero-Downtime Transition Plan

1. Install AIO module **alongside** existing modules (no conflicts)
2. AIO reads legacy tables without modifying them
3. New WHMCS products created with `servertype=aio_ssl`
4. Existing products gradually switched (change `servertype` to `aio_ssl`)
5. `CreateAccount` detects legacy cert → offers migration
6. Admin "Claims" legacy orders one-by-one or in bulk
7. Legacy modules deactivated only after all active orders are claimed

---

## 12. Security Architecture

### 12.1 Credential Encryption

All provider API credentials encrypted at rest using AES-256-CBC with HMAC integrity verification:

```php
class EncryptionService
{
    private static function getKey(): string
    {
        // Derive from WHMCS cc_encryption_hash + module-specific salt
        return hash('sha256', $GLOBALS['cc_encryption_hash'] . '|aio_ssl_v1');
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        $hmac = hash_hmac('sha256', $iv . $encrypted, $key);
        return base64_encode($iv . '::' . $hmac . '::' . $encrypted);
    }

    public static function decrypt(string $ciphertext): string
    {
        $key = self::getKey();
        $parts = explode('::', base64_decode($ciphertext), 3);
        if (count($parts) !== 3) throw new \Exception('Invalid encrypted data');
        [$iv, $hmac, $encrypted] = $parts;

        // Verify integrity
        $calcHmac = hash_hmac('sha256', $iv . $encrypted, $key);
        if (!hash_equals($calcHmac, $hmac)) throw new \Exception('Data integrity check failed');

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
```

### 12.2 Access Control

- Admin area: WHMCS admin session required (`defined('ADMINAREA')`)
- Client area: Service ownership validation (`tblhosting.userid` = session user)
- AJAX requests: CSRF + session validation via `ActionDispatcher::validateAccess()`
- API tokens masked in all logs (first 8 chars + `***`)
- GoGetSSL session tokens cached in memory only, never persisted to DB

### 12.3 Input Validation

- All user inputs sanitized via `htmlspecialchars()` + Capsule ORM (parameterized queries)
- CSR format validated before API submission
- Domain names validated with regex
- JSON payloads validated with `json_last_error()`

---

## 13. UI/UX Design Specifications

### 13.1 Design System: Ant Design-Inspired

**Mandatory**: Follow existing NicSRS module design patterns for visual consistency.

CSS variables (matching existing modules):
```css
:root {
    --aio-primary: #1890ff;
    --aio-success: #52c41a;
    --aio-warning: #faad14;
    --aio-danger: #ff4d4f;
    --aio-info: #1890ff;
    --aio-text: #595959;
    --aio-text-secondary: #8c8c8c;
    --aio-heading: #262626;
    --aio-border: #d9d9d9;
    --aio-bg: #f5f5f5;
    --aio-white: #ffffff;
}
```

Font stack: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif`

### 13.2 Admin Navigation

```
Addons → AIO SSL Manager
│
├── Dashboard         — Unified stats, charts (Chart.js), alerts
├── Providers         — Add/edit/test/disable providers
├── Products          — Catalog browser, sync, mapping
├── Price Compare     — Cross-provider price matrix
├── Orders            — Unified order list (3 sources), detail, actions
├── Import            — Legacy migration, API import, bulk import
├── Reports           — Revenue, performance, expiry forecast
└── Settings          — Sync config, notifications, currency
```

---

## 14. WHMCS Template Engine Rules

> **CRITICAL**: This section documents WHMCS's template engine constraints that must be followed.

### 14.1 Admin Addon Module → PHP Templates

WHMCS admin addon modules render via the `_output($vars)` function which outputs HTML directly. **Smarty is NOT available** in admin addon context.

**Pattern** (from NicSRS reference):
```php
// BaseController.php
protected function includeTemplate(string $template, array $data = []): void
{
    $data['modulelink'] = $this->modulelink;
    $data['lang'] = $this->lang;
    $data['helper'] = $this->viewHelper;
    extract($data);

    $templateFile = AIO_ADMIN_PATH . "/templates/{$template}.php";
    if (file_exists($templateFile)) {
        include $templateFile;
    }
}
```

Templates are **plain PHP files** using `<?php ?>` and `<?= ?>` for output:
```php
<!-- templates/dashboard.php -->
<div class="aio-stats-grid">
    <div class="aio-stat-card">
        <div class="aio-stat-value"><?= $totalOrders ?></div>
        <div class="aio-stat-label"><?= $lang['total_orders'] ?></div>
    </div>
</div>
```

### 14.2 Server Module Client Area → Smarty Templates

WHMCS server modules use Smarty `.tpl` templates for the client area. The `ClientArea` function returns:
```php
return [
    'templatefile' => 'path/to/template',  // .tpl extension auto-added
    'vars' => ['key' => 'value'],
];
```

Templates use Smarty syntax:
```smarty
{* view/applycert.tpl *}
<div class="sslm-container">
    <h1>{$_LANG.apply_title}</h1>
    <form id="ssl-apply-form">
        <input type="hidden" name="serviceid" value="{$serviceid}" />
    </form>
</div>
```

### 14.3 Template Summary

| Module | Template Location | Engine | Extension | Example |
|--------|------------------|--------|-----------|---------|
| Admin Addon (`aio_ssl_admin`) | `templates/*.php` | Plain PHP | `.php` | `<?= $helper->e($order->domain) ?>` |
| Server Client Area (`aio_ssl`) | `view/*.tpl` | Smarty | `.tpl` | `{$order.domain\|escape}` |
| Server Admin Tab | Inline PHP in module function | PHP | N/A | Direct HTML string return |

---

## 15. Implementation Plan

### Phase 1: Foundation (Est. 80h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 1.1 | Project scaffolding: file structure, autoloader, module entry points | Critical | 8h |
| 1.2 | Database schema: all 6 custom tables + activation SQL | Critical | 8h |
| 1.3 | `ProviderInterface`, `AbstractProvider`, `ProviderFactory`, `ProviderRegistry` | Critical | 12h |
| 1.4 | `EncryptionService` for credential storage (AES-256-CBC + HMAC) | Critical | 4h |
| 1.5 | Provider CRUD: `ProviderController` + PHP template | Critical | 12h |
| 1.6 | NicSRS provider plugin (port from existing module) | Critical | 16h |
| 1.7 | Settings controller with sync configuration | High | 8h |
| 1.8 | Admin navigation + Ant Design CSS framework | High | 8h |
| 1.9 | Activation/deactivation/upgrade handlers | High | 4h |

### Phase 2: Provider Plugins (Est. 100h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 2.1 | GoGetSSL provider plugin (session auth, numeric IDs, lifecycle) | Critical | 24h |
| 2.2 | TheSSLStore provider plugin (JSON auth, renewal-as-new-order) | Critical | 24h |
| 2.3 | SSL2Buy provider plugin (limited tier, brand-based routing) | Critical | 20h |
| 2.4 | Product catalog sync service (all providers, normalized) | Critical | 12h |
| 2.5 | `ProductMapService` — auto-mapping + admin PHP template | Critical | 12h |
| 2.6 | `PriceCompareService` + comparison PHP template | High | 8h |

### Phase 3: Server Module & Client Area (Est. 80h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 3.1 | Server module: ConfigOptions, CreateAccount, MetaData | Critical | 8h |
| 3.2 | `ProviderBridge` — resolves provider from service/order | Critical | 8h |
| 3.3 | Multi-step client area — Smarty templates (apply, CSR, DCV, contacts) | Critical | 20h |
| 3.4 | Certificate download, reissue, renew, revoke — Smarty templates | Critical | 16h |
| 3.5 | SSL2Buy limited-tier client area — Smarty template (config link) | High | 8h |
| 3.6 | Admin service tab (inline PHP) + custom buttons | High | 8h |
| 3.7 | Client area CSS (Ant Design-inspired, matching NicSRS) | High | 12h |

### Phase 4: Dashboard, Reports, Migration (Est. 90h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 4.1 | `UnifiedOrderService` — reads from 3 tables | Critical | 12h |
| 4.2 | Dashboard PHP template with Chart.js | High | 12h |
| 4.3 | Order management controller (list, detail, actions) — PHP template | Critical | 16h |
| 4.4 | Auto-sync engine via WHMCS hooks | Critical | 12h |
| 4.5 | `MigrationService` + configdata normalizer | Critical | 12h |
| 4.6 | Import controller (single, bulk, legacy claim) — PHP template | High | 8h |
| 4.7 | Notification service (HTML emails via WHMCS SendAdminEmail) | High | 8h |
| 4.8 | Report service + PHP template | Medium | 6h |
| 4.9 | Multi-language (English + Vietnamese) | Medium | 4h |

### Total Estimated: ~350 hours

---

## 16. Risk Assessment

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| SSL2Buy API too limited for management | High | Confirmed | Two-tier architecture + config link fallback |
| Provider API changes break integration | Medium | Low | Abstract provider interface isolates changes |
| Legacy configdata format varies (JSON vs serialized) | High | Confirmed | Dual-parsing: `json_decode` → `unserialize` fallback |
| NicSRS uses custom table vs others use `tblsslorders` | High | Confirmed | `UnifiedOrderService` reads both tables |
| GoGetSSL session token expiry during long operations | Medium | Medium | Auto-refresh on 401, token caching in memory |
| Performance with 10K+ orders across 3 tables | Medium | Low | Indexed queries, pagination, UNION optimizations |
| WHMCS version incompatibility | Medium | Low | Target 7.10+, use Capsule ORM, test on 8.x |
| Product mapping errors (wrong canonical match) | Medium | Medium | Admin review UI + manual override capability |
| Concurrent sync conflicts | Medium | Medium | File-based lock + error count tracking |

---

## Appendix A: Provider Authentication Code Examples

### A.1 NicSRS
```php
// POST with api_token as form field
$data = ['api_token' => $token, 'vendor' => 'Sectigo'];
curl_post($this->baseUrl . '/productList', http_build_query($data), [
    'Content-Type: application/x-www-form-urlencoded',
]);
```

### A.2 GoGetSSL (Session-Based)
```php
// Step 1: Authenticate → get session token
$auth = curl_post($this->baseUrl . '/auth/', http_build_query([
    'user' => $username, 'pass' => $password
]));
$authKey = $auth['key']; // Cache this, refresh on 401

// Step 2: Use auth_key for subsequent calls
$products = curl_get($this->baseUrl . '/products/?auth_key=' . $authKey);
```

### A.3 TheSSLStore (JSON Body Auth)
```php
$payload = json_encode([
    'AuthRequest' => [
        'PartnerCode' => $partnerCode,
        'AuthToken' => $authToken,
    ]
]);
curl_post($this->baseUrl . '/product/query', $payload, [
    'Content-Type: application/json; charset=utf-8'
]);
```

### A.4 SSL2Buy (JSON Body Auth)
```php
$payload = json_encode([
    'PartnerEmail' => $email,
    'ApiKey' => $apiKey,
    'ProductID' => 351,
    'Year' => 12
]);
curl_post($this->baseUrl . '/orderservice/order/getproductprice', $payload, [
    'Content-Type: application/json'
]);
```

---

**© HVN GROUP** — All rights reserved.  
**Document Version:** 1.1.0 | **Status:** Ready for Implementation  
**Revision Note:** v1.1.0 — Fixed template engine rules (PHP vs Smarty), corrected NicSRS custom table architecture, updated GoGetSSL session auth, fixed TheSSLStore renew mechanism, aligned UI framework to Ant Design.