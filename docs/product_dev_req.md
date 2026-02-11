# HVN - AIO SSL Manager
## Product Development Requirements (PDR)

> **Version:** 1.0.0  
> **Author:** HVN GROUP (https://hvn.vn)  
> **License:** Proprietary  
> **Created:** 2026-02-11  
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
14. [Implementation Plan](#14-implementation-plan)
15. [Risk Assessment](#15-risk-assessment)

---

## 1. Executive Summary

### 1.1 Problem Statement

HVN currently manages SSL certificates through **four separate WHMCS modules**, each with its own interface, database patterns, and management workflows:

| Module | Provider | Module Name | Storage |
|--------|----------|-------------|---------|
| NicSRS SSL | NicSRS | `nicsrs_ssl` | `nicsrs_sslorders` + `tblsslorders` |
| GoGetSSL (SSLCENTER) | GoGetSSL | `SSLCENTERWHMCS` | `tblsslorders` |
| TheSSLStore | TheSSLStore | `thesslstore_ssl` (or custom) | `tblsslorders` |
| SSL2Buy | SSL2Buy | `ssl2buy` | `tblsslorders` |

This fragmentation creates operational overhead: no unified dashboard, no cross-provider price comparison, inconsistent client experiences, and duplicated maintenance effort.

### 1.2 Solution

Build a **single, unified AIO (All-In-One) SSL Manager** module for WHMCS that:
- Centralizes all SSL operations across NicSRS, GoGetSSL, TheSSLStore, and SSL2Buy
- Provides cross-provider price comparison with intelligent product name mapping
- Uses a plugin-based architecture for easy addition of future providers
- Maintains full backward compatibility with existing orders from all four legacy modules
- Leverages WHMCS's native `tblsslorders` table for unified order visibility

### 1.3 Key Decision: Native `tblsslorders` Integration

**Critical architectural decision**: The AIO module writes to WHMCS's native `tblsslorders` table (not a custom table) so that:
- Existing orders from legacy modules remain visible and manageable
- WHMCS admin SSL order views work natively
- Third-party integrations expecting `tblsslorders` continue to function
- The module field (`tblsslorders.module`) distinguishes which provider handles each order

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

1. All existing orders from 4 legacy modules manageable through AIO interface
2. New orders can be placed with any enabled provider
3. Admin can compare prices for the same certificate type across providers
4. Adding a new provider requires only creating a new provider plugin class (no core changes)
5. Zero downtime migration from legacy modules

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
│              │  Provider Plugin Layer  │                          │
│              │  (ProviderInterface)    │                          │
│              ├────────────────────────┤                          │
│              │ NicSRS    │ GoGetSSL   │                          │
│              │ TheSSLSt  │ SSL2Buy    │                          │
│              │ [Future]  │ [Future]   │                          │
│              └───────────┬────────────┘                          │
│                          │                                       │
│              ┌───────────▼────────────┐                          │
│              │  Database Layer         │                          │
│              │  tblsslorders (native)  │                          │
│              │  mod_aio_ssl_*          │                          │
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
    public function getCapabilities(): array;  // ['cancel', 'revoke', 'reissue', 'renew', ...]
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
- Status sync via vendor-specific `GetOrderDetails` endpoints (branched by CA: Comodo, GlobalSign, Symantec)

---

## 4. Provider API Capability Matrix

### 4.1 Full Comparison

| Capability | NicSRS | GoGetSSL | TheSSLStore | SSL2Buy |
|------------|--------|----------|-------------|---------|
| **Auth Method** | API Token (POST param) | User/Pass → Auth Token | PartnerCode + AuthToken (JSON body) | PartnerEmail + ApiKey (JSON body) |
| **API Protocol** | REST POST, form-urlencoded | REST GET/POST | REST POST, JSON | REST POST, JSON |
| **API Base URL** | `portal.nicsrs.com/ssl` | `my.gogetssl.com/api` | `api.thesslstore.com/rest` | `api.ssl2buy.com` |
| **Get Products** | ✅ `/productList` | ✅ `/products/` | ✅ `/product/query` | ✅ `GetProductPrice` |
| **Get Pricing** | ✅ in productList | ✅ `/products/price/{id}` | ✅ in product/query | ✅ `GetProductPrice` |
| **Validate Order** | ✅ `/validate` | ✅ (via order params) | ✅ `/order/validate` | ✅ `ValidateOrder` |
| **Place Order** | ✅ `/place` | ✅ `/orders/add_ssl_order` | ✅ `/order/neworder` | ✅ `PlaceOrder` |
| **Order Status** | ✅ `/collect` | ✅ `/orders/status/{id}` | ✅ `/order/status` | ✅ `GetOrderDetails` (per CA) |
| **Download Cert** | ✅ `/collect` (cert in response) | ✅ `/orders/ssl/download/{id}` | ✅ `/order/download` | ❌ (via config link) |
| **Reissue** | ✅ `/reissue` | ✅ `/orders/ssl/reissue/{id}` | ✅ `/order/reissue` | ❌ |
| **Renew** | ✅ `/renew` | ✅ `/orders/add_ssl_renew_order` | ✅ (new order with `isRenewalOrder`) | ❌ |
| **Revoke** | ✅ `/revoke` | ✅ `/orders/ssl/revoke/{id}` | ✅ `/order/certificaterevokerequest` | ❌ |
| **Cancel** | ✅ `/cancel` | ✅ `/orders/cancel_ssl_order/{id}` | ✅ `/order/refundrequest` | ❌ |
| **DCV Emails** | ✅ `/DCVemail` | ✅ `/tools/domain/emails/` | ✅ `/order/approverlist` | ❌ |
| **Resend DCV** | ✅ `/DCVemail` (with certId) | ✅ `/orders/ssl/resend_validation_email/{id}` | ✅ `/order/resend` | ✅ `ResendApprovalMail` (per CA) |
| **Change DCV** | ✅ `/updateDCV` | ✅ `/orders/ssl/change_dcv_method/{id}` | ✅ `/order/changeapproveremail` | ❌ |
| **Get Balance** | ❌ | ✅ `/account/balance/` | ❌ (via health service) | ✅ `GetBalance` |
| **CSR Decode** | ✅ `/csrDecode` | ✅ `/tools/csr/decode/` | ❌ (client-side) | ❌ |
| **CAA Check** | ✅ `/caaCheck` | ❌ | ❌ | ❌ |
| **Config Link** | ❌ | ❌ | ❌ | ✅ `GetSSLConfigurationLink` |
| **Sandbox** | ❌ | ✅ (sandbox API) | ✅ (sandbox URL) | ✅ (test mode) |
| **Tier** | **Full** | **Full** | **Full** | **Limited** |

### 4.2 SSL2Buy Vendor-Specific Routing

SSL2Buy routes API calls by Certificate Authority brand. The `brand_name` field determines the endpoint:

| CA Brand | Order Details Endpoint | Resend Approval Endpoint |
|----------|----------------------|-------------------------|
| Comodo/Sectigo | `/queryservice/comodo/getorderdetails` | `/queryservice/comodo/resendapprovalemail` |
| GlobalSign | `/queryservice/globalsign/getorderdetails` | `/queryservice/globalsign/resendapprovalemail` |
| Symantec/DigiCert | `/queryservice/symantec/getorderdetails` | `/queryservice/symantec/resendapprovalemail` |
| PrimeSSL | `/queryservice/prime/primesubscriptionorderdetail` | N/A |
| ACME | `/queryservice/acme/GetAcmeOrderDetail` | N/A |

---

## 5. Product Name Mapping & Price Comparison

### 5.1 The Product Mapping Problem

Each provider uses different product names/codes for the **same certificate**. For price comparison, we need a canonical mapping:

**Example: "Sectigo PositiveSSL" (DV, Single Domain)**

| Provider | Product Code | Product Name |
|----------|-------------|--------------|
| **WHMCS Product** | (configoption1) | Sectigo PositiveSSL |
| **NicSRS** | `positivessl` | PositiveSSL |
| **GoGetSSL** | `71` (product ID) | Sectigo PositiveSSL DV |
| **TheSSLStore** | `positivessl` | Sectigo Positive SSL |
| **SSL2Buy** | `351` (product code) | Sectigo Positive SSL |

### 5.2 Canonical Product Mapping Table

A new database table `mod_aio_ssl_product_map` stores mappings between a canonical product identifier and each provider's product code. Key mappings:

| Canonical ID | Canonical Name | Type | NicSRS Code | GoGetSSL ID | TheSSLStore Code | SSL2Buy Code |
|-------------|---------------|------|-------------|-------------|-----------------|-------------|
| `sectigo-positivessl` | Sectigo PositiveSSL | DV | `positivessl` | `71` | `positivessl` | `351` |
| `sectigo-positivessl-wildcard` | Sectigo PositiveSSL Wildcard | DV | `positivessl_wildcard` | `72` | `positivesslwildcard` | `352` |
| `sectigo-positivessl-multi` | Sectigo PositiveSSL Multi-Domain | DV | `positivessl_multidomain` | `74` | `positivesslmultidomain` | `371` |
| `sectigo-essentialssl` | Sectigo EssentialSSL | DV | N/A | `65` | `essentialssl` | `362` |
| `sectigo-essentialssl-wildcard` | Sectigo EssentialSSL Wildcard | DV | N/A | `66` | `essentialsslwildcard` | `363` |
| `sectigo-instantssl` | Sectigo InstantSSL | OV | N/A | `22` | `instantssl` | `354` |
| `sectigo-instantssl-pro` | Sectigo InstantSSL Pro | OV | N/A | `23` | `instantsslpro` | `355` |
| `sectigo-ov-ssl` | Sectigo OV SSL | OV | `sectigo_ov` | `198` | `sectigosslovi` | `384` |
| `sectigo-ov-wildcard` | Sectigo OV Wildcard | OV | `sectigo_ov_wildcard` | `199` | `sectigosslwildcardov` | `385` |
| `sectigo-ev-ssl` | Sectigo EV SSL | EV | `sectigo_ev_ssl` | `21` | `sectigoevssl` | `360` |
| `sectigo-ev-multi` | Sectigo EV Multi-Domain | EV | `sectigo_ev_multidomain` | `68` | `sectigoevmultidomain` | `370` |
| `sectigo-premium-ssl` | Sectigo Premium SSL | OV | N/A | N/A | `premiumssl` | `357` |
| `sectigo-premium-wildcard` | Sectigo Premium Wildcard | OV | N/A | N/A | `premiumsslwildcard` | `358` |
| `geotrust-quickssl-premium` | GeoTrust QuickSSL Premium | DV | `geotrust_quickssl_premium` | `42` | `quicksslpremium` | `5` |
| `geotrust-truebiz-id` | GeoTrust True BusinessID | OV | `geotrust_truebusiness_id` | `45` | `truebusinessid` | `6` |
| `geotrust-truebiz-wildcard` | GeoTrust True BusinessID Wildcard | OV | N/A | `46` | `truebusinessidwildcard` | `7` |
| `geotrust-truebiz-ev` | GeoTrust True BusinessID EV | EV | N/A | `47` | `truebusinessidev` | `8` |
| `rapidssl-standard` | RapidSSL Certificate | DV | N/A | `14` | `rapidssl` | `1` |
| `rapidssl-wildcard` | RapidSSL Wildcard | DV | N/A | `15` | `rapidsslwildcard` | `2` |
| `thawte-ssl-webserver` | Thawte SSL Web Server | OV | N/A | `32` | `sslwebserver` | `11` |
| `thawte-ssl123` | Thawte SSL123 | DV | N/A | `30` | `ssl123` | `12` |
| `thawte-ev-ssl` | Thawte EV SSL | EV | N/A | `33` | `sslwebserverwithev` | `19` |
| `digicert-secure-site` | DigiCert Secure Site | OV | N/A | N/A | `securesite` | `13` |
| `digicert-secure-site-pro` | DigiCert Secure Site Pro | OV | N/A | N/A | `securesitepro` | `14` |
| `digicert-secure-site-ev` | DigiCert Secure Site EV | EV | N/A | N/A | `securesiteev` | `16` |
| `digicert-basic-ov` | DigiCert Basic OV | OV | N/A | N/A | `digicertov` | `528` |
| `digicert-basic-ev` | DigiCert Basic EV | EV | N/A | N/A | `digicertev` | `529` |
| `globalsign-domain-ssl` | GlobalSign DomainSSL | DV | N/A | `87` | N/A | `103` |
| `globalsign-org-ssl` | GlobalSign OrganizationSSL | OV | N/A | `88` | N/A | `105` |
| `globalsign-ev-ssl` | GlobalSign ExtendedSSL | EV | N/A | `89` | N/A | `109` |
| `alphassl-standard` | AlphaSSL Certificate | DV | N/A | `85` | N/A | `101` |
| `alphassl-wildcard` | AlphaSSL Wildcard | DV | N/A | `86` | N/A | `102` |
| `sectigo-code-signing` | Sectigo Code Signing | OV/CS | `sectigo_code_signing` | `61` | `codesigning` | `364` |
| `sectigo-ev-code-signing` | Sectigo EV Code Signing | EV/CS | `sectigo_ev_code_signing` | `62` | `evcodesigning` | `386` |

> **Note**: N/A means the provider does not offer that product. The mapping table supports NULL values for providers that don't carry a specific product.

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
Display comparison table:
┌─────────────┬─────────┬──────────┬──────────────┬─────────┐
│ Provider    │ 1 Year  │ 2 Years  │ 3 Years      │ Best?   │
├─────────────┼─────────┼──────────┼──────────────┼─────────┤
│ NicSRS      │ $7.95   │ $15.90   │ $23.85       │ ✅ Best │
│ GoGetSSL    │ $8.50   │ $16.00   │ $24.00       │         │
│ TheSSLStore │ $9.50   │ $18.00   │ $27.00       │         │
│ SSL2Buy     │ $8.00   │ $15.50   │ $23.00       │         │
└─────────────┴─────────┴──────────┴──────────────┴─────────┘
```

### 5.5 WHMCS Product Linking

Each WHMCS product (`tblproducts`) links to the AIO module via:

| Field | Usage |
|-------|-------|
| `servertype` | `aio_ssl` |
| `configoption1` | `canonical_id` (e.g., `sectigo-positivessl`) |
| `configoption2` | Preferred provider slug (e.g., `nicsrs`) — or `auto` for cheapest |
| `configoption3` | Provider-specific override (API token, etc.) |
| `configoption4` | Fallback provider slug (if primary fails) |

When `configoption2` = `auto`, the module selects the cheapest enabled provider at order time.

---

## 6. Database Design

### 6.1 Tables Overview

| Table | Purpose | Owner |
|-------|---------|-------|
| `tblsslorders` | **WHMCS native** — all SSL orders (read/write) | Shared |
| `mod_aio_ssl_providers` | Provider configuration & credentials | Admin Addon |
| `mod_aio_ssl_products` | Cached product catalog from all providers | Admin Addon |
| `mod_aio_ssl_product_map` | Cross-provider product name mapping | Admin Addon |
| `mod_aio_ssl_settings` | Module configuration (key-value) | Admin Addon |
| `mod_aio_ssl_activity_log` | Audit trail | Admin Addon |

### 6.2 Schema: `mod_aio_ssl_providers`

```sql
CREATE TABLE `mod_aio_ssl_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,                     -- 'nicsrs', 'gogetssl', 'thesslstore', 'ssl2buy'
  `name` varchar(100) NOT NULL,                    -- 'NicSRS', 'GoGetSSL', etc.
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

// GoGetSSL
{ "username": "xxx", "password": "xxx" }

// TheSSLStore
{ "partner_code": "xxx", "auth_token": "xxx" }

// SSL2Buy
{ "partner_email": "xxx", "api_key": "xxx" }
```

### 6.3 Schema: `mod_aio_ssl_products`

```sql
CREATE TABLE `mod_aio_ssl_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `provider_slug` varchar(50) NOT NULL,            -- FK to mod_aio_ssl_providers.slug
  `product_code` varchar(150) NOT NULL,            -- Provider-specific code
  `product_name` varchar(255) NOT NULL,
  `vendor` varchar(50) NOT NULL,                   -- CA brand: Sectigo, DigiCert, etc.
  `validation_type` enum('dv','ov','ev') NOT NULL,
  `product_type` enum('ssl','wildcard','multi_domain','code_signing','email') NOT NULL DEFAULT 'ssl',
  `support_wildcard` tinyint(1) NOT NULL DEFAULT 0,
  `support_san` tinyint(1) NOT NULL DEFAULT 0,
  `max_domains` int NOT NULL DEFAULT 1,
  `max_years` int NOT NULL DEFAULT 1,
  `min_years` int NOT NULL DEFAULT 1,
  `price_data` text,                               -- JSON pricing
  `extra_data` text,                               -- JSON: provider-specific metadata
  `canonical_id` varchar(100) DEFAULT NULL,         -- FK to mod_aio_ssl_product_map
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

**`price_data` JSON structure** (normalized across providers):

```json
{
  "base": {
    "12": 7.95,     // 12 months = $7.95
    "24": 15.90,    // 24 months = $15.90
    "36": 23.85     // 36 months = $23.85
  },
  "san": {
    "12": 5.00,     // per SAN per 12 months
    "24": 9.00,
    "36": 13.00
  },
  "wildcard_san": {
    "12": 45.00,
    "24": 85.00
  },
  "currency": "USD",
  "last_updated": "2026-02-11T10:00:00Z"
}
```

### 6.4 Schema: `mod_aio_ssl_product_map`

```sql
CREATE TABLE `mod_aio_ssl_product_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `canonical_id` varchar(100) NOT NULL,            -- 'sectigo-positivessl'
  `canonical_name` varchar(255) NOT NULL,          -- 'Sectigo PositiveSSL'
  `vendor` varchar(50) NOT NULL,                   -- 'Sectigo'
  `validation_type` enum('dv','ov','ev') NOT NULL,
  `product_type` enum('ssl','wildcard','multi_domain','code_signing','email') NOT NULL DEFAULT 'ssl',
  `nicsrs_code` varchar(150) DEFAULT NULL,
  `gogetssl_code` varchar(150) DEFAULT NULL,       -- GoGetSSL product ID
  `thesslstore_code` varchar(150) DEFAULT NULL,
  `ssl2buy_code` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_canonical` (`canonical_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.5 Schema: `mod_aio_ssl_providers` — Config JSON Examples

```json
// NicSRS config
{
  "supported_vendors": ["Sectigo","DigiCert","GlobalSign","GeoTrust","Thawte","RapidSSL","sslTrus","Entrust","BaiduTrust"],
  "sync_vendors_separately": true,
  "api_timeout": 60
}

// GoGetSSL config
{
  "brand_mapping": { "geotrust": 18, "rapidssl": 18, "digicert": 18, "thawte": 18 },
  "default_webserver_type": -1
}

// TheSSLStore config
{
  "date_time_culture": "en-US",
  "signature_hash": "SHA2-256",
  "cert_transparency": true
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

### 6.6 Using `tblsslorders` (WHMCS Native)

The module writes to `tblsslorders` with these conventions:

| Column | Usage |
|--------|-------|
| `id` | Auto-increment primary key |
| `userid` | WHMCS client ID |
| `serviceid` | WHMCS hosting/service ID |
| `addon_id` | 0 (not addon-based) |
| `remoteid` | Provider's order/certificate ID |
| `module` | `aio_ssl` for new orders. Legacy orders retain original module names |
| `certtype` | Canonical product ID (e.g., `sectigo-positivessl`) |
| `completiondate` | Certificate issuance date |
| `status` | Standard statuses: Awaiting Configuration, Draft, Pending, Processing, Complete, Cancelled, Revoked, Expired, Reissue |
| `configdata` | JSON blob containing all order data (see below) |

**`configdata` JSON for AIO orders**:

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
  "admin_contact": { "firstname": "", "lastname": "", "email": "", "phone": "", ... },
  "tech_contact": { ... },
  "org_info": { ... },
  "webserver_type": "Other",
  "beginDate": "2026-01-01",
  "endDate": "2027-01-01",
  "years": 1,
  "order_date": "2026-01-01 10:30:00",
  "migration": {
    "from_module": "nicsrs_ssl",
    "original_remoteid": "12345",
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
│   │   │   ├── ProviderInterface.php            # Provider contract
│   │   │   ├── AbstractProvider.php             # Base implementation
│   │   │   ├── ProviderFactory.php              # Factory: slug → provider instance
│   │   │   ├── ProviderRegistry.php             # Registry of all available providers
│   │   │   ├── EncryptionService.php            # AES-256-CBC for credentials
│   │   │   └── NormalizedProduct.php            # Value object for product data
│   │   ├── Provider/
│   │   │   ├── NicsrsProvider.php
│   │   │   ├── GoGetSSLProvider.php
│   │   │   ├── TheSSLStoreProvider.php
│   │   │   └── SSL2BuyProvider.php
│   │   ├── Controller/
│   │   │   ├── BaseController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ProviderController.php           # Provider CRUD
│   │   │   ├── ProductController.php            # Catalog + mapping
│   │   │   ├── PriceCompareController.php       # Price comparison
│   │   │   ├── OrderController.php
│   │   │   ├── ImportController.php             # Migration + import
│   │   │   ├── ReportController.php
│   │   │   └── SettingsController.php
│   │   ├── Service/
│   │   │   ├── SyncService.php                  # Auto-sync orchestrator
│   │   │   ├── ProductMapService.php            # Canonical mapping logic
│   │   │   ├── PriceCompareService.php          # Price comparison engine
│   │   │   ├── NotificationService.php
│   │   │   ├── MigrationService.php             # Legacy module migration
│   │   │   └── ReportService.php
│   │   └── Helper/
│   │       ├── ViewHelper.php
│   │       └── CurrencyHelper.php
│   └── templates/
│       ├── dashboard.tpl
│       ├── providers.tpl
│       ├── products.tpl
│       ├── price_compare.tpl
│       ├── orders.tpl
│       ├── order_detail.tpl
│       ├── import.tpl
│       ├── reports.tpl
│       └── settings.tpl
│
├── servers/aio_ssl/                             # ──── SERVER MODULE ────
│   ├── aio_ssl.php                              # Entry: ConfigOptions, CreateAccount, ClientArea
│   ├── src/
│   │   ├── Controller/
│   │   │   ├── ActionController.php             # AJAX actions (apply, reissue, revoke, etc.)
│   │   │   └── PageController.php               # Page rendering by status
│   │   ├── Dispatcher/
│   │   │   ├── ActionDispatcher.php             # Route + validate AJAX
│   │   │   └── PageDispatcher.php               # Route + validate pages
│   │   ├── Service/
│   │   │   ├── OrderService.php                 # CRUD on tblsslorders
│   │   │   ├── CertificateService.php           # CSR gen, cert ops
│   │   │   └── ProviderBridge.php               # Loads correct provider plugin
│   │   └── compatibility.php                    # Legacy class aliases
│   ├── templates/
│   │   ├── apply.tpl                            # Multi-step application
│   │   ├── pending.tpl
│   │   ├── complete.tpl
│   │   ├── reissue.tpl
│   │   ├── migrated.tpl                         # Legacy vendor cert view
│   │   └── limited_provider.tpl                 # For SSL2Buy (config link)
│   └── lang/
│       ├── english.php
│       ├── vietnamese.php
│       └── chinese.php
```

### 7.2 Provider Plugin Registration

Providers are auto-discovered via `ProviderRegistry`:

```php
// lib/Core/ProviderRegistry.php
class ProviderRegistry
{
    private static array $providers = [
        'nicsrs'      => NicsrsProvider::class,
        'gogetssl'    => GoGetSSLProvider::class,
        'thesslstore' => TheSSLStoreProvider::class,
        'ssl2buy'     => SSL2BuyProvider::class,
    ];

    public static function register(string $slug, string $class): void
    {
        self::$providers[$slug] = $class;
    }

    public static function get(string $slug): ProviderInterface
    {
        // Load credentials from mod_aio_ssl_providers
        // Instantiate and return
    }

    public static function getAllEnabled(): array
    {
        // Return instances for all enabled providers
    }
}
```

**Adding a new provider** requires:
1. Create `NewProvider.php` implementing `ProviderInterface`
2. Register in `ProviderRegistry::$providers`
3. Add a row to `mod_aio_ssl_providers`
4. Add column to `mod_aio_ssl_product_map` (or use `extra_data` JSON)
5. No core module changes needed

---

## 8. Feature Specifications

### 8.1 Admin Dashboard

**Unified statistics across all providers:**
- Total orders by provider (stacked bar chart)
- Order status distribution (doughnut chart)  
- Monthly order trends (line chart, per provider)
- Revenue by provider (bar chart)
- Expiring certificates (sortable table, all providers)
- API health status for each provider (color-coded indicators)
- Provider balance (for GoGetSSL and SSL2Buy)

### 8.2 Provider Management (CRUD)

| Action | Description |
|--------|-------------|
| **List** | Table showing all providers with status, tier, last sync, test result |
| **Add** | Form: name, slug, tier, API credentials (encrypted), sandbox toggle |
| **Edit** | Modify credentials, enable/disable, change config |
| **Test** | One-click API connection test for any provider |
| **Disable** | Soft-disable: existing orders remain, no new orders routed |
| **Delete** | Hard-delete with confirmation (only if 0 active orders) |

### 8.3 Product Catalog & Mapping

**Product Sync**: Fetches products from all enabled providers, normalizes, and stores in `mod_aio_ssl_products`.

**Auto-Mapping**: After sync, the `ProductMapService` attempts to match products to canonical entries using:
1. Exact code match
2. Name normalization (lowercase, remove "Certificate", "SSL", trim)
3. Fuzzy match (Levenshtein distance < 3)
4. Remaining unmapped products flagged for admin review

**Admin UI for Mapping**:
- Table of canonical products with columns for each provider's matched product
- Dropdown to manually assign/change provider product mapping
- "Unmapped Products" alert showing products that couldn't be auto-mapped
- Bulk-create canonical entries from provider products

### 8.4 Price Comparison

**Comparison View**: Admin selects a WHMCS product or canonical product:
- Side-by-side pricing from all providers (1yr, 2yr, 3yr)
- SAN pricing comparison
- Wildcard SAN pricing comparison  
- "Best price" highlighting per period
- Historical price tracking (optional, v2.0)

**Bulk Comparison**: Export all products with cross-provider pricing to CSV.

### 8.5 Unified Order Management

**Order List**: Filterable by provider, status, client, date range, domain.

**Order Detail**: Shows full certificate data regardless of provider:
- Certificate info (domain, status, dates, type)
- Provider badge (color-coded)
- DCV status per domain
- Action buttons (adapts to provider capabilities)
- Activity log for this order

**Provider-aware actions**: Buttons dynamically show/hide based on `getCapabilities()`:
- Full tier: Reissue, Renew, Revoke, Cancel, Refresh, Resend DCV
- Limited tier: Refresh Status, Manage at Provider (link), Resend Approval

### 8.6 Certificate Lifecycle (Server Module)

**Multi-step application** (client area):
1. Generate/paste CSR → auto-detect domains
2. Select DCV method per domain
3. Enter admin/tech contacts (OV/EV)
4. Confirm & submit

**The server module** delegates all API calls through `ProviderBridge`:

```php
class ProviderBridge
{
    public static function getProvider(int $serviceId): ProviderInterface
    {
        // 1. Check tblsslorders for existing order → get provider from configdata
        // 2. If new order → get provider from tblproducts.configoption2
        // 3. If 'auto' → use PriceCompareService to find cheapest
        // 4. Return provider instance via ProviderFactory
    }
}
```

### 8.7 Import & Migration

**Import Sources**:
1. **From provider API**: Enter remote ID → fetch data → create tblsslorders record
2. **From legacy modules**: Detect existing `tblsslorders` records with `module` = legacy module name
3. **Bulk import**: CSV upload with remote IDs

**Migration Strategy** (non-destructive):
- Legacy orders retain their original `module` value in `tblsslorders`
- AIO module can *read* and *display* legacy orders
- Admin can "claim" a legacy order → updates `module` to `aio_ssl` and adds provider info to configdata
- Vendor migration detection: when `servertype` changes to `aio_ssl`, detect existing certs from other modules

### 8.8 Auto-Sync Engine

```
┌─ WHMCS Cron (AfterCronJob / DailyCronJob) ─────────────┐
│                                                          │
│  SyncService::runScheduledSync()                         │
│  │                                                       │
│  ├─ For each enabled provider:                           │
│  │   ├─ Certificate Status Sync                          │
│  │   │   ├─ Query pending/processing orders              │
│  │   │   ├─ Call provider->getOrderStatus()              │
│  │   │   ├─ Update tblsslorders status + configdata      │
│  │   │   └─ Send notifications on status change          │
│  │   │                                                   │
│  │   ├─ Product Catalog Sync                             │
│  │   │   ├─ Call provider->fetchProducts()               │
│  │   │   ├─ Upsert mod_aio_ssl_products                 │
│  │   │   ├─ Detect price changes → notify               │
│  │   │   └─ Run auto-mapping for new products            │
│  │   │                                                   │
│  │   └─ Expiry Check                                     │
│  │       ├─ Scan active certs for upcoming expiry        │
│  │       └─ Send expiry warnings                         │
│  │                                                       │
│  └─ Update sync timestamps                               │
└──────────────────────────────────────────────────────────┘
```

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

    // Auth: api_token as POST parameter
    // Response: JSON { code: 1, msg: "Success", data: {...} }
    // Products: /productList (filterable by vendor)
    // Supported CAs: Sectigo, DigiCert, GlobalSign, GeoTrust,
    //                Thawte, RapidSSL, sslTrus, Entrust, BaiduTrust

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

    // Auth: POST /auth → token (session-based, cached)
    // Products use numeric IDs
    // Supports: add_ssl_order, reissue, renew, cancel, revoke
    // Has sandbox environment
    // Brand-specific webserver_type (18 for GeoTrust/RapidSSL/DigiCert/Thawte)

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
    // Content-Type: application/json
    // Products: /product/query
    // Orders: /order/neworder, /order/status, /order/download,
    //         /order/reissue, /order/certificaterevokerequest
    // Invite order: /order/inviteorder (email-based provisioning)
    // Has mid-term upgrade: /order/midtermupgrade

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
    // Order endpoints:
    //   /orderservice/order/placeorder
    //   /orderservice/order/getbalance
    //   /orderservice/order/getproductprice
    //   /orderservice/order/getsslconfigurationlink
    //   /orderservice/order/validateorder
    // Query endpoints (brand-specific):
    //   /queryservice/{brand}/getorderdetails
    //   /queryservice/{brand}/resendapprovalemail
    //   /queryservice/{brand}/{brand}subscriptionorderdetail

    // ❌ NO: cancel, revoke, reissue, renew, download, DCV management
    // ✅ Fallback: Configuration Link + PIN for manual management

    public function getCapabilities(): array
    {
        return ['order','validate','status','balance',
                'resend_approval','config_link'];
    }

    // Methods that throw UnsupportedOperationException:
    // reissueCertificate(), renewCertificate(), revokeCertificate(),
    // cancelOrder(), downloadCertificate(), getDcvEmails(), changeDcvMethod()

    public function getConfigurationLink(string $orderId): string
    {
        // Calls GetSSLConfigurationLink API
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
   ├─ Check for vendor migration (existing tblsslorders with other module)
   │   ├─ YES → Show migration warning, offer "Allow New Certificate"
   │   └─ NO → Continue
   │
   ├─ Resolve provider:
   │   ├─ configoption2 = specific slug → use that provider
   │   ├─ configoption2 = 'auto' → PriceCompareService::getCheapest()
   │   └─ configoption2 = empty → use first enabled provider
   │
   ├─ Create tblsslorders record:
   │   ├─ module = 'aio_ssl'
   │   ├─ certtype = canonical_id from configoption1
   │   ├─ status = 'Awaiting Configuration'
   │   └─ configdata = { provider: slug, canonical_id: ... }
   │
   └─ Return success → Client sees "Configure Certificate" in client area
```

### 10.2 Certificate Application Flow (Client Area)

```
Client clicks "Configure Certificate"
│
├─ Step 1: CSR
│   ├─ Option A: Paste existing CSR
│   ├─ Option B: Auto-generate CSR (key pair stored in configdata)
│   └─ CSR decoded → domains extracted → save as Draft
│
├─ Step 2: Domain Validation
│   ├─ Provider supports DCV emails? → Fetch email list
│   ├─ Select method per domain (EMAIL / HTTP / CNAME / HTTPS)
│   └─ Save DCV choices to configdata
│
├─ Step 3: Contacts (OV/EV only)
│   ├─ Admin contact (pre-filled from client profile)
│   ├─ Tech contact (option: same as admin)
│   └─ Organization info
│
├─ Step 4: Confirm & Submit
│   ├─ ProviderBridge::getProvider() → get provider instance
│   ├─ provider->validateOrder(params)
│   ├─ provider->placeOrder(params)
│   ├─ Update tblsslorders: remoteid, status → Pending
│   └─ configdata updated with full order details
│
└─ Auto-sync picks up from here → polls status until Complete
```

### 10.3 Legacy Order Read Flow

```
Admin opens Order Management in AIO
│
├─ Query tblsslorders for ALL orders:
│   WHERE module IN ('aio_ssl','nicsrs_ssl','SSLCENTERWHMCS','thesslstore_ssl','ssl2buy')
│   OR serviceid IN (services with servertype='aio_ssl')
│
├─ For each order:
│   ├─ module = 'aio_ssl' → use configdata.provider to resolve provider
│   ├─ module = 'nicsrs_ssl' → treat as NicSRS provider (read-only unless claimed)
│   ├─ module = 'SSLCENTERWHMCS' → treat as GoGetSSL provider (read-only unless claimed)
│   ├─ module = 'thesslstore_ssl' → treat as TheSSLStore (read-only unless claimed)
│   └─ module = 'ssl2buy' → treat as SSL2Buy (read-only unless claimed)
│
└─ Display unified table with provider badge
```

---

## 11. Backward Compatibility & Migration

### 11.1 Legacy Module Detection

| Legacy Module | Module Name in `tblsslorders` | configdata Format |
|--------------|-------------------------------|-------------------|
| NicSRS | `nicsrs_ssl` | JSON with `csr`, `crt`, `ca`, `private_key`, `domainInfo`, `beginDate`, `endDate` |
| GoGetSSL | `SSLCENTERWHMCS` | JSON/serialized with `csr`, `crt`, `ca`, `approver_email`, `order_id` |
| TheSSLStore | `thesslstore_ssl` | JSON with `csr`, `TheSSLStoreOrderID`, `crt_code`, `ca_code` |
| SSL2Buy | `ssl2buy` | JSON with `orderId`, `csr`, `brand_name`, configdata varies by CA |

### 11.2 configdata Normalization

The `MigrationService` normalizes legacy configdata formats:

```php
class MigrationService
{
    public function normalizeConfigdata(string $module, $configdata): array
    {
        $data = is_string($configdata) ? json_decode($configdata, true) : (array)$configdata;

        // Fall back to unserialize for old WHMCS versions
        if (empty($data) && is_string($configdata)) {
            $data = @unserialize($configdata);
        }

        return match ($module) {
            'nicsrs_ssl'      => $this->normalizeNicsrs($data),
            'SSLCENTERWHMCS'  => $this->normalizeGoGetSSL($data),
            'thesslstore_ssl' => $this->normalizeTheSSLStore($data),
            'ssl2buy'         => $this->normalizeSSL2Buy($data),
            default           => $data,
        };
    }
}
```

### 11.3 Client Interaction with Legacy Orders

When a client views a service linked to a legacy SSL order:
1. AIO server module checks `tblsslorders` for matching `serviceid`
2. If `module` != `aio_ssl`, render `migrated.tpl` (read-only view)
3. Shows: certificate details, status, expiry, domains, download (if cert available)
4. Admin can "Claim" the order → updates module to `aio_ssl`, enriches configdata

### 11.4 Zero-Downtime Transition Plan

1. Install AIO module alongside existing modules
2. AIO reads from `tblsslorders` without modifying legacy records
3. New products created with `servertype=aio_ssl`
4. Existing products can be gradually switched (`servertype` change)
5. `CreateAccount` detects legacy cert → offers migration
6. Legacy modules can be deactivated after all orders are claimed

---

## 12. Security Architecture

### 12.1 Credential Encryption

All provider API credentials encrypted at rest using AES-256-CBC:

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
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', self::getKey(), 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    public static function decrypt(string $ciphertext): string
    {
        $data = base64_decode($ciphertext);
        [$iv, $encrypted] = explode('::', $data, 2);
        return openssl_decrypt($encrypted, 'AES-256-CBC', self::getKey(), 0, $iv);
    }
}
```

### 12.2 Access Control

- Admin area: WHMCS admin session required (`defined('ADMINAREA')`)
- Client area: `ServiceOwnership` validation via `tblhosting.userid`
- AJAX requests: CSRF + session validation
- API tokens masked in all logs (first 8 chars + `***`)

### 12.3 Input Validation

- All user inputs sanitized via `htmlspecialchars()` + Capsule ORM parameterized queries
- CSR format validated before API submission
- Domain names validated with regex
- JSON payloads validated with `json_last_error()`
- File uploads (CSR/key files) validated for content type and size

---

## 13. UI/UX Design Specifications

### 13.1 Admin Navigation

```
Addons → AIO SSL Manager
│
├── Dashboard         — Unified stats, charts, alerts
├── Providers         — Add/edit/test/disable providers
├── Products          — Catalog browser, sync, mapping
├── Price Compare     — Cross-provider price matrix
├── Orders            — Unified order list, detail, actions
├── Import            — Legacy migration, API import, bulk import
├── Reports           — Revenue, performance, expiry forecast
└── Settings          — Sync config, notifications, currency
```

### 13.2 Provider Management UI

```
┌──────────────────────────────────────────────────────────┐
│  Providers                                    [+ Add]    │
├────┬──────────┬──────┬─────────┬──────────┬──────┬──────┤
│ #  │ Provider │ Tier │ Status  │ API Test │ Sync │ Act. │
├────┼──────────┼──────┼─────────┼──────────┼──────┼──────┤
│ 1  │ NicSRS   │ Full │ 🟢 On  │ ✅ OK    │ 2m   │ ⚙️   │
│ 2  │ GoGetSSL │ Full │ 🟢 On  │ ✅ OK    │ 5m   │ ⚙️   │
│ 3  │ TheSSLSt │ Full │ 🟢 On  │ ✅ OK    │ 8m   │ ⚙️   │
│ 4  │ SSL2Buy  │ Ltd  │ 🟡 On  │ ✅ OK    │ 3m   │ ⚙️   │
└────┴──────────┴──────┴─────────┴──────────┴──────┴──────┘
```

### 13.3 Price Comparison UI

```
┌──────────────────────────────────────────────────────────────────┐
│  Price Compare: Sectigo PositiveSSL                              │
│  Validation: DV | Type: Single Domain | Wildcard: No             │
├──────────┬──────────┬──────────┬──────────┬─────────────────────┤
│ Provider │ 1 Year   │ 2 Years  │ 3 Years  │ SAN (per/yr)       │
├──────────┼──────────┼──────────┼──────────┼─────────────────────┤
│ NicSRS   │ $7.95    │ $15.90   │ $23.85   │ N/A                │
│          │ ★ Best   │          │ ★ Best   │                     │
│ GoGetSSL │ $8.50    │ $16.00   │ $24.00   │ N/A                │
│ TheSSLSt │ $9.50    │ $18.00   │ $27.00   │ N/A                │
│ SSL2Buy  │ $8.00    │ $15.50 ★ │ $24.50  │ N/A                │
├──────────┴──────────┴──────────┴──────────┴─────────────────────┤
│ WHMCS Sell Price: $24.99/yr  │  Best Margin: NicSRS ($17.04)    │
└──────────────────────────────────────────────────────────────────┘
```

---

## 14. Implementation Plan

### Phase 1: Foundation (Est. 80h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 1.1 | Project scaffolding: file structure, autoloader, module entry points | Critical | 8h |
| 1.2 | Database schema: all 5 custom tables + migration script | Critical | 8h |
| 1.3 | `ProviderInterface`, `AbstractProvider`, `ProviderFactory`, `ProviderRegistry` | Critical | 12h |
| 1.4 | `EncryptionService` for credential storage | Critical | 4h |
| 1.5 | Provider CRUD: `ProviderController` + template | Critical | 12h |
| 1.6 | NicSRS provider plugin (port from existing module) | Critical | 16h |
| 1.7 | Settings controller with sync configuration | High | 8h |
| 1.8 | Basic admin navigation + Bootstrap UI framework | High | 8h |
| 1.9 | Activation/deactivation/upgrade handlers | High | 4h |

### Phase 2: Provider Plugins (Est. 100h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 2.1 | GoGetSSL provider plugin (auth, products, lifecycle) | Critical | 24h |
| 2.2 | TheSSLStore provider plugin (REST JSON, full lifecycle) | Critical | 24h |
| 2.3 | SSL2Buy provider plugin (limited tier, brand routing) | Critical | 20h |
| 2.4 | Product catalog sync service (all providers) | Critical | 12h |
| 2.5 | `ProductMapService` — auto-mapping + admin UI | Critical | 12h |
| 2.6 | `PriceCompareService` + comparison UI | High | 8h |

### Phase 3: Server Module & Client Area (Est. 80h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 3.1 | Server module: ConfigOptions, CreateAccount, MetaData | Critical | 8h |
| 3.2 | `ProviderBridge` — resolves provider from service/order | Critical | 8h |
| 3.3 | Multi-step client area (apply, CSR, DCV, contacts) | Critical | 20h |
| 3.4 | Certificate download, reissue, renew, revoke actions | Critical | 16h |
| 3.5 | SSL2Buy limited-tier client area (config link + PIN) | High | 8h |
| 3.6 | Admin service tab + custom buttons | High | 8h |
| 3.7 | Client area templates (apply, pending, complete, migrated) | High | 12h |

### Phase 4: Dashboard, Reports, Migration (Est. 90h)

| # | Task | Priority | Est. |
|---|------|----------|------|
| 4.1 | Unified dashboard with Chart.js | High | 12h |
| 4.2 | Order management controller (list, detail, actions) | Critical | 16h |
| 4.3 | Auto-sync engine via WHMCS hooks | Critical | 12h |
| 4.4 | Notification service (issuance, expiry, sync errors, price changes) | High | 8h |
| 4.5 | Legacy module migration service | Critical | 16h |
| 4.6 | Import controller (single, bulk, API import) | High | 8h |
| 4.7 | Report service (revenue, performance, by provider) | Medium | 10h |
| 4.8 | Multi-language (English + Vietnamese) | Medium | 8h |

### Total Estimated: ~350 hours

### Milestone Summary

| Phase | Milestone | Deliverable |
|-------|-----------|-------------|
| Phase 1 | Foundation complete | Working admin with provider CRUD, NicSRS integrated |
| Phase 2 | All providers integrated | 4 providers functional, price comparison working |
| Phase 3 | Client area complete | Full certificate lifecycle for all providers |
| Phase 4 | Production ready | Dashboard, reports, migration, notifications |

---

## 15. Risk Assessment

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| SSL2Buy API too limited for basic management | High | Confirmed | Two-tier architecture with config link fallback |
| Provider API changes break integration | Medium | Low | Abstract provider interface isolates changes |
| Legacy configdata format inconsistencies | High | Medium | Thorough format analysis + fallback parsing |
| Performance with 10K+ orders across providers | Medium | Low | Database indexing, pagination, lazy loading |
| WHMCS version incompatibility | Medium | Low | Target 7.10+, use Capsule ORM, test on 8.x |
| Credential security breach | Critical | Low | AES-256-CBC encryption, masked logging |
| Concurrent sync conflicts (multiple cron runs) | Medium | Medium | File-based lock + `sync_error_count` tracking |
| Product mapping errors (wrong canonical match) | Medium | Medium | Admin review UI + manual override capability |

---

## Appendix A: WHMCS `tblsslorders` Schema Reference

```sql
CREATE TABLE `tblsslorders` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `userid` int(10) NOT NULL DEFAULT 0,
  `serviceid` int(10) NOT NULL DEFAULT 0,
  `addon_id` int(10) NOT NULL DEFAULT 0,
  `remoteid` varchar(255) NOT NULL DEFAULT '',
  `module` varchar(255) NOT NULL DEFAULT '',
  `certtype` varchar(255) NOT NULL DEFAULT '',
  `completiondate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` varchar(255) NOT NULL DEFAULT '',
  `configdata` text,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `serviceid` (`serviceid`),
  KEY `addon_id` (`addon_id`),
  KEY `remoteid` (`remoteid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

---

## Appendix B: Provider Authentication Reference

### B.1 NicSRS
```php
// POST with api_token as form field
$data = ['api_token' => $token, 'vendor' => 'Sectigo'];
$response = curl_post('https://portal.nicsrs.com/ssl/productList', http_build_query($data));
```

### B.2 GoGetSSL
```php
// Step 1: Authenticate → get session token
$auth = curl_post('https://my.gogetssl.com/api/auth/', http_build_query([
    'user' => $username, 'pass' => $password
]));
$token = $auth['key'];

// Step 2: Use token for subsequent calls
$products = curl_get('https://my.gogetssl.com/api/products/?auth_key=' . $token);
```

### B.3 TheSSLStore
```php
// JSON body with AuthRequest object
$payload = json_encode([
    'AuthRequest' => [
        'PartnerCode' => $partnerCode,
        'AuthToken' => $authToken,
    ]
]);
$response = curl_post('https://api.thesslstore.com/rest/product/query', $payload, [
    'Content-Type: application/json; charset=utf-8'
]);
```

### B.4 SSL2Buy
```php
// JSON body with PartnerEmail + ApiKey
$payload = json_encode([
    'PartnerEmail' => $email,
    'ApiKey' => $apiKey,
    'ProductCode' => 351,
    'NumberOfMonths' => 12
]);
$response = curl_post('https://api.ssl2buy.com/orderservice/order/getproductprice', $payload, [
    'Content-Type: application/json'
]);
```

---

**© HVN GROUP** — All rights reserved.  
**Document Version:** 1.0.0 | **Status:** Ready for Implementation