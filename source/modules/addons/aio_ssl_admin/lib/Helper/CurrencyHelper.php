<?php
/**
 * Currency Helper — USD/VND conversion, formatting, VAT, and auto-update via API
 *
 * CURRENCY NOTES FOR REPORTS:
 * - WHMCS revenue from tblhosting is VND (including 10% VAT)
 * - Provider costs are in USD (no VAT)
 * - Profit = revenueVndToUsd(revenue_vnd) - cost_usd
 *
 * Settings used (from mod_aio_ssl_settings):
 * - currency_usd_vnd_rate: exchange rate (default 25000)
 * - currency_display: 'usd' | 'vnd' | 'both'
 * - exchangerate_api_key: for auto-update
 *
 * @package    AioSSL\Helper
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Helper;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class CurrencyHelper
{
    /** @var float VAT rate in Vietnam */
    public const VAT_RATE = 0.10;

    /** @var float Default exchange rate */
    public const DEFAULT_RATE = 25000;

    /** @var float USD → VND exchange rate */
    private $usdVndRate;

    /** @var string Display mode: 'usd' | 'vnd' | 'both' */
    private $displayMode;

    /** @var string API base URL */
    private const API_BASE = 'https://v6.exchangerate-api.com/v6';

    /** @var int cURL timeout */
    private const TIMEOUT = 15;

    public function __construct()
    {
        $this->usdVndRate = (float)$this->getSetting('currency_usd_vnd_rate', self::DEFAULT_RATE);
        $this->displayMode = $this->getSetting('currency_display', 'usd');
    }

    // ═══════════════════════════════════════════════════════════════
    // CORE CONVERSION
    // ═══════════════════════════════════════════════════════════════

    public function toVnd(float $usd): float  { return $usd * $this->usdVndRate; }
    public function toUsd(float $vnd): float  { return $this->usdVndRate > 0 ? $vnd / $this->usdVndRate : 0; }
    public function getRate(): float          { return $this->usdVndRate; }
    public function getDisplayMode(): string  { return $this->displayMode; }

    // ═══════════════════════════════════════════════════════════════
    // VAT CALCULATION (NEW — for Reports)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Remove 10% VAT from VND amount
     * Formula: base = amount / (1 + VAT_RATE)
     */
    public function removeVat(float $amountWithVat): float
    {
        return $amountWithVat / (1 + self::VAT_RATE);
    }

    /**
     * Add VAT to amount
     */
    public function addVat(float $amountWithoutVat): float
    {
        return $amountWithoutVat * (1 + self::VAT_RATE);
    }

    /**
     * Calculate VAT amount from VAT-inclusive price
     */
    public function calculateVatAmount(float $amountWithVat): float
    {
        return $amountWithVat - $this->removeVat($amountWithVat);
    }

    // ═══════════════════════════════════════════════════════════════
    // REVENUE CONVERSION (VND with VAT → USD without VAT)
    // Key method for profit calculation in reports
    // ═══════════════════════════════════════════════════════════════

    /**
     * Convert WHMCS revenue (VND with VAT) to USD (without VAT)
     *
     * Formula:
     * 1. Remove 10% VAT: VND_base = VND_with_vat / 1.1
     * 2. Convert to USD: USD = VND_base / exchange_rate
     *
     * @param float $revenueVndWithVat Revenue in VND (including VAT from tblhosting)
     * @return float Revenue in USD (excluding VAT, comparable to provider cost)
     */
    public function revenueVndToUsd(float $revenueVndWithVat): float
    {
        return $this->toUsd($this->removeVat($revenueVndWithVat));
    }

    /**
     * Get detailed revenue breakdown for display
     */
    public function getRevenueBreakdown(float $revenueVndWithVat): array
    {
        $vatAmount = $this->calculateVatAmount($revenueVndWithVat);
        $revenueVndNoVat = $this->removeVat($revenueVndWithVat);
        $revenueUsd = $this->toUsd($revenueVndNoVat);

        return [
            'revenue_vnd_with_vat' => $revenueVndWithVat,
            'vat_amount_vnd'       => $vatAmount,
            'revenue_vnd_no_vat'   => $revenueVndNoVat,
            'revenue_usd'          => $revenueUsd,
            'exchange_rate'        => $this->usdVndRate,
            'vat_rate'             => self::VAT_RATE * 100 . '%',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // FORMATTING — Display mode aware
    // ═══════════════════════════════════════════════════════════════

    /**
     * Format USD amount based on display mode setting
     *
     * @param float|null $usdAmount Amount in USD
     * @return string Formatted amount based on currency_display setting
     */
    public function format(?float $usdAmount): string
    {
        if ($usdAmount === null) return '—';

        switch ($this->displayMode) {
            case 'vnd':
                return $this->formatVnd($usdAmount * $this->usdVndRate);
            case 'both':
                return $this->formatUsd($usdAmount)
                    . ' <small class="text-muted">('
                    . $this->formatVnd($usdAmount * $this->usdVndRate)
                    . ')</small>';
            default:
                return $this->formatUsd($usdAmount);
        }
    }

    /**
     * Format VND amount based on display mode setting
     *
     * @param float|null $vndAmount Amount in VND
     * @return string Formatted amount
     */
    public function formatFromVnd(?float $vndAmount): string
    {
        if ($vndAmount === null) return '—';
        $usd = $this->toUsd($vndAmount);

        switch ($this->displayMode) {
            case 'usd':
                return $this->formatUsd($usd);
            case 'both':
                return $this->formatVnd($vndAmount)
                    . ' <small class="text-muted">('
                    . $this->formatUsd($usd)
                    . ')</small>';
            default:
                return $this->formatVnd($vndAmount);
        }
    }

    public function formatUsd(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }

    public function formatVnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }

    /**
     * Compact format for stat cards: $1.2K, $3.5M
     */
    public function formatCompact(?float $usdAmount): string
    {
        if ($usdAmount === null) return '—';

        switch ($this->displayMode) {
            case 'vnd':
                return $this->formatVndCompact($usdAmount * $this->usdVndRate);
            case 'both':
                return $this->formatUsdCompact($usdAmount)
                    . ' (' . $this->formatVndCompact($usdAmount * $this->usdVndRate) . ')';
            default:
                return $this->formatUsdCompact($usdAmount);
        }
    }

    public function formatUsdCompact(float $amount): string
    {
        if (abs($amount) >= 1000000) return '$' . number_format($amount / 1000000, 1) . 'M';
        if (abs($amount) >= 1000)    return '$' . number_format($amount / 1000, 1) . 'K';
        return '$' . number_format($amount, 2);
    }

    public function formatVndCompact(float $amount): string
    {
        if (abs($amount) >= 1000000000) return number_format($amount / 1000000000, 1) . 'B ₫';
        if (abs($amount) >= 1000000)    return number_format($amount / 1000000, 1) . 'M ₫';
        if (abs($amount) >= 1000)       return number_format($amount / 1000, 0) . 'K ₫';
        return number_format($amount, 0) . ' ₫';
    }

    // ═══════════════════════════════════════════════════════════════
    // INFO & UTILITIES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get currency info array for templates
     */
    public function getInfo(): array
    {
        $lastUpdate = $this->getSetting('exchangerate_last_update', '');
        return [
            'rate'                  => $this->usdVndRate,
            'rate_formatted'        => '1 USD = ' . number_format($this->usdVndRate, 0, ',', '.') . ' VND',
            'display_mode'          => $this->displayMode,
            'vat_rate'              => self::VAT_RATE,
            'vat_rate_formatted'    => (self::VAT_RATE * 100) . '%',
            'last_updated'          => $lastUpdate,
            'last_updated_formatted'=> $lastUpdate ? date('d/m/Y H:i', strtotime($lastUpdate)) : 'Never',
        ];
    }

    /**
     * Map billing cycle to NicSRS/provider price key
     */
    public function billingCycleToPriceKey(string $billingCycle): string
    {
        $map = [
            'Monthly'       => 'price001',
            'Quarterly'     => 'price003',
            'Semi-Annually' => 'price006',
            'Annually'      => 'price012',
            'Biennially'    => 'price024',
            'Triennially'   => 'price036',
        ];
        return $map[$billingCycle] ?? 'price012';
    }

    /**
     * Extract cost from provider product price_data
     *
     * @param string $priceDataJson JSON from mod_aio_ssl_products.price_data or mod_nicsrs_products.price_data
     * @param string $billingCycle  WHMCS billing cycle
     * @return float|null Cost in USD, or null if not found
     */
    public function extractCostFromPriceData(?string $priceDataJson, string $billingCycle = 'Annually'): ?float
    {
        if (empty($priceDataJson)) return null;

        $priceData = json_decode($priceDataJson, true);
        if (!is_array($priceData)) return null;

        $priceKey = $this->billingCycleToPriceKey($billingCycle);

        // AIO format: { "1yr": 9.99, "2yr": 17.99 } or nested
        // NicSRS format: { "basePrice": { "price012": 9.99, "price024": 17.99 } }
        if (isset($priceData['basePrice'][$priceKey])) {
            return (float)$priceData['basePrice'][$priceKey];
        }

        // AIO flat format: { "price_1yr": 9.99 }
        $yearMap = [
            'price012' => ['price_1yr', '1yr', '1year', 'annually'],
            'price024' => ['price_2yr', '2yr', '2year', 'biennially'],
            'price036' => ['price_3yr', '3yr', '3year', 'triennially'],
        ];
        foreach ($yearMap[$priceKey] ?? [] as $altKey) {
            if (isset($priceData[$altKey])) return (float)$priceData[$altKey];
        }

        // Direct numeric at top level
        if (isset($priceData[$priceKey])) return (float)$priceData[$priceKey];

        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // EXCHANGE RATE API
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fetch live exchange rate from exchangerate-api.com
     */
    public function fetchRateFromApi(string $apiKey, string $from = 'USD', string $to = 'VND'): array
    {
        if (empty($apiKey)) {
            return ['success' => false, 'rate' => null, 'message' => 'API key is required.'];
        }

        $url = self::API_BASE . '/' . urlencode($apiKey) . '/pair/'
             . strtoupper($from) . '/' . strtoupper($to);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'AIO-SSL-Manager/' . (defined('AIO_SSL_VERSION') ? AIO_SSL_VERSION : '1.0'),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return ['success' => false, 'rate' => null, 'message' => 'Connection failed: ' . ($curlError ?: 'Unknown error')];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'rate' => null, 'message' => 'Invalid JSON response (HTTP ' . $httpCode . ')'];
        }

        if (($data['result'] ?? '') !== 'success') {
            $errorType = $data['error-type'] ?? 'unknown';
            $errorMessages = [
                'unsupported-code'  => 'Unsupported currency code.',
                'malformed-request' => 'Malformed API request.',
                'invalid-key'       => 'Invalid API key.',
                'inactive-account'  => 'API account is inactive.',
                'quota-reached'     => 'Monthly API quota reached (1,500 requests).',
            ];
            return ['success' => false, 'rate' => null, 'message' => $errorMessages[$errorType] ?? "API error: {$errorType}", 'raw' => $data];
        }

        $rate = (float)($data['conversion_rate'] ?? 0);
        if ($rate <= 0) {
            return ['success' => false, 'rate' => null, 'message' => 'Invalid rate returned: ' . ($data['conversion_rate'] ?? 'null')];
        }

        return [
            'success'     => true,
            'rate'        => $rate,
            'message'     => "Rate fetched: 1 {$from} = " . number_format($rate, 2) . " {$to}",
            'time_update' => $data['time_last_update_utc'] ?? '',
            'time_next'   => $data['time_next_update_utc'] ?? '',
            'raw'         => $data,
        ];
    }

    /**
     * Fetch rate and save to DB settings
     */
    public function updateRateFromApi(): array
    {
        $apiKey = $this->getSetting('exchangerate_api_key', '');
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'API key not configured.'];
        }

        $result = $this->fetchRateFromApi($apiKey);
        if (!$result['success']) {
            $errors = (int)$this->getSetting('exchangerate_error_count', 0);
            $this->saveSetting('exchangerate_error_count', $errors + 1);
            $this->saveSetting('exchangerate_last_error', $result['message']);
            ActivityLogger::log('rate_update_fail', 'system', 'currency', $result['message']);
            return $result;
        }

        $oldRate = $this->usdVndRate;
        $newRate = $result['rate'];
        $change = $oldRate > 0 ? round(($newRate - $oldRate) / $oldRate * 100, 3) : 0;

        $this->saveSetting('currency_usd_vnd_rate', (string)$newRate);
        $this->saveSetting('exchangerate_last_update', date('Y-m-d H:i:s'));
        $this->saveSetting('exchangerate_last_rate', (string)$newRate);
        $this->saveSetting('exchangerate_error_count', '0');
        $this->saveSetting('exchangerate_last_error', '');
        $this->saveSetting('exchangerate_api_next_update', $result['time_next'] ?? '');

        $this->usdVndRate = $newRate;

        $msg = "Rate updated: 1 USD = " . number_format($newRate, 2) . " VND";
        if ($change != 0) {
            $msg .= " (change: " . ($change > 0 ? '+' : '') . $change . "%)";
        }
        ActivityLogger::log('rate_updated', 'system', 'currency', $msg);

        return [
            'success'  => true,
            'message'  => $msg,
            'rate'     => $newRate,
            'old_rate' => $oldRate,
            'change'   => $change,
        ];
    }

    /**
     * Check if auto-update should run
     */
    public function shouldAutoUpdate(): bool
    {
        if ($this->getSetting('exchangerate_auto_enabled', '0') !== '1') return false;
        if (empty($this->getSetting('exchangerate_api_key', ''))) return false;

        $interval = (int)$this->getSetting('exchangerate_update_interval', 24);
        $lastUpdate = $this->getSetting('exchangerate_last_update', '');
        if (empty($lastUpdate)) return true;

        return (time() - strtotime($lastUpdate)) >= $interval * 3600;
    }

    // ─── DB Helpers ────────────────────────────────────────────────

    private function getSetting(string $key, $default = null)
    {
        try {
            $row = Capsule::table('mod_aio_ssl_settings')->where('setting', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
                ['setting' => $key],
                ['value' => $value]
            );
        } catch (\Exception $e) {
            // Silent
        }
    }
}