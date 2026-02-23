<?php
/**
 * Price Compare Service — Cross-provider price comparison engine
 *
 * Fetches pricing from all providers for a canonical product,
 * determines best price per period, calculates margin vs WHMCS sell price.
 *
 * @package    AioSSL\Service
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Service;

use WHMCS\Database\Capsule;

class PriceCompareService
{
    /** @var array Provider slug → display name */
    private const PROVIDER_NAMES = [
        'nicsrs'      => 'NicSRS',
        'gogetssl'    => 'GoGetSSL',
        'thesslstore' => 'TheSSLStore',
        'ssl2buy'     => 'SSL2Buy',
    ];

    /** @var array Provider code columns in mapping table */
    private const CODE_COLUMNS = [
        'nicsrs'      => 'nicsrs_code',
        'gogetssl'    => 'gogetssl_code',
        'thesslstore' => 'thesslstore_code',
        'ssl2buy'     => 'ssl2buy_code',
    ];

    // ─── Compare by Canonical ID ───────────────────────────────────

    /**
     * Compare prices across providers for a canonical product
     *
     * @param string $canonicalId
     * @return array ['canonical' => ..., 'providers' => [...], 'best' => [...], 'whmcs_price' => ...]
     */
    public function compare(string $canonicalId): array
    {
        $mapping = Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $canonicalId)
            ->first();

        if (!$mapping) {
            return ['canonical' => null, 'providers' => [], 'best' => []];
        }

        $providerPrices = [];
        $periods = [];

        foreach (self::CODE_COLUMNS as $slug => $col) {
            $code = $mapping->$col ?? null;
            if (!$code) continue;

            $product = Capsule::table('mod_aio_ssl_products')
                ->where('provider_slug', $slug)
                ->where('product_code', $code)
                ->first();

            if (!$product) continue;

            $priceData = json_decode($product->price_data ?? '{}', true) ?: [];
            $basePrices = $priceData['base'] ?? $priceData ?? [];

            $normalized = $this->normalizePricePeriods($basePrices);
            $providerPrices[$slug] = [
                'name'    => self::PROVIDER_NAMES[$slug] ?? $slug,
                'code'    => $code,
                'product' => $product->product_name,
                'prices'  => $normalized,
                'san_price' => $priceData['san'] ?? null,
            ];

            foreach (array_keys($normalized) as $p) {
                $periods[$p] = true;
            }
        }

        ksort($periods);
        $best = $this->findBestPrices($providerPrices, array_keys($periods));

        // Get WHMCS sell price (if linked)
        $whmcsPrice = $this->getWhmcsSellPrice($canonicalId);

        return [
            'canonical' => [
                'id'              => $mapping->canonical_id,
                'name'            => $mapping->canonical_name,
                'vendor'          => $mapping->vendor,
                'validation_type' => $mapping->validation_type,
                'product_type'    => $mapping->product_type,
            ],
            'providers' => $providerPrices,
            'periods'   => array_keys($periods),
            'best'      => $best,
            'whmcs_price' => $whmcsPrice,
        ];
    }

    /**
     * Find best (cheapest) price per period across providers
     */
    private function findBestPrices(array $providerPrices, array $periods): array
    {
        $best = [];
        foreach ($periods as $period) {
            $cheapest = null;
            $cheapestSlug = null;
            foreach ($providerPrices as $slug => $data) {
                $price = $data['prices'][$period] ?? null;
                if ($price !== null && ($cheapest === null || $price < $cheapest)) {
                    $cheapest = $price;
                    $cheapestSlug = $slug;
                }
            }
            $best[$period] = [
                'provider' => $cheapestSlug,
                'price'    => $cheapest,
                'name'     => self::PROVIDER_NAMES[$cheapestSlug] ?? $cheapestSlug,
            ];
        }
        return $best;
    }

    /**
     * Normalize price periods to months: {"12": X, "24": Y, ...}
     * Handles different provider formats
     */
    private function normalizePricePeriods(array $prices): array
    {
        $normalized = [];

        foreach ($prices as $key => $value) {
            if (!is_numeric($value) || $value <= 0) continue;

            // Handle keys like "price012", "price024", "1year", "12", etc.
            $months = $this->parseMonthsFromKey($key);
            if ($months > 0) {
                $normalized[$months] = round((float)$value, 2);
            }
        }

        // If no valid periods found, try flat price as 12 months
        if (empty($normalized) && count($prices) === 1) {
            $val = reset($prices);
            if (is_numeric($val) && $val > 0) {
                $normalized[12] = round((float)$val, 2);
            }
        }

        ksort($normalized);
        return $normalized;
    }

    private function parseMonthsFromKey(string $key): int
    {
        $k = strtolower(trim($key));

        // "price012", "price024" → 12, 24
        if (preg_match('/price0?(\d+)/', $k, $m)) {
            return (int)$m[1];
        }
        // "1year", "2year" → 12, 24
        if (preg_match('/(\d+)\s*year/', $k, $m)) {
            return (int)$m[1] * 12;
        }
        // Pure numeric: "12", "24", "36"
        if (is_numeric($k)) {
            $n = (int)$k;
            return $n <= 10 ? $n * 12 : $n; // 1-10 = years, >10 = months
        }
        return 0;
    }

    // ─── WHMCS Sell Price ──────────────────────────────────────────

    /**
     * Get WHMCS product sell price linked to this canonical product
     */
    private function getWhmcsSellPrice(string $canonicalId): ?array
    {
        try {
            $product = Capsule::table('tblproducts')
                ->where('servertype', 'aio_ssl')
                ->where('configoption1', $canonicalId)
                ->first();

            if (!$product) return null;

            $pricing = Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('relid', $product->id)
                ->where('currency', 1) // Default currency
                ->first();

            if (!$pricing) return null;

            return [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'annually'     => (float)($pricing->annually ?? 0),
                'biennially'   => (float)($pricing->biennially ?? 0),
                'triennially'  => (float)($pricing->triennially ?? 0),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    // ─── Bulk Comparison (All mapped products) ─────────────────────

    /**
     * Get comparison data for all mapped canonical products
     *
     * @return array of comparison results
     */
    public function compareAll(): array
    {
        $canonicals = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get();

        $results = [];
        foreach ($canonicals as $c) {
            $comparison = $this->compare($c->canonical_id);
            if (!empty($comparison['providers'])) {
                $results[] = $comparison;
            }
        }

        return $results;
    }

    /**
     * Export all comparisons as CSV
     *
     * @return string CSV content
     */
    public function exportCsv(): string
    {
        $comparisons = $this->compareAll();
        $lines = ["Canonical ID,Product Name,Vendor,Type,Period (months),NicSRS,GoGetSSL,TheSSLStore,SSL2Buy,Best Price,Best Provider,WHMCS Sell Price,Margin"];

        foreach ($comparisons as $comp) {
            $c = $comp['canonical'];
            foreach ($comp['periods'] as $period) {
                $prices = [];
                foreach (['nicsrs', 'gogetssl', 'thesslstore', 'ssl2buy'] as $slug) {
                    $prices[] = $comp['providers'][$slug]['prices'][$period] ?? '';
                }
                $best = $comp['best'][$period] ?? [];
                $whmcs = $comp['whmcs_price'];
                $sellPrice = '';
                $margin = '';
                if ($whmcs && $period == 12 && $whmcs['annually'] > 0) {
                    $sellPrice = $whmcs['annually'];
                    if ($best['price']) {
                        $margin = round($sellPrice - $best['price'], 2);
                    }
                }

                $line = implode(',', [
                    $c['id'], '"' . $c['name'] . '"', $c['vendor'],
                    strtoupper($c['validation_type']), $period,
                    ...$prices,
                    $best['price'] ?? '', $best['name'] ?? '',
                    $sellPrice, $margin,
                ]);
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}