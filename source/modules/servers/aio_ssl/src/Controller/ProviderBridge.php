<?php
/**
 * ProviderBridge — Resolves the correct provider for a service/order
 *
 * FIXED: getOrder() now checks all 3 tables per constraints C4 + C5
 * Resolution: mod_aio_ssl_orders → tblsslorders → nicsrs_sslorders
 *
 * @package    AioSSL\Server
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Server;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderInterface;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ProviderFactory;

class ProviderBridge
{
    /**
     * Legacy module → provider slug mapping
     */
    private const LEGACY_MAP = [
        'nicsrs_ssl'         => 'nicsrs',
        'SSLCENTERWHMCS'     => 'gogetssl',
        'thesslstore_ssl'    => 'thesslstore',
        'thesslstorefullv2'  => 'thesslstore',
        'thesslstore'        => 'thesslstore',
        'ssl2buy'            => 'ssl2buy',
    ];

    /**
     * Get provider instance for a service
     *
     * @param int $serviceId
     * @return ProviderInterface
     * @throws \RuntimeException
     */
    public static function getProvider(int $serviceId): ProviderInterface
    {
        $order = self::getOrder($serviceId);

        if ($order) {
            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $slug = $order->provider_slug ?? $configdata['provider'] ?? '';

            // Legacy module detection
            if (empty($slug) && !empty($order->module)) {
                $slug = self::LEGACY_MAP[$order->module] ?? '';
            }

            if (!empty($slug) && $slug !== 'auto') {
                return ProviderRegistry::get($slug);
            }
        }

        // Step 2: Check tblproducts.configoption2
        try {
            $hosting = Capsule::table('tblhosting')->find($serviceId);
            if ($hosting) {
                $product = Capsule::table('tblproducts')->find($hosting->packageid);
                if ($product) {
                    $configoption2 = $product->configoption2 ?? '';
                    if (!empty($configoption2) && $configoption2 !== 'auto') {
                        return ProviderRegistry::get($configoption2);
                    }
                }
            }
        } catch (\Exception $e) {}

        // Step 3: Auto — find cheapest provider for canonical product
        try {
            $hosting = Capsule::table('tblhosting')->find($serviceId);
            if ($hosting) {
                $product = Capsule::table('tblproducts')->find($hosting->packageid);
                $canonicalId = $product->configoption1 ?? '';

                if (!empty($canonicalId)
                    && class_exists('AioSSL\\Service\\PriceCompareService')
                    && method_exists('AioSSL\\Service\\PriceCompareService', 'findCheapest')) {
                    $cheapest = \AioSSL\Service\PriceCompareService::findCheapest($canonicalId);
                    if ($cheapest) {
                        return ProviderRegistry::get($cheapest['provider_slug']);
                    }
                }
            }
        } catch (\Exception $e) {}

        // Step 4: First enabled provider
        $firstProvider = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->first();

        if ($firstProvider) {
            return ProviderRegistry::get($firstProvider->slug);
        }

        throw new \RuntimeException('No SSL provider available. Configure providers in AIO SSL Admin.');
    }

    /**
     * Resolve provider for a product/service (used by CreateAccount)
     *
     * @param int    $serviceId
     * @param string $canonicalId
     * @return array ['slug' => string, 'provider' => ProviderInterface]
     */
    public static function resolveProvider(int $serviceId, string $canonicalId = ''): array
    {
        // Check configoption2 first
        try {
            $hosting = Capsule::table('tblhosting')->find($serviceId);
            if ($hosting) {
                $product = Capsule::table('tblproducts')->find($hosting->packageid);
                $slug = $product->configoption2 ?? '';

                if (!empty($slug) && $slug !== 'auto') {
                    return ['slug' => $slug, 'provider' => ProviderRegistry::get($slug)];
                }

                // Use canonical from product if not passed
                if (empty($canonicalId)) {
                    $canonicalId = $product->configoption1 ?? '';
                }
            }
        } catch (\Exception $e) {}

        // Try cheapest (PriceCompareService may not be implemented yet)
        if (!empty($canonicalId)) {
            try {
                if (class_exists('AioSSL\\Service\\PriceCompareService')
                    && method_exists('AioSSL\\Service\\PriceCompareService', 'findCheapest')) {
                    $cheapest = \AioSSL\Service\PriceCompareService::findCheapest($canonicalId);
                    if ($cheapest) {
                        return [
                            'slug' => $cheapest['provider_slug'],
                            'provider' => ProviderRegistry::get($cheapest['provider_slug']),
                        ];
                    }
                }
            } catch (\Exception $e) {}
        }

        // First enabled
        $first = Capsule::table('mod_aio_ssl_providers')
            ->where('is_enabled', 1)->orderBy('sort_order')->first();

        if ($first) {
            return ['slug' => $first->slug, 'provider' => ProviderRegistry::get($first->slug)];
        }

        throw new \RuntimeException('No SSL provider available.');
    }

    /**
     * Get SSL order for a service — checks ALL 3 tables
     *
     * Priority: mod_aio_ssl_orders → tblsslorders → nicsrs_sslorders
     * Constraint C4 (dual-table read) + C5 (NicSRS custom table)
     *
     * @param int $serviceId
     * @return object|null Normalized order object with `_source_table` property
     */
    public static function getOrder(int $serviceId): ?object
    {
        // ── 1. mod_aio_ssl_orders (primary) ──
        try {
            $order = Capsule::table('mod_aio_ssl_orders')
                ->where('service_id', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($order) {
                $order->_source_table = 'mod_aio_ssl_orders';
                // Normalize to common field names
                $order->serviceid = $order->service_id;
                $order->remoteid  = $order->remote_id;
                return $order;
            }
        } catch (\Exception $e) {
            // Table may not exist — continue
        }

        // ── 2. tblsslorders ──
        try {
            $order = Capsule::table('tblsslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($order) {
                $order->_source_table = 'tblsslorders';
                $order->service_id = $order->serviceid;
                $order->remote_id  = $order->remoteid ?? null;
                $order->provider_slug = null; // Will be resolved from configdata/module

                // Resolve provider_slug from module name
                if (!empty($order->module) && $order->module !== 'aio_ssl') {
                    $order->provider_slug = self::LEGACY_MAP[$order->module] ?? null;
                }

                return $order;
            }
        } catch (\Exception $e) {}

        // ── 3. nicsrs_sslorders (constraint C5) ──
        try {
            $order = Capsule::table('nicsrs_sslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($order) {
                $order->_source_table = 'nicsrs_sslorders';
                $order->service_id    = $order->serviceid;
                $order->remote_id     = $order->remoteid ?? null;
                $order->provider_slug = 'nicsrs';
                $order->module        = 'nicsrs_ssl';
                return $order;
            }
        } catch (\Exception $e) {}

        return null;
    }

    /**
     * Get provider slug from an order object
     */
    public static function resolveSlugFromOrder(object $order): string
    {
        // Direct provider_slug field
        if (!empty($order->provider_slug)) {
            return $order->provider_slug;
        }

        // From configdata
        $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
        if (!empty($configdata['provider'])) {
            return $configdata['provider'];
        }

        // From legacy module name
        if (!empty($order->module)) {
            return self::LEGACY_MAP[$order->module] ?? '';
        }

        return '';
    }

    /**
     * Update the correct table based on order source
     *
     * @param object $order  Order with _source_table
     * @param array  $data   Fields to update
     */
    public static function updateOrder(object $order, array $data): bool
    {
        $table = $order->_source_table ?? 'mod_aio_ssl_orders';

        if ($table === 'mod_aio_ssl_orders') {
            return OrderService::update($order->id, $data);
        }

        // Legacy tables — map field names
        $legacyData = [];
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'service_id': $legacyData['serviceid'] = $value; break;
                case 'remote_id':  $legacyData['remoteid'] = $value; break;
                default:           $legacyData[$key] = $value;
            }
        }

        try {
            return Capsule::table($table)
                ->where('id', $order->id)
                ->update($legacyData) >= 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}