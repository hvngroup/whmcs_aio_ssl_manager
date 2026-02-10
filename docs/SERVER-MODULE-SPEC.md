# Server Provision Module Specification

> **Module:** `nicsrs_ssl` | **Version:** 2.1.0  
> **Location:** `modules/servers/nicsrs_ssl/`  
> **Last Updated:** 2026-02-09

---

## 1. WHMCS Module Functions

**File:** `nicsrs_ssl.php` — All standard WHMCS provisioning module functions.

### Configuration

| Function | Description |
|---|---|
| `nicsrs_ssl_ConfigOptions()` | Returns 2 fields: **Certificate Type** (dropdown from `mod_nicsrs_products`) and **API Token Override** (password field). Displays API status and cached product count. |

### Provisioning

| Function | Trigger | Behavior |
|---|---|---|
| `CreateAccount` | Service creation / admin "Create" | Checks for existing order → vendor migration → creates `nicsrs_sslorders` record with status "Awaiting Configuration" |
| `SuspendAccount` | Service suspension | Updates order status (reserved) |
| `TerminateAccount` | Service termination | Updates order status (reserved) |

### Admin Actions

| Function | Button Label | Behavior |
|---|---|---|
| `AdminServicesTabFields` | — | Displays order info OR vendor migration warning in admin service tab |
| `AdminCustomButtonArray` | — | Returns 4 buttons: Manage Order, Refresh Status, Resend DCV Email, Allow New Certificate |
| `AdminManageOrder` | "Manage Order" | Redirects to addon order detail page via JavaScript |
| `AdminRefreshStatus` | "Refresh Status" | Calls `ActionDispatcher::dispatch('refreshStatus')` |
| `AdminResendDCV` | "Resend DCV Email" | Calls `ActionDispatcher::dispatch('resendDCVEmail')` |
| `AdminAllowNewCert` | "Allow New Certificate" | Creates new `nicsrs_sslorders` record with vendor migration flags |

### Client Actions

| Function | Button Label | Condition |
|---|---|---|
| `ClientAreaCustomButtonArray` | — | Returns buttons based on order status |
| `clientDownload` | "Download Certificate" | Status = Complete/Issued |
| `clientReissue` | "Reissue Certificate" | Status = Complete/Issued |
| `clientRefresh` | "Refresh Status" | Status = Pending |
| `ClientArea` | — | **Main entry point**: AJAX routing + page rendering |

---

## 2. Client Area Entry Point (`ClientArea`)

### Request Routing

```
1. Ensure nicsrs_sslorders table exists
2. Check $_REQUEST['step'] for AJAX action
3. If AJAX:
   a. Map step → action via $stepToAction (25+ mappings)
   b. Validate request method = POST or XMLHttpRequest
   c. Clear output buffers, set JSON headers
   d. Call ActionController::$action($params)
   e. Echo JSON response, exit
4. If page view:
   a. Get 'step' or 'modop=custom&a=X' for page name
   b. Default: PageDispatcher::dispatchByStatus($params)
   c. Custom: PageDispatcher::dispatch($page, $params)
   d. Return WHMCS template format:
      { tabOverviewReplacementTemplate, templateVariables }
```

### Step-to-Action Mapping

```php
$stepToAction = [
    // New names          // Old module names (backward compat)
    'submitApply'    ←    'applyssl'
    'saveDraft'      ←    'savedraft'
    'refreshStatus'  ←    'refresh'
    'downCert'       ←    'downcert', 'download', 'downkey'
    'batchUpdateDCV' ←    (same)
    'resendDCVEmail' ←    (same)
    'getDcvEmails'   ←    (new v2.0.1)
    'cancelOrder'    ←    'cancleOrder' (note: old typo preserved)
    'revoke'         ←    (same)
    'submitReissue'  ←    'replacessl', 'submitReplace', 'reissue'
    'renew'          ←    (same)
    'generateCSR'    ←    (same)
    'decodeCsr'      ←    (same)
];
```

---

## 3. Page Dispatcher

**File:** `src/model/Dispatcher/PageDispatcher.php`

### Route Map

| URL Page | Controller Method | Template |
|---|---|---|
| `index` / `apply` | `PageController::index()` | Status-dependent |
| `manage` | `PageController::manage()` | `manage.tpl` |
| `reissue` / `replace` | `PageController::reissue()` | `reissue.tpl` |

### Status-Based Routing (`PageController::index`)

```
normalizeStatus($order->status)
├─ 'awaiting' / 'draft'                         → renderApplyCert()   → applycert.tpl
├─ 'pending' / 'processing' / 'reissue'         → renderPending()     → message.tpl
├─ 'complete' / 'issued' / 'active'             → renderComplete()    → complete.tpl
├─ 'cancelled' / 'revoked' / 'expired' / etc    → renderCancelled()   → cancelled.tpl
└─ default:
   ├─ has remoteid?  → renderPending()
   └─ no remoteid?   → renderApplyCert()
```

### Status Normalization

`normalizeStatus()` maps 15+ status string variants to 10 canonical values:
- `'awaiting configuration'` → `'awaiting'`
- `'completed'` → `'complete'`
- `'canceled'` → `'cancelled'`
- `'reissued'` → `'reissue'`

### No Order Handling

When `nicsrs_sslorders` has no record for the service:
1. Check `tblsslorders` for vendor certificate → if found: `migrated.tpl`
2. If no vendor cert: auto-create new order with `SSL_STATUS_AWAITING` → `applycert.tpl`

---

## 4. Action Dispatcher

**File:** `src/model/Dispatcher/ActionDispatcher.php`

### Features
- 25+ route mappings with alias support
- Public actions exempt from auth: `generateCSR`, `decodeCsr`
- Access validation: `validateServiceOwnership()` for protected actions
- File download support: binary response with proper headers
- Redirect support: `Location` header for page redirections

---

## 5. Client Area Templates

### 5.1 `applycert.tpl` — Certificate Application

**Multi-step form** with visual progress indicator:

| Step | Content | Required For |
|---|---|---|
| 1 - Configure | CSR input (paste / auto-generate) + server type | All |
| 2 - Domains | Domain names + DCV method per domain (dropdown with email optgroup) | All |
| 3 - Admin Contact | First/last name, email, phone, organization, job title, address, city, postal code, country | All |
| 4 - Organization | Organization name, address, city, state, postal code, country, phone | OV/EV only |

**Features:**
- Draft save/resume: `isDraft` status card shows when draft exists
- CSR auto-generation via JavaScript (OpenSSL)
- CSR decode button: parses CSR to extract CN, organization, country
- Dynamic domain list: add/remove additional domains for SAN certificates
- DCV email loading: fetches available emails per domain via API
- Pre-fill from existing `configdata` (for drafts and renewals)
- Pre-fill admin contact from `$client` (WHMCS client details)
- Country dropdown from `country.json` (250+ countries)

**JavaScript Actions:**
- `saveDraft` → POST `step=saveDraft` with `data` JSON
- `submitApply` → POST `step=submitApply` with `data` JSON
- `generateCSR` → POST `step=generateCSR`
- `decodeCsr` → POST `step=decodeCsr` with `csr` field

### 5.2 `message.tpl` — Pending Status

Displays when certificate is pending validation.

**Content:**
- Status card with current status badge
- Certificate ID and Vendor ID
- DCV information per domain:
  - For FILE: file path, file content (copyable)
  - For DNS/CNAME: DNS host, DNS value, DNS type (copyable)
  - For EMAIL: email address, resend button
- Domain validation status list (verified ✓ / pending ⏳)
- Refresh status button
- Cancel order button

### 5.3 `complete.tpl` — Issued Certificate

Displays when certificate has been issued.

**Content:**
- Status card: "Certificate Issued" with dates
- Certificate info: begin date, end date, days remaining
- Download section:
  - PEM format (certificate + CA bundle)
  - PKCS#12 format (with password modal)
  - JKS format (with password modal — for Tomcat)
  - Private key (if stored locally)
  - Copy-to-clipboard buttons
- Action buttons: Reissue, Refresh Status
- Domain list with validation status

**JavaScript:**
- `SSLManager.copyToClipboard()` — clipboard copy
- `showPasswordModal()` — PKCS12/JKS password display
- Download triggers via `step=downCert&format=X`

### 5.4 `reissue.tpl` — Reissue/Replace

Same multi-step structure as `applycert.tpl` but:
- Pre-filled with existing order data from `$cfgData`
- Uses `$clientsdetails` instead of `$client` for Smarty variables
- Submit action: `step=submitReissue`
- All 4 steps available (CSR, Domains, Admin, Organization)

### 5.5 `migrated.tpl` — Vendor Migration

Read-only display when service has a certificate from another provider.

**Content:**
- Information card: previous vendor name, cert ID, status, expiry
- Message: "Contact administrator to migrate to NicSRS"
- No action buttons (admin must use "Allow New Certificate")

### 5.6 `error.tpl` — Error Display

Generic error page with message and timestamp.

---

## 6. Action Controller

**File:** `src/model/Controller/ActionController.php`

### Certificate Application Actions

| Method | Input | API Call | DB Update |
|---|---|---|---|
| `submitApply` | POST `data` JSON | `/place` | status → Pending, remoteid = certId |
| `saveDraft` | POST `data` JSON | None | status → Draft, configdata merged |

**`submitApply` Flow:**
1. Get order by serviceid
2. Parse form data via `getPostData('data')`
3. `validateFormData()` — CSR, domains, admin contact
4. `buildApiRequest()` — format domainInfo, contacts, organization
5. Determine period from billing cycle (1/2/3 years)
6. `ApiService::place()` — submit to NicSRS
7. Store certId as `remoteid`, full configdata
8. Update `tblhosting.domain` with primary domain

**`saveDraft` Flow:**
1. Parse form data, merge with existing configdata
2. Preserve `applyReturn` from existing data
3. Handle `isRenew`/`originalfromOthers` flags consistently
4. Update status to `SSL_STATUS_DRAFT`

### CSR Actions

| Method | Input | Output |
|---|---|---|
| `generateCSR` | POST: CN, org, country, etc. | `{ csr, privateKey }` |
| `decodeCsr` | POST: `csr` | `{ cn, organization, country, state, city, email }` |

Both are **public actions** (no service ownership check required).

### Status & Download Actions

| Method | Input | Behavior |
|---|---|---|
| `refreshStatus` | serviceid | API `/collect` → update order status + configdata |
| `downCert` | serviceid + format | Returns certificate in requested format (PEM/PKCS12/JKS/key) |

**Download formats:** `pem` (cert + CA), `pkcs12` (with password), `jks` (with password), `key` (private key)

### DCV Actions

| Method | Input | API Call |
|---|---|---|
| `batchUpdateDCV` | POST `data.domains[]` | `/batchUpdateDCV` |
| `resendDCVEmail` | POST `domain`, `email` | `/DCVemail` |
| `getDcvEmails` | POST `domain` | `/DCVemail` |

### Order Management Actions

| Method | Input | API Call |
|---|---|---|
| `cancelOrder` | serviceid | `/cancel` (reason = "Customer requested") |
| `revoke` | serviceid | `/revoke` (reason = "Customer requested") |
| `submitReissue` | POST `data` JSON | `/reissue` (new CSR + domainInfo) |
| `renew` | serviceid | None — resets order for new application |

---

## 7. Vendor Migration

### Detection (`CreateAccount`)

```
1. Check: nicsrs_sslorders exists for serviceid? → abort
2. hasActiveVendorCert(serviceid):
   a. Check tblsslorders table exists
   b. Query for serviceid with non-empty remoteid
   c. Return true if found
3. If active vendor cert → return 'success' silently
   Client sees migrated.tpl
4. If no vendor cert → normal order creation
```

### Admin Override (`AdminAllowNewCert`)

Creates `nicsrs_sslorders` record with migration-specific configdata:
```json
{
  "migratedFromVendor": true,
  "adminOverride": true,
  "adminOverrideAt": "2025-06-15 10:30:00",
  "originalfromOthers": "1",
  "isRenew": "1",
  "previousVendor": "cpanel_ssl",
  "previousRemoteId": "OLD-CERT-123",
  "previousStatus": "Complete",
  "previousOrderId": "456"
}
```

### Admin Tab Display (`AdminServicesTabFields`)

Shows either:
- **Normal**: Order info (ID, cert ID, status, domain, type, dates, manage link)
- **Migration warning**: Vendor name, cert ID, status, expiry, "Allow New Certificate" prompt
- **No order**: "Order will be created when client configures the service"

---

## 8. Service Layer

### `ApiService` (Modern)

Static methods, 4-level token fallback. All methods accept `$params` array for token resolution.

Key methods: `getApiToken()`, `call()`, `validate()`, `place()`, `collect()`, `cancel()`, `revoke()`, `reissue()`, `renew()`, `batchUpdateDCV()`, `resendDCVEmail()`, `removeMdcDomain()`, `getProductList()`

Response parsing: `parseResponse($response)` returns standardized `['success' => bool, 'message' => string, 'data' => array, 'status' => string]`

### `nicsrsAPI` (Legacy)

Static methods with token caching. Same endpoints but different method signatures.

Maintained for backward compatibility with existing code paths.

### `OrderRepository`

Static CRUD wrapper around WHMCS Capsule ORM:
- `ensureTableExists()` — auto-create table
- `getByServiceId()`, `getById()`, `getByRemoteId()`, `getByUserId()`, `getByStatus()`
- `create()` → returns insert ID
- `update()`, `updateConfigData()` — separate methods for full and partial updates
- `delete()` — remove record

### `CertificateFunc`

Product information utilities:
- `normalizeToCode($identifier)` — accepts product name or code, always returns code
- `getCertAttributes($code)` — returns product capabilities (wildcard, SAN, max domains, validation type)
- `getProductFromDatabase($identifier)` — direct DB lookup in `mod_nicsrs_products`
- Name↔code bidirectional cache: `$nameToCodeCache`, `$codeToNameCache`
- Build cache from DB first (higher priority), then static definitions as fallback

### `TemplateHelper`

Template rendering factory:
- `getBaseVars($params)` — common variables: `WEB_ROOT`, `countries`, `supportOptions`, `productCode`, `sslValidationType`
- Status-specific renderers: `applyCert()`, `message()`, `pending()`, `complete()`, `reissue()`, `cancelled()`, `migrated()`, `error()`
- Each returns: `['templatefile' => 'view/xxx.tpl', 'vars' => [...]]`