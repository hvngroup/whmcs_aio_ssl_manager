<?php
/**
 * PSR-4 Compatible Autoloader for AIO SSL Manager
 *
 * Namespace: AioSSL\ -> modules/addons/aio_ssl_admin/lib/
 *
 * @package    AioSSL
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

spl_autoload_register(function ($class) {
    // Namespace prefix
    $prefix = 'AioSSL\\';
    $prefixLen = strlen($prefix);

    // Base directory for the namespace
    $baseDir = __DIR__ . '/';

    // Check if the class uses the namespace prefix
    if (strncmp($prefix, $class, $prefixLen) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $prefixLen);

    // Replace namespace separators with directory separators, append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});