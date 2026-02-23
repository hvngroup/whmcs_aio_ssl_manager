<?php

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;

class PriceCompareController extends BaseController
{
    public function render(string $action = ''): void
    {
        $canonicalId = $this->input('product', '');

        // Get all canonical products for dropdown
        $canonicals = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get()
            ->toArray();

        $comparison = null;
        if ($canonicalId) {
            $comparison = $this->compareProduct($canonicalId);
        }

        $this->renderTemplate('price_compare.tpl', [
            'canonicals'  => $canonicals,
            'selectedId'  => $canonicalId,
            'comparison'  => $comparison,
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'compare':
                $canonicalId = $this->input('canonical_id');
                if (empty($canonicalId)) {
                    return ['success' => false, 'message' => 'Select a product.'];
                }
                $data = $this->compareProduct($canonicalId);
                return ['success' => true, 'data' => $data];
            case 'export_csv':
                return $this->exportCsv();
            default:
                return parent::handleAjax($action);
        }
    }

    private function compareProduct(string $canonicalId): array
    {
        $map = Capsule::table('mod_aio_ssl_product_map')
            ->where('canonical_id', $canonicalId)
            ->first();

        if (!$map) {
            return ['error' => 'Product not found in mapping.'];
        }

        $providers = [
            'nicsrs'      => $map->nicsrs_code,
            'gogetssl'    => $map->gogetssl_code,
            'thesslstore' => $map->thesslstore_code,
            'ssl2buy'     => $map->ssl2buy_code,
        ];

        $prices = [];
        $periods = ['12', '24', '36'];

        foreach ($providers as $slug => $code) {
            if (empty($code)) {
                $prices[$slug] = null;
                continue;
            }

            $product = Capsule::table('mod_aio_ssl_products')
                ->where('provider_slug', $slug)
                ->where('product_code', $code)
                ->first();

            if ($product && !empty($product->price_data)) {
                $priceData = json_decode($product->price_data, true) ?: [];
                $prices[$slug] = $priceData;
            } else {
                $prices[$slug] = null;
            }
        }

        // Determine best price per period
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
            'canonical'   => $map,
            'prices'      => $prices,
            'bestPrices'  => $bestPrices,
            'periods'     => $periods,
        ];
    }

    private function exportCsv(): array
    {
        // Build CSV of all canonical products with prices
        $maps = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->get();

        $csv = "Canonical ID,Product Name,Vendor,Validation,NicSRS 1Y,GoGetSSL 1Y,TheSSLStore 1Y,SSL2Buy 1Y,Best Provider,Best Price\n";

        foreach ($maps as $map) {
            $comparison = $this->compareProduct($map->canonical_id);
            if (isset($comparison['error'])) continue;

            $best12 = $comparison['bestPrices']['12'] ?? ['price' => '', 'provider' => ''];

            $getNicSRS = $comparison['prices']['nicsrs']['base']['12'] ?? '';
            $getGoGet = $comparison['prices']['gogetssl']['base']['12'] ?? '';
            $getTheSSL = $comparison['prices']['thesslstore']['base']['12'] ?? '';
            $getSSL2Buy = $comparison['prices']['ssl2buy']['base']['12'] ?? '';

            $csv .= implode(',', [
                $map->canonical_id,
                '"' . $map->canonical_name . '"',
                $map->vendor,
                strtoupper($map->validation_type),
                $getNicSRS, $getGoGet, $getTheSSL, $getSSL2Buy,
                $best12['provider'] ?? '', $best12['price'] ?? '',
            ]) . "\n";
        }

        return ['success' => true, 'csv' => $csv, 'filename' => 'aio_ssl_price_comparison_' . date('Ymd') . '.csv'];
    }
}