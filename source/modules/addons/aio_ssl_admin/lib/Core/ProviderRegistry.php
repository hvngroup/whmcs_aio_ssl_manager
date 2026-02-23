<?php

namespace AioSSL\Core;

use WHMCS\Database\Capsule;

/**
 * Static registry of provider instances — getAllEnabled(), get(slug)
 */
class ProviderRegistry
{
    /** @var ProviderInterface[] Cached instances */
    private static $instances = [];

    /**
     * Get a single provider by slug
     *
     * @param string $slug
     * @return ProviderInterface
     */
    public static function get(string $slug): ProviderInterface
    {
        if (!isset(self::$instances[$slug])) {
            self::$instances[$slug] = ProviderFactory::create($slug);
        }
        return self::$instances[$slug];
    }

    /**
     * Get all enabled providers
     *
     * @return ProviderInterface[]
     */
    public static function getAllEnabled(): array
    {
        $rows = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();

        $providers = [];
        foreach ($rows as $row) {
            try {
                $providers[$row->slug] = self::get($row->slug);
            } catch (\Exception $e) {
                // Skip providers that fail to initialize
                ActivityLogger::log('provider_error', 'provider', $row->slug, 'Failed to init: ' . $e->getMessage());
            }
        }

        return $providers;
    }

    /**
     * Get all provider database records (for admin listing)
     *
     * @param bool $enabledOnly
     * @return array
     */
    public static function getAllRecords(bool $enabledOnly = false): array
    {
        $q = Capsule::table('mod_aio_ssl_providers')->orderBy('sort_order');
        if ($enabledOnly) {
            $q->where('is_enabled', 1);
        }
        return $q->get()->toArray();
    }

    /**
     * Clear cached instances (for testing / after config changes)
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}