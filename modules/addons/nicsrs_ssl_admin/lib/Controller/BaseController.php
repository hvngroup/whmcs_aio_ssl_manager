<?php
/**
 * Base Controller
 * Abstract class providing common functionality for all controllers
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\ActivityLogger;
use NicsrsAdmin\Helper\ViewHelper;

abstract class BaseController
{
    /**
     * @var array Module configuration variables
     */
    protected $vars;
    
    /**
     * @var string Module link for URLs
     */
    protected $modulelink;
    
    /**
     * @var array Language strings
     */
    protected $lang;
    
    /**
     * @var int Current admin ID
     */
    protected $adminId;
    
    /**
     * @var ActivityLogger Activity logger instance
     */
    protected $logger;
    
    /**
     * @var ViewHelper View helper instance
     */
    protected $viewHelper;

    /**
     * Constructor
     * 
     * @param array $vars Module variables
     */
    public function __construct(array $vars)
    {
        $this->vars = $vars;
        $this->modulelink = $vars['modulelink'];
        $this->lang = $this->loadLanguage();
        $this->adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
        $this->logger = new ActivityLogger($this->adminId);
        $this->viewHelper = new ViewHelper();
    }

    /**
     * Load language file based on WHMCS admin language
     * 
     * @return array Language strings
     */
    protected function loadLanguage(): array
    {
        $language = isset($GLOBALS['CONFIG']['Language']) ? $GLOBALS['CONFIG']['Language'] : 'english';
        $langFile = NICSRS_ADMIN_PATH . "/lang/{$language}.php";
        
        if (!file_exists($langFile)) {
            $langFile = NICSRS_ADMIN_PATH . '/lang/english.php';
        }
        
        $lang = [];
        if (file_exists($langFile)) {
            include $langFile;
        }
        
        return $lang;
    }

    /**
     * Get API token from module config
     * 
     * @return string API token
     */
    protected function getApiToken(): string
    {
        return isset($this->vars['api_token']) ? $this->vars['api_token'] : '';
    }

    /**
     * Get items per page setting
     * 
     * @return int Items per page
     */
    protected function getItemsPerPage(): int
    {
        return isset($this->vars['items_per_page']) ? (int) $this->vars['items_per_page'] : 25;
    }

    /**
     * Get current page number from request
     * 
     * @return int Page number
     */
    protected function getCurrentPage(): int
    {
        return isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    }

    /**
     * Include a template file
     * 
     * @param string $template Template name (without .php extension)
     * @param array $data Data to pass to template
     * @return void
     */
    protected function includeTemplate(string $template, array $data = []): void
    {
        // Make variables available to template
        $data['modulelink'] = $this->modulelink;
        $data['lang'] = $this->lang;
        $data['version'] = NICSRS_ADMIN_VERSION;
        $data['helper'] = $this->viewHelper;
        
        extract($data);
        
        $templateFile = NICSRS_ADMIN_PATH . "/templates/{$template}.php";
        
        if (file_exists($templateFile)) {
            include $templateFile;
        } else {
            echo '<div class="alert alert-danger">Template not found: ' . htmlspecialchars($template) . '</div>';
        }
    }

    /**
     * Return JSON response
     * 
     * @param array $data Response data
     * @return string JSON string
     */
    protected function jsonResponse(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Return success JSON response
     * 
     * @param string $message Success message
     * @param array $data Additional data
     * @return string JSON string
     */
    protected function jsonSuccess(string $message = 'Success', array $data = []): string
    {
        return $this->jsonResponse(array_merge([
            'success' => true,
            'message' => $message,
        ], $data));
    }

    /**
     * Return error JSON response
     * 
     * @param string $message Error message
     * @param array $data Additional data
     * @return string JSON string
     */
    protected function jsonError(string $message = 'Error', array $data = []): string
    {
        return $this->jsonResponse(array_merge([
            'success' => false,
            'message' => $message,
        ], $data));
    }

    /**
     * Get module setting from database
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    protected function getSetting(string $key, $default = null)
    {
        $setting = Capsule::table('mod_nicsrs_settings')
            ->where('setting_key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        switch ($setting->setting_type) {
            case 'boolean':
                return (bool) $setting->setting_value;
            case 'integer':
                return (int) $setting->setting_value;
            case 'json':
                return json_decode($setting->setting_value, true);
            default:
                return $setting->setting_value;
        }
    }

    /**
     * Save module setting to database
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $type Setting type (string, boolean, integer, json)
     * @return bool Success status
     */
    protected function saveSetting(string $key, $value, string $type = 'string'): bool
    {
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        }

        $exists = Capsule::table('mod_nicsrs_settings')
            ->where('setting_key', $key)
            ->exists();

        if ($exists) {
            Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => $value,
                    'setting_type' => $type,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            Capsule::table('mod_nicsrs_settings')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => $type,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    protected function generateCsrfToken(): string
    {
        if (!isset($_SESSION['nicsrs_csrf_token'])) {
            $_SESSION['nicsrs_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['nicsrs_csrf_token'];
    }

    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @return bool Valid or not
     */
    protected function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['nicsrs_csrf_token']) 
            && hash_equals($_SESSION['nicsrs_csrf_token'], $token);
    }

    /**
     * Sanitize input string
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    protected function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build pagination URL
     * 
     * @param int $page Page number
     * @param array $extraParams Extra URL parameters
     * @return string URL
     */
    protected function buildPaginationUrl(int $page, array $extraParams = []): string
    {
        $params = array_merge($_GET, $extraParams, ['page' => $page]);
        unset($params['module']); // Remove module param as it's in modulelink
        
        $queryString = http_build_query($params);
        return $this->modulelink . ($queryString ? '&' . $queryString : '');
    }

    /**
     * Handle AJAX request - to be overridden by child classes
     * 
     * @param array $post POST data
     * @return string JSON response
     */
    public function handleAjax(array $post): string
    {
        return $this->jsonError('Not implemented');
    }

    /**
     * Render page - to be implemented by child classes
     * 
     * @param string $action Current action
     * @return void
     */
    abstract public function render(string $action): void;
}