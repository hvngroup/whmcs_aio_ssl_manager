<?php
/**
 * Currency Helper — USD/VND conversion and formatting
 *
 * @package    AioSSL\Helper
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Helper;

use WHMCS\Database\Capsule;

class CurrencyHelper
{
    /** @var float USD → VND exchange rate */
    private $usdVndRate;

    /** @var string Display mode: 'usd' | 'vnd' | 'both' */
    private $displayMode;

    public function __construct()
    {
        $this->usdVndRate = (float)$this->getSetting('currency_usd_vnd_rate', 25000);
        $this->displayMode = $this->getSetting('currency_display', 'usd');
    }

    /**
     * Format price for display based on settings
     *
     * @param float|null $usdAmount Amount in USD
     * @return string Formatted price string
     */
    public function format(?float $usdAmount): string
    {
        if ($usdAmount === null) return '—';

        switch ($this->displayMode) {
            case 'vnd':
                return $this->formatVnd($usdAmount * $this->usdVndRate);
            case 'both':
                return $this->formatUsd($usdAmount) . ' <small class="text-muted">(' . $this->formatVnd($usdAmount * $this->usdVndRate) . ')</small>';
            default:
                return $this->formatUsd($usdAmount);
        }
    }

    public function formatUsd(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    public function formatVnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }

    /**
     * Convert USD to VND
     */
    public function toVnd(float $usd): float
    {
        return $usd * $this->usdVndRate;
    }

    /**
     * Convert VND to USD
     */
    public function toUsd(float $vnd): float
    {
        return $this->usdVndRate > 0 ? $vnd / $this->usdVndRate : 0;
    }

    public function getRate(): float
    {
        return $this->usdVndRate;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    /**
     * Update exchange rate from API (exchangerate-api.com)
     * Planned feature — currently returns stored rate.
     */
    public function updateRateFromApi(): ?float
    {
        // Future: fetch from https://v6.exchangerate-api.com/v6/{key}/pair/USD/VND
        return null;
    }

    private function getSetting(string $key, $default = null)
    {
        try {
            $row = Capsule::table('mod_aio_ssl_settings')->where('setting', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}