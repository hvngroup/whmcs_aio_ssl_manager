<?php
/**
 * Product Mapping Service — Auto-map provider products to canonical entries
 *
 * Strategy: exact code match → normalized name → fuzzy (Levenshtein < 3)
 *
 * @package    AioSSL\Service
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class ProductMapService
{
    /** @var array Provider slug → column name in mod_aio_ssl_product_map */
    private const PROVIDER_COLUMNS = [
        'nicsrs'      => 'nicsrs_code',
        'gogetssl'    => 'gogetssl_code',
        'thesslstore' => 'thesslstore_code',
        'ssl2buy'     => 'ssl2buy_code',
    ];

    /** @var array Words to strip during normalization */
    private const STRIP_WORDS = [
        'certificate', 'ssl', 'ssl/tls', 'tls', 'with', 'the', 'for',
        'standard', 'basic', 'premium', 'pro', 'plus', 'lite',
    ];

    // ─── Auto-Mapping ──────────────────────────────────────────────

    /**
     * Run auto-mapping for all unmapped products
     *
     * @return array ['mapped' => int, 'unmapped' => int, 'details' => array]
     */
    public function autoMap(): array
    {
        $results = ['mapped' => 0, 'unmapped' => 0, 'details' => []];

        // Load all canonical entries
        $canonicals = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->get()
            ->toArray();

        // Load all unmapped products
        $products = Capsule::table('mod_aio_ssl_products')
            ->where(function ($q) {
                $q->whereNull('canonical_id')->orWhere('canonical_id', '');
            })
            ->get();

        foreach ($products as $product) {
            $slug = $product->provider_slug;
            $col = self::PROVIDER_COLUMNS[$slug] ?? null;
            if (!$col) continue;

            $match = $this->findMatch($product, $canonicals, $col);

            if ($match) {
                // Update product's canonical_id
                Capsule::table('mod_aio_ssl_products')
                    ->where('id', $product->id)
                    ->update(['canonical_id' => $match['canonical_id']]);

                // Update mapping table
                Capsule::table('mod_aio_ssl_product_map')
                    ->where('canonical_id', $match['canonical_id'])
                    ->update([$col => $product->product_code]);

                $results['mapped']++;
                $results['details'][] = [
                    'product' => $product->product_code,
                    'provider' => $slug,
                    'canonical' => $match['canonical_id'],
                    'method' => $match['method'],
                ];
            } else {
                $results['unmapped']++;
            }
        }

        ActivityLogger::log('auto_map', 'system', '',
            "Auto-mapped {$results['mapped']} products, {$results['unmapped']} unmapped");

        return $results;
    }

    /**
     * Find best canonical match for a product
     */
    private function findMatch(object $product, array $canonicals, string $col): ?array
    {
        $code = $product->product_code;
        $name = $product->product_name;
        $vendor = strtolower($product->vendor ?? '');
        $valType = strtolower($product->validation_type ?? '');

        // Strategy 1: Exact code match
        foreach ($canonicals as $c) {
            if (!empty($c->$col) && $c->$col === $code) {
                return ['canonical_id' => $c->canonical_id, 'method' => 'exact_code'];
            }
        }

        // Strategy 2: Normalized name match
        $normName = $this->normalizeName($name);
        foreach ($canonicals as $c) {
            $normCanonical = $this->normalizeName($c->canonical_name);
            if ($normName === $normCanonical) {
                // Verify vendor + validation match
                if ($this->vendorMatches($vendor, strtolower($c->vendor))
                    && ($valType === '' || $valType === strtolower($c->validation_type))) {
                    return ['canonical_id' => $c->canonical_id, 'method' => 'name_match'];
                }
            }
        }

        // Strategy 3: Fuzzy match (Levenshtein distance < 3)
        $bestMatch = null;
        $bestDist = 999;
        foreach ($canonicals as $c) {
            $normCanonical = $this->normalizeName($c->canonical_name);
            $dist = levenshtein($normName, $normCanonical);
            if ($dist < 3 && $dist < $bestDist) {
                if ($this->vendorMatches($vendor, strtolower($c->vendor))) {
                    $bestMatch = $c;
                    $bestDist = $dist;
                }
            }
        }

        if ($bestMatch) {
            return ['canonical_id' => $bestMatch->canonical_id, 'method' => "fuzzy_lev{$bestDist}"];
        }

        return null;
    }

    // ─── Name Normalization ────────────────────────────────────────

    /**
     * Normalize product name for comparison
     * Strip "Certificate", "SSL", trim, lowercase, handle abbreviations
     */
    public function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));

        // Replace common abbreviations
        $n = str_replace([
            'domain validation', 'organization validation', 'extended validation',
            'multi-domain', 'multidomain', 'multi domain',
            'unified communications', 'code signing',
        ], [
            'dv', 'ov', 'ev',
            'md', 'md', 'md',
            'ucc', 'cs',
        ], $n);

        // Strip noise words
        foreach (self::STRIP_WORDS as $word) {
            $n = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', '', $n);
        }

        // Collapse whitespace, trim
        $n = preg_replace('/\s+/', ' ', trim($n));
        // Remove special chars except alphanumeric and spaces
        $n = preg_replace('/[^a-z0-9 ]/', '', $n);

        return trim($n);
    }

    /**
     * Check if vendor names match (handle aliases)
     */
    private function vendorMatches(string $a, string $b): bool
    {
        if ($a === $b) return true;
        $aliases = [
            'sectigo' => ['comodo', 'positivessl', 'instantssl', 'essentialssl'],
            'digicert' => ['symantec', 'geotrust', 'thawte', 'rapidssl'],
            'globalsign' => ['alphassl'],
        ];
        foreach ($aliases as $primary => $alts) {
            if (($a === $primary || in_array($a, $alts))
                && ($b === $primary || in_array($b, $alts))) {
                return true;
            }
        }
        return false;
    }

    // ─── Manual Mapping ────────────────────────────────────────────

    /**
     * Manually set mapping for a provider product to canonical
     */
    public function setMapping(string $canonicalId, string $providerSlug, string $productCode): bool
    {
        $col = self::PROVIDER_COLUMNS[$providerSlug] ?? null;
        if (!$col) return false;

        // Clear any existing mapping for this product
        Capsule::table('mod_aio_ssl_product_map')
            ->where($col, $productCode)
            ->update([$col => null]);

        // Set new mapping
        Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $canonicalId)
            ->update([$col => $productCode]);

        // Update product canonical_id
        Capsule::table('mod_aio_ssl_products')
            ->where('provider_slug', $providerSlug)
            ->where('product_code', $productCode)
            ->update(['canonical_id' => $canonicalId]);

        ActivityLogger::log('manual_map', 'product', $canonicalId,
            "Mapped {$providerSlug}:{$productCode}");

        return true;
    }

    /**
     * Create new canonical entry from unmatched product
     */
    public function createCanonical(array $data): ?string
    {
        $id = $data['canonical_id'] ?? $this->generateCanonicalId($data);

        Capsule::table('mod_aio_ssl_product_map')->insert([
            'canonical_id'   => $id,
            'canonical_name' => $data['name'] ?? '',
            'vendor'         => $data['vendor'] ?? '',
            'validation_type'=> strtolower($data['validation_type'] ?? 'dv'),
            'product_type'   => strtolower($data['product_type'] ?? 'ssl'),
            'is_active'      => 1,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function generateCanonicalId(array $data): string
    {
        $vendor = strtolower(preg_replace('/[^a-z0-9]/i', '', $data['vendor'] ?? 'unknown'));
        $name = strtolower(preg_replace('/[^a-z0-9]/i', '-', $data['name'] ?? 'cert'));
        $name = trim(preg_replace('/-+/', '-', $name), '-');
        return "{$vendor}-{$name}";
    }

    // ─── Stats ─────────────────────────────────────────────────────

    /**
     * Get mapping statistics
     */
    public function getStats(): array
    {
        $total = Capsule::table('mod_aio_ssl_products')->count();
        $mapped = Capsule::table('mod_aio_ssl_products')
            ->whereNotNull('canonical_id')
            ->where('canonical_id', '!=', '')
            ->count();
        $canonicals = Capsule::table('mod_aio_ssl_product_map')->count();

        return [
            'total_products'   => $total,
            'mapped_products'  => $mapped,
            'unmapped_products'=> $total - $mapped,
            'canonical_entries'=> $canonicals,
            'mapping_rate'     => $total > 0 ? round(($mapped / $total) * 100, 1) : 0,
        ];
    }
}