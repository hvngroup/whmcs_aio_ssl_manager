# DATABASE-STRUCTURE.md

## Database Structure - NicSRS SSL WHMCS Module

**Version:** 2.0.0  
**Last Updated:** 2025-01-19  
**Maintainer:** HVN GROUP - Development Team

---

## Table of Contents

1. [Overview](#overview)
2. [Database Schema Diagram](#database-schema-diagram)
3. [Custom Tables](#custom-tables)
4. [WHMCS Core Tables](#whmcs-core-tables)
5. [Relationships](#relationships)
6. [Indexes & Performance](#indexes--performance)
7. [Data Types & Constraints](#data-types--constraints)
8. [Migration Scripts](#migration-scripts)

---

## 1. Overview

### 1.1 Database Architecture

The NicSRS SSL module uses a **hybrid database design**:
- **Custom tables** for SSL-specific data
- **References** to WHMCS core tables
- **JSON fields** for flexible configuration storage

**Database Engine:** InnoDB (ACID compliant, supports foreign keys)  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_unicode_ci

### 1.2 Tables Summary

| Table | Module | Purpose | Records (typical) |
|-------|--------|---------|-------------------|
| `nicsrs_sslorders` | Server | SSL certificate orders | 100-10,000 |
| `mod_nicsrs_products` | Admin | Product catalog cache | 50-200 |
| `mod_nicsrs_settings` | Admin | Module configuration | 15-30 |
| `mod_nicsrs_activity_log` | Admin | Audit trail | 1,000-100,000 |

---

## 2. Database Schema Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      WHMCS Core Tables                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐        ┌──────────────┐      ┌─────────────┐ │
│  │ tblclients  │        │ tblproducts  │      │  tbladmins  │ │
│  ├─────────────┤        ├──────────────┤      ├─────────────┤ │
│  │ id (PK)     │        │ id (PK)      │      │ id (PK)     │ │
│  │ firstname   │        │ name         │      │ username    │ │
│  │ lastname    │        │ description  │      │ firstname   │ │
│  │ email       │        │ servertype   │      │ lastname    │ │
│  │ country     │        │ configoption1│◄─┐   │ email       │ │
│  └─────────────┘        │ configoption2│  │   └─────────────┘ │
│         │               └──────────────┘  │          │        │
│         │                      │          │          │        │
│         │               ┌──────────────┐  │          │        │
│         │               │ tblhosting   │  │          │        │
│         │               ├──────────────┤  │          │        │
│         │               │ id (PK)      │  │          │        │
│         │               │ userid (FK)  │  │          │        │
│         │               │ packageid(FK)│  │          │        │
│         │               │ domainstatus │  │          │        │
│         │               └──────────────┘  │          │        │
│         │                      │          │          │        │
└─────────┼──────────────────────┼──────────┼──────────┼────────┘
          │                      │          │          │
          │                      │          │          │
┌─────────┼──────────────────────┼──────────┼──────────┼────────┐
│         │    Custom Tables     │          │          │        │
├─────────┼──────────────────────┼──────────┼──────────┼────────┤
│         │                      │          │          │        │
│  ┌──────▼──────────┐    ┌──────▼──────────────┐     │        │
│  │ nicsrs_sslorders│    │mod_nicsrs_products  │     │        │
│  ├─────────────────┤    ├─────────────────────┤     │        │
│  │ id (PK)         │    │ id (PK)             │     │        │
│  │ userid (FK) ────┼───►│ product_code (UK)   │◄────┘        │
│  │ serviceid (FK)  │    │ product_name        │              │
│  │ remoteid        │    │ vendor              │              │
│  │ certtype        │    │ validation_type     │              │
│  │ configdata      │    │ support_wildcard    │              │
│  │ status          │    │ support_san         │              │
│  │ provisiondate   │    │ max_domains         │              │
│  │ completiondate  │    │ price_data          │              │
│  └─────────────────┘    └─────────────────────┘              │
│                                                               │
│  ┌──────────────────┐   ┌─────────────────────────┐          │
│  │mod_nicsrs_settings│   │mod_nicsrs_activity_log  │          │
│  ├──────────────────┤   ├─────────────────────────┤          │
│  │ id (PK)          │   │ id (PK)                 │          │
│  │ setting_key (UK) │   │ admin_id (FK) ──────────┼─────────►│
│  │ setting_value    │   │ action                  │          │
│  │ setting_type     │   │ entity_type             │          │
│  │ created_at       │   │ entity_id               │          │
│  │ updated_at       │   │ old_value               │          │
│  └──────────────────┘   │ new_value               │          │
│                         │ ip_address              │          │
│                         │ created_at              │          │
│                         └─────────────────────────┘          │
└─────────────────────────────────────────────────────────────┘
```

**Legend:**
- `PK` = Primary Key
- `FK` = Foreign Key (logical, not enforced)
- `UK` = Unique Key
- `───►` = Relationship

---

## 3. Custom Tables

### 3.1 `nicsrs_sslorders`

**Purpose:** Stores SSL certificate order information, linked to WHMCS services.

**Owner:** Server Provision Module (`nicsrs_ssl`)

#### Schema

```sql
CREATE TABLE `nicsrs_sslorders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL,
  `serviceid` int(10) unsigned NOT NULL,
  `addon_id` text COLLATE utf8mb4_unicode_ci,
  `remoteid` text COLLATE utf8mb4_unicode_ci,
  `module` text COLLATE utf8mb4_unicode_ci,
  `certtype` text COLLATE utf8mb4_unicode_ci,
  `configdata` longtext COLLATE utf8mb4_unicode_ci,
  `provisiondate` date DEFAULT NULL,
  `completiondate` datetime DEFAULT '0000-00-00 00:00:00',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Awaiting Configuration',
  PRIMARY KEY (`id`),
  KEY `idx_userid` (`userid`),
  KEY `idx_serviceid` (`serviceid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Field Definitions

| Field | Type | Null | Description |
|-------|------|------|-------------|
| `id` | int(10) unsigned | NO | Primary key, auto-increment |
| `userid` | int(10) unsigned | NO | WHMCS client ID (tblclients.id) |
| `serviceid` | int(10) unsigned | NO | WHMCS service ID (tblhosting.id) |
| `addon_id` | text | YES | Optional addon service ID |
| `remoteid` | text | YES | NicSRS certificate ID (certId) |
| `module` | text | YES | Original provisioning module (for migrations) |
| `certtype` | text | YES | Product code (e.g., "sectigo-positivessl-dv") |
| `configdata` | longtext | YES | JSON configuration (see structure below) |
| `provisiondate` | date | YES | Date order was created |
| `completiondate` | datetime | YES | Date certificate was issued |
| `status` | varchar(50) | YES | Current order status |

#### `configdata` JSON Structure

```json
{
  "csr": "-----BEGIN CERTIFICATE REQUEST-----...",
  "domainInfo": [
    {
      "domainName": "example.com",
      "dcvMethod": "EMAIL",
      "dcvEmail": "admin@example.com"
    },
    {
      "domainName": "www.example.com",
      "dcvMethod": "CNAME_CSR_HASH"
    }
  ],
  "Administrator": {
    "firstname": "John",
    "lastname": "Doe",
    "organization": "Acme Corp",
    "email": "john@acme.com",
    "mobile": "+84.123456789",
    "address": "123 Main St",
    "city": "Hanoi",
    "state": "HN",
    "postCode": "100000",
    "country": "VN"
  },
  "organizationInfo": {
    "organizationName": "Acme Corporation",
    "organizationAddress": "123 Business Ave",
    "organizationCity": "Ho Chi Minh",
    "organizationState": "HCM",
    "organizationPostCode": "700000",
    "organizationCountry": "VN",
    "organizationPhone": "+84.987654321"
  },
  "applyReturn": {
    "certId": "12345678",
    "vendorId": "VC123456",
    "certStatus": "complete",
    "certificate": "-----BEGIN CERTIFICATE-----...",
    "caCertificate": "-----BEGIN CERTIFICATE-----...",
    "privateKey": "-----BEGIN PRIVATE KEY-----...",
    "pkcs12": "base64encodedPKCS12...",
    "pkcsPass": "randomPassword123",
    "jks": "base64encodedJKS...",
    "jksPass": "randomPassword456",
    "beginDate": "2025-01-01",
    "endDate": "2026-01-01",
    "orderDate": "2025-01-01 10:30:00",
    "verificationDetails": [
      {
        "domain": "example.com",
        "dcvMethod": "EMAIL",
        "dcvEmail": "admin@example.com",
        "dcvStatus": "validated"
      }
    ]
  }
}
```

#### Status Values

| Status | Description | Typical Duration |
|--------|-------------|------------------|
| `Awaiting Configuration` | Initial state, no CSR yet | Until customer submits |
| `Draft` | CSR saved, not yet submitted | Until customer confirms |
| `Pending` | Submitted to CA, awaiting DCV | 1-7 days |
| `Processing` | DCV complete, CA processing | 1-24 hours |
| `Complete` | Certificate issued | Permanent |
| `Cancelled` | Order cancelled by customer/admin | Permanent |
| `Revoked` | Certificate revoked | Permanent |
| `Expired` | Certificate expired | Permanent |
| `Reissue` | Reissue in progress | 1-7 days |

#### Indexes

- `PRIMARY` on `id` - Fast lookups by order ID
- `idx_userid` on `userid` - Query orders by customer
- `idx_serviceid` on `serviceid` - Query orders by service (most common)
- `idx_status` on `status` - Filter by status (e.g., pending certificates)

---

### 3.2 `mod_nicsrs_products`

**Purpose:** Cached product catalog from NicSRS API, synced periodically.

**Owner:** Admin Addon Module (`nicsrs_ssl_admin`)

#### Schema

```sql
CREATE TABLE `mod_nicsrs_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validation_type` enum('dv','ov','ev') COLLATE utf8mb4_unicode_ci NOT NULL,
  `support_wildcard` tinyint(1) NOT NULL DEFAULT '0',
  `support_san` tinyint(1) NOT NULL DEFAULT '0',
  `max_domains` int(11) NOT NULL DEFAULT '1',
  `max_years` int(11) NOT NULL DEFAULT '1',
  `price_data` text COLLATE utf8mb4_unicode_ci,
  `last_sync` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_code` (`product_code`),
  KEY `idx_vendor` (`vendor`),
  KEY `idx_validation_type` (`validation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Field Definitions

| Field | Type | Null | Description |
|-------|------|------|-------------|
| `id` | int(10) unsigned | NO | Primary key |
| `product_code` | varchar(100) | NO | Unique product identifier (e.g., "sectigo-positivessl-dv") |
| `product_name` | varchar(255) | NO | Display name (e.g., "Sectigo PositiveSSL DV") |
| `vendor` | varchar(50) | NO | CA vendor (Sectigo, DigiCert, GlobalSign, etc.) |
| `validation_type` | enum('dv','ov','ev') | NO | Validation level |
| `support_wildcard` | tinyint(1) | NO | Supports wildcard domains (*.example.com) |
| `support_san` | tinyint(1) | NO | Supports Subject Alternative Names (SAN/multi-domain) |
| `max_domains` | int(11) | NO | Maximum domains per certificate |
| `max_years` | int(11) | NO | Maximum validity period in years |
| `price_data` | text | YES | JSON pricing info from API |
| `last_sync` | datetime | YES | Last sync timestamp |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record last update timestamp |

#### `price_data` JSON Structure

```json
{
  "1_year": {
    "usd": 7.95,
    "vnd": 208000
  },
  "2_years": {
    "usd": 15.90,
    "vnd": 416000
  },
  "3_years": {
    "usd": 23.85,
    "vnd": 624000
  }
}
```

#### Sample Data

```sql
INSERT INTO mod_nicsrs_products VALUES
(1, 'sectigo-positivessl-dv', 'Sectigo PositiveSSL DV', 'Sectigo', 'dv', 0, 0, 1, 1, '{"1_year":{"usd":7.95}}', NOW(), NOW(), NOW()),
(2, 'sectigo-positivessl-wildcard-dv', 'Sectigo PositiveSSL Wildcard DV', 'Sectigo', 'dv', 1, 0, 1, 1, '{"1_year":{"usd":79.95}}', NOW(), NOW(), NOW()),
(3, 'sectigo-positivessl-multi-domain-dv', 'Sectigo PositiveSSL Multi-Domain DV', 'Sectigo', 'dv', 0, 1, 5, 1, '{"1_year":{"usd":29.95}}', NOW(), NOW(), NOW());
```

#### Indexes

- `PRIMARY` on `id` - Default primary key
- `uk_product_code` on `product_code` - Ensure uniqueness, fast lookup
- `idx_vendor` on `vendor` - Filter products by CA vendor
- `idx_validation_type` on `validation_type` - Filter by validation level

---

### 3.3 `mod_nicsrs_settings`

**Purpose:** Module configuration and settings storage (key-value store).

**Owner:** Admin Addon Module (`nicsrs_ssl_admin`)

#### Schema

```sql
CREATE TABLE `mod_nicsrs_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Field Definitions

| Field | Type | Null | Description |
|-------|------|------|-------------|
| `id` | int(10) unsigned | NO | Primary key |
| `setting_key` | varchar(100) | NO | Unique setting identifier |
| `setting_value` | text | YES | Setting value (stored as string) |
| `setting_type` | varchar(20) | YES | Data type hint (string, boolean, integer, datetime, number) |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record last update timestamp |

#### Default Settings

```sql
INSERT INTO mod_nicsrs_settings (setting_key, setting_value, setting_type) VALUES
-- Notification Settings
('email_on_issuance', '1', 'boolean'),
('email_on_expiry', '1', 'boolean'),
('expiry_days', '30', 'integer'),
('admin_email', 'admin@example.com', 'string'),

-- Auto-Sync Settings
('auto_sync_status', '1', 'boolean'),
('sync_interval_hours', '6', 'integer'),
('product_sync_hours', '24', 'integer'),
('sync_batch_size', '50', 'integer'),
('last_status_sync', '', 'datetime'),
('last_product_sync', '', 'datetime'),
('sync_error_count', '0', 'integer'),

-- Display Settings
('date_format', 'Y-m-d', 'string'),

-- Currency Settings
('usd_vnd_rate', '26200', 'number'),
('currency_display', 'both', 'string'),
('rate_last_updated', '', 'datetime');
```

#### Setting Types

| Type | PHP Cast | Example Value |
|------|----------|---------------|
| `string` | (string) | "hello" |
| `boolean` | (bool) | "1", "0" |
| `integer` | (int) | "50" |
| `number` | (float) | "26200.50" |
| `datetime` | DateTime | "2025-01-19 10:30:00" |

#### Indexes

- `PRIMARY` on `id` - Default primary key
- `uk_setting_key` on `setting_key` - Ensure uniqueness, fast lookup

---

### 3.4 `mod_nicsrs_activity_log`

**Purpose:** Audit trail for all administrative actions in the module.

**Owner:** Admin Addon Module (`nicsrs_ssl_admin`)

#### Schema

```sql
CREATE TABLE `mod_nicsrs_activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Field Definitions

| Field | Type | Null | Description |
|-------|------|------|-------------|
| `id` | int(10) unsigned | NO | Primary key |
| `admin_id` | int(11) | NO | Admin user ID (tbladmins.id) |
| `action` | varchar(50) | NO | Action performed (edit, delete, sync, etc.) |
| `entity_type` | varchar(50) | YES | Entity being modified (order, product, settings) |
| `entity_id` | int(11) | YES | Entity primary key |
| `old_value` | text | YES | JSON: previous state |
| `new_value` | text | YES | JSON: new state |
| `ip_address` | varchar(45) | YES | Admin IP address (IPv4/IPv6) |
| `user_agent` | varchar(255) | YES | Browser user agent string |
| `created_at` | timestamp | YES | Action timestamp |

#### Action Types

| Action | Entity Type | Description |
|--------|-------------|-------------|
| `edit` | order | Modified order details |
| `delete` | order | Deleted order record |
| `sync` | settings | Triggered manual sync |
| `update_settings` | settings | Changed module settings |
| `import` | order | Imported certificate |
| `download` | order | Downloaded certificate |
| `revoke` | order | Revoked certificate |

#### Sample Data

```sql
INSERT INTO mod_nicsrs_activity_log VALUES
(1, 1, 'edit', 'order', 123, '{"status":"Pending"}', '{"status":"Complete"}', '192.168.1.1', 'Mozilla/5.0...', NOW()),
(2, 1, 'sync', 'settings', NULL, NULL, '{"synced":50}', '192.168.1.1', 'Mozilla/5.0...', NOW());
```

#### Indexes

- `PRIMARY` on `id` - Default primary key
- `idx_admin_id` on `admin_id` - Query logs by admin
- `idx_action` on `action` - Filter by action type
- `idx_entity` on `(entity_type, entity_id)` - Query entity history
- `idx_created_at` on `created_at` - Time-based queries (recent activity)

---

## 4. WHMCS Core Tables

### 4.1 Referenced Tables

**Note:** These tables are managed by WHMCS core. **Never modify schema directly.**

#### `tblclients`

```sql
-- Relevant fields only
CREATE TABLE `tblclients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(50),
  `lastname` varchar(50),
  `companyname` varchar(100),
  `email` varchar(255),
  `phonenumber` varchar(30),
  `address1` varchar(100),
  `city` varchar(50),
  `state` varchar(50),
  `postcode` varchar(20),
  `country` varchar(2),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

**Usage:** Pre-fill customer info in certificate application forms.

#### `tblhosting`

```sql
-- Relevant fields only
CREATE TABLE `tblhosting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned,
  `packageid` int(10) unsigned,
  `domain` varchar(255),
  `domainstatus` varchar(20),
  `firstpaymentamount` decimal(10,2),
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `packageid` (`packageid`)
) ENGINE=InnoDB;
```

**Usage:** Link SSL orders to WHMCS services.

#### `tblproducts`

```sql
-- Relevant fields only
CREATE TABLE `tblproducts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255),
  `servertype` varchar(50),        -- Module name (nicsrs_ssl)
  `configoption1` text,             -- Product code (certtype)
  `configoption2` text,             -- API token (optional)
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

**Usage:** Product configuration for SSL certificates.

**Important Relationship:**
```
tblproducts.configoption1 = mod_nicsrs_products.product_code
```

#### `tbladmins`

```sql
-- Relevant fields only
CREATE TABLE `tbladmins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50),
  `firstname` varchar(50),
  `lastname` varchar(50),
  `email` varchar(255),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

**Usage:** Admin user tracking in activity logs.

#### `tbladdonmodules`

```sql
-- Relevant fields only
CREATE TABLE `tbladdonmodules` (
  `module` varchar(50),
  `setting` varchar(50),
  `value` text,
  UNIQUE KEY `module_setting` (`module`, `setting`)
) ENGINE=InnoDB;
```

**Usage:** Admin addon configuration (API token, items per page).

---

## 5. Relationships

### 5.1 Entity Relationship Diagram

```
tblclients (1) ──────► (N) tblhosting
                             │
                             │ (1)
                             ▼
                         (1) nicsrs_sslorders
                             │
                             │ (N)
                             ▼
                         (1) mod_nicsrs_products
                                 (via certtype = product_code)

tbladmins (1) ──────► (N) mod_nicsrs_activity_log
```

### 5.2 Lookup Patterns

#### Get Order with Service Details

```php
$order = Capsule::table('nicsrs_sslorders as o')
    ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
    ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
    ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
    ->where('o.id', $orderId)
    ->select([
        'o.*',
        'h.domain',
        'h.domainstatus',
        'p.name as product_name',
        'c.firstname',
        'c.lastname',
        'c.email'
    ])
    ->first();
```

#### Get Product Info from Order

```php
$order = OrderRepository::getById($orderId);
$configData = json_decode($order->configdata, true);
$productCode = $configData['certtype'] ?? $order->certtype;

$product = Capsule::table('mod_nicsrs_products')
    ->where('product_code', $productCode)
    ->first();
```

#### Get Orders by Client

```php
$orders = Capsule::table('nicsrs_sslorders')
    ->where('userid', $userId)
    ->orderBy('id', 'desc')
    ->get();
```

---

## 6. Indexes & Performance

### 6.1 Index Strategy

**Primary Keys:**
- Auto-increment integers (clustered index in InnoDB)
- Fast inserts, efficient storage

**Foreign Key Indexes:**
- `userid`, `serviceid` in `nicsrs_sslorders`
- Enable fast JOIN operations

**Status Index:**
- Critical for sync queries (`WHERE status = 'Pending'`)
- Covers ~10-20% of records typically

**Composite Indexes:**
- `(entity_type, entity_id)` in activity log
- Efficient entity history queries

### 6.2 Query Optimization

#### Slow Query: Find Pending Certificates

**Before (no index on status):**
```sql
SELECT * FROM nicsrs_sslorders WHERE status = 'Pending';
-- Full table scan: O(n)
```

**After (with index):**
```sql
ALTER TABLE nicsrs_sslorders ADD KEY `idx_status` (`status`);
SELECT * FROM nicsrs_sslorders WHERE status = 'Pending';
-- Index scan: O(log n)
```

#### Covering Index for Sync

```sql
-- Create covering index
ALTER TABLE nicsrs_sslorders 
ADD KEY `idx_sync_cover` (`status`, `remoteid`, `id`);

-- Query uses index only, no table lookup
SELECT id, remoteid FROM nicsrs_sslorders WHERE status = 'Pending';
```

### 6.3 JSON Field Performance

**Limitation:** Cannot index JSON fields directly in MySQL < 8.0

**Workaround:** Use generated columns (MySQL 5.7+)

```sql
-- Extract certId from JSON
ALTER TABLE nicsrs_sslorders 
ADD COLUMN cert_id_generated VARCHAR(50) 
GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(configdata, '$.applyReturn.certId'))) STORED,
ADD KEY `idx_cert_id` (`cert_id_generated`);

-- Now can query efficiently
SELECT * FROM nicsrs_sslorders WHERE cert_id_generated = '12345678';
```

---

## 7. Data Types & Constraints

### 7.1 Field Type Guidelines

| Data Type | Use Case | Example |
|-----------|----------|---------|
| `int(10) unsigned` | Primary keys, foreign keys | id, userid, serviceid |
| `varchar(50)` | Short text, codes | status, vendor |
| `varchar(255)` | Longer text, names | product_name, email |
| `text` | Medium text (< 64KB) | setting_value, price_data |
| `longtext` | Large text (< 4GB) | configdata (JSON) |
| `datetime` | Timestamp with date | completiondate, last_sync |
| `timestamp` | Auto-updating timestamp | created_at, updated_at |
| `enum('dv','ov','ev')` | Fixed set of values | validation_type |
| `tinyint(1)` | Boolean flags | support_wildcard, auto_sync_status |
| `decimal(10,2)` | Currency amounts | firstpaymentamount |

### 7.2 Constraints

#### NOT NULL Constraints

```sql
-- Required fields
userid int(10) unsigned NOT NULL,
serviceid int(10) unsigned NOT NULL,
product_code varchar(100) NOT NULL,
setting_key varchar(100) NOT NULL
```

#### UNIQUE Constraints

```sql
-- Prevent duplicates
UNIQUE KEY `uk_product_code` (`product_code`),
UNIQUE KEY `uk_setting_key` (`setting_key`)
```

#### DEFAULT Values

```sql
-- Sensible defaults
status varchar(50) DEFAULT 'Awaiting Configuration',
support_wildcard tinyint(1) DEFAULT '0',
created_at timestamp DEFAULT CURRENT_TIMESTAMP
```

#### CHECK Constraints (MySQL 8.0+)

```sql
-- Validate ranges
CONSTRAINT chk_max_domains CHECK (max_domains > 0),
CONSTRAINT chk_sync_interval CHECK (sync_interval_hours > 0)
```

### 7.3 Character Sets

**Always use UTF-8:**

```sql
CREATE TABLE xyz (
  ...
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;
```

**Why utf8mb4?**
- Supports full Unicode (including emojis)
- 4-byte characters (utf8 only supports 3-byte)
- Required for international domains (IDN)

---

## 8. Migration Scripts

### 8.1 Initial Installation

**File:** `nicsrs_ssl_admin.php` → `nicsrs_ssl_admin_activate()`

```php
function nicsrs_ssl_admin_activate()
{
    try {
        // Create products table
        if (!Capsule::schema()->hasTable('mod_nicsrs_products')) {
            Capsule::schema()->create('mod_nicsrs_products', function ($table) {
                $table->increments('id');
                $table->string('product_code', 100)->unique();
                $table->string('product_name', 255);
                $table->string('vendor', 50)->index();
                $table->enum('validation_type', ['dv', 'ov', 'ev'])->index();
                $table->boolean('support_wildcard')->default(false);
                $table->boolean('support_san')->default(false);
                $table->integer('max_domains')->default(1);
                $table->integer('max_years')->default(1);
                $table->text('price_data')->nullable();
                $table->dateTime('last_sync')->nullable();
                $table->timestamps();
            });
        }

        // Create activity log table
        if (!Capsule::schema()->hasTable('mod_nicsrs_activity_log')) {
            Capsule::schema()->create('mod_nicsrs_activity_log', function ($table) {
                $table->increments('id');
                $table->integer('admin_id')->index();
                $table->string('action', 50)->index();
                $table->string('entity_type', 50)->nullable();
                $table->integer('entity_id')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
                
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // Create settings table
        if (!Capsule::schema()->hasTable('mod_nicsrs_settings')) {
            Capsule::schema()->create('mod_nicsrs_settings', function ($table) {
                $table->increments('id');
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_type', 20)->default('string');
                $table->timestamps();
            });
            
            // Insert defaults (see section 3.3)
            insertDefaultSettings();
        }

        return ['status' => 'success'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
}
```

### 8.2 Server Module Table Creation

**File:** `OrderRepository.php` → `ensureTableExists()`

```php
public static function ensureTableExists(): void
{
    if (Capsule::schema()->hasTable('nicsrs_sslorders')) {
        return;
    }
    
    Capsule::schema()->create('nicsrs_sslorders', function ($table) {
        $table->increments('id');
        $table->integer('userid')->unsigned();
        $table->integer('serviceid')->unsigned();
        $table->text('addon_id')->nullable();
        $table->text('remoteid')->nullable();
        $table->text('module')->nullable();
        $table->text('certtype')->nullable();
        $table->longText('configdata')->nullable();
        $table->date('provisiondate')->nullable();
        $table->datetime('completiondate')->default('0000-00-00 00:00:00');
        $table->string('status', 50)->default('Awaiting Configuration');
        
        $table->index('userid');
        $table->index('serviceid');
    });
}
```

### 8.3 Upgrade Scripts

#### Add New Index (v1.2.0 → v1.3.0)

```sql
-- Check if index exists
SELECT COUNT(*) 
FROM information_schema.statistics 
WHERE table_name = 'nicsrs_sslorders' 
  AND index_name = 'idx_status' 
  AND table_schema = DATABASE();

-- Add if not exists
ALTER TABLE nicsrs_sslorders ADD KEY `idx_status` (`status`);
```

#### Add New Column (v1.3.0 → v2.0.0)

```php
if (!Capsule::schema()->hasColumn('mod_nicsrs_settings', 'setting_type')) {
    Capsule::schema()->table('mod_nicsrs_settings', function ($table) {
        $table->string('setting_type', 20)->default('string')->after('setting_value');
    });
}
```

### 8.4 Data Migration

#### Migrate from Old SSL Module

```php
function migrateFromOldModule()
{
    $oldOrders = Capsule::table('old_sslorders')
        ->where('module', 'old_ssl_module')
        ->get();
    
    foreach ($oldOrders as $old) {
        $newConfig = [
            'csr' => $old->csr,
            'domainInfo' => json_decode($old->domains, true),
            'applyReturn' => [
                'certId' => $old->cert_id,
            ]
        ];
        
        Capsule::table('nicsrs_sslorders')->insert([
            'userid' => $old->userid,
            'serviceid' => $old->serviceid,
            'remoteid' => $old->cert_id,
            'module' => 'old_ssl_module',
            'certtype' => $old->product_code,
            'configdata' => json_encode($newConfig),
            'status' => mapOldStatus($old->status),
            'provisiondate' => $old->order_date,
        ]);
    }
}
```

---

## Appendix A: SQL Cheat Sheet

### Useful Queries

#### Count Orders by Status

```sql
SELECT status, COUNT(*) as count
FROM nicsrs_sslorders
GROUP BY status
ORDER BY count DESC;
```

#### Find Expiring Certificates

```sql
SELECT o.id, o.remoteid, o.status,
       JSON_UNQUOTE(JSON_EXTRACT(o.configdata, '$.applyReturn.endDate')) as expiry_date
FROM nicsrs_sslorders o
WHERE o.status = 'Complete'
  AND JSON_EXTRACT(o.configdata, '$.applyReturn.endDate') IS NOT NULL
  AND STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(o.configdata, '$.applyReturn.endDate')), '%Y-%m-%d')
      BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY);
```

#### Admin Activity Report

```sql
SELECT 
    a.username,
    l.action,
    l.entity_type,
    COUNT(*) as action_count
FROM mod_nicsrs_activity_log l
JOIN tbladmins a ON l.admin_id = a.id
WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY a.username, l.action, l.entity_type
ORDER BY action_count DESC;
```

#### Product Sync Status

```sql
SELECT 
    vendor,
    COUNT(*) as product_count,
    MAX(last_sync) as last_sync
FROM mod_nicsrs_products
GROUP BY vendor;
```

---

**Document Version:** 1.0  
**Last Review:** 2025-01-19  
**Next Review:** 2025-04-19