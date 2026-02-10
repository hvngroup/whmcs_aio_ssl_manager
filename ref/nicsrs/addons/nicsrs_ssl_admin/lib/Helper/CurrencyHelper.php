<?php
/**
 * Currency Helper
 * Handles currency conversion and formatting for reports
 * 
 * IMPORTANT NOTES:
 * - WHMCS default currency is VND
 * - tblhosting.firstpaymentamount contains VND amount (including 10% VAT)
 * - NicSRS costs are in USD (no VAT)
 * - VAT rate in Vietnam is 10%
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Helper;

use WHMCS\Database\Capsule;

class CurrencyHelper
{
    /** @var float Default USD to VND rate */
    const DEFAULT_USD_VND_RATE = 26200.00;

    /** @var float VAT rate in Vietnam (10%) */
    const VAT_RATE = 0.10;

    /** @var array Cached settings */
    private static array $cache = [];

    // =========================================================================
    // EXCHANGE RATE METHODS
    // =========================================================================

    /**
     * Get USD to VND exchange rate from settings
     * 
     * @return float Exchange rate
     */
    public static function getUsdVndRate(): float
    {
        if (isset(self::$cache['usd_vnd_rate'])) {
            return self::$cache['usd_vnd_rate'];
        }

        try {
            $rate = Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', 'usd_vnd_rate')
                ->value('setting_value');
            
            self::$cache['usd_vnd_rate'] = $rate ? (float) $rate : self::DEFAULT_USD_VND_RATE;
        } catch (\Exception $e) {
            self::$cache['usd_vnd_rate'] = self::DEFAULT_USD_VND_RATE;
        }

        return self::$cache['usd_vnd_rate'];
    }

    /**
     * Set USD to VND exchange rate
     * 
     * @param float $rate New rate
     * @return bool Success status
     */
    public static function setUsdVndRate(float $rate): bool
    {
        try {
            Capsule::table('mod_nicsrs_settings')
                ->updateOrInsert(
                    ['setting_key' => 'usd_vnd_rate'],
                    [
                        'setting_value' => (string) $rate,
                        'setting_type' => 'number',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );

            Capsule::table('mod_nicsrs_settings')
                ->updateOrInsert(
                    ['setting_key' => 'rate_last_updated'],
                    [
                        'setting_value' => date('Y-m-d H:i:s'),
                        'setting_type' => 'datetime',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );

            unset(self::$cache['usd_vnd_rate']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get last rate update timestamp
     * 
     * @return string|null Timestamp or null
     */
    public static function getRateLastUpdated(): ?string
    {
        try {
            return Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', 'rate_last_updated')
                ->value('setting_value');
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================================
    // CURRENCY CONVERSION METHODS
    // =========================================================================

    /**
     * Convert USD to VND
     * 
     * @param float $usd Amount in USD
     * @return float Amount in VND
     */
    public static function usdToVnd(float $usd): float
    {
        return $usd * self::getUsdVndRate();
    }

    /**
     * Convert VND to USD
     * 
     * @param float $vnd Amount in VND
     * @return float Amount in USD
     */
    public static function vndToUsd(float $vnd): float
    {
        $rate = self::getUsdVndRate();
        return $rate > 0 ? $vnd / $rate : 0;
    }

    // =========================================================================
    // VAT CALCULATION METHODS (NEW)
    // =========================================================================

    /**
     * Get VAT rate
     * 
     * @return float VAT rate (0.10 = 10%)
     */
    public static function getVatRate(): float
    {
        return self::VAT_RATE;
    }

    /**
     * Remove VAT from amount (extract base price from VAT-inclusive price)
     * Formula: Base = Amount / (1 + VAT_RATE)
     * 
     * @param float $amountWithVat Amount including VAT
     * @return float Amount excluding VAT
     */
    public static function removeVat(float $amountWithVat): float
    {
        return $amountWithVat / (1 + self::VAT_RATE);
    }

    /**
     * Add VAT to amount
     * Formula: Total = Amount * (1 + VAT_RATE)
     * 
     * @param float $amountWithoutVat Amount excluding VAT
     * @return float Amount including VAT
     */
    public static function addVat(float $amountWithoutVat): float
    {
        return $amountWithoutVat * (1 + self::VAT_RATE);
    }

    /**
     * Calculate VAT amount from VAT-inclusive price
     * 
     * @param float $amountWithVat Amount including VAT
     * @return float VAT amount
     */
    public static function calculateVatAmount(float $amountWithVat): float
    {
        return $amountWithVat - self::removeVat($amountWithVat);
    }

    // =========================================================================
    // REVENUE CONVERSION (VND with VAT -> USD without VAT)
    // =========================================================================

    /**
     * Convert WHMCS revenue (VND with VAT) to USD (without VAT)
     * This is the key method for profit calculation
     * 
     * Formula:
     * 1. Remove 10% VAT: VND_base = VND_with_vat / 1.1
     * 2. Convert to USD: USD = VND_base / exchange_rate
     * 
     * @param float $revenueVndWithVat Revenue in VND (including VAT from tblhosting)
     * @return float Revenue in USD (excluding VAT, comparable to NicSRS cost)
     */
    public static function revenueVndToUsd(float $revenueVndWithVat): float
    {
        // Step 1: Remove VAT
        $revenueVndWithoutVat = self::removeVat($revenueVndWithVat);
        
        // Step 2: Convert to USD
        return self::vndToUsd($revenueVndWithoutVat);
    }

    /**
     * Get detailed revenue breakdown
     * 
     * @param float $revenueVndWithVat Revenue in VND (including VAT)
     * @return array Detailed breakdown
     */
    public static function getRevenueBreakdown(float $revenueVndWithVat): array
    {
        $rate = self::getUsdVndRate();
        $vatAmount = self::calculateVatAmount($revenueVndWithVat);
        $revenueVndWithoutVat = self::removeVat($revenueVndWithVat);
        $revenueUsd = self::vndToUsd($revenueVndWithoutVat);

        return [
            'revenue_vnd_with_vat' => $revenueVndWithVat,
            'vat_amount_vnd' => $vatAmount,
            'revenue_vnd_without_vat' => $revenueVndWithoutVat,
            'revenue_usd' => $revenueUsd,
            'exchange_rate' => $rate,
            'vat_rate' => self::VAT_RATE * 100 . '%',
        ];
    }

    // =========================================================================
    // DISPLAY MODE SETTINGS
    // =========================================================================

    /**
     * Get currency display mode from settings
     * 
     * @return string 'usd', 'vnd', or 'both'
     */
    public static function getDisplayMode(): string
    {
        if (isset(self::$cache['currency_display'])) {
            return self::$cache['currency_display'];
        }

        try {
            $mode = Capsule::table('mod_nicsrs_settings')
                ->where('setting_key', 'currency_display')
                ->value('setting_value');
            
            self::$cache['currency_display'] = in_array($mode, ['usd', 'vnd', 'both']) ? $mode : 'both';
        } catch (\Exception $e) {
            self::$cache['currency_display'] = 'both';
        }

        return self::$cache['currency_display'];
    }

    /**
     * Set currency display mode
     * 
     * @param string $mode 'usd', 'vnd', or 'both'
     * @return bool Success status
     */
    public static function setDisplayMode(string $mode): bool
    {
        if (!in_array($mode, ['usd', 'vnd', 'both'])) {
            return false;
        }

        try {
            Capsule::table('mod_nicsrs_settings')
                ->updateOrInsert(
                    ['setting_key' => 'currency_display'],
                    [
                        'setting_value' => $mode,
                        'setting_type' => 'string',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );

            unset(self::$cache['currency_display']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // FORMATTING METHODS
    // =========================================================================

    /**
     * Format amount for display based on currency type
     * 
     * @param float $amountUsd Amount in USD
     * @param string|null $displayMode Override display mode ('usd', 'vnd', 'both')
     * @return string Formatted amount
     */
    public static function format(float $amountUsd, ?string $displayMode = null): string
    {
        $mode = $displayMode ?? self::getDisplayMode();

        switch ($mode) {
            case 'vnd':
                return self::formatVnd(self::usdToVnd($amountUsd));
            
            case 'both':
                return self::formatUsd($amountUsd) . ' (' . self::formatVnd(self::usdToVnd($amountUsd)) . ')';
            
            case 'usd':
            default:
                return self::formatUsd($amountUsd);
        }
    }

    /**
     * Format USD amount
     * 
     * @param float $amount Amount
     * @return string Formatted USD
     */
    public static function formatUsd(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }

    /**
     * Format VND amount
     * 
     * @param float $amount Amount
     * @return string Formatted VND
     */
    public static function formatVnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }

    /**
     * Format amount with compact notation for large numbers
     * 
     * @param float $amountUsd Amount in USD
     * @param string|null $displayMode Override display mode
     * @return string Formatted amount (e.g., $1.2K, 30M ₫)
     */
    public static function formatCompact(float $amountUsd, ?string $displayMode = null): string
    {
        $mode = $displayMode ?? self::getDisplayMode();

        switch ($mode) {
            case 'vnd':
                return self::formatVndCompact(self::usdToVnd($amountUsd));
            
            case 'both':
                return self::formatUsdCompact($amountUsd) . ' (' . self::formatVndCompact(self::usdToVnd($amountUsd)) . ')';
            
            case 'usd':
            default:
                return self::formatUsdCompact($amountUsd);
        }
    }

    /**
     * Format USD with compact notation
     * 
     * @param float $amount Amount
     * @return string Formatted (e.g., $1.2K, $3.5M)
     */
    public static function formatUsdCompact(float $amount): string
    {
        if ($amount >= 1000000) {
            return '$' . number_format($amount / 1000000, 1) . 'M';
        } elseif ($amount >= 1000) {
            return '$' . number_format($amount / 1000, 1) . 'K';
        }
        return '$' . number_format($amount, 2);
    }

    /**
     * Format VND with compact notation
     * 
     * @param float $amount Amount
     * @return string Formatted (e.g., 30M ₫, 1.5B ₫)
     */
    public static function formatVndCompact(float $amount): string
    {
        if ($amount >= 1000000000) {
            return number_format($amount / 1000000000, 1) . 'B ₫';
        } elseif ($amount >= 1000000) {
            return number_format($amount / 1000000, 1) . 'M ₫';
        } elseif ($amount >= 1000) {
            return number_format($amount / 1000, 0) . 'K ₫';
        }
        return number_format($amount, 0) . ' ₫';
    }

    // =========================================================================
    // INFO & UTILITIES
    // =========================================================================

    /**
     * Get exchange rate info for display
     * 
     * @return array Rate info with formatted values
     */
    public static function getRateInfo(): array
    {
        $rate = self::getUsdVndRate();
        $lastUpdated = self::getRateLastUpdated();

        return [
            'rate' => $rate,
            'rate_formatted' => '1 USD = ' . number_format($rate, 0, ',', '.') . ' VND',
            'last_updated' => $lastUpdated,
            'last_updated_formatted' => $lastUpdated 
                ? date('d/m/Y H:i', strtotime($lastUpdated)) 
                : 'Never',
            'vat_rate' => self::VAT_RATE,
            'vat_rate_formatted' => (self::VAT_RATE * 100) . '%',
        ];
    }

    /**
     * Clear cached values
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Fetch exchange rate from external API (optional)
     * Uses exchangerate-api.com free tier
     * 
     * @return float|null Rate or null on failure
     */
    public static function fetchRateFromApi(): ?float
    {
        try {
            $url = 'https://api.exchangerate-api.com/v4/latest/USD';
            $response = @file_get_contents($url);
            
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            
            if (isset($data['rates']['VND'])) {
                return (float) $data['rates']['VND'];
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Update rate from external API
     * 
     * @return array Result with success status and message
     */
    public static function updateRateFromApi(): array
    {
        $rate = self::fetchRateFromApi();

        if ($rate === null) {
            return [
                'success' => false,
                'message' => 'Failed to fetch exchange rate from API',
            ];
        }

        if (self::setUsdVndRate($rate)) {
            return [
                'success' => true,
                'message' => 'Exchange rate updated successfully',
                'rate' => $rate,
                'rate_formatted' => '1 USD = ' . number_format($rate, 0, ',', '.') . ' VND',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to save exchange rate',
        ];
    }
}