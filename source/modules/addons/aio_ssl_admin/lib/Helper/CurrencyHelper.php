<?php

namespace AioSSL\Helper;

use WHMCS\Database\Capsule;

class CurrencyHelper
{
    /** @var float|null Cached exchange rate */
    private static $rate = null;

    /**
     * Get USD to VND exchange rate
     */
    public static function getRate(): float
    {
        if (self::$rate !== null) return self::$rate;

        try {
            $val = Capsule::table('mod_aio_ssl_settings')
                ->where('setting', 'currency_usd_vnd_rate')
                ->value('value');
            self::$rate = (float)($val ?: 25000);
        } catch (\Exception $e) {
            self::$rate = 25000;
        }

        return self::$rate;
    }

    /**
     * Format price based on currency display setting
     */
    public static function formatPrice(float $usdPrice, ?string $displayMode = null): string
    {
        if ($displayMode === null) {
            try {
                $displayMode = Capsule::table('mod_aio_ssl_settings')
                    ->where('setting', 'currency_display')
                    ->value('value') ?: 'usd';
            } catch (\Exception $e) {
                $displayMode = 'usd';
            }
        }

        switch ($displayMode) {
            case 'vnd':
                $vnd = $usdPrice * self::getRate();
                return number_format($vnd, 0, ',', '.') . ' ₫';
            case 'both':
                $vnd = $usdPrice * self::getRate();
                return '$' . number_format($usdPrice, 2) . ' (' . number_format($vnd, 0, ',', '.') . ' ₫)';
            default: // usd
                return '$' . number_format($usdPrice, 2);
        }
    }
}