<?php
/**
 * Abstract Provider — Base implementation for all SSL providers
 *
 * Provides: HTTP client (cURL), logging, error handling, retry logic,
 * credential access, and default UnsupportedOperationException for optional methods.
 *
 * @package    AioSSL\Core
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Core;

abstract class AbstractProvider implements ProviderInterface
{
    /** @var array Decrypted API credentials */
    protected $credentials = [];

    /** @var string API mode: 'live' or 'sandbox' */
    protected $apiMode = 'live';

    /** @var array Provider-specific config */
    protected $config = [];

    /** @var int Maximum retry attempts for API calls */
    protected $maxRetries = 2;

    /** @var int Retry delay in milliseconds */
    protected $retryDelay = 1000;

    /** @var int cURL timeout in seconds */
    protected $timeout = 30;

    /** @var int cURL connection timeout */
    protected $connectTimeout = 10;

    /**
     * @param array  $credentials Decrypted API credentials
     * @param string $apiMode     'live' or 'sandbox'
     * @param array  $config      Extra provider config
     */
    public function __construct(array $credentials = [], string $apiMode = 'live', array $config = [])
    {
        $this->credentials = $credentials;
        $this->apiMode = $apiMode;
        $this->config = $config;
    }

    // ─── HTTP Client ───────────────────────────────────────────────

    /**
     * Send HTTP GET request
     *
     * @param string $url
     * @param array  $params Query parameters
     * @param array  $headers
     * @return array ['code'=>int, 'body'=>string, 'decoded'=>array|null]
     * @throws \RuntimeException
     */
    protected function httpGet(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->httpRequest('GET', $url, null, $headers);
    }

    /**
     * Send HTTP POST request
     *
     * @param string       $url
     * @param array|string $data POST data
     * @param array        $headers
     * @return array
     * @throws \RuntimeException
     */
    protected function httpPost(string $url, $data = [], array $headers = []): array
    {
        $body = is_array($data) ? http_build_query($data) : $data;
        return $this->httpRequest('POST', $url, $body, $headers);
    }

    /**
     * Send HTTP POST with JSON body
     *
     * @param string $url
     * @param array  $data
     * @param array  $headers
     * @return array
     */
    protected function httpPostJson(string $url, array $data = [], array $headers = []): array
    {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($data);
        return $this->httpRequest('POST', $url, $body, $headers);
    }

    /**
     * Core HTTP request with retry logic
     *
     * @param string      $method
     * @param string      $url
     * @param string|null $body
     * @param array       $headers
     * @return array
     * @throws \RuntimeException
     */
    protected function httpRequest(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $attempt = 0;
        $lastError = '';

        while ($attempt <= $this->maxRetries) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'AIO-SSL-Manager/' . AIO_SSL_VERSION . ' (WHMCS)',
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            // Success: 2xx status
            if ($curlErrno === 0 && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                return [
                    'code'    => $httpCode,
                    'body'    => $response,
                    'decoded' => (json_last_error() === JSON_ERROR_NONE) ? $decoded : null,
                ];
            }

            // Non-retryable: 4xx client errors (except 429)
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                $decoded = json_decode($response, true);
                return [
                    'code'    => $httpCode,
                    'body'    => $response,
                    'decoded' => (json_last_error() === JSON_ERROR_NONE) ? $decoded : null,
                ];
            }

            $lastError = $curlErrno ? "cURL #{$curlErrno}: {$curlError}" : "HTTP {$httpCode}";

            $this->log('warning', "API retry #{$attempt}: {$lastError}", [
                'url'    => $this->maskUrl($url),
                'method' => $method,
            ]);

            $attempt++;
            if ($attempt <= $this->maxRetries) {
                usleep($this->retryDelay * 1000 * $attempt); // Exponential backoff
            }
        }

        throw new \RuntimeException(
            "API request failed after {$this->maxRetries} retries: {$lastError}"
        );
    }

    // ─── Logging ───────────────────────────────────────────────────

    /**
     * Log activity to mod_aio_ssl_activity_log
     *
     * @param string $level   'info','warning','error'
     * @param string $message
     * @param array  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        try {
            ActivityLogger::log(
                'provider_' . $level,
                'provider',
                $this->getSlug(),
                $message,
                $context
            );
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Mask sensitive data in URLs for logging
     *
     * @param string $url
     * @return string
     */
    protected function maskUrl(string $url): string
    {
        return preg_replace(
            '/([?&](api_token|auth_key|password|api_key|auth_token)=)([^&]+)/i',
            '$1***MASKED***',
            $url
        );
    }

    /**
     * Mask API token for display: show first 8 chars + ***
     *
     * @param string $token
     * @return string
     */
    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return '***';
        }
        return substr($token, 0, 8) . '***';
    }

    /**
     * Get a credential value with optional default
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function getCredential(string $key, $default = '')
    {
        return isset($this->credentials[$key]) ? $this->credentials[$key] : $default;
    }

    /**
     * Get API base URL (live or sandbox)
     *
     * @return string
     */
    abstract protected function getBaseUrl(): string;

    // ─── Default implementations (throw for unsupported) ───────────

    public function getBalance(): array
    {
        throw new UnsupportedOperationException($this->getName(), 'getBalance');
    }

    public function getConfigurationLink(string $orderId): array
    {
        throw new UnsupportedOperationException($this->getName(), 'getConfigurationLink');
    }
}