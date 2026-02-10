# Code Standards & Development Rules

> **Project:** NicSRS SSL Management System for WHMCS  
> **Last Updated:** 2026-02-09

---

## 1. General Principles

- **Backward compatibility first**: Never break existing customer data or workflows
- **WHMCS conventions over custom**: Follow WHMCS patterns for hooks, APIs, templates
- **Capsule ORM always**: No raw SQL; use `WHMCS\Database\Capsule` for all DB operations
- **Fail gracefully**: Never expose raw errors to end users; log everything via `logModuleCall()`
- **Mask secrets**: API tokens must be masked in all logs (first 8 chars + `***`)

---

## 2. Naming Conventions

### PHP Classes

| Component | Pattern | Example |
|---|---|---|
| Admin Controller | `PascalCase` + `Controller` suffix | `DashboardController`, `OrderController` |
| Admin Service | `PascalCase` + `Service` suffix | `SyncService`, `NotificationService` |
| Admin Helper | `PascalCase` + `Helper` suffix | `ViewHelper`, `CurrencyHelper` |
| Server Controller | `PascalCase` + `Controller` suffix | `PageController`, `ActionController` |
| Server Dispatcher | `PascalCase` + `Dispatcher` suffix | `PageDispatcher`, `ActionDispatcher` |
| Server Service | `PascalCase` | `ApiService`, `OrderRepository`, `CertificateFunc` |

### Namespaces

```php
// Admin Addon
namespace NicsrsAdmin\Controller;
namespace NicsrsAdmin\Service;
namespace NicsrsAdmin\Helper;

// Server Module
namespace nicsrsSSL;  // Note: lowercase 'n' (legacy convention, maintain consistency)
```

### Files

| Type | Pattern | Example |
|---|---|---|
| PHP class | PascalCase matching class name | `DashboardController.php`, `SyncService.php` |
| Template (Admin) | lowercase with underscores | `order_detail.php`, `dashboard.php` |
| Template (Client) | lowercase | `applycert.tpl`, `complete.tpl` |
| Language file | lowercase | `english.php`, `vietnamese.php` |
| Asset | lowercase with hyphens | `ssl-manager.css`, `nicsrs-admin.js` |

### Methods

| Type | Convention | Example |
|---|---|---|
| Public method | `camelCase` | `render()`, `handleAjax()`, `getStatistics()` |
| Private method | `camelCase` | `getRecentOrders()`, `buildApiRequest()` |
| Static method | `camelCase` | `getApiToken()`, `dispatch()`, `getByServiceId()` |
| AJAX handler | match `ajax_action` value | `handleManualSync()`, `lookupCertificate()` |

### Database

| Element | Convention | Example |
|---|---|---|
| Custom table | `mod_nicsrs_` prefix | `mod_nicsrs_products`, `mod_nicsrs_settings` |
| Order table | No prefix (legacy) | `nicsrs_sslorders` |
| Column | `snake_case` | `product_code`, `setting_key`, `admin_id` |
| Index | `idx_` prefix | `idx_vendor`, `idx_created_at` |
| Unique key | `uk_` prefix | `uk_product_code`, `uk_setting_key` |

### Constants

```php
// Module-level (nicsrs_ssl_admin.php)
define('NICSRS_ADMIN_VERSION', '1.3.1');
define('NICSRS_ADMIN_PATH', __DIR__);

// Status constants (const.php)
define('SSL_STATUS_AWAITING', 'awaiting');
define('SSL_STATUS_DRAFT', 'draft');
define('SSL_STATUS_PENDING', 'pending');
define('SSL_STATUS_COMPLETE', 'complete');
define('SSL_STATUS_CANCELLED', 'cancelled');
define('SSL_STATUS_REVOKED', 'revoked');
define('SSL_STATUS_EXPIRED', 'expired');
define('SSL_STATUS_REISSUE', 'reissue');

// API URL
define('NICSRS_API_URL', 'https://portal.nicsrs.com/ssl');
```

---

## 3. Architecture Patterns

### Admin Addon — Controller-Template Pattern

```php
// Controller MUST extend BaseController
class ProductController extends BaseController
{
    public function __construct(array $vars)
    {
        parent::__construct($vars);         // Initialize: modulelink, lang, viewHelper, logger, adminId
        $this->apiService = new NicsrsApiService($this->getApiToken());
    }

    // render() for page display — ALWAYS call includeTemplate()
    public function render(string $action): void
    {
        $data = [
            'products' => $this->getProducts(),
            'filters'  => $this->getFilters(),
        ];
        $this->includeTemplate('products', $data);  // → templates/products.php
    }

    // handleAjax() for AJAX — ALWAYS return JSON string
    public function handleAjax(array $post): string
    {
        $action = $post['ajax_action'] ?? '';
        switch ($action) {
            case 'sync_all':
                return $this->syncAll();        // → jsonSuccess() or jsonError()
            default:
                return $this->jsonError('Unknown action');
        }
    }
}
```

### BaseController Provided Methods

```php
// Template rendering
$this->includeTemplate('template_name', $dataArray);

// JSON responses (for AJAX handlers)
$this->jsonSuccess('Message', ['extra' => 'data']);  // → {"success":true,"message":"...","extra":"data"}
$this->jsonError('Error message');                    // → {"success":false,"message":"..."}
$this->jsonResponse($anyArray);                       // → raw JSON encode

// Settings access
$this->getSetting('setting_key', $default);           // Auto type-cast (bool, int, json, string)
$this->saveSetting('key', $value, 'type');

// Common utilities
$this->getApiToken();        // From $vars['api_token']
$this->getItemsPerPage();    // From $vars['items_per_page']
$this->getCurrentPage();     // From $_GET['page']
$this->sanitize($input);     // htmlspecialchars + trim
```

### Server Module — Dispatcher Pattern

```php
// ActionDispatcher resolves action name → controller method
// Supports aliases for backward compatibility
ActionDispatcher::dispatch('refreshStatus', $params);
ActionDispatcher::dispatch('refresh', $params);        // Same target via alias

// PageDispatcher resolves page name → controller method
PageDispatcher::dispatch('reissue', $params);
PageDispatcher::dispatch('replace', $params);           // Alias → reissue

// dispatchByStatus delegates to PageController::index()
// which uses normalizeStatus() to determine the right template
PageDispatcher::dispatchByStatus($params);
```

### Server Module — Static Service Pattern

```php
// All service classes use STATIC methods (no constructor, no DI)
$response = ApiService::collect($params, $certId);
$order    = OrderRepository::getByServiceId($serviceId);
$code     = CertificateFunc::normalizeToCode($identifier);
$template = TemplateHelper::complete($params, $order, $cert);
$result   = ResponseFormatter::success($data, 'Message');
```

---

## 4. Error Handling

### Try-Catch Pattern (Admin Addon)

```php
public function handleAjax(array $post): string
{
    try {
        // Business logic
        return $this->jsonSuccess('Done', $data);
    } catch (\Exception $e) {
        // Always log with module name + function context
        logModuleCall(
            'nicsrs_ssl_admin',
            'ClassName::methodName',
            ['context' => $post],
            $e->getMessage(),
            $e->getTraceAsString()  // Stack trace in debug field
        );
        return $this->jsonError('Operation failed: ' . $e->getMessage());
    }
}
```

### Try-Catch Pattern (Server Module)

```php
public static function submitApply(array $params): array
{
    try {
        // Business logic
        return ResponseFormatter::success($data, 'Submitted');
    } catch (Exception $e) {
        logModuleCall('nicsrs_ssl', 'submitApply', $params, $e->getMessage());
        return ResponseFormatter::error($e->getMessage());
    }
}
```

### Rules

1. **Every public method** in controllers MUST have try-catch
2. **Every API call** MUST be wrapped in try-catch
3. **Log before returning error** — use `logModuleCall()` with module name, action, input, output
4. **Never expose stack traces** to end users — log them, return user-friendly message
5. **Continue on non-critical failures** in sync/cron (one cert failure shouldn't stop the batch)

---

## 5. WHMCS Best Practices

### Local API Usage

```php
// ✅ CORRECT: Use WHMCS Local API for emails
$results = localAPI('SendAdminEmail', [
    'customsubject' => $subject,
    'custommessage' => $htmlBody,
    'type' => 'system',
]);

// ❌ WRONG: Never use PHP mail() directly
mail($to, $subject, $body);  // DEPRECATED — unreliable, no WHMCS logging
```

### Database Access

```php
// ✅ CORRECT: Capsule ORM
use WHMCS\Database\Capsule;
$order = Capsule::table('nicsrs_sslorders')->where('id', $id)->first();

// ✅ CORRECT: Parameterized queries for raw SQL (rare cases)
Capsule::select("SELECT * FROM nicsrs_sslorders WHERE status = ?", [$status]);

// ❌ WRONG: Never build SQL strings with user input
$result = Capsule::select("SELECT * FROM orders WHERE id = $id");  // SQL injection risk!
```

### Hook Registration

```php
// hooks.php — Always check module is active before heavy operations
add_hook('AfterCronJob', 1, function ($vars) {
    if (!nicsrs_ssl_admin_is_module_active()) {
        return;  // Skip if module deactivated
    }
    // Sync logic...
});
```

### Module Logging

```php
// Always use logModuleCall() — appears in Utilities → Logs → Module Log
logModuleCall(
    'nicsrs_ssl',         // Module identifier
    'ActionName',         // Action/function being performed
    $requestData,         // Input data (arrays auto-serialized)
    $responseData,        // Output data
    $errorTrace           // Optional error/debug info
);
```

### Template Variables

```php
// Admin: PHP templates with extract()
$this->includeTemplate('orders', ['orders' => $orderList, 'filters' => $filters]);
// In template: $orders and $filters are available directly
// Also always available: $modulelink, $lang, $version, $helper

// Client: Smarty .tpl templates
return [
    'tabOverviewReplacementTemplate' => 'view/applycert.tpl',
    'templateVariables' => [
        'productCode' => $cert['name'],
        'configData'  => $configdata,
        // Smarty: {$productCode}, {$configData.domainInfo}
    ],
];
```

---

## 6. AJAX Communication Standards

### Admin Addon AJAX

```javascript
// Request pattern (from templates):
$.ajax({
    url: modulelink,           // PHP-provided modulelink variable
    type: 'POST',
    dataType: 'json',
    data: {
        ajax_action: 'manual_sync',    // Always required
        sync_type: 'status',           // Action-specific params
    },
    success: function(response) {
        if (response.success) {
            showToast(response.message, 'success');
        } else {
            showToast(response.message, 'error');
        }
    }
});
```

### Server Module AJAX

```javascript
// Request pattern (from .tpl templates):
$.ajax({
    url: '{$smarty.server.REQUEST_URI}',
    type: 'POST',
    dataType: 'json',
    data: {
        step: 'submitApply',          // Action identifier
        data: JSON.stringify(formData) // Complex data as JSON string
    },
    success: function(response) {
        if (response.success) { ... }
    }
});
```

### Response Format

```json
// Success
{ "success": true, "message": "Operation completed", "data": { ... } }

// Error
{ "success": false, "message": "Error description" }
```

---

## 7. Security Rules

| Rule | Implementation |
|---|---|
| CSRF protection | WHMCS handles for addon modules; server module validates request origin |
| Admin area check | `defined('ADMINAREA') && ADMINAREA` for admin-only operations |
| Service ownership | `PageDispatcher::validateServiceOwnership()` — checks `tblhosting.userid` |
| API token masking | All log entries mask tokens: `substr($token, 0, 8) . '***'` |
| Input sanitization | `htmlspecialchars()` via `ViewHelper::e()` for display; Capsule ORM for DB |
| SSL verification | `CURLOPT_SSL_VERIFYPEER => true`, `CURLOPT_SSL_VERIFYHOST => 2` |
| No secrets in JS | API tokens never exposed to client-side code |
| Password fields | `configoption2` uses WHMCS `Type: password` |

---

## 8. Template Standards

### Admin Templates (PHP)

```php
<?php
/**
 * Template description
 * 
 * @var array $data Variable description
 * @var string $modulelink Module link
 * @var \NicsrsAdmin\Helper\ViewHelper $helper View helper
 */
?>
<div class="nicsrs-section">
    <!-- Content -->
    <?php echo $helper->e($unsafeString); ?>           <!-- Always escape output -->
    <?php echo $helper->statusBadge($status); ?>       <!-- Formatted badge -->
    <?php echo $helper->formatDate($date); ?>           <!-- Formatted date -->
</div>
```

### Client Templates (Smarty)

```smarty
{* Template description *}
{* Load CSS *}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/nicsrs_ssl/assets/css/ssl-manager.css">

<div class="sslm-container">
    {* Always escape dynamic content *}
    {$variable|escape:'html'}
    
    {* Default values for optional variables *}
    {$_LANG.label|default:'Default Text'}
    
    {* Conditional sections *}
    {if $requiresOrganization}
        {* OV/EV only content *}
    {/if}
</div>
```

---

## 9. Version Management

### Version Constants

```php
// Admin: nicsrs_ssl_admin.php
define('NICSRS_ADMIN_VERSION', '1.3.1');

// Server: src/config/const.php
define('NICSRS_MODULE_VERSION', '2.1.0');
```

### Upgrade Function Pattern

```php
function nicsrs_ssl_admin_upgrade($vars)
{
    $currentVersion = $vars['version'];
    
    // Always use version_compare for reliable comparison
    if (version_compare($currentVersion, '1.3.0', '<')) {
        // Migration: add currency settings
        $newSettings = [...];
        foreach ($newSettings as $setting) {
            $exists = Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', $setting['setting_key'])->exists();
            if (!$exists) {
                Capsule::table('mod_nicsrs_settings')->insert($setting);
            }
        }
    }
    
    // Future: if (version_compare($currentVersion, '1.4.0', '<')) { ... }
    
    return ['status' => 'success', 'description' => 'Upgraded to v' . NICSRS_ADMIN_VERSION];
}
```

### Asset Versioning

```php
// Cache-bust via version query string
<link rel="stylesheet" href="<?php echo $assetPath; ?>/css/admin.css?v=<?php echo NICSRS_ADMIN_VERSION; ?>">
<script src="<?php echo $assetPath; ?>/js/admin.js?v=<?php echo NICSRS_ADMIN_VERSION; ?>"></script>
```