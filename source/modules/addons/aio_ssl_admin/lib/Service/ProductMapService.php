<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class ProductMapService
{
    /**
     * Run auto-mapping algorithm for all unmapped products
     *
     * @return array ['mapped'=>int, 'unmapped'=>int]
     */
    public function autoMap(): array
    {
        $unmapped = Capsule::table('mod_aio_ssl_products')
            ->where(function ($q) {
                $q->whereNull('canonical_id')->orWhere('canonical_id', '');
            })
            ->get();

        $mapped = 0;
        $stillUnmapped = 0;

        foreach ($unmapped as $product) {
            $canonicalId = $this->findMatch($product);

            if ($canonicalId) {
                Capsule::table('mod_aio_ssl_products')
                    ->where('id', $product->id)
                    ->update(['canonical_id' => $canonicalId]);

                // Update mapping table
                $column = $product->provider_slug . '_code';
                $allowed = ['nicsrs_code', 'gogetssl_code', 'thesslstore_code', 'ssl2buy_code'];
                if (in_array($column, $allowed)) {
                    Capsule::table('mod_aio_ssl_product_map')
                        ->where('canonical_id', $canonicalId)
                        ->whereNull($column)
                        ->update([$column => $product->product_code]);
                }

                $mapped++;
            } else {
                $stillUnmapped++;
            }
        }

        ActivityLogger::log('auto_mapping', 'product', null, "Auto-mapped {$mapped} products, {$stillUnmapped} unmapped");
        return ['mapped' => $mapped, 'unmapped' => $stillUnmapped];
    }

    /**
     * Find canonical match for a product using 3-layer strategy
     */
    private function findMatch($product): ?string
    {
        // Layer 1: Exact code match
        $columns = ['nicsrs_code', 'gogetssl_code', 'thesslstore_code', 'ssl2buy_code'];
        foreach ($columns as $col) {
            $match = Capsule::table('mod_aio_ssl_product_map')
                ->where($col, $product->product_code)
                ->value('canonical_id');
            if ($match) return $match;
        }

        // Layer 2: Name normalization + fuzzy match
        $normalizedName = $this->normalizeName($product->product_name);
        $canonicals = Capsule::table('mod_aio_ssl_product_map')
            ->where('vendor', $product->vendor)
            ->where('validation_type', $product->validation_type)
            ->get();

        foreach ($canonicals as $canonical) {
            $canonicalNorm = $this->normalizeName($canonical->canonical_name);

            // Exact normalized match
            if ($normalizedName === $canonicalNorm) {
                return $canonical->canonical_id;
            }

            // Fuzzy match (Levenshtein distance < 3)
            if (levenshtein($normalizedName, $canonicalNorm) < 3) {
                return $canonical->canonical_id;
            }
        }

        // Layer 3: No match — return null (admin must map manually)
        return null;
    }

    /**
     * Normalize product name for matching
     *
     * Strip "Certificate", "SSL", trim, lowercase, handle abbreviations
     */
    public function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        // Remove common suffixes/prefixes
        $remove = ['certificate', 'ssl', 'ssl/tls', 'tls', '(', ')', '-', '_'];
        $name = str_replace($remove, ' ', $name);

        // Normalize abbreviations
        $name = preg_replace('/\bdomain\s+validation\b/', 'dv', $name);
        $name = preg_replace('/\borganization\s+validation\b/', 'ov', $name);
        $name = preg_replace('/\bextended\s+validation\b/', 'ev', $name);
        $name = preg_replace('/\bmulti[\s-]*domain\b/', 'multidomain', $name);

        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    /**
     * Create canonical entry from a product
     */
    public function createCanonical(string $productName, string $vendor, string $validationType, string $productType): string
    {
        // Generate canonical_id: vendor-normalized-name
        $slug = strtolower($vendor) . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($productName));
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $existing = Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $slug)
            ->exists();

        if (!$existing) {
            Capsule::table('mod_aio_ssl_product_map')->insert([
                'canonical_id'    => $slug,
                'canonical_name'  => $productName,
                'vendor'          => $vendor,
                'validation_type' => $validationType,
                'product_type'    => $productType,
                'is_active'       => 1,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        return $slug;
    }
}