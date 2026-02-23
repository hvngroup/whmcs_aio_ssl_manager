<?php

namespace AioSSL\Core;

use WHMCS\Database\Capsule;

/**
 * Factory: Instantiate provider by slug, inject decrypted credentials
 */
class ProviderFactory
{
    /** @var array Slug → class mapping */
    private static $providerClasses = [
        'nicsrs'       => 'AioSSL\\Provider\\NicsrsProvider',
        'gogetssl'     => 'AioSSL\\Provider\\GoGetSSLProvider',
        'thesslstore'  => 'AioSSL\\Provider\\TheSSLStoreProvider',
        'ssl2buy'      => 'AioSSL\\Provider\\SSL2BuyProvider',
    ];

    /**
     * Create provider instance by slug
     *
     * @param string $slug
     * @return ProviderInterface
     * @throws \RuntimeException
     */
    public static function create(string $slug): ProviderInterface
    {
        if (!isset(self::$providerClasses[$slug])) {
            throw new \RuntimeException("Unknown provider slug: {$slug}");
        }

        // Fetch provider config from database
        $provider = Capsule::table('mod_aio_ssl_providers')
            ->where('slug', $slug)
            ->first();

        if (!$provider) {
            throw new \RuntimeException("Provider not found in database: {$slug}");
        }

        if (!$provider->is_enabled) {
            throw new \RuntimeException("Provider is disabled: {$slug}");
        }

        // Decrypt credentials
        $credentials = [];
        if (!empty($provider->api_credentials)) {
            $credentials = EncryptionService::decryptCredentials($provider->api_credentials);
        }

        $config = [];
        if (!empty($provider->config)) {
            $config = json_decode($provider->config, true) ?: [];
        }

        $className = self::$providerClasses[$slug];
        return new $className($credentials, $provider->api_mode, $config);
    }

    /**
     * Register a new provider class (for extensibility)
     *
     * @param string $slug
     * @param string $className Fully qualified class name
     */
    public static function register(string $slug, string $className): void
    {
        self::$providerClasses[$slug] = $className;
    }

    /**
     * Get all registered provider slugs
     *
     * @return string[]
     */
    public static function getRegisteredSlugs(): array
    {
        return array_keys(self::$providerClasses);
    }
}