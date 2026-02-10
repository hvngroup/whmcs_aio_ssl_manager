<?php
/**
 * DCV (Domain Control Validation) Helper
 * Utility functions for DCV method display and handling
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Helper;

class DcvHelper
{
    /**
     * DCV Method mappings
     * Maps API values to display names
     */
    private static $dcvMethods = [
        'CNAME_CSR_HASH' => [
            'name' => 'DNS CNAME',
            'type' => 'dns',
            'icon' => 'fa-globe',
            'description' => 'Add a CNAME record to your DNS'
        ],
        'HTTP_CSR_HASH' => [
            'name' => 'HTTP File',
            'type' => 'http',
            'icon' => 'fa-file-text-o',
            'description' => 'Upload a file to your web server'
        ],
        'HTTPS_CSR_HASH' => [
            'name' => 'HTTPS File',
            'type' => 'https',
            'icon' => 'fa-lock',
            'description' => 'Upload a file via HTTPS'
        ],
        'DNS_CSR_HASH' => [
            'name' => 'DNS TXT',
            'type' => 'dns',
            'icon' => 'fa-list-alt',
            'description' => 'Add a TXT record to your DNS'
        ],
        'EMAIL' => [
            'name' => 'Email',
            'type' => 'email',
            'icon' => 'fa-envelope',
            'description' => 'Verify via email'
        ]
    ];

    /**
     * Get display name for DCV method
     * Converts API values like CNAME_CSR_HASH to "DNS CNAME"
     * 
     * @param string $method DCV method from API
     * @return string Human-readable name
     */
    public static function getDisplayName(string $method): string
    {
        $method = strtoupper(trim($method));
        
        // Check if it's an email address
        if (filter_var($method, FILTER_VALIDATE_EMAIL) || strpos($method, '@') !== false) {
            return 'Email';
        }
        
        if (isset(self::$dcvMethods[$method])) {
            return self::$dcvMethods[$method]['name'];
        }
        
        // Fallback: clean up the method name
        $cleaned = str_replace(['_CSR_HASH', '_'], ['', ' '], $method);
        return ucwords(strtolower($cleaned));
    }

    /**
     * Get FontAwesome icon class for DCV method
     * 
     * @param string $method DCV method from API
     * @return string FontAwesome icon class
     */
    public static function getIcon(string $method): string
    {
        $method = strtoupper(trim($method));
        
        // Check if it's an email address
        if (filter_var($method, FILTER_VALIDATE_EMAIL) || strpos($method, '@') !== false) {
            return 'fa-envelope';
        }
        
        if (isset(self::$dcvMethods[$method])) {
            return self::$dcvMethods[$method]['icon'];
        }
        
        return 'fa-question-circle';
    }

    /**
     * Get DCV method type (dns, http, https, email)
     * 
     * @param string $method DCV method from API
     * @return string Method type
     */
    public static function getType(string $method): string
    {
        $method = strtoupper(trim($method));
        
        if (filter_var($method, FILTER_VALIDATE_EMAIL) || strpos($method, '@') !== false) {
            return 'email';
        }
        
        if (isset(self::$dcvMethods[$method])) {
            return self::$dcvMethods[$method]['type'];
        }
        
        return 'unknown';
    }

    /**
     * Get description for DCV method
     * 
     * @param string $method DCV method from API
     * @return string Description
     */
    public static function getDescription(string $method): string
    {
        $method = strtoupper(trim($method));
        
        if (filter_var($method, FILTER_VALIDATE_EMAIL) || strpos($method, '@') !== false) {
            return 'Verification email will be sent to this address';
        }
        
        if (isset(self::$dcvMethods[$method])) {
            return self::$dcvMethods[$method]['description'];
        }
        
        return 'Complete domain validation';
    }

    /**
     * Get HTML badge for DCV method with icon
     * 
     * @param string $method DCV method from API
     * @return string HTML badge
     */
    public static function getBadge(string $method): string
    {
        $displayName = self::getDisplayName($method);
        $icon = self::getIcon($method);
        $type = self::getType($method);
        
        // Determine badge class based on type
        $badgeClass = 'default';
        switch ($type) {
            case 'dns':
                $badgeClass = 'info';
                break;
            case 'http':
            case 'https':
                $badgeClass = 'primary';
                break;
            case 'email':
                $badgeClass = 'warning';
                break;
        }
        
        return sprintf(
            '<span class="label label-%s" title="%s"><i class="fa %s"></i> %s</span>',
            htmlspecialchars($badgeClass),
            htmlspecialchars(self::getDescription($method)),
            htmlspecialchars($icon),
            htmlspecialchars($displayName)
        );
    }

    /**
     * Get verification status badge
     * 
     * @param bool $isVerified Whether domain is verified
     * @param string $isVerifyStr Optional raw is_verify string from API
     * @return string HTML badge
     */
    public static function getVerificationBadge(bool $isVerified, string $isVerifyStr = ''): string
    {
        // Check both bool and string values
        $verified = $isVerified || strtolower($isVerifyStr) === 'verified';
        
        if ($verified) {
            return '<span class="label label-success"><i class="fa fa-check"></i> Verified</span>';
        }
        
        return '<span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>';
    }

    /**
     * Get DCV instructions based on method and available data
     * 
     * @param string $method DCV method
     * @param array $dcvData DCV data from API (DCVdnsHost, DCVdnsValue, etc.)
     * @return array Instructions array with 'title', 'steps', 'values'
     */
    public static function getInstructions(string $method, array $dcvData): array
    {
        $type = self::getType($method);
        $instructions = [
            'title' => '',
            'steps' => [],
            'values' => []
        ];
        
        switch ($type) {
            case 'dns':
                if (strpos(strtoupper($method), 'CNAME') !== false) {
                    $instructions['title'] = 'DNS CNAME Validation';
                    $instructions['steps'] = [
                        'Log in to your DNS provider/registrar',
                        'Add a CNAME record with the values below',
                        'Wait for DNS propagation (may take up to 24 hours)',
                        'Click "Resend DCV" to trigger validation check'
                    ];
                    $instructions['values'] = [
                        ['label' => 'Record Type', 'value' => 'CNAME'],
                        ['label' => 'Host/Name', 'value' => $dcvData['DCVdnsHost'] ?? '_dnsauth'],
                        ['label' => 'Value/Points to', 'value' => $dcvData['DCVdnsValue'] ?? ''],
                    ];
                } else {
                    $instructions['title'] = 'DNS TXT Validation';
                    $instructions['steps'] = [
                        'Log in to your DNS provider/registrar',
                        'Add a TXT record with the values below',
                        'Wait for DNS propagation',
                        'Click "Resend DCV" to trigger validation check'
                    ];
                    $instructions['values'] = [
                        ['label' => 'Record Type', 'value' => $dcvData['DCVdnsType'] ?? 'TXT'],
                        ['label' => 'Host/Name', 'value' => $dcvData['DCVdnsHost'] ?? '_dnsauth'],
                        ['label' => 'Value', 'value' => $dcvData['DCVdnsValue'] ?? ''],
                    ];
                }
                break;
                
            case 'http':
            case 'https':
                $protocol = ($type === 'https') ? 'https' : 'http';
                $instructions['title'] = strtoupper($protocol) . ' File Validation';
                $instructions['steps'] = [
                    'Create a file with the name shown below',
                    'Add the content shown below to the file',
                    'Upload the file to your web server at the specified path',
                    'Ensure the file is accessible via ' . strtoupper($protocol),
                    'Click "Resend DCV" to trigger validation check'
                ];
                $instructions['values'] = [
                    ['label' => 'File Name', 'value' => $dcvData['DCVfileName'] ?? 'fileauth.txt'],
                    ['label' => 'File Content', 'value' => $dcvData['DCVfileContent'] ?? ''],
                    ['label' => 'File Path', 'value' => $dcvData['DCVfilePath'] ?? "/.well-known/pki-validation/{$dcvData['DCVfileName']}"],
                ];
                break;
                
            case 'email':
                $instructions['title'] = 'Email Validation';
                $instructions['steps'] = [
                    'Check your email inbox (and spam folder)',
                    'Open the validation email from the Certificate Authority',
                    'Click the verification link in the email',
                    'Complete any additional steps in the email'
                ];
                if (!empty($dcvData['dcvEmail'])) {
                    $instructions['values'] = [
                        ['label' => 'Email sent to', 'value' => $dcvData['dcvEmail']],
                    ];
                }
                break;
        }
        
        return $instructions;
    }

    /**
     * Check if DCV method is DNS-based
     * 
     * @param string $method DCV method
     * @return bool
     */
    public static function isDnsMethod(string $method): bool
    {
        return self::getType($method) === 'dns';
    }

    /**
     * Check if DCV method is HTTP-based
     * 
     * @param string $method DCV method
     * @return bool
     */
    public static function isHttpMethod(string $method): bool
    {
        $type = self::getType($method);
        return $type === 'http' || $type === 'https';
    }

    /**
     * Check if DCV method is Email-based
     * 
     * @param string $method DCV method
     * @return bool
     */
    public static function isEmailMethod(string $method): bool
    {
        return self::getType($method) === 'email';
    }
}