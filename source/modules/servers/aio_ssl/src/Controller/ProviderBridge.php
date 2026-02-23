<?php
/**
 * ProviderBridge — Resolves the correct provider for a service/order
 *
 * Resolution order:
 * 1. Check tblsslorders configdata.provider (existing order)
 * 2. Check tblproducts.configoption2 (product-level preference)
 * 3. If 'auto' → use PriceCompareService to find cheapest
 * 4. Return provider instance via ProviderFactory
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
     * Get provider instance for a service
     *
     * @param int $serviceId
     * @return ProviderInterface
     * @throws \RuntimeException
     */
    public static function getProvider(int $serviceId): ProviderInterface
    {
        // Step 1: Check existing SSL order configdata
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

            // Legacy module detection: map module name to provider
            if ($order->module !== 'aio_ssl' && !empty($order->module)) {
                $legacyMap = [
                    'nicsrs_ssl'      => 'nicsrs',
                    'SSLCENTERWHMCS'  => 'gogetssl',
                    'thesslstore_ssl' => 'thesslstore',
                    'ssl2buy'         => 'ssl2buy',
                ];
                $legacySlug = $legacyMap[$order->module] ?? '';
                if ($legacySlug) {
                    return ProviderRegistry::get($legacySlug);
                }
            }
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

    /**
     * Get the SSL order for a service
     *
     * @param int $serviceId
     * @return object|null
     */
    public static function getOrder(int $serviceId)
    {
        return Capsule::table('tblsslorders')
            ->where('serviceid', $serviceId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Update order configdata (merge)
     *
     * @param int   $orderId
     * @param array $data Data to merge into configdata
     * @return void
     */
    public static function updateOrderConfig(int $orderId, array $data): void
    {
        $order = Capsule::table('tblsslorders')->find($orderId);
        if (!$order) return;

        $configdata = json_decode($order->configdata, true) ?: [];
        $configdata = array_merge($configdata, $data);

        Capsule::table('tblsslorders')
            ->where('id', $orderId)
            ->update(['configdata' => json_encode($configdata)]);
    }
}