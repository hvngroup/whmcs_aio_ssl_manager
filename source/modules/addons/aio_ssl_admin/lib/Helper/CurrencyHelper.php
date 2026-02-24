<?php
/**
 * Currency Helper — USD/VND conversion, formatting, and auto-update via API
 *
 * API: https://v6.exchangerate-api.com/v6/{key}/pair/USD/VND
 * Free tier: 1,500 requests/month (sufficient for hourly updates)
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
        $this->usdVndRate = (float)$this->getSetting('currency_usd_vnd_rate', 25000);
        $this->displayMode = $this->getSetting('currency_display', 'usd');
    }

    // ─── Formatting ────────────────────────────────────────────────

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

    public function formatUsd(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    public function formatVnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }

    public function toVnd(float $usd): float  { return $usd * $this->usdVndRate; }
    public function toUsd(float $vnd): float  { return $this->usdVndRate > 0 ? $vnd / $this->usdVndRate : 0; }
    public function getRate(): float          { return $this->usdVndRate; }
    public function getDisplayMode(): string  { return $this->displayMode; }

    // ─── Exchange Rate API ─────────────────────────────────────────

    /**
     * Fetch live exchange rate from exchangerate-api.com
     *
     * @param string      $apiKey   API key
     * @param string      $from     Source currency (default: USD)
     * @param string      $to       Target currency (default: VND)
     * @return array ['success'=>bool, 'rate'=>float|null, 'message'=>string, 'raw'=>array]
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

        // cURL error
        if ($response === false || !empty($curlError)) {
            return [
                'success' => false,
                'rate'    => null,
                'message' => 'Connection failed: ' . ($curlError ?: 'Unknown error'),
            ];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'rate'    => null,
                'message' => 'Invalid JSON response (HTTP ' . $httpCode . ')',
            ];
        }

        // API error responses
        if (($data['result'] ?? '') !== 'success') {
            $errorType = $data['error-type'] ?? 'unknown';
            $errorMessages = [
                'unsupported-code'  => 'Unsupported currency code.',
                'malformed-request' => 'Malformed API request.',
                'invalid-key'       => 'Invalid API key.',
                'inactive-account'  => 'API account is inactive.',
                'quota-reached'     => 'Monthly API quota reached (1,500 requests).',
            ];
            return [
                'success' => false,
                'rate'    => null,
                'message' => $errorMessages[$errorType] ?? "API error: {$errorType}",
                'raw'     => $data,
            ];
        }

        $rate = (float)($data['conversion_rate'] ?? 0);
        if ($rate <= 0) {
            return [
                'success' => false,
                'rate'    => null,
                'message' => 'Invalid rate returned: ' . ($data['conversion_rate'] ?? 'null'),
            ];
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
     *
     * @return array Result with success/message
     */
    public function updateRateFromApi(): array
    {
        $apiKey = $this->getSetting('exchangerate_api_key', '');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'API key not configured.'];
        }

        $result = $this->fetchRateFromApi($apiKey);

        if (!$result['success']) {
            // Increment error count
            $errors = (int)$this->getSetting('exchangerate_error_count', 0);
            $this->saveSetting('exchangerate_error_count', $errors + 1);
            $this->saveSetting('exchangerate_last_error', $result['message']);

            ActivityLogger::log('rate_update_fail', 'system', 'currency', $result['message']);
            return $result;
        }

        // Save rate + metadata
        $oldRate = $this->usdVndRate;
        $newRate = $result['rate'];
        $change = $oldRate > 0 ? round(($newRate - $oldRate) / $oldRate * 100, 3) : 0;

        $this->saveSetting('currency_usd_vnd_rate', (string)$newRate);
        $this->saveSetting('exchangerate_last_update', date('Y-m-d H:i:s'));
        $this->saveSetting('exchangerate_last_rate', (string)$newRate);
        $this->saveSetting('exchangerate_error_count', '0');
        $this->saveSetting('exchangerate_last_error', '');
        $this->saveSetting('exchangerate_api_next_update', $result['time_next'] ?? '');

        // Update in-memory
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
     * Check if auto-update should run (based on interval)
     *
     * @return bool
     */
    public function shouldAutoUpdate(): bool
    {
        $enabled = $this->getSetting('exchangerate_auto_enabled', '0');
        if ($enabled !== '1') return false;

        $apiKey = $this->getSetting('exchangerate_api_key', '');
        if (empty($apiKey)) return false;

        $interval = (int)$this->getSetting('exchangerate_update_interval', 24); // hours
        $lastUpdate = $this->getSetting('exchangerate_last_update', '');

        if (empty($lastUpdate)) return true;

        $elapsed = time() - strtotime($lastUpdate);
        return ($elapsed >= $interval * 3600);
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
                ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
            );
        } catch (\Exception $e) {
            // Silent
        }
    }
}