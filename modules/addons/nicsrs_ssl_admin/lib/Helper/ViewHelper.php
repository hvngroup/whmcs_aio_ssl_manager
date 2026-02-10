<?php
/**
 * View Helper
 * Utility functions for template rendering
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Helper;
use WHMCS\Database\Capsule;

class ViewHelper
{
    /**
     * @var array Product name cache (static for performance across instances)
     */
    private static $productCache = [];

    /**
     * Format date for display
     * 
     * @param string|null $date Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public function formatDate(?string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '-';
    }

    /**
     * Format date with time
     * 
     * @param string|null $date Date string
     * @return string Formatted datetime
     */
    public function formatDateTime(?string $date): string
    {
        return $this->formatDate($date, 'Y-m-d H:i');
    }

    /**
     * Get time ago string
     * 
     * @param string $date Date string
     * @return string Time ago string
     */
    public function timeAgo(string $date): string
    {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '-';
        }
    
        $diff = time() - $timestamp;
        
        if ($diff < 0) {
            // Future date
            $diff = abs($diff);
            if ($diff < 60) {
                return 'in a moment';
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                return 'in ' . $mins . ' min' . ($mins > 1 ? 's' : '');
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return 'in ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                $days = floor($diff / 86400);
                return 'in ' . $days . ' day' . ($days > 1 ? 's' : '');
            }
        }
        
        // Past date
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } else {
            return $this->formatDate($date);
        }
    }

    /**
     * Get status badge HTML
     * 
     * @param string $status Status code
     * @return string HTML badge
     */
    public function statusBadge(string $status): string
    {
        $status = strtolower($status);
        
        $badges = [
            'complete' => ['class' => 'success', 'label' => 'Complete'],
            'issued' => ['class' => 'success', 'label' => 'Issued'],
            'active' => ['class' => 'success', 'label' => 'Active'],
            'pending' => ['class' => 'warning', 'label' => 'Pending'],
            'processing' => ['class' => 'warning', 'label' => 'Processing'],
            'awaiting' => ['class' => 'default', 'label' => 'Awaiting'],
            'draft' => ['class' => 'info', 'label' => 'Draft'],
            'cancelled' => ['class' => 'danger', 'label' => 'Cancelled'],
            'revoked' => ['class' => 'danger', 'label' => 'Revoked'],
            'expired' => ['class' => 'danger', 'label' => 'Expired'],
            'awaiting configuration' => ['class' => 'default', 'label' => 'Awaiting'],
        ];

        $badge = isset($badges[$status]) ? $badges[$status] : ['class' => 'default', 'label' => ucfirst($status)];
        
        return sprintf(
            '<span class="label label-%s">%s</span>',
            htmlspecialchars($badge['class']),
            htmlspecialchars($badge['label'])
        );
    }

    /**
     * Get validation type badge HTML
     * 
     * @param string $type Validation type (dv, ov, ev)
     * @return string HTML badge
     */
    public function validationBadge(string $type): string
    {
        $type = strtoupper($type);
        
        $badges = [
            'DV' => ['class' => 'primary', 'label' => 'DV'],
            'OV' => ['class' => 'info', 'label' => 'OV'],
            'EV' => ['class' => 'success', 'label' => 'EV'],
        ];

        $badge = isset($badges[$type]) ? $badges[$type] : ['class' => 'default', 'label' => $type];
        
        return sprintf(
            '<span class="label label-%s">%s</span>',
            htmlspecialchars($badge['class']),
            htmlspecialchars($badge['label'])
        );
    }

    /**
     * Format price for display
     * 
     * @param float|null $price Price value
     * @param string $currency Currency symbol
     * @return string Formatted price
     */
    public function formatPrice(?float $price, string $currency = '$'): string
    {
        if ($price === null) {
            return '-';
        }
        
        return $currency . number_format($price, 2);
    }

    /**
     * Format currency for display (using WHMCS default currency)
     * 
     * ADD this method to lib/Helper/ViewHelper.php
     * 
     * @param float|string|null $amount Amount value
     * @param bool $includeSymbol Whether to include currency symbol
     * @return string Formatted currency
     */
    public function formatCurrency($amount, bool $includeSymbol = true): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }
        
        $amount = (float) $amount;
        
        // Get WHMCS default currency
        try {
            $currency = Capsule::table('tblcurrencies')
                ->where('default', 1)
                ->first();
            
            if ($currency) {
                $prefix = $includeSymbol ? $currency->prefix : '';
                $suffix = $includeSymbol ? $currency->suffix : '';
                $formatted = number_format($amount, 2, '.', ',');
                return $prefix . $formatted . $suffix;
            }
        } catch (\Exception $e) {
            // Fallback if database query fails
        }
        
        // Fallback to USD format
        return ($includeSymbol ? '$' : '') . number_format($amount, 2);
    }

    /**
     * Format billing cycle for display
     * 
     * @param string|null $cycle Billing cycle value
     * @return string Formatted cycle
     */
    public function formatBillingCycle(?string $cycle): string
    {
        if (empty($cycle)) {
            return '-';
        }
        
        $cycles = [
            'Free Account' => 'Free',
            'One Time' => 'One Time',
            'Monthly' => 'Monthly',
            'Quarterly' => 'Quarterly',
            'Semi-Annually' => 'Semi-Annual',
            'Annually' => 'Annually',
            'Biennially' => 'Biennially',
            'Triennially' => 'Triennially',
        ];
        
        return isset($cycles[$cycle]) ? $cycles[$cycle] : $cycle;
    }

    /**
     * Render Yes/No icon
     * 
     * @param bool $value Boolean value
     * @return string HTML icon
     */
    public function yesNoIcon(bool $value): string
    {
        if ($value) {
            return '<i class="fa fa-check text-success"></i>';
        }
        return '<i class="fa fa-times text-muted"></i>';
    }

    /**
     * Truncate string with ellipsis
     * 
     * @param string $string Input string
     * @param int $length Max length
     * @return string Truncated string
     */
    public function truncate(string $string, int $length = 50): string
    {
        if (strlen($string) <= $length) {
            return htmlspecialchars($string);
        }
        
        return htmlspecialchars(substr($string, 0, $length)) . '...';
    }

    /**
     * Calculate days until date
     * 
     * @param string $date Target date
     * @return int|null Days (negative if past)
     */
    public function daysUntil(string $date): ?int
    {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return null;
        }
        
        return (int) ceil(($timestamp - time()) / 86400);
    }

    /**
     * Get WHMCS product link status badge HTML
     * 
     * @param object|null $whmcsProduct WHMCS product object
     * @param string $productCode NicSRS product code
     * @return string HTML badge
     */
    public function linkedProductBadge($whmcsProduct, string $productCode = ''): string
    {
        if (empty($whmcsProduct)) {
            return '<span class="label label-default" title="Not linked to any WHMCS product">
                        <i class="fa fa-unlink"></i> Not Linked
                    </span>';
        }

        $productUrl = 'configproducts.php?action=edit&id=' . $whmcsProduct->id;
        
        // Determine status
        if ($whmcsProduct->retired) {
            $statusClass = 'label-warning';
            $statusIcon = 'fa-archive';
            $statusText = 'Retired';
        } elseif ($whmcsProduct->hidden) {
            $statusClass = 'label-info';
            $statusIcon = 'fa-eye-slash';
            $statusText = 'Hidden';
        } else {
            $statusClass = 'label-success';
            $statusIcon = 'fa-check-circle';
            $statusText = 'Active';
        }

        return sprintf(
            '<a href="%s" target="_blank" class="label %s" title="%s - %s">
                <i class="fa %s"></i> Linked
            </a>
            <br>
            <small class="text-muted">#%d - %s</small>',
            $productUrl,
            $statusClass,
            $this->e($whmcsProduct->name),
            $statusText,
            $statusIcon,
            $whmcsProduct->id,
            $this->truncate($whmcsProduct->name, 15)
        );
    }   
     
    /**
     * Get days left badge
     * 
     * @param string $endDate End date
     * @return string HTML badge
     */
    public function daysLeftBadge(string $endDate): string
    {
        $days = $this->daysUntil($endDate);
        
        if ($days === null) {
            return '-';
        }
        
        if ($days < 0) {
            return '<span class="label label-danger">Expired</span>';
        }
        
        if ($days <= 7) {
            $class = 'danger';
        } elseif ($days <= 30) {
            $class = 'warning';
        } else {
            $class = 'success';
        }
        
        return sprintf('<span class="label label-%s">%d days</span>', $class, $days);
    }

    /**
     * Get product name from product code
     * Uses cached lookup from mod_nicsrs_products table
     * Falls back to product code if not found
     * 
     * @param string|null $productCode Product code (certtype)
     * @return string Product name or original code if not found
     */
    public function getProductName(?string $productCode): string
    {
        if (empty($productCode)) {
            return '-';
        }
        
        // Check cache first
        if (isset(self::$productCache[$productCode])) {
            return self::$productCache[$productCode];
        }
        
        try {
            $product = Capsule::table('mod_nicsrs_products')
                ->where('product_code', $productCode)
                ->first();
            
            if ($product && !empty($product->product_name)) {
                self::$productCache[$productCode] = $product->product_name;
            } else {
                // Fallback: Format code as readable name
                self::$productCache[$productCode] = $this->formatProductCode($productCode);
            }
        } catch (\Exception $e) {
            self::$productCache[$productCode] = $this->formatProductCode($productCode);
        }
        
        return self::$productCache[$productCode];
    }

    /**
     * Format product code as readable name (fallback)
     * Converts "sectigo-positive-ssl" to "Sectigo Positive SSL"
     * 
     * @param string $code Product code
     * @return string Formatted name
     */
    public function formatProductCode(string $code): string
    {
        // Replace dashes/underscores with spaces
        $name = str_replace(['-', '_'], ' ', $code);
        
        // Uppercase known abbreviations
        $abbreviations = ['ssl', 'ev', 'ov', 'dv', 'san', 'ucc'];
        $words = explode(' ', $name);
        
        foreach ($words as $i => $word) {
            if (in_array(strtolower($word), $abbreviations)) {
                $words[$i] = strtoupper($word);
            } else {
                $words[$i] = ucfirst($word);
            }
        }
        
        return implode(' ', $words);
    }

    /**
     * Get primary domain from order
     * Extracts from configdata domainInfo or CSR
     * 
     * @param object $order Order object with configdata
     * @return string Primary domain or '-'
     */
    public function getPrimaryDomain($order): string
    {
        if (empty($order->configdata)) {
            return '-';
        }
        
        $config = json_decode($order->configdata, true);
        
        // Try domainInfo first
        if (!empty($config['domainInfo'][0]['domainName'])) {
            return $config['domainInfo'][0]['domainName'];
        }
        
        // Try CSR common name
        if (!empty($config['csr'])) {
            $parsed = openssl_csr_get_subject($config['csr']);
            if (!empty($parsed['CN'])) {
                return $parsed['CN'];
            }
        }
        
        return '-';
    }
        
    /**
     * Get certificate status badge with additional status info
     * 
     * @param string $status Main status
     * @param string $certStatus Additional cert status from applyReturn
     * @return string HTML badges
     */
    public function certStatusBadges(string $status, string $certStatus = ''): string
    {
        $html = $this->statusBadge($status);
        
        if (!empty($certStatus) && $certStatus !== 'done') {
            $certStatus = strtolower(str_replace('_', ' ', $certStatus));
            $class = 'default';
            
            if (strpos($certStatus, 'pending') !== false) {
                $class = 'warning';
            } elseif (strpos($certStatus, 'done') !== false) {
                $class = 'success';
            } elseif (strpos($certStatus, 'failed') !== false) {
                $class = 'danger';
            }
            
            $html .= sprintf(
                ' <span class="label label-%s">%s</span>',
                htmlspecialchars($class),
                htmlspecialchars(ucfirst($certStatus))
            );
        }
        
        return $html;
    }
    
    /**
     * Get format availability badges (JKS, PKCS12)
     * 
     * @param bool $hasJks Has JKS format
     * @param bool $hasPkcs12 Has PKCS12 format
     * @return string HTML badges
     */
    public function formatBadges(bool $hasJks, bool $hasPkcs12): string
    {
        $badges = [];
        
        if ($hasJks) {
            $badges[] = '<span class="label label-success" title="Java KeyStore available"><i class="fa fa-coffee"></i> JKS</span>';
        }
        
        if ($hasPkcs12) {
            $badges[] = '<span class="label label-success" title="PKCS12/PFX available"><i class="fa fa-windows"></i> PKCS12</span>';
        }
        
        if (empty($badges)) {
            return '<span class="text-muted">Standard formats only</span>';
        }
        
        return implode(' ', $badges);
    }
    
    /**
     * Calculate renewal due date (30 days before expiry)
     * 
     * @param string|null $endDate End date
     * @return string|null Renewal due date or null
     */
    public function getRenewalDue(?string $endDate): ?string
    {
        if (empty($endDate)) {
            return null;
        }
        
        $timestamp = strtotime($endDate);
        if (!$timestamp) {
            return null;
        }
        
        // 30 days before expiry
        $renewalTimestamp = $timestamp - (30 * 86400);
        
        return date('Y-m-d', $renewalTimestamp);
    }
    
    /**
     * Get renewal status badge
     * 
     * @param string|null $endDate End date
     * @return string HTML badge
     */
    public function renewalStatusBadge(?string $endDate): string
    {
        if (empty($endDate)) {
            return '';
        }
        
        $renewalDue = $this->getRenewalDue($endDate);
        if (!$renewalDue) {
            return '';
        }
        
        $renewalTimestamp = strtotime($renewalDue);
        $now = time();
        
        if ($renewalTimestamp <= $now) {
            return '<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Renewal Due</span>';
        }
        
        $daysUntilRenewal = ceil(($renewalTimestamp - $now) / 86400);
        
        if ($daysUntilRenewal <= 7) {
            return '<span class="label label-warning" title="Renewal due in ' . $daysUntilRenewal . ' days"><i class="fa fa-clock-o"></i> Renewal Soon</span>';
        }
        
        return '';
    }
    
    /**
     * Format vendor ID for display
     * 
     * @param string|null $vendorId Vendor ID
     * @param string|null $vendorCertId Vendor Cert ID
     * @return string Formatted vendor info
     */
    public function formatVendorInfo(?string $vendorId, ?string $vendorCertId): string
    {
        $parts = [];
        
        if (!empty($vendorId)) {
            $parts[] = 'Order: <code>' . htmlspecialchars($vendorId) . '</code>';
        }
        
        if (!empty($vendorCertId)) {
            $parts[] = 'Cert: <code>' . htmlspecialchars($vendorCertId) . '</code>';
        }
        
        if (empty($parts)) {
            return '<span class="text-muted">N/A</span>';
        }
        
        return implode(' | ', $parts);
    }    

    /**
     * Escape HTML output
     * 
     * @param string|null $string Input string
     * @return string Escaped string
     */
    public function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}