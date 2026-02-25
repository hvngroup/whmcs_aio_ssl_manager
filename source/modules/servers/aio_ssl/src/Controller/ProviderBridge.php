<?php
/**
 * ProviderBridge — Resolves the correct provider for a service/order
 *
 * Resolution order:
 * 1. Check mod_aio_ssl_orders configdata.provider (claimed order)
 * 2. Check tblsslorders configdata.provider (existing order)
 * 3. Check tblproducts.configoption2 (product-level preference)
 * 4. If 'auto' → use PriceCompareService to find cheapest
 * 5. Return provider instance via ProviderFactory
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
use AioSSL\Service\PriceCompareService;

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
        // Step 0: Check mod_aio_ssl_orders (claimed orders)
        try {
            $aioOrder = Capsule::table('mod_aio_ssl_orders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($aioOrder && !empty($aioOrder->provider)) {
                return ProviderRegistry::get($aioOrder->provider);
            }
        } catch (\Exception $e) {
            // Table may not exist yet — continue
        }

        // Step 1: Check tblsslorders configdata
        $order = Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->orderBy('id', 'desc')
            ->first();

        if ($order) {
            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';

            if (!empty($slug) && $slug !== 'auto') {
                return ProviderRegistry::get($slug);
            }

            // Legacy module detection
            if ($order->module !== 'aio_ssl' && !empty($order->module)) {
                $legacySlug = self::LEGACY_MAP[$order->module] ?? '';
                if ($legacySlug) {
                    return ProviderRegistry::get($legacySlug);
                }
            }
        }

        // Step 1b: Check nicsrs_sslorders (NicSRS uses separate table)
        try {
            $nicsrsOrder = Capsule::table('nicsrs_sslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($nicsrsOrder) {
                return ProviderRegistry::get('nicsrs');
            }
        } catch (\Exception $e) {
            // Table may not exist — continue
        }

        // Step 2: Check product config options
        $product = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $serviceId)
            ->select('tblproducts.configoption1', 'tblproducts.configoption2', 'tblproducts.configoption4')
            ->first();

        if ($product) {
            $preferredSlug = $product->configoption2 ?? 'auto';
            $canonicalId = $product->configoption1 ?? '';
            $fallbackSlug = $product->configoption4 ?? '';

            // Specific provider selected
            if (!empty($preferredSlug) && $preferredSlug !== 'auto') {
                try {
                    return ProviderRegistry::get($preferredSlug);
                } catch (\Exception $e) {
                    // Try fallback
                    if (!empty($fallbackSlug)) {
                        try {
                            return ProviderRegistry::get($fallbackSlug);
                        } catch (\Exception $e2) {
                            // Continue to auto-select
                        }
                    }
                }
            }

            // Step 3: Auto-select cheapest provider
            if (!empty($canonicalId)) {
                $pcs = new PriceCompareService();
                $cheapest = $pcs->getCheapest($canonicalId, 12);
                if ($cheapest && !empty($cheapest['slug'])) {
                    return ProviderRegistry::get($cheapest['slug']);
                }
            }
        }

        throw new \RuntimeException(
            'Unable to resolve provider for service #' . $serviceId
            . '. Please configure a provider in the product settings.'
        );
    }

    /**
     * Get provider slug for a service (without creating instance)
     *
     * @param int $serviceId
     * @return string Provider slug
     */
    public static function getProviderSlug(int $serviceId): string
    {
        try {
            $provider = self::getProvider($serviceId);
            return $provider->getSlug();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get the active SSL order for a service
     *
     * Checks mod_aio_ssl_orders first, then tblsslorders, then nicsrs_sslorders
     *
     * @param int $serviceId
     * @return object|null
     */
    public static function getOrder(int $serviceId): ?object
    {
        // Priority 1: mod_aio_ssl_orders (AIO native + claimed)
        try {
            $aioOrder = Capsule::table('mod_aio_ssl_orders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();
            if ($aioOrder) {
                return $aioOrder;
            }
        } catch (\Exception $e) {}

        // Priority 2: tblsslorders
        $order = Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->orderBy('id', 'desc')
            ->first();
        if ($order) {
            return $order;
        }

        // Priority 3: nicsrs_sslorders
        try {
            $nicsrs = Capsule::table('nicsrs_sslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();
            if ($nicsrs) {
                // Inject 'module' for compatibility
                $nicsrs->module = 'nicsrs_ssl';
                return $nicsrs;
            }
        } catch (\Exception $e) {}

        return null;
    }

    /**
     * Update order configdata (merge)
     *
     * @param int   $orderId
     * @param array $mergeData
     * @param string $table Which table (auto-detect if empty)
     */
    public static function updateOrderConfig(int $orderId, array $mergeData, string $table = ''): void
    {
        $tables = $table ? [$table] : ['mod_aio_ssl_orders', 'tblsslorders'];

        foreach ($tables as $t) {
            try {
                $order = Capsule::table($t)->find($orderId);
                if ($order) {
                    $existing = json_decode($order->configdata, true) ?: [];
                    $merged = array_merge($existing, $mergeData);
                    Capsule::table($t)->where('id', $orderId)->update([
                        'configdata' => json_encode($merged),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    return;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Get canonical product ID for a service
     *
     * @param int $serviceId
     * @return string
     */
    public static function getCanonicalId(int $serviceId): string
    {
        $product = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $serviceId)
            ->value('tblproducts.configoption1');

        return $product ?: '';
    }

    /**
     * Get provider-specific product code from canonical mapping
     *
     * @param string $canonicalId
     * @param string $providerSlug
     * @return string
     */
    public static function getProviderProductCode(string $canonicalId, string $providerSlug): string
    {
        $column = $providerSlug . '_code';
        $allowed = ['nicsrs_code', 'gogetssl_code', 'thesslstore_code', 'ssl2buy_code'];

        if (!in_array($column, $allowed)) {
            return '';
        }

        return (string)Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $canonicalId)
            ->value($column) ?: '';
    }
}