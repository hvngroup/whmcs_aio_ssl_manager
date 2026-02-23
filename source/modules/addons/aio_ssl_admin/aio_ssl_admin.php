<?php
/**
 * HVN - AIO SSL Manager — Admin Addon Module
 *
 * Centralized SSL Certificate Management for WHMCS
 * Supports: NicSRS, GoGetSSL, TheSSLStore, SSL2Buy
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 * @license    Proprietary
 * @version    1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

// Module constants
define('AIO_SSL_VERSION', '1.0.0');
define('AIO_SSL_MODULE', 'aio_ssl_admin');
define('AIO_SSL_PATH', __DIR__);
define('AIO_SSL_LIB_PATH', AIO_SSL_PATH . '/lib');
define('AIO_SSL_TEMPLATE_PATH', AIO_SSL_PATH . '/templates');
define('AIO_SSL_LANG_PATH', AIO_SSL_PATH . '/lang');

// PSR-4 compatible autoloader
require_once AIO_SSL_PATH . '/lib/autoload.php';

use AioSSL\Controller\BaseController;

/**
 * Module configuration
 *
 * @return array
 */
function aio_ssl_admin_config()
{
    return [
        'name'        => 'HVN - AIO SSL Manager',
        'description' => 'Centralized SSL Certificate Management across NicSRS, GoGetSSL, TheSSLStore, and SSL2Buy. '
                       . 'Cross-provider price comparison, unified dashboard, and backward-compatible order management.',
        'version'     => AIO_SSL_VERSION,
        'author'      => '<a href="https://hvn.vn" target="_blank">HVN GROUP</a>',
        'language'    => 'english',
        'fields'      => [],
    ];
}

/**
 * Module activation — Create database tables and seed defaults
 *
 * @return array
 */
function aio_ssl_admin_activate()
{
    try {
        $pdo = \WHMCS\Database\Capsule::connection()->getPdo();

        // Table: mod_aio_ssl_providers
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mod_aio_ssl_providers` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `slug` varchar(50) NOT NULL,
            `name` varchar(100) NOT NULL,
            `tier` enum('full','limited') NOT NULL DEFAULT 'full',
            `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int NOT NULL DEFAULT 0,
            `api_credentials` text,
            `api_mode` enum('live','sandbox') NOT NULL DEFAULT 'live',
            `config` text,
            `last_sync` datetime DEFAULT NULL,
            `last_test` datetime DEFAULT NULL,
            `test_result` tinyint(1) DEFAULT NULL,
            `sync_error_count` int NOT NULL DEFAULT 0,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_slug` (`slug`),
            KEY `idx_enabled` (`is_enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Table: mod_aio_ssl_products
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mod_aio_ssl_products` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `provider_slug` varchar(50) NOT NULL,
            `product_code` varchar(150) NOT NULL,
            `product_name` varchar(255) NOT NULL,
            `vendor` varchar(50) NOT NULL,
            `validation_type` enum('dv','ov','ev') NOT NULL,
            `product_type` enum('ssl','wildcard','multi_domain','code_signing','email') NOT NULL DEFAULT 'ssl',
            `support_wildcard` tinyint(1) NOT NULL DEFAULT 0,
            `support_san` tinyint(1) NOT NULL DEFAULT 0,
            `max_domains` int NOT NULL DEFAULT 1,
            `max_years` int NOT NULL DEFAULT 1,
            `min_years` int NOT NULL DEFAULT 1,
            `price_data` text,
            `extra_data` text,
            `canonical_id` varchar(100) DEFAULT NULL,
            `last_sync` datetime DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_provider_product` (`provider_slug`, `product_code`),
            KEY `idx_canonical` (`canonical_id`),
            KEY `idx_vendor` (`vendor`),
            KEY `idx_validation` (`validation_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Table: mod_aio_ssl_product_map
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mod_aio_ssl_product_map` (
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
            UNIQUE KEY `uk_canonical` (`canonical_id`),
            KEY `idx_vendor` (`vendor`),
            KEY `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Table: mod_aio_ssl_settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mod_aio_ssl_settings` (
            `setting` varchar(100) NOT NULL,
            `value` text,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Table: mod_aio_ssl_activity_log
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mod_aio_ssl_activity_log` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `admin_id` int(10) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `entity_type` varchar(50) DEFAULT NULL,
            `entity_id` varchar(100) DEFAULT NULL,
            `details` text,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_action` (`action`),
            KEY `idx_entity` (`entity_type`, `entity_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Seed default settings
        $defaults = [
            ['sync_status_interval', '6'],       // hours
            ['sync_product_interval', '24'],      // hours
            ['sync_batch_size', '50'],
            ['sync_enabled', '1'],
            ['notify_issuance', '1'],
            ['notify_expiry', '1'],
            ['notify_expiry_days', '30'],
            ['notify_sync_errors', '1'],
            ['notify_price_changes', '1'],
            ['notify_admin_email', ''],
            ['currency_display', 'usd'],          // usd | vnd | both
            ['currency_usd_vnd_rate', '25000'],
            ['items_per_page', '25'],
            ['date_format', 'Y-m-d'],
        ];

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO `mod_aio_ssl_settings` (`setting`, `value`) VALUES (?, ?)"
        );
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }

        return ['status' => 'success', 'description' => 'AIO SSL Manager activated successfully.'];

    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

/**
 * Module deactivation
 *
 * @return array
 */
function aio_ssl_admin_deactivate()
{
    // We do NOT drop tables on deactivation to preserve data.
    // Tables are only dropped via manual cleanup if needed.
    return ['status' => 'success', 'description' => 'AIO SSL Manager deactivated. Database tables preserved.'];
}

/**
 * Module upgrade handler — version-based migrations
 *
 * @param array $vars Contains 'version' key with the current module version
 * @return void
 */
function aio_ssl_admin_upgrade($vars)
{
    $currentVersion = $vars['version'];

    // Future migrations go here
    // if (version_compare($currentVersion, '1.1.0', '<')) {
    //     // Migration for 1.1.0
    // }
}

/**
 * Module output — Admin area page rendering
 *
 * @param array $vars Module configuration variables
 * @return void
 */
function aio_ssl_admin_output($vars)
{
    // Load language file
    $lang = _aio_ssl_load_language();

    // Determine current page from request
    $page = isset($_REQUEST['page']) ? trim($_REQUEST['page']) : 'dashboard';
    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

    // AJAX request detection
    $isAjax = (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === '1');

    // Controller mapping
    $controllers = [
        'dashboard'     => 'DashboardController',
        'providers'     => 'ProviderController',
        'products'      => 'ProductController',
        'compare'       => 'PriceCompareController',
        'orders'        => 'OrderController',
        'import'        => 'ImportController',
        'reports'       => 'ReportController',
        'settings'      => 'SettingsController',
    ];

    // Resolve controller
    $controllerName = isset($controllers[$page]) ? $controllers[$page] : 'DashboardController';
    $controllerClass = 'AioSSL\\Controller\\' . $controllerName;

    if (!class_exists($controllerClass)) {
        echo '<div class="alert alert-danger">Controller not found: ' . htmlspecialchars($controllerName) . '</div>';
        return;
    }

    try {
        /** @var BaseController $controller */
        $controller = new $controllerClass($vars, $lang);

        if ($isAjax) {
            // AJAX: dispatch action and return JSON
            header('Content-Type: application/json');
            $response = $controller->handleAjax($action);
            echo json_encode($response);
            return;
        }

        // Render page navigation + controller output
        _aio_ssl_render_navigation($page);
        $controller->render($action);

    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
}

/**
 * Render admin navigation tabs
 *
 * @param string $activePage
 * @return void
 */
function _aio_ssl_render_navigation($activePage)
{
    $pages = [
        'dashboard' => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
        'providers' => ['icon' => 'fa-plug',           'label' => 'Providers'],
        'products'  => ['icon' => 'fa-box',            'label' => 'Products'],
        'compare'   => ['icon' => 'fa-balance-scale',  'label' => 'Price Compare'],
        'orders'    => ['icon' => 'fa-list-alt',       'label' => 'Orders'],
        'import'    => ['icon' => 'fa-file-import',    'label' => 'Import'],
        'reports'   => ['icon' => 'fa-chart-bar',      'label' => 'Reports'],
        'settings'  => ['icon' => 'fa-cog',            'label' => 'Settings'],
    ];

    $moduleLink = 'addonmodules.php?module=aio_ssl_admin';

    echo '<div class="aio-ssl-header" style="margin-bottom:20px;">';
    echo '<h2><i class="fas fa-shield-alt" style="color:#1890ff;"></i> HVN — AIO SSL Manager <small>v' . AIO_SSL_VERSION . '</small></h2>';
    echo '</div>';

    echo '<ul class="nav nav-tabs" role="tablist" style="margin-bottom:20px;">';
    foreach ($pages as $key => $tab) {
        $active = ($key === $activePage) ? ' class="active"' : '';
        $url = $moduleLink . '&page=' . $key;
        echo '<li' . $active . '>';
        echo '<a href="' . $url . '"><i class="fas ' . $tab['icon'] . '"></i> ' . $tab['label'] . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Load language file based on WHMCS admin language setting
 *
 * @return array
 */
function _aio_ssl_load_language()
{
    $lang = [];
    $adminLang = isset($_SESSION['Language']) ? strtolower($_SESSION['Language']) : 'english';
    $langFile = AIO_SSL_LANG_PATH . '/' . $adminLang . '.php';

    if (!file_exists($langFile)) {
        $langFile = AIO_SSL_LANG_PATH . '/english.php';
    }

    if (file_exists($langFile)) {
        include $langFile;
    }

    return $lang;
}