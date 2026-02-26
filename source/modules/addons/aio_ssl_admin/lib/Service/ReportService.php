<?php
/**
 * Report Service — Query data from mod_aio_ssl_orders ONLY
 *
 * Data source: mod_aio_ssl_orders (includes both new AIO orders AND claimed legacy orders)
 * Legacy orders NOT yet claimed are NOT included in reports.
 *
 * CURRENCY:
 * - Revenue from tblhosting = VND including 10% VAT
 * - Provider cost from mod_aio_ssl_products.price_data = USD
 * - Profit = revenueVndToUsd(revenue) - cost
 *
 * @package    AioSSL\Service
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Helper\CurrencyHelper;

class ReportService
{
    /** @var CurrencyHelper */
    private $currency;

    /** @var array Provider display names */
    private const PROVIDER_NAMES = [
        'aio' => 'AIO', 'nicsrs' => 'NicSRS', 'gogetssl' => 'GoGetSSL',
        'thesslstore' => 'TheSSLStore', 'ssl2buy' => 'SSL2Buy',
    ];

    /** @var array Known SSL brand patterns */
    private const BRAND_PATTERNS = [
        'Sectigo'    => ['sectigo', 'comodo', 'positivessl', 'essentialssl', 'instantssl', 'premiumssl'],
        'DigiCert'   => ['digicert', 'secure site', 'securesite'],
        'GeoTrust'   => ['geotrust', 'truebusiness', 'true business', 'quickssl'],
        'Thawte'     => ['thawte', 'ssl123', 'ssl web server'],
        'RapidSSL'   => ['rapidssl'],
        'AlphaSSL'   => ['alphassl'],
        'GlobalSign' => ['globalsign'],
        'Certum'     => ['certum'],
        'GoGetSSL'   => ['gogetssl', 'domain ssl'],
    ];

    public function __construct(?CurrencyHelper $currency = null)
    {
        $this->currency = $currency ?? new CurrencyHelper();
    }

    // ═══════════════════════════════════════════════════════════════
    // §1. BASE QUERY — mod_aio_ssl_orders only
    // ═══════════════════════════════════════════════════════════════

    /**
     * Build base query joining mod_aio_ssl_orders with hosting, products, clients
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function baseQuery()
    {
        return Capsule::table('mod_aio_ssl_orders as o')
            ->join('tblhosting as h', 'o.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->leftJoin('tblclients as c', 'h.userid', '=', 'c.id');
    }

    /**
     * Apply common filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->where('h.regdate', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('h.regdate', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['provider'])) {
            $query->where('o.provider_slug', $filters['provider']);
        }
        if (!empty($filters['status'])) {
            $query->where('o.status', $filters['status']);
        }
    }

    /**
     * Collect orders as array
     *
     * @return array Each: [order_id, provider, product_code, product_name, status,
     *               domain, configdata, order_date, end_date, service_date,
     *               revenue_vnd, billingcycle, client_name, company]
     */
    private function collectOrders(array $filters = []): array
    {
        try {
            $q = $this->baseQuery()->select([
                'o.id as order_id',
                'o.provider_slug as provider',
                'o.product_code',
                Capsule::raw('COALESCE(p.name, o.certtype, o.product_code) as product_name'),
                'o.status',
                'o.domain',
                'o.configdata',
                'o.created_at as order_date',
                'o.end_date',
                'h.regdate as service_date',
                Capsule::raw('COALESCE(h.firstpaymentamount, h.amount, 0) as revenue_vnd'),
                'h.billingcycle',
                Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                'c.companyname as company',
            ]);

            $this->applyFilters($q, $filters);

            return $q->get()->map(function ($r) { return (array)$r; })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // §2. REVENUE BY PROVIDER
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array ['providers' => [...], 'totals' => [...]]
     */
    public function getRevenueByProvider(array $filters = []): array
    {
        $orders = $this->collectOrders($filters);
        $grouped = [];

        foreach ($orders as $o) {
            $slug = $o['provider'] ?? 'unknown';
            if (!isset($grouped[$slug])) {
                $grouped[$slug] = ['orders' => 0, 'revenue_vnd' => 0, 'cost_usd' => 0];
            }
            $grouped[$slug]['orders']++;
            $grouped[$slug]['revenue_vnd'] += (float)($o['revenue_vnd'] ?? 0);
            $grouped[$slug]['cost_usd'] += $this->lookupOrderCost($o);
        }

        $providers = [];
        $totals = ['orders' => 0, 'revenue_vnd' => 0, 'revenue_usd' => 0, 'cost_usd' => 0, 'profit_usd' => 0];

        foreach ($grouped as $slug => $d) {
            $revUsd = $this->currency->revenueVndToUsd($d['revenue_vnd']);
            $profit = $revUsd - $d['cost_usd'];
            $margin = $revUsd > 0 ? ($profit / $revUsd) * 100 : 0;

            $providers[] = [
                'slug' => $slug, 'name' => self::PROVIDER_NAMES[$slug] ?? ucfirst($slug),
                'orders' => $d['orders'], 'revenue_vnd' => $d['revenue_vnd'],
                'revenue_usd' => round($revUsd, 2), 'cost_usd' => round($d['cost_usd'], 2),
                'profit_usd' => round($profit, 2), 'margin' => round($margin, 1),
            ];

            $totals['orders'] += $d['orders'];
            $totals['revenue_vnd'] += $d['revenue_vnd'];
            $totals['revenue_usd'] += $revUsd;
            $totals['cost_usd'] += $d['cost_usd'];
        }

        $totals['revenue_vnd'] = round($totals['revenue_vnd'], 0);
        $totals['revenue_usd'] = round($totals['revenue_usd'], 2);
        $totals['cost_usd'] = round($totals['cost_usd'], 2);
        $totals['profit_usd'] = round($totals['revenue_usd'] - $totals['cost_usd'], 2);
        $totals['margin'] = $totals['revenue_usd'] > 0
            ? round(($totals['profit_usd'] / $totals['revenue_usd']) * 100, 1) : 0;

        usort($providers, function ($a, $b) { return $b['revenue_usd'] <=> $a['revenue_usd']; });

        return ['providers' => $providers, 'totals' => $totals];
    }

    /**
     * Revenue chart data for Chart.js
     */
    public function getRevenueChartData(array $filters = []): array
    {
        $result = $this->getRevenueByProvider($filters);
        $labels = $revenue = $cost = $bgColors = [];
        $colors = [
            'nicsrs' => '#1890ff', 'gogetssl' => '#52c41a',
            'thesslstore' => '#722ed1', 'ssl2buy' => '#fa8c16', 'aio' => '#13c2c2',
        ];

        foreach ($result['providers'] as $p) {
            $labels[] = $p['name'];
            $revenue[] = $p['revenue_usd'];
            $cost[] = $p['cost_usd'];
            $bgColors[] = $colors[$p['slug']] ?? '#8c8c8c';
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'cost' => $cost, 'colors' => $bgColors];
    }

    // ═══════════════════════════════════════════════════════════════
    // §3. PROFIT ANALYSIS
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array ['orders' => [...], 'summary' => [...], 'currency_info' => [...]]
     */
    public function getProfitReport(array $filters = []): array
    {
        $orders = $this->collectOrders($filters);
        $results = [];
        $totalRevVnd = $totalRevUsd = $totalCost = 0;

        foreach ($orders as $o) {
            $revVnd = (float)($o['revenue_vnd'] ?? 0);
            $revUsd = $this->currency->revenueVndToUsd($revVnd);
            $cost = $this->lookupOrderCost($o);
            $profit = $revUsd - $cost;
            $margin = $revUsd > 0 ? ($profit / $revUsd) * 100 : 0;

            $totalRevVnd += $revVnd;
            $totalRevUsd += $revUsd;
            $totalCost += $cost;

            $results[] = [
                'order_id' => $o['order_id'], 'provider' => $o['provider'],
                'product_name' => $o['product_name'] ?? '', 'product_code' => $o['product_code'] ?? '',
                'service_date' => $o['service_date'] ?? $o['order_date'] ?? '',
                'client_name' => trim($o['client_name'] ?? ''),
                'revenue_vnd' => $revVnd, 'revenue_usd' => round($revUsd, 2),
                'cost_usd' => round($cost, 2), 'profit_usd' => round($profit, 2),
                'margin' => round($margin, 1), 'billingcycle' => $o['billingcycle'] ?? '',
            ];
        }

        $totalProfit = $totalRevUsd - $totalCost;
        $overallMargin = $totalRevUsd > 0 ? ($totalProfit / $totalRevUsd) * 100 : 0;

        return [
            'orders' => $results,
            'summary' => [
                'order_count' => count($results),
                'total_revenue_vnd' => round($totalRevVnd, 0),
                'total_vat_vnd' => round($this->currency->calculateVatAmount($totalRevVnd), 0),
                'total_revenue_vnd_no_vat' => round($this->currency->removeVat($totalRevVnd), 0),
                'total_revenue_usd' => round($totalRevUsd, 2),
                'total_cost_usd' => round($totalCost, 2),
                'total_profit_usd' => round($totalProfit, 2),
                'total_profit_vnd' => round($this->currency->toVnd($totalProfit), 0),
                'profit_margin' => round($overallMargin, 1),
            ],
            'currency_info' => $this->currency->getInfo(),
        ];
    }

    /**
     * Profit grouped by period for chart
     */
    public function getProfitByPeriod(string $groupBy = 'month', array $filters = []): array
    {
        $orders = $this->collectOrders($filters);
        $grouped = [];

        foreach ($orders as $o) {
            $date = $o['service_date'] ?? $o['order_date'] ?? '';
            if (empty($date) || $date === '0000-00-00') continue;
            $ts = strtotime($date);
            if (!$ts) continue;

            $key = match ($groupBy) {
                'day' => date('Y-m-d', $ts), 'week' => date('Y-\WW', $ts),
                'quarter' => date('Y', $ts) . '-Q' . ceil(date('n', $ts) / 3),
                'year' => date('Y', $ts), default => date('Y-m', $ts),
            };

            if (!isset($grouped[$key])) $grouped[$key] = ['revenue_vnd' => 0, 'cost_usd' => 0];
            $grouped[$key]['revenue_vnd'] += (float)($o['revenue_vnd'] ?? 0);
            $grouped[$key]['cost_usd'] += $this->lookupOrderCost($o);
        }

        ksort($grouped);
        $labels = $revenue = $cost = $profit = [];

        foreach ($grouped as $period => $d) {
            $labels[] = $period;
            $revUsd = $this->currency->revenueVndToUsd($d['revenue_vnd']);
            $revenue[] = round($revUsd, 2);
            $cost[] = round($d['cost_usd'], 2);
            $profit[] = round($revUsd - $d['cost_usd'], 2);
        }

        return compact('labels', 'revenue', 'cost', 'profit');
    }

    // ═══════════════════════════════════════════════════════════════
    // §4. PRODUCT PERFORMANCE
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array ['products' => [...], 'summary' => [...]]
     */
    public function getProductPerformance(array $filters = []): array
    {
        $orders = $this->collectOrders($filters);
        $grouped = [];
        $domainHistory = [];

        foreach ($orders as $o) {
            $key = ($o['product_name'] ?? 'Unknown') . '|' . ($o['provider'] ?? '');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_name' => $o['product_name'] ?? 'Unknown',
                    'product_code' => $o['product_code'] ?? '',
                    'provider' => $o['provider'] ?? '',
                    'total' => 0, 'active' => 0, 'cancelled' => 0, 'pending' => 0,
                    'revenue_vnd' => 0, 'renewals' => 0,
                ];
            }

            $g = &$grouped[$key];
            $g['total']++;
            $g['revenue_vnd'] += (float)($o['revenue_vnd'] ?? 0);

            $status = strtolower($o['status'] ?? '');
            if (in_array($status, ['complete', 'completed', 'issued', 'active'])) $g['active']++;
            elseif (in_array($status, ['cancelled', 'refunded', 'revoked'])) $g['cancelled']++;
            else $g['pending']++;

            // Renewal detection: same domain + same product appearing > 1 time
            $domain = $o['domain'] ?? '';
            if (empty($domain)) $domain = $this->extractDomain($o['configdata'] ?? '');
            if (!empty($domain)) {
                $dk = strtolower($domain) . '|' . $key;
                $domainHistory[$dk] = ($domainHistory[$dk] ?? 0) + 1;
                if ($domainHistory[$dk] > 1) $g['renewals']++;
            }
        }

        $products = [];
        $totalOrders = 0;
        $totalRevenue = $completionSum = $renewalSum = 0;

        foreach ($grouped as $d) {
            $revUsd = $this->currency->revenueVndToUsd($d['revenue_vnd']);
            $completion = $d['total'] > 0 ? ($d['active'] / $d['total']) * 100 : 0;
            $renewal = $d['total'] > 0 ? ($d['renewals'] / $d['total']) * 100 : 0;

            $products[] = [
                'product_name' => $d['product_name'], 'product_code' => $d['product_code'],
                'provider' => $d['provider'], 'validation_type' => $this->detectValidationType($d['product_name']),
                'total_orders' => $d['total'], 'active_count' => $d['active'],
                'cancelled_count' => $d['cancelled'], 'pending_count' => $d['pending'],
                'revenue_vnd' => $d['revenue_vnd'], 'revenue_usd' => round($revUsd, 2),
                'completion_rate' => round($completion, 1), 'renewal_rate' => round($renewal, 1),
            ];

            $totalOrders += $d['total'];
            $totalRevenue += $revUsd;
            $completionSum += $completion;
            $renewalSum += $renewal;
        }

        usort($products, function ($a, $b) { return $b['total_orders'] <=> $a['total_orders']; });
        $count = count($products);

        return [
            'products' => $products,
            'summary' => [
                'total_products' => $count, 'total_orders' => $totalOrders,
                'total_revenue_usd' => round($totalRevenue, 2),
                'avg_completion' => $count > 0 ? round($completionSum / $count, 1) : 0,
                'avg_renewal' => $count > 0 ? round($renewalSum / $count, 1) : 0,
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // §5. REVENUE BY BRAND
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array ['brands' => [...], 'totals' => [...]]
     */
    public function getRevenueByBrand(array $filters = []): array
    {
        $orders = $this->collectOrders($filters);
        $grouped = [];

        foreach ($orders as $o) {
            $brand = $this->detectBrand($o['product_name'] ?? '', $o['product_code'] ?? '');
            if (!isset($grouped[$brand])) $grouped[$brand] = ['orders' => 0, 'revenue_vnd' => 0];
            $grouped[$brand]['orders']++;
            $grouped[$brand]['revenue_vnd'] += (float)($o['revenue_vnd'] ?? 0);
        }

        $totalRevUsd = $totalRevVnd = 0;
        $brands = [];

        foreach ($grouped as $brand => $d) {
            $revUsd = $this->currency->revenueVndToUsd($d['revenue_vnd']);
            $totalRevUsd += $revUsd;
            $totalRevVnd += $d['revenue_vnd'];
            $brands[] = [
                'brand' => $brand, 'orders' => $d['orders'],
                'revenue_vnd' => $d['revenue_vnd'], 'revenue_usd' => round($revUsd, 2),
                'share_pct' => 0,
            ];
        }

        foreach ($brands as &$b) {
            $b['share_pct'] = $totalRevUsd > 0 ? round(($b['revenue_usd'] / $totalRevUsd) * 100, 1) : 0;
        }
        usort($brands, function ($a, $b) { return $b['revenue_usd'] <=> $a['revenue_usd']; });

        return [
            'brands' => $brands,
            'totals' => [
                'total_brands' => count($brands),
                'total_orders' => array_sum(array_column($brands, 'orders')),
                'total_revenue_vnd' => round($totalRevVnd, 0),
                'total_revenue_usd' => round($totalRevUsd, 2),
            ],
        ];
    }

    /**
     * Brand chart data for doughnut
     */
    public function getBrandChartData(array $filters = []): array
    {
        $result = $this->getRevenueByBrand($filters);
        $labels = $data = [];
        $colors = ['#1890ff', '#52c41a', '#722ed1', '#fa8c16', '#ff4d4f', '#13c2c2', '#faad14', '#eb2f96', '#2f54eb'];

        foreach ($result['brands'] as $b) {
            $labels[] = $b['brand'];
            $data[] = $b['revenue_usd'];
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => array_slice($colors, 0, count($labels))];
    }

    // ═══════════════════════════════════════════════════════════════
    // §6. EXPIRY FORECAST — mod_aio_ssl_orders only
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array ['buckets' => [...], 'details' => [...], 'total' => int]
     */
    public function getExpiryForecast(array $filters = []): array
    {
        $buckets = ['7' => 0, '30' => 0, '60' => 0, '90' => 0];
        $details = [];
        $now = time();

        try {
            $orders = Capsule::table('mod_aio_ssl_orders as o')
                ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
                ->leftJoin('tblclients as c', 'h.userid', '=', 'c.id')
                ->whereIn('o.status', ['Completed', 'Issued', 'Active', 'complete'])
                ->select([
                    'o.id as order_id', 'o.provider_slug as provider', 'o.domain',
                    'o.end_date', 'o.configdata',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                ])
                ->get();

            foreach ($orders as $o) {
                // Prefer end_date column, fallback to configdata parsing
                $expiry = $o->end_date ?: $this->extractExpiry($o->configdata ?? '');
                if (!$expiry) continue;

                $expiryTs = strtotime($expiry);
                if (!$expiryTs || $expiryTs < $now) continue;

                $daysLeft = (int)(($expiryTs - $now) / 86400);
                if ($daysLeft > 90) continue;

                if ($daysLeft <= 7) $buckets['7']++;
                elseif ($daysLeft <= 30) $buckets['30']++;
                elseif ($daysLeft <= 60) $buckets['60']++;
                else $buckets['90']++;

                if (count($details) < 100) {
                    $domain = $o->domain ?: $this->extractDomain($o->configdata ?? '');
                    $details[] = [
                        'order_id' => $o->order_id, 'source_table' => 'mod_aio_ssl_orders',
                        'provider' => $o->provider ?? '', 'domain' => $domain ?: '—',
                        'client_name' => trim($o->client_name ?? ''), 'expiry' => $expiry,
                        'days_left' => $daysLeft,
                    ];
                }
            }
        } catch (\Exception $e) {}

        usort($details, function ($a, $b) { return $a['days_left'] - $b['days_left']; });
        $total = $buckets['7'] + $buckets['30'] + $buckets['60'] + $buckets['90'];

        return ['buckets' => $buckets, 'details' => $details, 'total' => $total];
    }

    // ═══════════════════════════════════════════════════════════════
    // §7. QUICK STATS (for index page)
    // ═══════════════════════════════════════════════════════════════

    public function getQuickStats(): array
    {
        $thisMonth = ['date_from' => date('Y-m-01')];
        $revenue = $this->getRevenueByProvider($thisMonth);
        $totals = $revenue['totals'] ?? [];
        $expiry = $this->getExpiryForecast();
        $topProvider = !empty($revenue['providers']) ? $revenue['providers'][0] : null;

        return [
            'total_revenue_usd' => $totals['revenue_usd'] ?? 0,
            'total_profit_usd' => $totals['profit_usd'] ?? 0,
            'profit_margin' => $totals['margin'] ?? 0,
            'order_count' => $totals['orders'] ?? 0,
            'expiring_30d' => ($expiry['buckets']['7'] ?? 0) + ($expiry['buckets']['30'] ?? 0),
            'expiring_7d' => $expiry['buckets']['7'] ?? 0,
            'top_provider' => $topProvider['name'] ?? '—',
            'top_provider_orders' => $topProvider['orders'] ?? 0,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // §8. CSV EXPORT
    // ═══════════════════════════════════════════════════════════════

    public function exportCsv(string $type, array $filters = []): array
    {
        $rate = $this->currency->getRate();
        $csv = "# Exchange Rate: 1 USD = " . number_format($rate, 0) . " VND | VAT: 10% | Generated: " . date('Y-m-d H:i:s') . "\n";
        $csv .= "# Data source: mod_aio_ssl_orders (AIO orders only)\n";

        switch ($type) {
            case 'revenue':
                $data = $this->getRevenueByProvider($filters);
                $csv .= "Provider,Orders,Revenue (USD),Revenue (VND),Cost (USD),Profit (USD),Margin %\n";
                foreach ($data['providers'] as $p) {
                    $csv .= implode(',', [$p['name'], $p['orders'], $p['revenue_usd'], round($p['revenue_vnd']), $p['cost_usd'], $p['profit_usd'], $p['margin']]) . "\n";
                }
                break;

            case 'profit':
                $data = $this->getProfitReport($filters);
                $csv .= "Order ID,Date,Product,Provider,Revenue (VND),Revenue (USD),Cost (USD),Profit (USD),Margin %\n";
                foreach ($data['orders'] as $o) {
                    $csv .= implode(',', [$o['order_id'], $o['service_date'], '"' . str_replace('"', '""', $o['product_name']) . '"', $o['provider'], round($o['revenue_vnd']), $o['revenue_usd'], $o['cost_usd'], $o['profit_usd'], $o['margin']]) . "\n";
                }
                break;

            case 'products':
                $data = $this->getProductPerformance($filters);
                $csv .= "#,Product,Provider,Type,Orders,Revenue (USD),Completion %,Renewal %\n";
                foreach ($data['products'] as $i => $p) {
                    $csv .= implode(',', [$i + 1, '"' . str_replace('"', '""', $p['product_name']) . '"', $p['provider'], $p['validation_type'], $p['total_orders'], $p['revenue_usd'], $p['completion_rate'], $p['renewal_rate']]) . "\n";
                }
                break;

            case 'brands':
                $data = $this->getRevenueByBrand($filters);
                $csv .= "#,Brand,Orders,Revenue (USD),Market Share %\n";
                foreach ($data['brands'] as $i => $b) {
                    $csv .= implode(',', [$i + 1, '"' . $b['brand'] . '"', $b['orders'], $b['revenue_usd'], $b['share_pct']]) . "\n";
                }
                break;

            case 'expiry':
                $data = $this->getExpiryForecast($filters);
                $csv .= "Domain,Provider,Client,Expiry Date,Days Left\n";
                foreach ($data['details'] as $d) {
                    $csv .= implode(',', [$d['domain'], $d['provider'], '"' . str_replace('"', '""', $d['client_name']) . '"', $d['expiry'], $d['days_left']]) . "\n";
                }
                break;

            default:
                return ['csv' => '', 'filename' => 'empty.csv'];
        }

        $period = $filters['date_from'] ?? 'all';
        return ['csv' => $csv, 'filename' => "aio_ssl_{$type}_{$period}_" . date('Ymd') . '.csv'];
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lookup cost from mod_aio_ssl_products by product_code + provider
     */
    private function lookupOrderCost(array $order): float
    {
        $productCode = $order['product_code'] ?? '';
        $provider = $order['provider'] ?? '';
        $billingCycle = $order['billingcycle'] ?? 'Annually';

        if (empty($productCode)) return 0;

        try {
            $product = Capsule::table('mod_aio_ssl_products')
                ->where('product_code', $productCode)
                ->where('provider_slug', $provider)
                ->first();

            if ($product && !empty($product->price_data)) {
                $cost = $this->currency->extractCostFromPriceData($product->price_data, $billingCycle);
                if ($cost !== null) return $cost;
            }
        } catch (\Exception $e) {}

        return 0;
    }

    /**
     * Extract expiry date from configdata JSON
     * Handles all provider formats since claimed orders have normalized configdata
     */
    private function extractExpiry(?string $configdata): ?string
    {
        if (empty($configdata)) return null;

        $cfg = json_decode($configdata, true);
        if (!$cfg || !is_array($cfg)) return null;

        // AIO normalized format (after claim/migration)
        return $cfg['end_date'] ?? $cfg['endDate'] ?? $cfg['valid_till']
            ?? $cfg['CertificateEndDate'] ?? $cfg['expire_date'] ?? null;
    }

    /**
     * Extract domain from configdata
     */
    private function extractDomain(?string $configdata): string
    {
        if (empty($configdata)) return '';
        $cfg = json_decode($configdata, true);
        if (!$cfg || !is_array($cfg)) return '';

        return $cfg['domain'] ?? $cfg['CommonName']
            ?? ($cfg['domains'][0]['domainName'] ?? $cfg['domains'][0] ?? '') ?: '';
    }

    private function detectBrand(string $productName, string $productCode = ''): string
    {
        $search = strtolower($productName . ' ' . $productCode);
        foreach (self::BRAND_PATTERNS as $brand => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($search, $pattern) !== false) return $brand;
            }
        }
        return 'Other';
    }

    private function detectValidationType(string $productName): string
    {
        $lower = strtolower($productName);
        if (strpos($lower, ' ev ') !== false || strpos($lower, 'extended validation') !== false) return 'EV';
        if (strpos($lower, ' ov ') !== false || strpos($lower, 'organization validation') !== false
            || strpos($lower, 'truebusiness') !== false || strpos($lower, 'secure site') !== false) return 'OV';
        return 'DV';
    }
}