# Architecture Overview

> **Project:** NicSRS SSL Management System for WHMCS  
> **Version:** 1.3.1 / 2.1.0  
> **Last Updated:** 2026-02-09

## 1. High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          WHMCS Platform                                 │
│                                                                         │
│  ┌──── Admin Area ──────────────────┐  ┌──── Client Area ─────────────┐│
│  │                                  │  │                              ││
│  │  ┌───────────────────────────┐   │  │  ┌────────────────────────┐  ││
│  │  │   ADMIN ADDON MODULE      │   │  │  │  SERVER PROVISION MOD  │  ││
│  │  │   (nicsrs_ssl_admin)      │   │  │  │  (nicsrs_ssl)          │  ││
│  │  │                           │   │  │  │                        │  ││
│  │  │  Controllers (7)          │   │  │  │  Dispatchers (2)       │  ││
│  │  │  Services (5)             │   │  │  │  Controllers (2)       │  ││
│  │  │  Helpers (2)              │   │  │  │  Services (7)          │  ││
│  │  │  Templates (7 PHP)        │   │  │  │  Templates (7 TPL)     │  ││
│  │  └─────────┬─────────────────┘   │  │  └──────────┬─────────────┘  ││
│  └────────────┼─────────────────────┘  └─────────────┼────────────────┘│
│               │                                      │                  │
│  ┌────────────▼──────────────────────────────────────▼──────────────┐  │
│  │                    SHARED DATABASE LAYER                          │  │
│  │  ┌──────────────┐ ┌────────────────┐ ┌──────────────────────┐    │  │
│  │  │nicsrs_ssl    │ │mod_nicsrs_     │ │ WHMCS Core Tables    │    │  │
│  │  │  orders      │ │  products      │ │ tblhosting           │    │  │
│  │  └──────────────┘ │  settings      │ │ tblproducts          │    │  │
│  │                    │  activity_log  │ │ tblclients           │    │  │
│  │                    └────────────────┘ │ tbladdonmodules      │    │  │
│  │                                       │ tblsslorders         │    │  │
│  │                                       └──────────────────────┘    │  │
│  │                      WHMCS Capsule ORM (Illuminate\Database)      │  │
│  └───────────────────────────────┬───────────────────────────────────┘  │
│                                  │                                      │
│  ┌───────────────────────────────▼───────────────────────────────────┐  │
│  │                     WHMCS Hook System                              │  │
│  │  DailyCronJob · AfterCronJob · AdminAreaHeaderOutput               │  │
│  │  ClientAreaPage · ServiceRenewal · AddonActivation                 │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────┬───────────────────────────────────┘
                                      │ HTTPS POST
                                      │ application/x-www-form-urlencoded
                                      ▼
                        ┌──────────────────────────┐
                        │     NicSRS REST API       │
                        │ portal.nicsrs.com/ssl/*   │
                        │                           │
                        │  Auth: api_token param    │
                        │  Format: JSON response    │
                        │  Timeout: 30–60s          │
                        │                           │
                        │  ┌─────────────────────┐  │
                        │  │ Certificate          │  │
                        │  │ Authorities:          │  │
                        │  │ Sectigo · DigiCert   │  │
                        │  │ GlobalSign · Entrust │  │
                        │  │ sslTrus · GeoTrust   │  │
                        │  │ BaiduTrust · Thawte  │  │
                        │  │ RapidSSL             │  │
                        │  └─────────────────────┘  │
                        └──────────────────────────┘
```

---

## 2. Module Component Architecture

### 2.1 Admin Addon Module — Component Diagram

```
nicsrs_ssl_admin_output($vars)
│
├─ POST[ajax_action] ?
│   ├─ YES → handleAjaxRequest($vars, $action)
│   │         ├─ Resolve controller from $controllerMap
│   │         ├─ $controller = new XController($vars)
│   │         ├─ $response = $controller->handleAjax($_POST)
│   │         ├─ header('Content-Type: application/json')
│   │         └─ echo $response; exit;
│   │
│   └─ NO → Page Render Flow
│            ├─ Resolve controller from $controllerMap
│            ├─ $controller = new XController($vars)
│            ├─ outputAssets()         → CSS/JS
│            ├─ renderNavigation()     → Tab menu
│            ├─ $controller->render()  → PHP template
│            └─ renderFooter()         → Module info

$controllerMap = [
    'dashboard' → DashboardController    ← Default
    'products'  → ProductController
    'orders'    → OrderController
    'order'     → OrderController         ← Detail view
    'settings'  → SettingsController
    'activity'  → ActivityController
    'import'    → ImportController
    'reports'   → ReportController
]
```

### Class Hierarchy — Admin Addon

```
BaseController (abstract)
├── Properties: $vars, $modulelink, $lang, $viewHelper, $logger, $adminId
├── Methods: includeTemplate(), jsonResponse(), jsonSuccess(), jsonError()
├── Methods: getSetting(), saveSetting(), getCurrentPage(), getApiToken()
│
├── DashboardController
│   └── render(): stats cards, recent orders, Chart.js charts
│
├── ProductController
│   ├── render(): product table with search/filter
│   └── handleAjax(): sync_all, sync_vendor, search
│
├── OrderController
│   ├── render(): order list OR order detail (by action param)
│   └── handleAjax(): refresh_status, cancel, revoke, delete, edit, resend_dcv
│
├── SettingsController
│   ├── render(): notification, sync, display, currency settings forms
│   └── handleAjax(): save_settings, manual_sync, get_sync_status,
│                      check_expiring, update_exchange_rate, test_api,
│                      clear_logs, export_logs
│
├── ActivityController
│   └── render(): paginated activity log table
│
├── ImportController
│   ├── render(): import form + recently imported list
│   └── handleAjax(): lookup_cert, import_cert, link_existing, bulk_import
│
└── ReportController
    ├── render(): report tabs (profit, performance, brand) + Chart.js
    └── handleAjax(): get_report_data, export_csv, save_currency_settings
```

### Service Layer — Admin Addon

```
SyncService
├── runScheduledSync()        → Called by cron hooks
├── forceSyncNow($type)       → Called by manual sync button
├── syncCertificateStatus()   → Process pending + active certs
├── syncProducts()            → Fetch product catalog from all vendors
├── getSyncStatus()           → Return current sync state
└── Dependencies: NicsrsApiService, ActivityLogger, NotificationService

NicsrsApiService (instance-based)
├── Constructor: __construct(string $apiToken)
├── productList(?$vendor), collect($certId), cancel($certId, $reason)
├── revoke($certId, $reason), reissue($certId, $data), renew($certId)
├── validate($productCode, $csr, $domainInfo)
├── updateDcv($certId, $domainInfo), batchUpdateDcv($certId, $list)
├── getDcvEmails($domain), resendDcv($certId, $domain)
├── caaCheck($domain), getCertByRefId($refId)
└── testConnection() → Quick /productList call to verify token

NotificationService
├── sendCertificateIssuedNotification($cert)
├── sendExpiryWarning($cert, $daysUntilExpiry)
├── sendSyncErrorNotification($errors, $errorCount)
├── checkAndSendExpiryWarnings()   → Full expiry scan
└── sendAdminNotification($subject, $body) → via WHMCS Local API SendAdminEmail

ReportService
├── getProfitReport($filters)
├── getProductPerformance($filters)
└── getRevenueByBrand($filters)

ActivityLogger
└── log($action, $entityType, $entityId, $oldValue, $newValue)
```

### Helper Layer — Admin Addon

```
ViewHelper
├── e($string)                    → HTML escape
├── formatDate($date, $format)    → Date formatting
├── truncate($string, $length)    → String truncation
├── statusBadge($status)          → Bootstrap label HTML
├── validationBadge($type)        → DV/OV/EV badge
├── formatPrice($price, $symbol)  → Currency formatting
├── formatCurrency($amount)       → WHMCS currency formatting
└── formatBillingCycle($cycle)    → Billing cycle display

CurrencyHelper (static)
├── getUsdVndRate()
├── setUsdVndRate($rate)
├── convertUsdToVnd($usd)
├── formatVnd($amount)
├── setDisplayMode($mode)
├── getDisplayMode()
├── getRateInfo()
└── updateRateFromApi()           → External exchange rate API
```

---

### 2.2 Server Provision Module — Component Diagram

```
nicsrs_ssl_ClientArea($params)
│
├─ $_REQUEST['step'] exists AND is AJAX?
│   ├─ YES → AJAX Action Flow
│   │   ├─ Validate CSRF / access
│   │   ├─ ActionDispatcher::dispatch($action, $params)
│   │   │   ├─ Resolve from $routes (with alias support)
│   │   │   ├─ Validate access (publicActions exempt)
│   │   │   └─ ActionController::$method($params)
│   │   ├─ echo json_encode($result)
│   │   └─ exit
│   │
│   └─ NO → Page Render Flow
│       ├─ OrderRepository::ensureTableExists()
│       ├─ PageDispatcher::dispatchByStatus($params)
│       │   └─ PageController::index($params)
│       │       ├─ Get order from DB
│       │       ├─ Get certificate info
│       │       └─ TemplateHelper::getTemplateForStatus($params, $order, $cert)
│       │           ├─ Awaiting/Draft   → applycert.tpl
│       │           ├─ Pending          → message.tpl
│       │           ├─ Complete/Issued  → complete.tpl
│       │           ├─ Reissue          → reissue.tpl
│       │           └─ Cancelled/etc    → cancelled view
│       └─ return ['tabOverviewReplacementTemplate' => 'view/xxx.tpl',
│                   'templateVariables' => [...]]

WHMCS Module Functions (nicsrs_ssl.php):
├── nicsrs_ssl_ConfigOptions()        → Product config fields
├── nicsrs_ssl_CreateAccount()        → Service provisioning
├── nicsrs_ssl_SuspendAccount()       → Service suspension
├── nicsrs_ssl_TerminateAccount()     → Service termination
├── nicsrs_ssl_AdminServicesTabFields() → Admin tab display
├── nicsrs_ssl_AdminCustomButtonArray() → Admin action buttons
├── nicsrs_ssl_AdminAllowNewCert()    → Vendor migration override
├── nicsrs_ssl_AdminManageOrder()     → Redirect to addon order page
├── nicsrs_ssl_AdminRefreshStatus()   → Admin refresh action
├── nicsrs_ssl_AdminResendDCV()       → Admin DCV resend
├── nicsrs_ssl_ClientAreaCustomButtonArray() → Client action buttons
└── nicsrs_ssl_ClientArea()           → Main client area entry point
```

### Action Dispatcher — Route Map

```
ActionDispatcher::$routes = [
    // CSR
    'generateCSR'         → ActionController::generateCSR()
    'decodeCsr'           → ActionController::decodeCsr()
    'decodeCSR'           → (alias)

    // Application
    'submitApply'         → ActionController::submitApply()
    'saveDraft'           → ActionController::saveDraft()

    // Status
    'refreshStatus'       → ActionController::refreshStatus()
    'refresh'             → (alias)

    // Download
    'downCert'            → ActionController::downCert()
    'download'            → (alias)
    'downloadCertificate' → (alias)

    // DCV
    'batchUpdateDCV'      → ActionController::batchUpdateDCV()
    'updateDCV'           → (alias)
    'resendDCVEmail'      → ActionController::resendDCVEmail()
    'resendDCV'           → (alias)

    // Order management
    'cancelOrder'         → ActionController::cancelOrder()
    'cancel'              → (alias)
    'revoke'              → ActionController::revoke()
    'revokeOrder'         → (alias)

    // Reissue
    'submitReissue'       → ActionController::submitReissue()
    'submitReplace'       → (alias)
    'reissue'             → (alias)

    // Renew
    'renew'               → ActionController::renew()
    'renewCertificate'    → (alias)
]

Public actions (no auth required): generateCSR, decodeCsr
```

### Service Layer — Server Module

```
ApiService (static methods, modern)
├── getApiToken($params)    → Priority-based token resolution (4 levels)
├── call($endpoint, $data)  → Core cURL request + logging
├── validate(), place(), collect(), cancel(), revoke()
├── reissue(), renew(), batchUpdateDCV(), resendDCVEmail()
├── removeMdcDomain(), getProductList()
└── parseResponse($response) → Standardized success/error extraction

nicsrsAPI (static methods, legacy — backward compatible)
├── getApiToken($params)    → 3-level fallback with caching
├── call($callable, $data)  → Core cURL request
├── validate(), place(), collect(), cancel(), revoke()
├── reissue(), renew(), replace(), getDcvEmails()
├── updateDCV(), batchUpdateDCV(), removeMdc()
└── productList()

OrderRepository (static CRUD)
├── ensureTableExists()
├── getById($id), getByServiceId($serviceId), getByRemoteId($remoteId)
├── getByUserId($userId, $status), getByStatus($status, $limit)
├── create($data) → int, update($id, $data), delete($id)
└── Wraps Capsule ORM calls with proper error handling

CertificateFunc (static utilities)
├── getCertCodeByName($name), getCertNameByCode($code)
├── normalizeToCode($identifier)
├── getProductFromDatabase($identifier)
├── getCertAttributes($productCode)  → Dynamic DB retrieval
└── getCertAttributesDropdown()      → Product list for config

TemplateHelper (static rendering)
├── getTemplateForStatus($params, $order, $cert)
├── applyCert(), complete(), message(), reissue()
├── cancelled(), error(), migrated()
└── getBaseVars($params)  → Common template variables

ResponseFormatter (static)
├── success($message, $data), error($message)
└── json($data)  → Set header + echo + exit

DcvHelper (static)
└── getDcvMethodLabel($method), getAvailableMethods($cert)
```

---

## 3. API Token Resolution Chain

Both modules implement a priority-based API token resolution system ensuring reliable authentication:

```
┌─────────────────────────────────────────────────────────────────┐
│                  API TOKEN RESOLUTION CHAIN                      │
│                                                                  │
│  Server Module (ApiService::getApiToken)                         │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Priority 1: $params['configoption2']                         ││
│  │   → Product-level override (set in WHMCS product config)     ││
│  │   → Use case: Different API account per product              ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │ Priority 2: serviceid → tblhosting → tblproducts.config2    ││
│  │   → Resolved via DB lookup from service context              ││
│  │   → Use case: When params don't include configoption2        ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │ Priority 3: tbladdonmodules.api_token                        ││
│  │   → Shared token from Admin Addon configuration              ││
│  │   → Use case: Default for all products (most common)         ││
│  ├─────────────────────────────────────────────────────────────┤│
│  │ Priority 4: mod_nicsrs_settings.api_token                    ││
│  │   → Fallback from settings table                             ││
│  │   → Use case: Edge case / legacy compatibility               ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  Legacy API Client (nicsrsAPI::getApiToken) — similar chain      │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Explicitly passed $params['api_token']                    ││
│  │ 2. Addon Module token (cached in static $cachedAddonToken)   ││
│  │ 3. Product-level configoption3 / configoption2               ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  Admin Addon (SyncService) — direct approach                     │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. ApiConfig::getApiToken() (if class exists)                ││
│  │ 2. mod_nicsrs_settings['api_token']                          ││
│  │ 3. tbladdonmodules (nicsrs_ssl_admin, api_token)             ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Auto-Sync Architecture

```
WHMCS Cron (every 5–15 min)
│
├─ Hook: AfterCronJob
│   └─ nicsrs_ssl_admin_run_sync('after')
│       ├─ Check module active (tbladdonmodules.status = 'Active')
│       ├─ Load SyncService
│       └─ $syncService->runScheduledSync()
│           │
│           ├─ Check: auto_sync_status enabled?
│           │
│           ├─ STATUS SYNC (if interval elapsed)
│           │   ├─ Query: nicsrs_sslorders WHERE status IN (pending_statuses)
│           │   ├─ Batch process (sync_batch_size per run)
│           │   ├─ For each cert: API /collect → update status + configdata
│           │   ├─ If status → Complete: sendCompletionNotification()
│           │   ├─ Then: query active certs for expiry check
│           │   ├─ For expired: update status, notify admin
│           │   └─ Update: last_status_sync, reset error_count on success
│           │
│           ├─ PRODUCT SYNC (if interval elapsed)
│           │   ├─ For each vendor in VENDORS[]:
│           │   │   ├─ API /productList?vendor=X
│           │   │   ├─ saveProducts() → INSERT or UPDATE mod_nicsrs_products
│           │   │   ├─ Detect price changes → collect for notification
│           │   │   └─ 500ms delay between vendors (rate limiting)
│           │   ├─ sendPriceChangeNotification() if any changes
│           │   └─ Update: last_product_sync
│           │
│           └─ ERROR HANDLING
│               ├─ On success: reset sync_error_count to 0
│               ├─ On failure: increment sync_error_count
│               ├─ If error_count >= 3: sendSyncErrorNotification()
│               └─ Admin area shows warning banner via AdminAreaHeaderOutput hook
│
├─ Hook: DailyCronJob
│   └─ Same flow (runs once daily as backup)
│
└─ Manual Trigger (Admin → Settings → Manual Sync)
    └─ SettingsController::handleManualSync()
        └─ SyncService::forceSyncNow($type)
            └─ Bypasses interval check, runs immediately
```

---

## 5. Certificate Lifecycle Data Flow

### 5.1 New Certificate Order

```
Client purchases SSL product
│
└─ WHMCS triggers: nicsrs_ssl_CreateAccount($params)
    ├─ Check: existing order? → abort
    ├─ Check: hasActiveVendorCert()? → block (vendor migration)
    └─ OrderRepository::create() → status: "Awaiting Configuration"
        │
        └─ Client visits service page
            └─ nicsrs_ssl_ClientArea() → PageDispatcher
                └─ TemplateHelper::applyCert() → applycert.tpl
                    │
                    ├─ Client fills form (multi-step):
                    │   Step 1: CSR (paste or auto-generate)
                    │   Step 2: Domain + DCV method selection
                    │   Step 3: Administrator contact info
                    │   Step 4: Organization info (OV/EV only)
                    │
                    ├─ Client clicks "Save Draft":
                    │   └─ AJAX POST step=saveDraft
                    │       └─ ActionController::saveDraft()
                    │           ├─ Merge form data with existing configdata
                    │           ├─ Set isDraft=true, lastSaved=now
                    │           ├─ Handle isRenew/originalfromOthers flags
                    │           └─ OrderRepository::update() → status: "Draft"
                    │
                    └─ Client clicks "Submit":
                        └─ AJAX POST step=submitApply
                            └─ ActionController::submitApply()
                                ├─ validateFormData() — CSR, domains, contacts
                                ├─ buildApiRequest() — format for NicSRS API
                                │   ├─ Process domainInfo (dcvMethod, dcvEmail)
                                │   ├─ Build Administrator/tech/finance contacts
                                │   └─ Add organizationInfo for OV/EV
                                ├─ Get period from billingcycle (1/2/3 years)
                                ├─ API: /place (productCode, years, params)
                                │   └─ Returns: certId, vendorId
                                ├─ Store certId as remoteid
                                ├─ Update configdata with applyReturn
                                ├─ Update tblhosting.domain = primary domain
                                └─ OrderRepository::update() → status: "Pending"
```

### 5.2 Certificate Status Sync (Auto + Manual)

```
┌─ AUTOMATIC (Cron) ──────────────────────────────────────────────────┐
│                                                                      │
│  SyncService::syncCertificateStatus()                                │
│  ├─ Query: nicsrs_sslorders WHERE status IN                         │
│  │    ('pending','processing','awaiting_issuance','draft',           │
│  │     'awaiting','awaiting configuration')                          │
│  ├─ Limit to sync_batch_size (default 50)                           │
│  │                                                                    │
│  │  For each pending cert:                                           │
│  │  ├─ Get API token for order's service                             │
│  │  ├─ API: /collect (certId)                                        │
│  │  ├─ Parse response → extract status, dates, cert data             │
│  │  ├─ Merge into configdata.applyReturn:                            │
│  │  │   vendorId, beginDate, endDate, certificate,                   │
│  │  │   caCertificate, DCVfile*, DCVdns*, dcvList                    │
│  │  ├─ Update lastRefresh + lastAutoSync timestamps                  │
│  │  ├─ If status changed → update nicsrs_sslorders.status            │
│  │  ├─ If → "complete":                                              │
│  │  │   ├─ Set completiondate                                        │
│  │  │   └─ sendCompletionNotification() → HTML email to admin        │
│  │  └─ If API error → log, continue to next cert                     │
│  │                                                                    │
│  ├─ Then: Check ACTIVE certs for expiry                              │
│  │  ├─ Query: WHERE status IN ('complete','active','issued')         │
│  │  ├─ For each: check endDate from configdata                       │
│  │  ├─ If expired → update status to 'expired'                       │
│  │  └─ If expiring within N days → sendExpiryWarning()               │
│  │                                                                    │
│  └─ Update last_status_sync; reset sync_error_count on success       │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘

┌─ MANUAL (Admin Panel) ──────────────────────────────────────────────┐
│                                                                      │
│  OrderController::handleAjax('refresh_status')                       │
│  ├─ Get order by ID                                                  │
│  ├─ API: /collect (certId)                                           │
│  ├─ Same data merge logic as auto-sync                               │
│  ├─ Update status + configdata + completiondate                      │
│  └─ Return JSON response to admin UI                                 │
│                                                                      │
│  Client-side: ActionController::refreshStatus()                      │
│  ├─ Similar logic but via ActionDispatcher                           │
│  └─ Updates order via OrderRepository::update()                      │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 5.3 Vendor Migration Flow

This flow handles the transition from another SSL provider (e.g., cPanel SSL, GoGetSSL) to NicSRS within an existing WHMCS service.

```
Service with servertype changed to nicsrs_ssl
│
└─ WHMCS triggers: nicsrs_ssl_CreateAccount($params)
    │
    ├─ hasActiveVendorCert($params['serviceid'])?
    │   └─ Checks tblsslorders for active cert from another module
    │
    ├─ YES → Vendor cert detected
    │   ├─ Log: "Blocked: Active certificate from another vendor"
    │   ├─ Return 'success' (silent — no error to admin)
    │   └─ Client visits service page:
    │       └─ PageController::index()
    │           ├─ No nicsrs_sslorders record exists
    │           ├─ checkVendorCert() finds tblsslorders record
    │           └─ TemplateHelper::migrated() → migrated.tpl
    │               └─ Shows read-only vendor cert info
    │
    └─ Admin views service tab:
        └─ nicsrs_ssl_AdminServicesTabFields()
            ├─ No nicsrs_sslorders → buildVendorMigrationWarning()
            │   └─ Displays: vendor name, cert ID, status, expiry
            └─ Shows "Allow New Certificate" button
                │
                └─ Admin clicks button:
                    └─ nicsrs_ssl_AdminAllowNewCert($params)
                        ├─ Check: NicSRS order already exists? → abort
                        ├─ Gather vendor info from tblsslorders:
                        │   previousVendor, previousRemoteId,
                        │   previousStatus, previousOrderId
                        ├─ Build configdata with migration flags:
                        │   migratedFromVendor: true
                        │   adminOverride: true
                        │   adminOverrideAt: timestamp
                        │   originalfromOthers: '1'
                        │   isRenew: '1'
                        ├─ OrderRepository::create() → "Awaiting Configuration"
                        └─ Client can now configure new NicSRS certificate
                            └─ Normal apply flow (5.1) with isRenew flag
                                └─ API /place receives originalfromOthers='1'
```

### 5.4 Certificate Import Flow

```
Admin → Addons → NicSRS SSL Admin → Import
│
├─ Single Import (with service link):
│   ├─ Admin enters Certificate ID + Service ID
│   ├─ ImportController::linkExistingService()
│   │   ├─ Validate cert not already imported
│   │   ├─ Validate service exists and servertype = nicsrs_ssl
│   │   ├─ Validate service not already linked to an order
│   │   ├─ API: /collect (certId) → get full cert data
│   │   ├─ Build configdata from API response:
│   │   │   domainInfo, applyReturn (dates, certificate, caCertificate)
│   │   │   importedAt, importedBy (admin ID)
│   │   ├─ Create nicsrs_sslorders with userid + serviceid from service
│   │   └─ Log activity: 'link_cert'
│   │
├─ Single Import (without service):
│   ├─ ImportController::importCertificate()
│   │   ├─ API: /collect (certId)
│   │   ├─ Create nicsrs_sslorders with userid=0, serviceid=0
│   │   └─ Can be linked to service later
│   │
└─ Bulk Import:
    ├─ Admin enters multiple Certificate IDs (one per line)
    ├─ ImportController::bulkImport()
    │   ├─ For each certId:
    │   │   ├─ Skip if already imported
    │   │   ├─ API: /collect (certId)
    │   │   ├─ Create unlinked order (userid=0, serviceid=0)
    │   │   └─ Set bulkImport=true in configdata
    │   └─ Return summary: imported count, error list
    └─ Log activity: 'bulk_import'
```

### 5.5 Reissue / Replace Flow

```
Client visits service page → Certificate is Complete/Issued
│
├─ Client clicks "Reissue Certificate" button
│   └─ Redirect to: ?modop=custom&a=reissue
│       └─ PageDispatcher::dispatch('reissue', $params)
│           └─ PageController::reissue() → reissue.tpl
│               └─ Multi-step form (same structure as applycert.tpl)
│                   ├─ Step 1: New CSR
│                   ├─ Step 2: Domain + DCV
│                   ├─ Step 3: Admin contact
│                   ├─ Step 4: Organization (OV/EV)
│                   └─ Pre-filled with existing order data
│
└─ Client submits reissue form:
    └─ AJAX POST step=submitReissue
        └─ ActionController::submitReissue()
            ├─ Get existing order + validate remoteid exists
            ├─ Build new request data from form
            ├─ API: /reissue (certId, csr, domainInfo)
            ├─ Update configdata with new CSR + domains
            ├─ OrderRepository::update() → status: "Reissue"
            └─ Auto-sync will track completion via /collect
```

### 5.6 Renew Flow

```
Client clicks "Renew" (when cert is Complete/near expiry)
│
└─ ActionController::renew()
    ├─ Get existing order
    ├─ Store renewFrom = current remoteid
    ├─ Set flags: originalfromOthers='1', isRenew='1'
    ├─ Reset: remoteid='', status='Awaiting Configuration'
    └─ Client sees applycert.tpl again
        └─ Submit triggers normal /place flow (5.1)
            └─ API receives originalfromOthers='1' indicating renewal
```

---

## 6. Notification Architecture

```
NotificationService
│
├─ sendCertificateIssuedNotification($cert)
│   ├─ Triggered by: SyncService (auto-sync completion)
│   ├─ Condition: email_on_issuance = true AND status changed to Complete
│   ├─ Email: HTML formatted with cert details, domain, dates
│   └─ Via: WHMCS Local API → SendAdminEmail
│
├─ sendExpiryWarning($cert, $daysUntilExpiry)
│   ├─ Triggered by: SyncService (active cert expiry check)
│   ├─ Condition: email_on_expiry = true AND days ≤ expiry_days setting
│   ├─ Email: HTML with urgency indicator (🚨 ≤7 days, ⚠️ otherwise)
│   └─ Via: WHMCS Local API → SendAdminEmail
│
├─ sendSyncErrorNotification($errors, $errorCount)
│   ├─ Triggered by: SyncService when sync_error_count ≥ 3
│   ├─ Email: HTML with error details list + Settings link
│   └─ Via: WHMCS Local API → SendAdminEmail
│
└─ SyncService::sendPriceChangeNotification($priceChanges)
    ├─ Triggered by: Product sync detects price changes
    ├─ Email: HTML table with old/new prices, % change, direction arrows
    └─ Via: WHMCS Local API → SendAdminEmail

All emails:
├─ Use HTML templates with inline CSS (email client compatibility)
├─ Include HVN GROUP branding in footer
├─ Sent via WHMCS SendAdminEmail Local API (NOT PHP mail())
└─ Recipient: admin_email setting OR WHMCS system email
```

---

## 7. Security Architecture

### CSRF Protection
- Admin Addon: WHMCS handles CSRF for addon module pages automatically
- Server Module: AJAX requests validated via `ActionDispatcher::validateAccess()`
- Admin area requests check `defined('ADMINAREA') && ADMINAREA`

### Access Control
- Client actions: `PageDispatcher::validateServiceOwnership()` verifies `tblhosting.userid` matches session
- Public actions (CSR generate/decode): exempt from auth in `ActionDispatcher::$publicActions`
- Admin actions: WHMCS admin session required

### API Token Security
- Tokens masked in all log entries (first 8 chars + `***`)
- Product-level tokens stored in `tblproducts.configoption2` (WHMCS password field type)
- Addon token stored in `tbladdonmodules` (WHMCS manages encryption)

### Input Validation
- All user inputs sanitized via `htmlspecialchars()` / `$helper->e()`
- CSR format validated before API submission
- Domain names validated
- SQL injection prevented by WHMCS Capsule ORM (parameterized queries)
- JSON payloads validated with `json_last_error()` checks