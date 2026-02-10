# API Integration Reference

> **Project:** NicSRS SSL Management System for WHMCS  
> **API Base URL:** `https://portal.nicsrs.com/ssl`  
> **Protocol:** HTTPS POST  
> **Content-Type:** `application/x-www-form-urlencoded`  
> **Response Format:** JSON  
> **Last Updated:** 2026-02-09

---

## 1. Authentication

### API Token

All requests require an `api_token` parameter. The token is obtained from your NicSRS reseller account at [portal.nicsrs.com](https://portal.nicsrs.com).

```
POST https://portal.nicsrs.com/ssl/collect
Content-Type: application/x-www-form-urlencoded

api_token=YOUR_TOKEN_HERE&certId=12345678
```

### Token Resolution Priority (Server Module)

The `ApiService::getApiToken()` method resolves the token in this order:

| Priority | Source | Location |
|---|---|---|
| 1 | Product-level override | `$params['configoption2']` from WHMCS product config |
| 2 | Service → Product lookup | `tblhosting.packageid → tblproducts.configoption2` |
| 3 | Admin Addon shared token | `tbladdonmodules` WHERE module=`nicsrs_ssl_admin`, setting=`api_token` |
| 4 | Settings table fallback | `mod_nicsrs_settings` WHERE setting_key=`api_token` |

If no token is found at any level, an `Exception` is thrown: *"API token not configured."*

### Token Resolution Priority (Legacy nicsrsAPI)

| Priority | Source |
|---|---|
| 1 | Explicitly passed `$params['api_token']` |
| 2 | Addon Module token (cached in `$cachedAddonToken`) |
| 3 | Product config: `$params['configoption3']` or `$params['configoption2']` (if >20 chars) |

### Token Caching

The legacy `nicsrsAPI` class caches the addon token in a static variable `$cachedAddonToken` to avoid repeated DB queries within the same request. Call `nicsrsAPI::clearTokenCache()` to reset.

---

## 2. Request / Response Format

### Request Format

All API calls use HTTP POST with `application/x-www-form-urlencoded` encoding:

```php
// Server Module (ApiService) — static
curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => http_build_query($data),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

// Admin Addon (NicsrsApiService) — instance
curl_setopt_array($ch, [
    CURLOPT_URL            => self::API_BASE . $endpoint,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
```

### Complex Parameters

Array/object parameters are JSON-encoded before being form-encoded:

```php
$data = [
    'api_token'   => $token,
    'productCode' => 'sectigo-ov',
    'years'       => 1,
    'params'      => json_encode($requestData),    // ← JSON string
    'domainInfo'  => json_encode($domainInfoArray), // ← JSON string
];
// Sent as: api_token=xxx&productCode=sectigo-ov&years=1&params=%7B...%7D&domainInfo=%5B...%5D
```

### Standard Response Format

```json
{
  "code": 1,
  "msg": "Success",
  "status": "complete",
  "data": { ... }
}
```

| Field | Type | Description |
|---|---|---|
| `code` | int | Result code: `1` = success, `2` = partial/pending, other = error |
| `msg` | string | Human-readable message |
| `status` | string | Certificate status (for order-related endpoints) |
| `data` | object | Response payload (varies by endpoint) |

### Error Response

```json
{
  "code": -1,
  "msg": "Invalid API token",
  "data": null
}
```

Common error codes: `-1` (auth failure), `-2` (invalid params), `-3` (not found), `-4` (rate limited).

---

## 3. API Endpoints

### 3.1 Product Catalog

#### `POST /productList` — Get Available Products

Retrieves the list of available SSL certificate products, optionally filtered by vendor.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API authentication token |
| `vendor` | string | ❌ | Filter by vendor name (e.g., `Sectigo`, `DigiCert`) |

**Response:**

```json
{
  "code": 1,
  "msg": "Success",
  "data": [
    {
      "code": "sectigo-ov",
      "productName": "Sectigo OV SSL",
      "supportWildcard": "N",
      "supportSan": "Y",
      "validationType": "ov",
      "maxDomain": 5,
      "maxYear": 2,
      "price": {
        "basePrice": { "price012": 59.00, "price024": 99.00 },
        "sanPrice": { "price012": 15.00, "price024": 25.00 }
      }
    }
  ]
}
```

**Used By:**
- `SyncService::syncProducts()` — auto-sync product catalog
- `NicsrsApiService::productList()` — admin manual sync
- `NicsrsApiService::testConnection()` — API health check
- `testApiConnection()` in `nicsrs_ssl.php` — ConfigOptions display

**Supported Vendor Values:** `Sectigo`, `Positive`, `DigiCert`, `GlobalSign`, `GeoTrust`, `Thawte`, `RapidSSL`, `sslTrus`, `Entrust`, `BaiduTrust`

---

### 3.2 Certificate Lifecycle

#### `POST /validate` — Validate Certificate Request

Pre-validates a certificate order before placing. Checks CSR validity, domain ownership requirements, and product compatibility.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `productCode` | string | ✅ | Product code (e.g., `sectigo-ov`) |
| `csr` | string | ✅ | PEM-encoded CSR |
| `domainInfo` | string (JSON) | ✅ | JSON array of domain validation info |

**`domainInfo` Structure:**
```json
[
  {
    "domainName": "example.com",
    "dcvMethod": "CNAME_CSR_HASH"
  },
  {
    "domainName": "www.example.com",
    "dcvMethod": "EMAIL",
    "dcvEmail": "admin@example.com"
  }
]
```

**Response (Success):**
```json
{
  "code": 1,
  "msg": "Validation passed"
}
```

**Used By:** `ActionController::submitApply()` (optionally before place)

---

#### `POST /place` — Place Certificate Order

Submits a new certificate order to the Certificate Authority.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `productCode` | string | ✅ | Product code |
| `years` | int | ✅ | Validity period (1, 2, or 3) |
| `params` | string (JSON) | ✅ | Full order request object |

**`params` JSON Structure:**
```json
{
  "server": "other",
  "csr": "-----BEGIN CERTIFICATE REQUEST-----\n...",
  "domainInfo": [
    {
      "domainName": "example.com",
      "dcvMethod": "CNAME_CSR_HASH"
    }
  ],
  "Administrator": {
    "firstName": "John",
    "lastName": "Doe",
    "email": "admin@example.com",
    "mobile": "+84.123456789",
    "organization": "Acme Corp",
    "job": "IT Manager",
    "address": "123 Main St",
    "city": "HCMC",
    "postCode": "700000",
    "country": "VN"
  },
  "tech": { "...same as Administrator..." },
  "finance": { "...same as Administrator..." },
  "organizationInfo": {
    "organizationName": "Acme Corporation",
    "organizationAddress": "123 Main Street",
    "organizationCity": "Ho Chi Minh",
    "organizationState": "",
    "organizationPostCode": "700000",
    "organizationCountry": "VN",
    "organizationPhone": "+84.28.12345678"
  },
  "originalfromOthers": "0",
  "privateKey": "..."
}
```

**Notes:**
- `organizationInfo` is required for OV and EV certificates
- `originalfromOthers: "1"` signals this is a renewal/migration from another provider
- `tech` and `finance` contacts default to `Administrator` if not provided

**Response (Success):**
```json
{
  "code": 1,
  "msg": "Order placed successfully",
  "status": "pending",
  "data": {
    "certId": "12345678",
    "vendorId": "87654321"
  }
}
```

**Used By:** `ActionController::submitApply()` — stores `certId` as `remoteid` in `nicsrs_sslorders`

---

#### `POST /collect` — Collect Certificate Status & Data

Retrieves the current status and full details of a certificate order. This is the primary endpoint used by auto-sync.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID (from `/place` response) |

**Response (Pending):**
```json
{
  "code": 2,
  "msg": "Certificate is pending validation",
  "status": "pending",
  "data": {
    "certStatus": "pending",
    "dcvList": [
      {
        "domainName": "example.com",
        "dcvMethod": "CNAME_CSR_HASH",
        "dcvEmail": "",
        "is_verify": "pending"
      }
    ],
    "DCVfileName": "fileauth.txt",
    "DCVfileContent": "abc123def456...",
    "DCVfilePath": "/.well-known/pki-validation/",
    "DCVdnsHost": "_dnsauth.example.com",
    "DCVdnsValue": "abc123.verify.sectigo.com",
    "DCVdnsType": "CNAME"
  }
}
```

**Response (Complete):**
```json
{
  "code": 1,
  "msg": "Certificate issued",
  "status": "complete",
  "data": {
    "certStatus": "issued",
    "beginDate": "2025-01-15",
    "endDate": "2026-01-15",
    "certificate": "-----BEGIN CERTIFICATE-----\n...",
    "caCertificate": "-----BEGIN CERTIFICATE-----\n...",
    "productType": "sectigo-ov",
    "dcvList": [
      {
        "domainName": "example.com",
        "dcvMethod": "CNAME_CSR_HASH",
        "is_verify": "verified"
      }
    ],
    "application": "approved",
    "dcv": "verified",
    "issued": "yes"
  }
}
```

**Used By:**
- `ActionController::refreshStatus()` — manual status refresh
- `SyncService::syncCertificateStatus()` — auto-sync engine
- `OrderController::handleAjax('refresh_status')` — admin refresh
- `ImportController::lookupCertificate()` — certificate lookup during import
- `ImportController::importCertificate()` — fetch data during import

---

#### `POST /cancel` — Cancel Certificate Order

Cancels a pending certificate order.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `reason` | string | ✅ | Cancellation reason text |

**Response:**
```json
{
  "code": 1,
  "msg": "Order cancelled successfully"
}
```

**Used By:** `ActionController::cancelOrder()`, `OrderController::handleAjax('cancel')`

---

#### `POST /revoke` — Revoke Issued Certificate

Revokes an already-issued certificate.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `reason` | string | ✅ | Revocation reason text |

**Response:**
```json
{
  "code": 1,
  "msg": "Certificate revoked successfully"
}
```

**Used By:** `ActionController::revoke()`, `OrderController::handleAjax('revoke')`

---

#### `POST /reissue` — Reissue Certificate

Reissues an existing certificate with a new CSR or updated domain info. The old certificate remains valid until the reissue completes.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `csr` | string | ✅ | New PEM-encoded CSR |
| `domainInfo` | string (JSON) | ✅ | Updated domain validation info |

**Response:**
```json
{
  "code": 1,
  "msg": "Reissue initiated",
  "status": "pending"
}
```

**Used By:** `ActionController::submitReissue()`

---

#### `POST /renew` — Renew Certificate

Initiates certificate renewal. Typically used before the current certificate expires.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |

**Response:**
```json
{
  "code": 1,
  "msg": "Renewal initiated"
}
```

**Used By:** `ActionController::renew()`

---

#### `POST /replace` — Replace Certificate

Replaces a certificate (similar to reissue but may differ by CA provider).

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `params` | string (JSON) | ✅ | Replacement request data |

**Used By:** `nicsrsAPI::replace()` (legacy)

---

### 3.3 Domain Validation (DCV)

#### `POST /DCVemail` — Get DCV Email Options

Retrieves available email addresses for email-based domain validation.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `domain` / `domainName` | string | ✅ | Domain name to validate |

Optionally, when resending a DCV email:

| Parameter | Type | Required | Description |
|---|---|---|---|
| `certId` | string | ✅ | Certificate ID |
| `dcvEmail` | string | ❌ | Specific email to send to |

**Response:**
```json
{
  "code": 1,
  "msg": "Success",
  "data": {
    "emails": [
      "admin@example.com",
      "administrator@example.com",
      "hostmaster@example.com",
      "postmaster@example.com",
      "webmaster@example.com"
    ]
  }
}
```

**Used By:** `ActionController::resendDCVEmail()`, `NicsrsApiService::getDcvEmails()`

---

#### `POST /updateDCV` — Update DCV Method (Single Domain)

Updates the domain control validation method for a single domain.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `domainName` | string | ✅ | Domain name |
| `dcvMethod` | string | ✅ | New DCV method |

**DCV Method Values:**

| Value | Description |
|---|---|
| `EMAIL` | Email verification (requires `dcvEmail` parameter) |
| `HTTP_CSR_HASH` | HTTP file validation (port 80) |
| `HTTPS_CSR_HASH` | HTTPS file validation (port 443) |
| `CNAME_CSR_HASH` | DNS CNAME record validation |

---

#### `POST /batchUpdateDCV` — Batch Update DCV Methods

Updates DCV methods for multiple domains in a single request.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `domainInfo` | string (JSON) | ✅ | Array of domain DCV updates |

**`domainInfo` Structure:**
```json
[
  {
    "domainName": "example.com",
    "dcvMethod": "CNAME_CSR_HASH"
  },
  {
    "domainName": "www.example.com",
    "dcvMethod": "EMAIL",
    "dcvEmail": "admin@example.com"
  }
]
```

**Used By:** `ActionController::batchUpdateDCV()`, `ApiService::batchUpdateDCV()`

---

### 3.4 Utility Endpoints

#### `POST /removeMdcDomain` — Remove Multi-Domain Entry

Removes a SAN (Subject Alternative Name) domain from a multi-domain certificate.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `certId` | string | ✅ | Certificate ID |
| `domainName` | string | ✅ | Domain to remove |

---

#### `POST /caaCheck` — Check CAA Records

Checks DNS CAA (Certificate Authority Authorization) records for a domain.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `domain` | string | ✅ | Domain name |

**Used By:** `NicsrsApiService::caaCheck()`

---

#### `POST /getCertByRefId` — Get Certificate by Reference ID

Retrieves a certificate using an external reference ID.

**Request:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `api_token` | string | ✅ | API token |
| `refId` | string | ✅ | External reference ID |

**Used By:** `NicsrsApiService::getCertByRefId()`

---

#### `POST /validatefile` — HTTP File Validation

Endpoint for HTTP-based file validation.

**Used By:** `nicsrsAPI::call('file', $data)` (legacy)

---

#### `POST /validatedns` — DNS Validation

Endpoint for DNS-based validation.

**Used By:** `nicsrsAPI::call('dns', $data)` (legacy)

---

#### `POST /country` — Get Country List

Retrieves the list of countries for certificate forms.

**Used By:** `nicsrsAPI::call('country', $data)` (legacy); modern module uses static `country.json`

---

## 4. Complete Endpoint Reference Table

| Endpoint | Internal Name | Method | Required Params | Description |
|---|---|---|---|---|
| `/productList` | `productList` | POST | `api_token` | Get product catalog |
| `/validate` | `validate` | POST | `api_token`, `productCode`, `csr`, `domainInfo` | Pre-validate order |
| `/place` | `place` | POST | `api_token`, `productCode`, `years`, `params` | Submit order to CA |
| `/collect` | `collect` | POST | `api_token`, `certId` | Get certificate status/data |
| `/cancel` | `cancel` | POST | `api_token`, `certId`, `reason` | Cancel pending order |
| `/revoke` | `revoke` | POST | `api_token`, `certId`, `reason` | Revoke issued certificate |
| `/reissue` | `reissue` | POST | `api_token`, `certId`, `csr`, `domainInfo` | Reissue certificate |
| `/renew` | `renew` | POST | `api_token`, `certId` | Renew certificate |
| `/replace` | `replace` | POST | `api_token`, `params` | Replace certificate |
| `/DCVemail` | `email` / `DCVemail` | POST | `api_token`, `domain` | Get DCV email options |
| `/updateDCV` | `updateDCV` | POST | `api_token`, `certId`, `domainName`, `dcvMethod` | Update single DCV |
| `/batchUpdateDCV` | `batchUpdateDCV` | POST | `api_token`, `certId`, `domainInfo` | Batch update DCV |
| `/removeMdcDomain` | `removeMdc` | POST | `api_token`, `certId`, `domainName` | Remove SAN domain |
| `/validatefile` | `file` | POST | `api_token`, ... | HTTP file validation |
| `/validatedns` | `dns` | POST | `api_token`, ... | DNS validation |
| `/country` | `country` | POST | `api_token` | Get country list |
| `/caaCheck` | `caaCheck` | POST | `api_token`, `domain` | Check CAA records |
| `/getCertByRefId` | `getCertByRefId` | POST | `api_token`, `refId` | Lookup by reference |

---

## 5. Error Handling

### cURL Error Handling

```php
// Both modules follow this pattern:
$response = curl_exec($curl);
$error    = curl_error($curl);
$errno    = curl_errno($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($errno) {
    throw new Exception("cURL Error ({$errno}): {$error}");
}
if ($httpCode >= 400) {
    throw new Exception("HTTP Error: {$httpCode}");
}
if (empty($response)) {
    throw new Exception("Empty response from API");
}

$decoded = json_decode($response);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Invalid JSON: " . json_last_error_msg());
}
```

### API Response Code Handling

```php
// NicsrsApiService (Admin Addon) returns associative array:
$result = $this->apiService->collect($certId);
if ($result['code'] != 1 && $result['code'] != 2) {
    // Error
    $errorMsg = $result['msg'] ?? 'Unknown error';
}

// ApiService (Server Module) returns object:
$response = ApiService::collect($params, $certId);
// parseResponse() standardizes to ['success' => bool, 'message' => string, 'data' => array]
```

### Rate Limiting

The module implements basic rate limiting:
- Product sync: 500ms delay between vendor API calls (`usleep(500000)`)
- Batch sync: configurable batch size (10–200) limits concurrent API calls
- Connection timeout: 10s (Admin), 60s (Server Module)
- Request timeout: 30s (Admin), 60s (Server Module)

### Retry Strategy

The `SyncService` tracks consecutive errors via `sync_error_count`:
- On success: reset to `0`
- On failure: increment by `1`
- At `≥ 3` errors: trigger admin notification email
- No automatic retry within the same sync run; retries occur on the next scheduled run

---

## 6. Logging

All API calls are logged via WHMCS's `logModuleCall()`:

```php
logModuleCall(
    'nicsrs_ssl',           // Module name
    'API/collect',          // Action (endpoint)
    $maskedRequestData,     // Request (token masked)
    $responseBody,          // Full response
    $errorIfAny             // Error trace
);
```

Logs are viewable at **WHMCS Admin → Utilities → Logs → Module Log**.

**Token Masking:**
- Server Module: first 8 chars + `***` (e.g., `abc12345***`)
- Admin Addon: `***MASKED***`
- Legacy: first 8 chars + `****`

---

## 7. API Client Classes Summary

| Class | Module | Style | Token Source |
|---|---|---|---|
| `ApiService` | Server | Static methods | `getApiToken($params)` with 4-level fallback |
| `nicsrsAPI` | Server (legacy) | Static methods + caching | `getApiToken($params)` with 3-level fallback |
| `NicsrsApiService` | Admin Addon | Instance-based | Constructor injection |

All three classes ultimately call the same NicSRS API endpoints. The `ApiService` class is the modern implementation; `nicsrsAPI` is maintained for backward compatibility. `NicsrsApiService` is used by the Admin Addon where the API token is known at initialization time.