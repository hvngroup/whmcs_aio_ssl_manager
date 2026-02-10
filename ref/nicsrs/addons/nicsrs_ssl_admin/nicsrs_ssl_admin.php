<?php
/**
 * NicSRS SSL Admin Addon Module
 * Main Entry Point
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 * @version    2.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Define module constants
if (!defined('NICSRS_ADMIN_VERSION')) {
    define('NICSRS_ADMIN_VERSION', '2.0.0');
}
if (!defined('NICSRS_ADMIN_PATH')) {
    define('NICSRS_ADMIN_PATH', __DIR__);
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'NicsrsAdmin\\';
    $baseDir = NICSRS_ADMIN_PATH . '/lib/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Module configuration
 * 
 * @return array
 */
function nicsrs_ssl_admin_config()
{
    return [
        'name' => 'HVN - NicSRS SSL Admin',
        'description' => 'Comprehensive SSL certificate management for NicSRS resellers. Manage products, orders, and certificates from admin panel.',
        'version' => NICSRS_ADMIN_VERSION,
        'author' => '<a href="https://hvn.vn" target="_blank">HVN GROUP</a>',
        'language' => 'english',
        'fields' => [
            'api_token' => [
                'FriendlyName' => 'NicSRS API Token',
                'Type' => 'password',
                'Size' => '64',
                'Description' => 'Enter your NicSRS API Token from portal.nicsrs.com',
            ],
            'items_per_page' => [
                'FriendlyName' => 'Items Per Page',
                'Type' => 'dropdown',
                'Options' => '10,25,50,100',
                'Default' => '25',
                'Description' => 'Number of items to display per page in tables',
            ],
        ],
    ];
}

/**
 * Module activation hook - creates database tables
 * 
 * @return array
 */
function nicsrs_ssl_admin_activate()
{
    try {
        // Create products cache table
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
            
            // Insert default settings
            $defaultSettings = [
                // Notification settings
                ['setting_key' => 'email_on_issuance', 'setting_value' => '1', 'setting_type' => 'boolean'],
                ['setting_key' => 'email_on_expiry', 'setting_value' => '1', 'setting_type' => 'boolean'],
                ['setting_key' => 'expiry_days', 'setting_value' => '30', 'setting_type' => 'integer'],
                ['setting_key' => 'admin_email', 'setting_value' => '', 'setting_type' => 'string'],
                
                // Auto-sync settings
                ['setting_key' => 'auto_sync_status', 'setting_value' => '1', 'setting_type' => 'boolean'],
                ['setting_key' => 'sync_interval_hours', 'setting_value' => '6', 'setting_type' => 'integer'],
                ['setting_key' => 'product_sync_hours', 'setting_value' => '24', 'setting_type' => 'integer'],
                ['setting_key' => 'sync_batch_size', 'setting_value' => '50', 'setting_type' => 'integer'],
                ['setting_key' => 'last_status_sync', 'setting_value' => '', 'setting_type' => 'datetime'],
                ['setting_key' => 'last_product_sync', 'setting_value' => '', 'setting_type' => 'datetime'],
                ['setting_key' => 'sync_error_count', 'setting_value' => '0', 'setting_type' => 'integer'],
                
                // Display settings
                ['setting_key' => 'date_format', 'setting_value' => 'Y-m-d', 'setting_type' => 'string'],
                
                // Currency settings
                ['setting_key' => 'usd_vnd_rate', 'setting_value' => '25000', 'setting_type' => 'number'],
                ['setting_key' => 'currency_display', 'setting_value' => 'both', 'setting_type' => 'string'],
                ['setting_key' => 'rate_last_updated', 'setting_value' => '', 'setting_type' => 'datetime'],
            ];

            foreach ($defaultSettings as $setting) {
                Capsule::table('mod_nicsrs_settings')->insert($setting);
            }
        }

        return [
            'status' => 'success',
            'description' => 'NicSRS SSL Admin module activated successfully. Database tables created.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation hook
 * 
 * @return array
 */
function nicsrs_ssl_admin_deactivate()
{
    // Note: We keep tables for data preservation
    // Uncomment below to drop tables on deactivation
    /*
    try {
        Capsule::schema()->dropIfExists('mod_nicsrs_products');
        Capsule::schema()->dropIfExists('mod_nicsrs_activity_log');
        Capsule::schema()->dropIfExists('mod_nicsrs_settings');
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
    */

    return [
        'status' => 'success',
        'description' => 'NicSRS SSL Admin module deactivated. Database tables preserved.',
    ];
}

/**
 * Module upgrade hook
 * 
 * @param array $vars Module variables including version
 * @return array
 */
function nicsrs_ssl_admin_upgrade($vars)
{
    $currentVersion = $vars['version'];
    
    try {
        // Upgrade to v1.2.1 - Add Auto-Sync settings
        if (version_compare($currentVersion, '1.2.1', '<')) {
            
            // New settings for Auto-Sync feature
            $newSettings = [
                ['setting_key' => 'sync_batch_size', 'setting_value' => '50', 'setting_type' => 'integer'],
                ['setting_key' => 'last_status_sync', 'setting_value' => '', 'setting_type' => 'datetime'],
                ['setting_key' => 'last_product_sync', 'setting_value' => '', 'setting_type' => 'datetime'],
                ['setting_key' => 'sync_error_count', 'setting_value' => '0', 'setting_type' => 'integer'],
            ];

            foreach ($newSettings as $setting) {
                $exists = Capsule::table('mod_nicsrs_settings')
                    ->where('setting_key', $setting['setting_key'])
                    ->exists();
                
                if (!$exists) {
                    Capsule::table('mod_nicsrs_settings')->insert($setting);
                }
            }
            
            // Log upgrade
            logModuleCall(
                'nicsrs_ssl_admin',
                'upgrade_to_1.2.1',
                ['from' => $currentVersion],
                'Added Auto-Sync settings',
                'SUCCESS'
            );
        }
        
        // Upgrade to v1.3.0 - Add currency settings for Reports
        if (version_compare($currentVersion, '1.3.0', '<')) {
            
            // Add new currency settings if not exist
            $currencySettings = [
                ['setting_key' => 'usd_vnd_rate', 'setting_value' => '25000', 'setting_type' => 'number'],
                ['setting_key' => 'currency_display', 'setting_value' => 'both', 'setting_type' => 'string'],
                ['setting_key' => 'rate_last_updated', 'setting_value' => '', 'setting_type' => 'datetime'],
            ];

            foreach ($currencySettings as $setting) {
                $exists = Capsule::table('mod_nicsrs_settings')
                    ->where('setting_key', $setting['setting_key'])
                    ->exists();
                
                if (!$exists) {
                    Capsule::table('mod_nicsrs_settings')->insert($setting);
                }
            }
        }
        
        // Future upgrades can be added here
        // if (version_compare($currentVersion, '1.4.0', '<')) { ... }
        
    } catch (\Exception $e) {
        // Log error but don't fail upgrade
        logModuleCall(
            'nicsrs_ssl_admin',
            'upgrade_error',
            ['from' => $currentVersion, 'to' => NICSRS_ADMIN_VERSION],
            $e->getMessage(),
            'ERROR'
        );
    }
    
    return [
        'status' => 'success',
        'description' => 'Module upgraded to v' . NICSRS_ADMIN_VERSION,
    ];
}

/**
 * Main output function - routes to appropriate controller
 * 
 * @param array $vars Module configuration variables
 * @return void
 */
function nicsrs_ssl_admin_output($vars)
{
    // Get current action from request
    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'dashboard';
    $modulelink = $vars['modulelink'];
    
    // Handle AJAX requests
    if (!empty($_POST['ajax_action'])) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set JSON header
        header('Content-Type: application/json; charset=utf-8');
        
        // Disable error display for AJAX (log instead)
        @ini_set('display_errors', 0);
        
        try {
            $response = handleAjaxRequest($vars, $action);
            echo $response;
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
        
        // Stop execution
        exit;
    }
    
    // Controller mapping
    $controllerMap = [
        'dashboard' => 'DashboardController',
        'products'  => 'ProductController',
        'orders'    => 'OrderController',
        'order'     => 'OrderController',
        'settings'  => 'SettingsController',
        'activity'  => 'ActivityController',
        'import'    => 'ImportController',
        'reports'    => 'ReportController',
    ];
    
    $controllerName = isset($controllerMap[$action]) ? $controllerMap[$action] : 'DashboardController';
    $controllerClass = "NicsrsAdmin\\Controller\\{$controllerName}";
    
    try {
        // Check if controller class exists
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller not found: {$controllerClass}");
        }
        
        $controller = new $controllerClass($vars);
        
        // Output CSS
        outputAssets();
        
        // Render navigation
        renderNavigation($modulelink, $action);
        
        // Render page content
        echo '<div class="nicsrs-content">';
        $controller->render($action);
        echo '</div>';
        
        // Render footer
        renderFooter();
        
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
        
        // Log error
        logModuleCall(
            'nicsrs_ssl_admin',
            'output_error',
            ['action' => $action],
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
}

/**
 * Handle AJAX requests
 * 
 * @param array $vars Module variables
 * @param string $action Current action
 * @return void
 */
function handleAjaxRequest($vars, $action)
{
    $controllerMap = [
        'dashboard' => 'DashboardController',
        'products'  => 'ProductController',
        'orders'    => 'OrderController',
        'order'     => 'OrderController',
        'settings'  => 'SettingsController',
        'import'    => 'ImportController',  
        'reports'   => 'ReportController',              
    ];
    
    $controllerName = isset($controllerMap[$action]) ? $controllerMap[$action] : 'DashboardController';
    $controllerClass = "NicsrsAdmin\\Controller\\{$controllerName}";
    
    if (!class_exists($controllerClass)) {
        return json_encode([
            'success' => false,
            'message' => 'Controller not found',
        ]);
    }
    
    $controller = new $controllerClass($vars);
    return $controller->handleAjax($_POST);
}

/**
 * Output CSS and JS assets
 * 
 * @return void
 */
function outputAssets()
{
    $assetPath = '../modules/addons/nicsrs_ssl_admin/assets';
    ?>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/css/admin.css?v=<?php echo NICSRS_ADMIN_VERSION; ?>">
    <script src="<?php echo $assetPath; ?>/js/admin.js?v=<?php echo NICSRS_ADMIN_VERSION; ?>"></script>
    <?php
}

/**
 * Render navigation tabs
 * 
 * @param string $modulelink Module link
 * @param string $currentAction Current action
 * @return void
 */
function renderNavigation($modulelink, $currentAction)
{
    $navItems = [
        'dashboard' => ['icon' => 'fa-dashboard', 'label' => 'Dashboard'],
        'products'  => ['icon' => 'fa-cube', 'label' => 'Products'],
        'orders'    => ['icon' => 'fa-shopping-cart', 'label' => 'Orders'],
        'import'    => ['icon' => 'fa-download', 'label' => 'Import'],
        'reports'    => ['icon' => 'fa-bar-chart', 'label' => 'Reports'],
        'settings'  => ['icon' => 'fa-cog', 'label' => 'Settings'],
    ];
    ?>
    <div class="nicsrs-admin-wrapper">
        <div class="nicsrs-header">
            <h2><i class="fa fa-shield"></i> NicSRS SSL Admin</h2>
        </div>
        <ul class="nav nav-tabs nicsrs-nav">
            <?php foreach ($navItems as $action => $item): ?>
                <li class="<?php echo ($currentAction === $action || ($currentAction === 'order' && $action === 'orders')) ? 'active' : ''; ?>">
                    <a href="<?php echo $modulelink; ?>&action=<?php echo $action; ?>">
                        <i class="fa <?php echo $item['icon']; ?>"></i> <?php echo $item['label']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php
}

/**
 * Render footer
 * 
 * @return void
 */
function renderFooter()
{
    ?>
        <div class="nicsrs-footer">
            <small>
                NicSRS SSL Admin v<?php echo NICSRS_ADMIN_VERSION; ?> | 
                Developed by <a href="https://hvn.vn" target="_blank">HVN GROUP</a>
            </small>
        </div>
    </div><!-- .nicsrs-admin-wrapper -->
    <?php
}

// Register hooks for cron integration (Auto-Sync feature)
if (file_exists(__DIR__ . '/hooks.php')) {
    require_once __DIR__ . '/hooks.php';
}