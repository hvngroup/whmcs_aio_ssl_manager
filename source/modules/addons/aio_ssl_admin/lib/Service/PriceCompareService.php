<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;

class PriceCompareService
{
    /**
     * Compare prices for a canonical product across all providers
     *
     * @param string $canonicalId
     * @return array
     */
    public function compare(string $canonicalId): array
    {
        $map = Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $canonicalId)
            ->first();

        if (!$map) {
            return ['error' => 'Canonical product not found.'];
        }

        $providerCodes = [
            'nicsrs'      => $map->nicsrs_code,
            'gogetssl'    => $map->gogetssl_code,
            'thesslstore' => $map->thesslstore_code,
            'ssl2buy'     => $map->ssl2buy_code,
        ];

        $prices = [];
        $periods = ['12', '24', '36'];

        foreach ($providerCodes as $slug => $code) {
            if (empty($code)) {
                $prices[$slug] = null;
                continue;
            }

            $product = Capsule::table('mod_aio_ssl_products')
                ->where('provider_slug', $slug)
                ->where('product_code', $code)
                ->first();

            if ($product && $product->price_data) {
                $prices[$slug] = json_decode($product->price_data, true) ?: null;
            } else {
                $prices[$slug] = null;
            }
        }

        // Best price per period
        $bestPrices = [];
        foreach ($periods as $period) {
            $best = null;
            $bestSlug = null;
            foreach ($prices as $slug => $data) {
                if ($data === null) continue;
                $base = $data['base'] ?? $data;
                $price = $base[$period] ?? null;
                if ($price !== null && ($best === null || $price < $best)) {
                    $best = $price;
                    $bestSlug = $slug;
                }
            }
            $bestPrices[$period] = ['price' => $best, 'provider' => $bestSlug];
        }

        return [
            'canonical'  => $map,
            'prices'     => $prices,
            'bestPrices' => $bestPrices,
            'periods'    => $periods,
        ];
    }

    /**
     * Get cheapest provider for a canonical product at given period
     *
     * @param string $canonicalId
     * @param int    $months
     * @return array ['slug'=>string, 'price'=>float]|null
     */
    public function getCheapest(string $canonicalId, int $months = 12): ?array
    {
        $comparison = $this->compare($canonicalId);
        if (isset($comparison['error'])) return null;

        $period = (string)$months;
        $best = $comparison['bestPrices'][$period] ?? null;

        if ($best && $best['provider']) {
            return ['slug' => $best['provider'], 'price' => $best['price']];
        }

        return null;
    }
}