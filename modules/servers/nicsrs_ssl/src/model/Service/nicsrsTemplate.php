<?php
/**
 * NicSRS SSL Template Helper
 * 
 * Utilities for template rendering
 * 
 * @package    WHMCS
 * @author     HVN GROUP
 * @version    2.0.0
 */

namespace nicsrsSSL;

class nicsrsTemplate
{
    /**
     * Template base path
     */
    protected static $basePath = '';
    
    /**
     * Initialize template path
     */
    protected static function initPath()
    {
        if (empty(self::$basePath)) {
            self::$basePath = dirname(dirname(dirname(__DIR__))) . '/view/';
        }
    }
    
    /**
     * Get template path
     * 
     * @param string $template Template name (without .tpl extension)
     * @return string Full path to template
     */
    public static function getTemplatePath($template)
    {
        self::initPath();
        
        $template = str_replace('.tpl', '', $template);
        $path = self::$basePath . $template . '.tpl';
        
        if (!file_exists($path)) {
            // Try with view/ prefix
            $path = 'view/' . $template . '.tpl';
        }
        
        return $path;
    }
    
    /**
     * Check if template exists
     * 
     * @param string $template Template name
     * @return bool
     */
    public static function templateExists($template)
    {
        self::initPath();
        
        $template = str_replace('.tpl', '', $template);
        return file_exists(self::$basePath . $template . '.tpl');
    }
    
    /**
     * Get template array for WHMCS ClientArea
     * 
     * @param string $template Template name
     * @param array $vars Template variables
     * @return array
     */
    public static function render($template, array $vars = [])
    {
        return [
            'tabOverviewReplacementTemplate' => self::getTemplatePath($template),
            'templateVariables' => $vars,
        ];
    }
    
    /**
     * Get template array with custom tab
     * 
     * @param string $template Template name
     * @param array $vars Template variables
     * @param string $tabName Custom tab name
     * @return array
     */
    public static function renderTab($template, array $vars = [], $tabName = '')
    {
        $result = [
            'tabOverviewReplacementTemplate' => self::getTemplatePath($template),
            'templateVariables' => $vars,
        ];
        
        if ($tabName) {
            $result['tabOverviewModuleOutputTemplate'] = $tabName;
        }
        
        return $result;
    }
    
    /**
     * Render error template
     * 
     * @param string $errorMessage Error message
     * @param array $extraVars Additional variables
     * @return array
     */
    public static function renderError($errorMessage, array $extraVars = [])
    {
        $vars = array_merge([
            'usefulErrorHelper' => $errorMessage,
        ], $extraVars);
        
        return self::render('error', $vars);
    }
    
    /**
     * Render message template
     * 
     * @param string $status Status type (success, error, warning, info)
     * @param string $message Message content
     * @param array $extraVars Additional variables
     * @return array
     */
    public static function renderMessage($status, $message, array $extraVars = [])
    {
        $vars = array_merge([
            'status' => $status,
            'statusMessage' => $message,
        ], $extraVars);
        
        return self::render('message', $vars);
    }
    
    /**
     * Escape HTML special characters
     * 
     * @param string $string Input string
     * @return string Escaped string
     */
    public static function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format date for display
     * 
     * @param string $date Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public static function formatDate($date, $format = 'Y-m-d')
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        try {
            $dt = new \DateTime($date);
            return $dt->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }
    
    /**
     * Format file size for display
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public static function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get status badge HTML
     * 
     * @param string $status Status value
     * @param string $customClass Custom CSS class
     * @return string HTML badge
     */
    public static function statusBadge($status, $customClass = '')
    {
        $statusMap = [
            'complete' => ['class' => 'success', 'icon' => 'check-circle', 'label' => 'Issued'],
            'issued' => ['class' => 'success', 'icon' => 'check-circle', 'label' => 'Issued'],
            'active' => ['class' => 'success', 'icon' => 'check-circle', 'label' => 'Active'],
            'pending' => ['class' => 'warning', 'icon' => 'clock-o', 'label' => 'Pending'],
            'processing' => ['class' => 'info', 'icon' => 'spinner fa-spin', 'label' => 'Processing'],
            'awaiting' => ['class' => 'default', 'icon' => 'hourglass-start', 'label' => 'Awaiting'],
            'cancelled' => ['class' => 'danger', 'icon' => 'ban', 'label' => 'Cancelled'],
            'revoked' => ['class' => 'danger', 'icon' => 'times-circle', 'label' => 'Revoked'],
            'expired' => ['class' => 'danger', 'icon' => 'calendar-times-o', 'label' => 'Expired'],
            'rejected' => ['class' => 'danger', 'icon' => 'exclamation-circle', 'label' => 'Rejected'],
            'expiring' => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'label' => 'Expiring'],
        ];
        
        $status = strtolower($status);
        $config = $statusMap[$status] ?? ['class' => 'default', 'icon' => 'question-circle', 'label' => ucfirst($status)];
        
        $class = $customClass ?: $config['class'];
        
        return sprintf(
            '<span class="label label-%s"><i class="fa fa-%s"></i> %s</span>',
            self::escape($class),
            self::escape($config['icon']),
            self::escape($config['label'])
        );
    }
    
    /**
     * Get DCV method badge HTML
     * 
     * @param string $method DCV method code
     * @param bool $verified Verification status
     * @return string HTML badge
     */
    public static function dcvMethodBadge($method, $verified = false)
    {
        $methodNames = [
            'EMAIL' => 'Email',
            'HTTP_CSR_HASH' => 'HTTP',
            'HTTPS_CSR_HASH' => 'HTTPS',
            'CNAME_CSR_HASH' => 'DNS CNAME',
            'DNS_CSR_HASH' => 'DNS TXT',
        ];
        
        $name = $methodNames[$method] ?? $method;
        $class = $verified ? 'success' : 'warning';
        $icon = $verified ? 'check' : 'clock-o';
        
        return sprintf(
            '<span class="label label-%s"><i class="fa fa-%s"></i> %s</span>',
            $class,
            $icon,
            self::escape($name)
        );
    }
    
    /**
     * Truncate string with ellipsis
     * 
     * @param string $string Input string
     * @param int $length Maximum length
     * @param string $suffix Suffix to append
     * @return string Truncated string
     */
    public static function truncate($string, $length = 100, $suffix = '...')
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        
        return substr($string, 0, $length - strlen($suffix)) . $suffix;
    }
    
    /**
     * Convert array to HTML attributes string
     * 
     * @param array $attributes Attributes array
     * @return string HTML attributes string
     */
    public static function htmlAttributes(array $attributes)
    {
        $parts = [];
        
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $parts[] = self::escape($key);
            } elseif ($value !== false && $value !== null) {
                $parts[] = self::escape($key) . '="' . self::escape($value) . '"';
            }
        }
        
        return implode(' ', $parts);
    }
}