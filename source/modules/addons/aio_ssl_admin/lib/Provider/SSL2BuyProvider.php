<?php
/**
 * SSL2Buy Provider — Full API implementation for AIO SSL Admin
 *
 * Tier: limited (no reissue/renew/revoke/cancel/download via API)
 *
 * API Reference: SSL2Buy API Docs (dev.ssl2buy.com)
 * Auth: PartnerEmail + ApiKey injected into every request body
 * Content-Type: application/json
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │ ORDER SERVICE  /orderservice/order/                             │
 * │  getbalance, getproductprice, placeorder, validateorder,       │
 * │  getsslconfigurationlink, getorderlist,                        │
 * │  getsubscriptionordershistory                                  │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ QUERY SERVICE  /queryservice/                                  │
 * │  {brand}/getorderdetails, {brand}/resendapprovalemail,         │
 * │  globalsign/globalsignsubscriptionorderdetail,                 │
 * │  comodo/comodosubscriptionorderdetail,                         │
 * │  symantec/digicertsubscriptionorderdetail,                     │
 * │  prime/primesubscriptionorderdetail,                           │
 * │  acme/GetAcmeOrderDetail, acme/PurchaseAdditionalDomain        │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ PRODUCT SERVICE /queryservice/Product/                         │
 * │  GetProductList                                                │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * @package    AioSSL\Provider
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Provider;

use AioSSL\Core\AbstractProvider;
use AioSSL\Core\NormalizedProduct;
use AioSSL\Core\UnsupportedOperationException;

class SSL2BuyProvider extends AbstractProvider
{
    /** @var string Production API base URL */
    private const API_URL = 'https://api.ssl2buy.com';

    /** @var string Demo/test mode base URL (same host, behavior toggled by test credentials) */
    private const DEMO_API_URL = 'https://api.ssl2buy.com';

    /**
     * Brand → query service route mapping
     * Used for getOrderDetails, resendApprovalEmail, subscriptionOrderDetail
     */
    private const BRAND_QUERY_ROUTES = [
        'Comodo'     => 'comodo',
        'Sectigo'    => 'comodo',      // Sectigo uses Comodo route
        'GlobalSign' => 'globalsign',
        'AlphaSSL'   => 'globalsign',  // AlphaSSL is GlobalSign sub-brand
        'Symantec'   => 'symantec',
        'DigiCert'   => 'symantec',    // DigiCert acquired Symantec, same route
        'GeoTrust'   => 'symantec',
        'Thawte'     => 'symantec',
        'RapidSSL'   => 'symantec',
        'Prime'      => 'prime',
        'PrimeSSL'   => 'prime',
        'Certera'    => 'comodo',      // Certera → Comodo route
    ];

    /**
     * Brand → subscription detail endpoint mapping
     * Each brand has its own specific subscription detail endpoint
     */
    private const BRAND_SUBSCRIPTION_ROUTES = [
        'comodo'     => 'queryservice/comodo/comodosubscriptionorderdetail',
        'globalsign' => 'queryservice/globalsign/globalsignsubscriptionorderdetail',
        'symantec'   => 'queryservice/symantec/digicertsubscriptionorderdetail',
        'prime'      => 'queryservice/prime/primesubscriptionorderdetail',
    ];

    /**
     * Static product catalog — SSL2Buy Product Table (official)
     * Source: https://dev.ssl2buy.com/product-table/
     *
     * Used as fallback when GetProductList API is unavailable.
     * Schema: code, name, brand, validation, type, wildcard, san, min_years, max_years
     *
     * Total: 96 products across 9 brands
     */
    private const PRODUCT_CATALOG = [

        // ═══════════════════════════════════════════════════════════
        // AlphaSSL (2 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 101, 'name' => 'AlphaSSL Standard Certificate',  'brand' => 'AlphaSSL',   'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 102, 'name' => 'AlphaSSL Wildcard Certificate',  'brand' => 'AlphaSSL',   'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],

        // ═══════════════════════════════════════════════════════════
        // GlobalSign (8 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 103, 'name' => 'GlobalSign Domain SSL',                        'brand' => 'GlobalSign', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 104, 'name' => 'GlobalSign Domain Wildcard SSL',               'brand' => 'GlobalSign', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 105, 'name' => 'GlobalSign Organization SSL',                  'brand' => 'GlobalSign', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 106, 'name' => 'GlobalSign Domain SSL + SAN/UCC',              'brand' => 'GlobalSign', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 107, 'name' => 'GlobalSign Organization Wildcard SSL (FLEX)',   'brand' => 'GlobalSign', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 108, 'name' => 'GlobalSign Organization SSL + SAN/UCC (FLEX)', 'brand' => 'GlobalSign', 'validation' => 'ov', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 109, 'name' => 'GlobalSign EV SSL',                            'brand' => 'GlobalSign', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 110, 'name' => 'GlobalSign EV SSL + SAN/UCC',                  'brand' => 'GlobalSign', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],

        // ═══════════════════════════════════════════════════════════
        // PrimeSSL (8 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 201, 'name' => 'PrimeSSL DV Certificate',                    'brand' => 'Prime', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 202, 'name' => 'PrimeSSL DV Wildcard Certificate',           'brand' => 'Prime', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 204, 'name' => 'PrimeSSL Multi-Domain Wildcard Certificate', 'brand' => 'Prime', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 205, 'name' => 'PrimeSSL Multi-Domain Certificate',          'brand' => 'Prime', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 208, 'name' => 'PrimeSSL OV Certificate',                    'brand' => 'Prime', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 209, 'name' => 'PrimeSSL OV Wildcard Certificate',           'brand' => 'Prime', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 210, 'name' => 'PrimeSSL EV Certificate',                    'brand' => 'Prime', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 211, 'name' => 'PrimeSSL EV Multi-Domain Certificate',       'brand' => 'Prime', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],

        // ═══════════════════════════════════════════════════════════
        // Comodo (21 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 301, 'name' => 'Comodo Positive SSL',                      'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 302, 'name' => 'Comodo Positive Wildcard SSL',             'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 303, 'name' => 'Comodo UCC/SAN/Multi-Domain SSL',          'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 305, 'name' => 'Comodo Instant SSL Pro',                   'brand' => 'Comodo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 307, 'name' => 'Comodo Premium SSL',                       'brand' => 'Comodo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 308, 'name' => 'Comodo Premium Wildcard SSL',              'brand' => 'Comodo', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 311, 'name' => 'Comodo EV SSL',                            'brand' => 'Comodo', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 313, 'name' => 'Comodo UCC Certificate',                   'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 314, 'name' => 'Comodo Essential SSL',                     'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 315, 'name' => 'Comodo Essential Wildcard SSL',            'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 316, 'name' => 'Comodo Code Signing Certificate',          'brand' => 'Comodo', 'validation' => 'ov', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 317, 'name' => 'Comodo Wildcard + SAN Certificate',        'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 318, 'name' => 'Comodo Wildcard + SAN Certificate',        'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 319, 'name' => 'Comodo Positive EV SSL',                   'brand' => 'Comodo', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 321, 'name' => 'Comodo CPAC / SMIME Standard',             'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'smime',    'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 2],
        ['code' => 322, 'name' => 'Comodo CPAC / SMIME Enterprise',           'brand' => 'Comodo', 'validation' => 'ov', 'type' => 'smime',    'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 2],
        ['code' => 331, 'name' => 'Comodo EV CodeSign',                       'brand' => 'Comodo', 'validation' => 'ev', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 410, 'name' => 'Comodo EV SSL Multi Domain',               'brand' => 'Comodo', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 411, 'name' => 'Comodo PositiveSSL MultiDomain Certificate','brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 412, 'name' => 'Comodo Multi-Domain + Wildcard SAN',       'brand' => 'Comodo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],

        // ═══════════════════════════════════════════════════════════
        // Sectigo (34 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 351, 'name' => 'Sectigo Positive SSL',                               'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 352, 'name' => 'Sectigo Positive Wildcard SSL',                      'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 354, 'name' => 'Sectigo Instant SSL',                                'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 355, 'name' => 'Sectigo Instant SSL Pro',                            'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 357, 'name' => 'Sectigo Premium SSL',                                'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 358, 'name' => 'Sectigo Premium Wildcard SSL',                       'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 360, 'name' => 'Sectigo EV SSL',                                     'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 361, 'name' => 'Sectigo UCC Certificate',                            'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 362, 'name' => 'Sectigo Essential SSL',                              'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 363, 'name' => 'Sectigo Essential Wildcard SSL',                     'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 364, 'name' => 'Sectigo Code Signing Certificate',                   'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 365, 'name' => 'Sectigo Wildcard + SAN Certificate',                 'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 366, 'name' => 'Sectigo Positive SSL + Multi-Domain + Wildcard SAN', 'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 367, 'name' => 'Sectigo Positive EV SSL',                            'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 368, 'name' => 'Sectigo Positive EV MultiDomain SSL',                'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 369, 'name' => 'Sectigo Personal Authentication',                    'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'smime',    'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 370, 'name' => 'Sectigo EV SSL Multi Domain',                        'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 371, 'name' => 'Sectigo PositiveSSL MultiDomain Certificate',        'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 373, 'name' => 'Sectigo Personal Authentication Enterprise',         'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'smime',    'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 2],
        ['code' => 374, 'name' => 'Sectigo Enterprise SSL',                             'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 375, 'name' => 'Sectigo Enterprise Pro',                             'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 376, 'name' => 'Sectigo Enterprise Pro Wildcard',                    'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 377, 'name' => 'Sectigo DV Multi-Domain SAN + Wildcard SAN',         'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 378, 'name' => 'Sectigo OV UCC SAN Certificate',                     'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 379, 'name' => 'Sectigo OV Multi-Domain SAN + Wildcard SAN',         'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'multi',    'wildcard' => true,  'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 380, 'name' => 'Sectigo Enterprise Pro EV SSL',                      'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 381, 'name' => 'Sectigo Enterprise Pro EV Multi Domain SSL',         'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 5],
        ['code' => 382, 'name' => 'Sectigo DV SSL',                                     'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 383, 'name' => 'Sectigo DV SSL Wildcard',                            'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 384, 'name' => 'Sectigo OV SSL',                                     'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 385, 'name' => 'Sectigo OV SSL Wildcard',                            'brand' => 'Sectigo', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 5],
        ['code' => 386, 'name' => 'Sectigo EV CodeSign',                                'brand' => 'Sectigo', 'validation' => 'ev', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 401, 'name' => 'Sectigo ACME Certificate – DV',                      'brand' => 'Sectigo', 'validation' => 'dv', 'type' => 'acme',     'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],

        // ═══════════════════════════════════════════════════════════
        // RapidSSL (2 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 501, 'name' => 'RapidSSL Certificate',          'brand' => 'RapidSSL', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 502, 'name' => 'RapidSSL Wildcard Certificate', 'brand' => 'RapidSSL', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],

        // ═══════════════════════════════════════════════════════════
        // GeoTrust (7 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 503, 'name' => 'GeoTrust QuickSSL Premium Certificate',              'brand' => 'GeoTrust', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 504, 'name' => 'GeoTrust True BusinessID Certificate',               'brand' => 'GeoTrust', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 505, 'name' => 'GeoTrust True BusinessID Wildcard Certificate',      'brand' => 'GeoTrust', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 506, 'name' => 'GeoTrust True BusinessID with EV Certificate',       'brand' => 'GeoTrust', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 507, 'name' => 'GeoTrust True BusinessID with Multi-Domain',         'brand' => 'GeoTrust', 'validation' => 'ov', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 3],
        ['code' => 508, 'name' => 'GeoTrust True BusinessID EV With Multi Domain',      'brand' => 'GeoTrust', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 3],
        ['code' => 510, 'name' => 'GeoTrust QuickSSL Premium Wildcard Certificate',     'brand' => 'GeoTrust', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],

        // ═══════════════════════════════════════════════════════════
        // Thawte (5 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 512, 'name' => 'Thawte SSL Web Server',   'brand' => 'Thawte', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 513, 'name' => 'Thawte SSL123',           'brand' => 'Thawte', 'validation' => 'dv', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 514, 'name' => 'Thawte SSL Wildcard',     'brand' => 'Thawte', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 515, 'name' => 'Thawte EV SSL',           'brand' => 'Thawte', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 517, 'name' => 'Thawte SSL123 Wildcard',  'brand' => 'Thawte', 'validation' => 'dv', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],

        // ═══════════════════════════════════════════════════════════
        // DigiCert (12 products)
        // ═══════════════════════════════════════════════════════════
        ['code' => 519, 'name' => 'DigiCert Secure Site Pro',                       'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 520, 'name' => 'DigiCert Secure Site Pro with EV (SGC)',          'brand' => 'DigiCert', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 522, 'name' => 'DigiCert EV Code Sign',                          'brand' => 'DigiCert', 'validation' => 'ev', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 524, 'name' => 'DigiCert Secure Site Wildcard',                   'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 525, 'name' => 'DigiCert Secure Site Multi-Domain SSL',           'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 3],
        ['code' => 526, 'name' => 'DigiCert Secure Site with EV Multi-Domain SSL',   'brand' => 'DigiCert', 'validation' => 'ev', 'type' => 'multi',    'wildcard' => false, 'san' => true,  'min_years' => 1, 'max_years' => 3],
        ['code' => 527, 'name' => 'DigiCert Code Sign',                              'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'codesign', 'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 528, 'name' => 'DigiCert Basic OV SSL',                           'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 529, 'name' => 'DigiCert Basic EV SSL',                           'brand' => 'DigiCert', 'validation' => 'ev', 'type' => 'single',   'wildcard' => false, 'san' => false, 'min_years' => 1, 'max_years' => 3],
        ['code' => 530, 'name' => 'DigiCert Basic OV Wildcard SSL',                  'brand' => 'DigiCert', 'validation' => 'ov', 'type' => 'wildcard',  'wildcard' => true,  'san' => false, 'min_years' => 1, 'max_years' => 3],
    ];

    // ─── Identity (required by ProviderInterface) ────────────────

    public function getSlug(): string  { return 'ssl2buy'; }
    public function getName(): string  { return 'SSL2Buy'; }
    public function getTier(): string  { return 'limited'; }

    // ─── Base URL ──────────────────────────────────────────────────

    protected function getBaseUrl(): string
    {
        return ($this->apiMode === 'sandbox' || $this->apiMode === 'test')
            ? self::DEMO_API_URL : self::API_URL;
    }

    // ─── Capabilities ──────────────────────────────────────────────

    public function getCapabilities(): array
    {
        return [
            'order',              // placeOrder
            'validate',           // validateOrder
            'status',             // getOrderStatus (brand-routed)
            'config_link',        // getConfigurationLink
            'resend_approval',    // resendApprovalEmail (brand-routed)
            'balance',            // getBalance
            'order_list',         // getOrderList (paginated)
            'product_list',       // getProductList (API-based)
            'product_price',      // getProductPrice (per product)
            'subscription_history', // getSubscriptionOrdersHistory
            'subscription_detail',  // per-brand subscription detail
            'acme_detail',        // ACME order detail
            'acme_purchase',      // ACME additional domain purchase
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Make authenticated API call
     * SSL2Buy auth: PartnerEmail + ApiKey injected into request body
     *
     * @param string $endpoint  e.g. '/orderservice/order/getbalance'
     * @param array  $data      Request body (auth auto-injected)
     * @return array ['code' => int, 'body' => string, 'decoded' => array|null]
     */
    protected function apiCall(string $endpoint, array $data = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;

        $data['PartnerEmail'] = $this->getCredential('partner_email');
        $data['ApiKey'] = $this->getCredential('api_key');

        return $this->httpPostJson($url, $data);
    }

    // ═══════════════════════════════════════════════════════════════
    // CONNECTION TEST
    // ═══════════════════════════════════════════════════════════════

    /**
     * Test API connection via getbalance
     * Implementation plan: §2.3.2
     */
    public function testConnection(): array
    {
        try {
            $balance = $this->getBalance();
            return [
                'success' => true,
                'message' => 'SSL2Buy connected. Balance: $' . number_format($balance['balance'], 2),
                'balance' => $balance['balance'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'balance' => null];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // BALANCE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get partner balance
     * API: POST /orderservice/order/getbalance
     * Implementation plan: §2.3.9
     *
     * Request: { PartnerEmail, ApiKey }
     * Response: { Balance: decimal, StatusCode: 0|-1, APIError: {...} }
     *
     * @return array ['balance' => float, 'currency' => string]
     * @throws \RuntimeException on API failure
     */
    public function getBalance(): array
    {
        $response = $this->apiCall('/orderservice/order/getbalance');

        if ($response['code'] === 200 && isset($response['decoded'])) {
            $data = $response['decoded'];

            // Check StatusCode first
            $statusCode = (int)($data['StatusCode'] ?? $data['statusCode'] ?? -1);
            if ($statusCode !== 0) {
                throw new \RuntimeException(
                    'SSL2Buy getBalance error: ' . ($data['APIError']['ErrorMessage'] ?? 'Unknown error')
                );
            }

            $balance = $data['Balance'] ?? $data['balance'] ?? 0;
            return ['balance' => (float)$balance, 'currency' => 'USD'];
        }

        throw new \RuntimeException('SSL2Buy API unreachable (HTTP ' . $response['code'] . ')');
    }

    // ═══════════════════════════════════════════════════════════════
    // PRODUCTS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Fetch product list from SSL2Buy API
     * API: POST /queryservice/Product/GetProductList
     *
     * NEW: SSL2Buy now has a GetProductList endpoint that returns
     * all products with ProductId, ProductName, BrandName, MinYear, MaxYear,
     * MaxSAN, MaxWildcardSAN. Use this as primary source, fallback to
     * static PRODUCT_CATALOG if API fails.
     *
     * Implementation plan: §2.3.3
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array
    {
        $products = [];
        $totalApiCalls = 0;

        // Try API-based product list first
        $apiProducts = $this->fetchProductListFromApi();
        $totalApiCalls++;

        // Use API products if available, otherwise fallback to static catalog
        $catalog = !empty($apiProducts) ? $apiProducts : self::PRODUCT_CATALOG;

        foreach ($catalog as $catalogItem) {
            try {
                $priceResult = $this->fetchProductPricing(
                    $catalogItem['code'],
                    $catalogItem['max_years'] ?? 3
                );

                $priceData = $priceResult['pricing'];
                $actualMaxYears = $priceResult['max_years'];
                $totalApiCalls += $priceResult['api_calls'];

                $catalogItemCopy = $catalogItem;
                if ($actualMaxYears > 0) {
                    $catalogItemCopy['max_years'] = $actualMaxYears;
                }

                $products[] = $this->normalizeProduct($catalogItemCopy, $priceData);

                // Rate limit: 200ms between products
                usleep(200000);

            } catch (\Exception $e) {
                $this->log('warning', "SSL2Buy: Failed pricing for #{$catalogItem['code']} ({$catalogItem['name']}): " . $e->getMessage());
                $products[] = $this->normalizeProduct($catalogItem, ['base' => []]);
            }
        }

        $this->log('info', "SSL2Buy: Fetched " . count($products) . " products ({$totalApiCalls} API calls)");
        return $products;
    }

    /**
     * Fetch product list from GetProductList API
     * API: POST /queryservice/Product/GetProductList
     *
     * Response: {
     *   ProductLists: [{ ProductId, ProductName, BrandName, MinYear, MaxYear, MaxSAN, MaxWildcardSAN }],
     *   StatusCode: 0|-1
     * }
     *
     * @return array[] Normalized catalog items or empty on failure
     */
    private function fetchProductListFromApi(): array
    {
        try {
            $response = $this->apiCall('/queryservice/Product/GetProductList');

            if ($response['code'] !== 200 || !isset($response['decoded'])) {
                return [];
            }

            $data = $response['decoded'];
            $statusCode = (int)($data['StatusCode'] ?? -1);

            if ($statusCode !== 0 || empty($data['ProductLists'])) {
                return [];
            }

            $catalog = [];
            foreach ($data['ProductLists'] as $p) {
                $productId = (int)($p['ProductId'] ?? $p['ProductID'] ?? 0);
                $productName = $p['ProductName'] ?? $p['Product Name'] ?? '';
                $brandName = $p['BrandName'] ?? '';
                $minYear = (int)($p['MinYear'] ?? 1);
                $maxYear = (int)($p['MaxYear'] ?? 1);
                $maxSan = (int)($p['MaxSAN'] ?? 0);
                $maxWildcardSan = (int)($p['MaxWildcardSAN'] ?? 0);

                if ($productId === 0 || empty($productName)) {
                    continue;
                }

                // Infer validation type from name
                $validation = 'dv';
                $nameLower = strtolower($productName);
                if (strpos($nameLower, ' ev ') !== false || strpos($nameLower, 'extended') !== false) {
                    $validation = 'ev';
                } elseif (strpos($nameLower, ' ov ') !== false || strpos($nameLower, 'organization') !== false
                    || strpos($nameLower, 'instant') !== false || strpos($nameLower, 'premium') !== false
                    || strpos($nameLower, 'business') !== false || strpos($nameLower, 'true business') !== false) {
                    $validation = 'ov';
                }

                // Infer type
                $isWildcard = (strpos($nameLower, 'wildcard') !== false);
                $isSan = ($maxSan > 0 || strpos($nameLower, 'multi') !== false || strpos($nameLower, 'ucc') !== false || strpos($nameLower, 'san') !== false);
                $isCodeSign = (strpos($nameLower, 'codesign') !== false || strpos($nameLower, 'code sign') !== false);

                $type = 'single';
                if ($isCodeSign) $type = 'codesign';
                elseif ($isWildcard && $isSan) $type = 'multi';
                elseif ($isWildcard) $type = 'wildcard';
                elseif ($isSan) $type = 'multi';

                $catalog[] = [
                    'code'             => $productId,
                    'name'             => $productName,
                    'brand'            => $brandName,
                    'validation'       => $validation,
                    'type'             => $type,
                    'wildcard'         => $isWildcard,
                    'san'              => $isSan,
                    'max_san'          => $maxSan,
                    'max_wildcard_san' => $maxWildcardSan,
                    'min_years'        => $minYear,
                    'max_years'        => $maxYear,
                ];
            }

            $this->log('info', "SSL2Buy: GetProductList returned " . count($catalog) . " products");
            return $catalog;

        } catch (\Exception $e) {
            $this->log('warning', 'SSL2Buy: GetProductList failed, using static catalog: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product price for a specific product/year
     * API: POST /orderservice/order/getproductprice
     *
     * Request: { PartnerEmail, ApiKey, ProductID: int, Year: int }
     * Response: { ProductName, Year, Price, AddDomainPrice, StatusCode, APIError }
     *
     * @param int $productCode
     * @param int $year
     * @return array ['price' => float, 'add_domain_price' => float, 'product_name' => string] or empty on error
     */
    public function getProductPrice(int $productCode, int $year): array
    {
        try {
            $response = $this->apiCall('/orderservice/order/getproductprice', [
                'ProductID' => $productCode,
                'Year'      => $year,
            ]);

            if ($response['code'] !== 200 || !isset($response['decoded'])) {
                return [];
            }

            $data = $response['decoded'];
            $statusCode = (int)($data['StatusCode'] ?? -1);

            if ($statusCode !== 0) {
                return [];
            }

            return [
                'price'            => (float)($data['Price'] ?? $data['price'] ?? 0),
                'add_domain_price' => (float)($data['AddDomainPrice'] ?? $data['addDomainPrice'] ?? 0),
                'product_name'     => $data['ProductName'] ?? $data['productName'] ?? '',
                'year'             => (int)($data['Year'] ?? $year),
            ];

        } catch (\Exception $e) {
            $this->log('warning', "SSL2Buy: getProductPrice failed for #{$productCode} yr={$year}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch pricing for all valid periods of a product
     *
     * IMPORTANT: Normalized format uses MONTHS as keys and flat float as values
     * to match other providers (NicSRS, GoGetSSL, TheSSLStore):
     *
     * {
     *   "base":         { "12": 8.00, "24": 14.00, "36": 20.80 },
     *   "san":          { "12": 3.50, "24": 6.00 },
     *   "wildcard_san": {}
     * }
     *
     * @param int $productCode
     * @param int $maxYears
     * @return array ['pricing' => [...], 'max_years' => int, 'api_calls' => int]
     */
    private function fetchProductPricing(int $productCode, int $maxYears): array
    {
        $pricing = ['base' => [], 'san' => [], 'wildcard_san' => []];
        $actualMaxYears = 0;
        $apiCalls = 0;

        for ($year = 1; $year <= $maxYears; $year++) {
            $result = $this->getProductPrice($productCode, $year);
            $apiCalls++;

            if (!empty($result) && $result['price'] > 0) {
                $months = (string)($year * 12);  // 1→"12", 2→"24", 3→"36"

                // Base price: flat float keyed by months
                $pricing['base'][$months] = (float)$result['price'];

                // SAN (additional domain) price: same structure
                if ($result['add_domain_price'] > 0) {
                    $pricing['san'][$months] = (float)$result['add_domain_price'];
                }

                $actualMaxYears = $year;
            }

            usleep(100000); // 100ms rate limit between pricing calls
        }

        return [
            'pricing'   => $pricing,
            'max_years' => $actualMaxYears,
            'api_calls' => $apiCalls,
        ];
    }

    /**
     * Fetch pricing for a specific product (required by ProviderInterface)
     *
     * Returns pricing keyed by months with flat float values:
     * { "12": 8.00, "24": 14.00, "36": 20.80 }
     *
     * @param string $productCode Provider-specific product code
     * @return array Pricing data keyed by months
     */
    public function fetchPricing(string $productCode): array
    {
        $pricing = [];

        // Try up to 5 years
        for ($year = 1; $year <= 5; $year++) {
            $result = $this->getProductPrice((int)$productCode, $year);
            if (!empty($result) && $result['price'] > 0) {
                $months = (string)($year * 12);
                $pricing[$months] = (float)$result['price'];
            }
            usleep(100000); // 100ms rate limit
        }

        return $pricing;
    }

    /**
     * Normalize a catalog item + pricing into NormalizedProduct
     */
    private function normalizeProduct(array $catalogItem, array $priceData): NormalizedProduct
    {
        $type = $catalogItem['type'] ?? 'single';
        $maxYears = $catalogItem['max_years'] ?? 1;
        $maxDomains = 1;

        if (($catalogItem['san'] ?? false) && ($catalogItem['max_san'] ?? 0) > 0) {
            $maxDomains = $catalogItem['max_san'];
        }

        return new NormalizedProduct([
            'product_code'     => (string)$catalogItem['code'],
            'product_name'     => $catalogItem['name'],
            'vendor'           => $this->mapBrandToVendor($catalogItem['brand'] ?? 'Unknown'),
            'validation_type'  => $catalogItem['validation'] ?? 'dv',
            'product_type'     => $type,
            'support_wildcard' => (bool)($catalogItem['wildcard'] ?? false),
            'support_san'      => (bool)($catalogItem['san'] ?? false),
            'max_domains'      => $maxDomains,
            'max_years'        => $maxYears,
            'min_years'        => $catalogItem['min_years'] ?? 1,
            'price_data'       => $priceData,
            'extra_data'       => [
                'ssl2buy_code'      => (int)$catalogItem['code'],
                'brand_name'        => $catalogItem['brand'] ?? '',
                'query_route'       => self::BRAND_QUERY_ROUTES[$catalogItem['brand'] ?? ''] ?? 'comodo',
                'max_san'           => $catalogItem['max_san'] ?? 0,
                'max_wildcard_san'  => $catalogItem['max_wildcard_san'] ?? 0,
            ],
        ]);
    }

    /**
     * Map SSL2Buy brand name to display vendor
     */
    private function mapBrandToVendor(string $brand): string
    {
        $map = [
            'Comodo'     => 'Sectigo',
            'Sectigo'    => 'Sectigo',
            'GlobalSign' => 'GlobalSign',
            'AlphaSSL'   => 'GlobalSign',
            'Symantec'   => 'DigiCert',
            'DigiCert'   => 'DigiCert',
            'GeoTrust'   => 'DigiCert',
            'Thawte'     => 'DigiCert',
            'RapidSSL'   => 'DigiCert',
            'Prime'      => 'PrimeSSL',
            'PrimeSSL'   => 'PrimeSSL',
            'Certera'    => 'Certera',
        ];
        return $map[$brand] ?? $brand;
    }

    // ═══════════════════════════════════════════════════════════════
    // ORDER LIFECYCLE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate order before placing
     * API: POST /orderservice/order/validateorder
     * Implementation plan: §2.3.5
     *
     * Request: {
     *   PartnerEmail, ApiKey, ProductCode: int, Year: int,
     *   IsRenew: bool, AddDomains: int, WildcardSAN: int,
     *   PartnerOrderID: string (unique per request)
     * }
     * Response: { StatusCode: 0|-1, APIError: { ErrorNumber, ErrorField, ErrorMessage } }
     */
    public function validateOrder(array $params): array
    {
        try {
            $data = [
                'ProductCode'    => (int)($params['product_code'] ?? 0),
                'Year'           => (int)($params['year'] ?? $params['period'] ?? 1),
                'PartnerOrderID' => $params['partner_order_id'] ?? $this->generatePartnerOrderId(),
            ];

            // Optional fields
            if (!empty($params['is_renew'])) {
                $data['IsRenew'] = true;
            }
            if (!empty($params['add_domains'])) {
                $data['AddDomains'] = (int)$params['add_domains'];
            }
            if (!empty($params['wildcard_san'])) {
                $data['WildcardSAN'] = (int)$params['wildcard_san'];
            }

            $response = $this->apiCall('/orderservice/order/validateorder', $data);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $respData = $response['decoded'];
                $statusCode = (int)($respData['StatusCode'] ?? -1);

                if ($statusCode === 0) {
                    return ['valid' => true, 'errors' => []];
                }

                $errorMsg = $respData['APIError']['ErrorMessage']
                    ?? $respData['Message'] ?? 'Validation failed';
                $errorField = $respData['APIError']['ErrorField'] ?? '';

                return [
                    'valid'  => false,
                    'errors' => [($errorField ? "[{$errorField}] " : '') . $errorMsg],
                ];
            }

            return ['valid' => false, 'errors' => ['HTTP ' . $response['code']]];

        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Place a new SSL order
     * API: POST /orderservice/order/placeorder
     * Implementation plan: §2.3.4
     *
     * Request: {
     *   PartnerEmail, ApiKey, ProductCode: int, Year: int,
     *   IsRenew: bool, AddDomains: int, WildcardSAN: int,
     *   PartnerOrderID: string,
     *   ProvisioningOption: string (CodeSign only: Token_Std|Token_Int|Token_Exp|HSM)
     * }
     * Response: {
     *   ConfigurationLink: string, Pin: string, OrderNumber: int,
     *   StatusCode: 0|-1, APIError: {...}
     * }
     */
    public function placeOrder(array $params): array
    {
        $data = [
            'ProductCode'    => (int)($params['product_code'] ?? 0),
            'Year'           => (int)($params['year'] ?? $params['period'] ?? 1),
            'PartnerOrderID' => $params['partner_order_id'] ?? $this->generatePartnerOrderId(),
        ];

        // Optional fields
        if (!empty($params['is_renew'])) {
            $data['IsRenew'] = true;
        }
        if (!empty($params['add_domains'])) {
            $data['AddDomains'] = (int)$params['add_domains'];
        }
        if (!empty($params['wildcard_san'])) {
            $data['WildcardSAN'] = (int)$params['wildcard_san'];
        }
        if (!empty($params['provisioning_option'])) {
            $data['ProvisioningOption'] = $params['provisioning_option'];
        }

        try {
            $response = $this->apiCall('/orderservice/order/placeorder', $data);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $respData = $response['decoded'];
                $statusCode = (int)($respData['StatusCode'] ?? -1);

                if ($statusCode === 0) {
                    return [
                        'success'    => true,
                        'order_id'   => (string)($respData['OrderNumber'] ?? ''),
                        'config_link'=> $respData['ConfigurationLink'] ?? '',
                        'pin'        => $respData['Pin'] ?? '',
                        'message'    => 'Order placed successfully.',
                    ];
                }

                $error = $this->extractApiError($respData);
                return ['success' => false, 'order_id' => '', 'message' => $error];
            }

            return ['success' => false, 'order_id' => '', 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'order_id' => '', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get SSL Configuration Link for an order
     * API: POST /orderservice/order/getsslconfigurationlink
     * Implementation plan: §2.3.7
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int }
     * Response: { ConfigurationLink, PIN, StatusCode, APIError }
     *
     * This is the PRIMARY management method for limited tier —
     * allows end users to configure their cert via SSL2Buy's web interface.
     */
    public function getConfigurationLink(string $orderId): array
    {
        try {
            $response = $this->apiCall('/orderservice/order/getsslconfigurationlink', [
                'OrderNumber' => (int)$orderId,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                if ($statusCode === 0) {
                    return [
                        'success' => true,
                        'link'    => $data['ConfigurationLink'] ?? '',
                        'pin'     => $data['PIN'] ?? $data['Pin'] ?? '',
                    ];
                }

                return ['success' => false, 'link' => '', 'pin' => '',
                    'message' => $this->extractApiError($data)];
            }

            return ['success' => false, 'link' => '', 'pin' => '',
                'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'link' => '', 'pin' => '', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get order status — BRAND-ROUTED
     * API: POST /queryservice/{brand}/getorderdetails
     * Implementation plan: §2.3.6 (C8)
     *
     * CRITICAL: SSL2Buy routes order detail queries through brand-specific endpoints.
     * The brand must be known from the original order.
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int }
     * Response: Brand-specific (varies by Comodo/GlobalSign/Symantec/Prime)
     *
     * @param string $orderId    SSL2Buy OrderNumber
     * @return array Normalized status
     */
    public function getOrderStatus(string $orderId): array
    {
        // Try to resolve brand from existing order data
        $brand = $this->resolveBrandFromOrder($orderId);

        if (!empty($brand)) {
            // Brand known → single direct call
            return $this->queryOrderByRoute($orderId, $this->resolveBrandRoute($brand));
        }

        // Brand unknown (new import) → try all routes
        return $this->probeAllRoutes($orderId);
    }

    /**
     * Try all brand routes to find the order
     *
     * SSL2Buy has only 4 query routes. We try each one until we get
     * StatusCode=0. This is only used for new imports where brand is unknown.
     *
     * Order of probing: comodo first (most common), then others.
     *
     * @param string $orderId
     * @return array
     */
    private function probeAllRoutes(string $orderId): array
    {
        $routes = ['comodo', 'symantec', 'globalsign', 'prime'];
        $lastError = '';

        foreach ($routes as $route) {
            try {
                $response = $this->apiCall("/queryservice/{$route}/getorderdetails", [
                    'OrderNumber' => (int)$orderId,
                ]);

                if ($response['code'] !== 200 || !isset($response['decoded'])) {
                    $lastError = "HTTP {$response['code']} on route {$route}";
                    continue;
                }

                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                // -102 = Invalid Order Number → order doesn't exist on this route
                $errNum = $data['APIError']['ErrorNumber']
                    ?? $data['Errors']['ErrorNumber']
                    ?? 0;

                if ($statusCode !== 0) {
                    // Error codes that mean "order not found on this route" → try next
                    // -100 = Generic exception (SSL2Buy returns this when order doesn't belong to route)
                    // -102 = Invalid or missing Order Number
                    // -105 = Invalid OrderID
                    $skipErrors = [-100, -102, -105];

                    if (in_array((int)$errNum, $skipErrors, true)) {
                        $lastError = $this->extractApiError($data) . " (route: {$route})";
                        continue;
                    }

                    // Real error (e.g. -101 auth failure) → stop probing
                    $lastError = $this->extractApiError($data);
                    break;
                }

                // SUCCESS — found the order on this route
                $result = $this->normalizeOrderStatus($data, $route);

                // Inject the resolved brand route so caller can store it
                $result['_resolved_brand_route'] = $route;
                $result['_resolved_brand_name']  = $this->routeToBrandName($route);

                $this->log('info', "SSL2Buy: Order #{$orderId} found on route '{$route}'");

                return $result;

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        // None of the routes returned the order
        return [
            'success' => false,
            'status'  => 'unknown',
            'message' => "Order #{$orderId} not found on any SSL2Buy brand route. Last error: {$lastError}",
        ];
    }

    /**
     * Query order details on a specific route
     *
     * @param string $orderId
     * @param string $route   Brand route slug (comodo, globalsign, symantec, prime)
     * @return array
     */
    private function queryOrderByRoute(string $orderId, string $route): array
    {
        try {
            $response = $this->apiCall("/queryservice/{$route}/getorderdetails", [
                'OrderNumber' => (int)$orderId,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                if ($statusCode !== 0) {
                    return [
                        'success' => false,
                        'status'  => 'unknown',
                        'message' => $this->extractApiError($data),
                    ];
                }

                return $this->normalizeOrderStatus($data, $route);
            }

            return ['success' => false, 'status' => 'unknown', 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    /**
     * Map route slug back to a brand name for storage
     *
     * @param string $route
     * @return string
     */
    private function routeToBrandName(string $route): string
    {
        $map = [
            'comodo'     => 'Sectigo',
            'globalsign' => 'GlobalSign',
            'symantec'   => 'DigiCert',
            'prime'      => 'PrimeSSL',
        ];
        return $map[$route] ?? ucfirst($route);
    }

    /**
     * Normalize brand-specific order detail response into unified format
     *
     * Maps SSL2Buy's varying response structures to the standard keys
     * that ImportController and other consumers expect:
     *   status, domains[], begin_date, end_date, product_name, product_id, extra
     */
    private function normalizeOrderStatus(array $data, string $route): array
    {
        $status = $data['OrderStatus'] ?? $data['CertificateStatus'] ?? 'unknown';
        $normalizedStatus = $this->mapStatus($status);

        // ── Domains: primary + SANs ──
        $domains = [];
        if (!empty($data['DomainName'])) {
            $domains[] = $data['DomainName'];
        }
        if (!empty($data['AdditionalDomainList'])) {
            foreach ((array)$data['AdditionalDomainList'] as $d) {
                $name = $d['DomainName'] ?? (is_object($d) ? ($d->DomainName ?? '') : '');
                if (!empty($name) && !in_array($name, $domains)) {
                    $domains[] = $name;
                }
            }
        }

        // ── BaseOrderDetails (nested in all brand responses) ──
        $base = $data['BaseOrderDetails'] ?? [];
        $productName = $base['ProductName'] ?? '';
        $productId   = $base['ProductId'] ?? null;

        // ── Build standard result ──
        $result = [
            'success'        => true,
            'status'         => $normalizedStatus,
            'raw_status'     => $status,

            // Standard keys expected by ImportController
            'domains'        => $domains,
            'domain'         => $data['DomainName'] ?? '',
            'begin_date'     => $data['StartDate'] ?? null,
            'end_date'       => $data['EndDate'] ?? null,
            'product_name'   => $productName,
            'product_id'     => $productId,
            'serial_number'  => null, // SSL2Buy doesn't return serial in getorderdetails

            // SSL2Buy-specific
            'validity_period' => (int)($data['ValidityPeriod'] ?? 0),
            'approver_email'  => $data['ApproverEmail'] ?? '',
            'approval_method' => $data['ApprovalMethod'] ?? '',
            'config_link'     => $base['ConfigurationLink'] ?? '',
            'config_pin'      => $base['Pin'] ?? '',
            'order_amount'    => $base['TotalAmount'] ?? $base['ProductAmount'] ?? null,
            'year'            => $base['Year'] ?? null,
            'san_count'       => $base['SAN'] ?? 0,

            // Brand-specific vendor order ID
            'vendor_order_id' => '',

            // Extra: full raw data for resolveProductName() and raw data display
            'extra'          => $data,
        ];

        // ── Brand-specific vendor order IDs ──
        switch ($route) {
            case 'comodo':
                $result['vendor_order_id'] = $data['ComodoOrderNumber'] ?? '';
                break;
            case 'globalsign':
                $result['vendor_order_id'] = $data['GlobalSignOrderID']
                                        ?? $data['GlobalSignOrderNumber'] ?? '';
                break;
            case 'symantec':
                $result['vendor_order_id'] = $data['SymantecOrderNumber']
                                        ?? $data['DigiCertOrderNumber']
                                        ?? $data['DigicertOrderNumber'] ?? '';
                break;
            case 'prime':
                $result['vendor_order_id'] = $data['PrimeSSLOrderNumber'] ?? '';
                break;
        }

        // ── Contacts (if present) ──
        if (isset($data['ContactDetail'])) {
            $result['contact'] = $data['ContactDetail'];
        }
        if (isset($data['OrganizationDetails'])) {
            $result['organization'] = $data['OrganizationDetails'];
        }

        return $result;
    }

    /**
     * Map SSL2Buy status strings to AIO normalized status
     *
     * SSL2Buy API returns UPPERCASE statuses:
     *   LINKPENDING, COMPLETED, CANCELLED, INPROCESS,
     *   WAIT_FOR_APPROVAL, SECURITY_REVIEW
     */
    private function mapStatus(string $status): string
    {
        $map = [
            // SSL2Buy API statuses (from API docs — uppercase)
            'linkpending'         => 'Awaiting Configuration',
            'completed'           => 'Active',
            'cancelled'           => 'Cancelled',
            'inprocess'           => 'Processing',
            'wait_for_approval'   => 'Awaiting Validation',
            'security_review'     => 'Processing',

            // Generic aliases
            'active'              => 'Active',
            'issued'              => 'Active',
            'pending'             => 'Pending',
            'processing'          => 'Processing',
            'expired'             => 'Expired',
            'revoked'             => 'Revoked',
            'rejected'            => 'Cancelled',
            'refunded'            => 'Cancelled',
        ];

        return $map[strtolower(trim($status))] ?? ucfirst(strtolower($status));
    }
    
    // ═══════════════════════════════════════════════════════════════
    // ORDER LIST
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get paginated order list
     * API: POST /orderservice/order/getorderlist
     *
     * Request: { PartnerEmail, ApiKey, PageNo: int, PageSize: int (max 50) }
     * Response: {
     *   OrderList: [{ OrderNumber, OrderDate, SubscriptionYear, ProductName,
     *                  OrderStatus, DomainName, OrderAmount, ExpireOn }],
     *   TotalOrders: int, TotalPages: int,
     *   StatusCode: 0|-1, APIError: {...}
     * }
     *
     * @param int $page     Page number (1-based)
     * @param int $pageSize Items per page (max 50)
     * @return array
     */
    public function getOrderList(int $page = 1, int $pageSize = 50): array
    {
        $pageSize = min($pageSize, 50); // API max is 50

        try {
            $response = $this->apiCall('/orderservice/order/getorderlist', [
                'PageNo'   => $page,
                'PageSize' => $pageSize,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                if ($statusCode !== 0) {
                    return ['success' => false, 'orders' => [],
                        'message' => $this->extractApiError($data)];
                }

                $orders = [];
                foreach (($data['OrderList'] ?? []) as $o) {
                    $orders[] = [
                        'order_number'      => $o['OrderNumber'] ?? '',
                        'order_date'        => $o['OrderDate'] ?? '',
                        'subscription_year' => $o['SubscriptionYear'] ?? 0,
                        'product_name'      => $o['ProductName'] ?? '',
                        'status'            => $this->mapStatus($o['OrderStatus'] ?? ''),
                        'raw_status'        => $o['OrderStatus'] ?? '',
                        'domain'            => $o['DomainName'] ?? '',
                        'amount'            => (float)($o['OrderAmount'] ?? 0),
                        'expire_on'         => $o['ExpireOn'] ?? null,
                    ];
                }

                return [
                    'success'      => true,
                    'orders'       => $orders,
                    'total_orders' => (int)($data['TotalOrders'] ?? 0),
                    'total_pages'  => (int)($data['TotalPages'] ?? 0),
                    'page'         => $page,
                    'page_size'    => $pageSize,
                ];
            }

            return ['success' => false, 'orders' => [], 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'orders' => [], 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SUBSCRIPTION MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get subscription order history
     * API: POST /orderservice/order/getsubscriptionordershistory
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int }
     * Response: { SubscriptionHistory: [{ Pin, CertificateStatus }], StatusCode, Errors }
     *
     * @param string $orderId SSL2Buy OrderNumber
     * @return array
     */
    public function getSubscriptionHistory(string $orderId): array
    {
        try {
            $response = $this->apiCall('/orderservice/order/getsubscriptionordershistory', [
                'OrderNumber' => (int)$orderId,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];

                $errNum = (int)($data['Errors']['ErrorNumber'] ?? 0);
                if ($errNum !== 0) {
                    return ['success' => false, 'history' => [],
                        'message' => $data['Errors']['ErrorMessage'] ?? 'Error'];
                }

                $statusCode = (int)($data['StatusCode'] ?? -1);
                if ($statusCode !== 0) {
                    return ['success' => false, 'history' => [],
                        'message' => 'StatusCode: ' . $statusCode];
                }

                $history = [];
                foreach (($data['SubscriptionHistory'] ?? []) as $idx => $item) {
                    $history[] = [
                        'index'              => $idx,
                        'pin'                => $item['Pin'] ?? $item->Pin ?? '',
                        'certificate_status' => $item['CertificateStatus'] ?? $item->CertificateStatus ?? '',
                    ];
                }

                return ['success' => true, 'history' => $history];
            }

            return ['success' => false, 'history' => [], 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'history' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Get subscription order detail — BRAND-ROUTED
     * API: POST /queryservice/{brand}/{brand}subscriptionorderdetail
     *
     * Each brand has its own subscription detail endpoint and response structure.
     *
     * Request: { PartnerEmail, ApiKey, Pin: string }
     * Response: brand-specific
     *
     * @param string $pin   Subscription PIN
     * @param string $brand Brand name for routing
     * @return array
     */
    public function getSubscriptionDetail(string $pin, string $brand): array
    {
        $route = $this->resolveBrandRoute($brand);

        if (!isset(self::BRAND_SUBSCRIPTION_ROUTES[$route])) {
            return ['success' => false, 'message' => "Unknown brand route: {$route}"];
        }

        $endpoint = '/' . self::BRAND_SUBSCRIPTION_ROUTES[$route];

        try {
            $response = $this->apiCall($endpoint, [
                'Pin' => $pin,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                if ($statusCode === 0) {
                    return ['success' => true, 'data' => $data, 'brand_route' => $route];
                }

                return ['success' => false, 'message' => $this->extractApiError($data)];
            }

            return ['success' => false, 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // DCV (ProviderInterface compliance)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Resend DCV email (required by ProviderInterface)
     * Delegates to brand-routed resendApprovalEmail
     *
     * @param string $orderId SSL2Buy OrderNumber
     * @param string $email   Ignored (SSL2Buy doesn't accept target email)
     * @return array
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array
    {
        $brand = $this->resolveBrandFromOrder($orderId);
        return $this->resendApprovalEmail($orderId, $brand);
    }

    // ═══════════════════════════════════════════════════════════════
    // APPROVAL EMAIL
    // ═══════════════════════════════════════════════════════════════

    /**
     * Resend approval (DCV) email — BRAND-ROUTED
     * API: POST /queryservice/{brand}/resendapprovalemail
     * Implementation plan: §2.3.8
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int }
     * Response: { StatusCode: 0|-1, APIError: {...} }
     *
     * @param string $orderId  SSL2Buy OrderNumber
     * @param string $brand    Brand name for routing
     * @return array
     */
    public function resendApprovalEmail(string $orderId, string $brand = ''): array
    {
        $route = $this->resolveBrandRoute($brand);

        try {
            $response = $this->apiCall("/queryservice/{$route}/resendapprovalemail", [
                'OrderNumber' => (int)$orderId,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                return [
                    'success' => ($statusCode === 0),
                    'message' => ($statusCode === 0)
                        ? 'Approval email resent successfully.'
                        : $this->extractApiError($data),
                ];
            }

            return ['success' => false, 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ACME SUPPORT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get ACME order detail
     * API: POST /queryservice/acme/GetAcmeOrderDetail
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int }
     * Response: {
     *   EABID, EABKey, ServerUrL,
     *   AcmeAccountStatus: [{ ACMEID, AccountStatus, IpAddress, LastActivity, UserAgent }],
     *   Domains: [{ DomainName, TransactionDate, DomainAction }],
     *   StatusCode, Errors
     * }
     *
     * @param string $orderId SSL2Buy OrderNumber
     * @return array
     */
    public function getAcmeOrderDetail(string $orderId): array
    {
        try {
            $response = $this->apiCall('/queryservice/acme/GetAcmeOrderDetail', [
                'OrderNumber' => (int)$orderId,
            ]);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $data = $response['decoded'];
                $statusCode = (int)($data['StatusCode'] ?? -1);

                if ($statusCode === 0) {
                    return [
                        'success'   => true,
                        'eab_id'    => $data['EABID'] ?? '',
                        'eab_key'   => $data['EABKey'] ?? '',
                        'server_url'=> $data['ServerUrL'] ?? '',
                        'accounts'  => $data['AcmeAccountStatus'] ?? [],
                        'domains'   => $data['Domains'] ?? [],
                    ];
                }

                return ['success' => false, 'message' => $this->extractApiError($data)];
            }

            return ['success' => false, 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Purchase additional ACME domains
     * API: POST /queryservice/acme/PurchaseAdditionalDomain
     *
     * Request: { PartnerEmail, ApiKey, OrderNumber: int, AddSAN: int, AddWildcardSAN: int }
     * Response: { StatusCode: 0|-1, Errors: {...} }
     *
     * @param string $orderId        SSL2Buy OrderNumber
     * @param int    $addSan         Number of additional SAN domains
     * @param int    $addWildcardSan Number of additional wildcard SAN domains
     * @return array
     */
    public function purchaseAcmeAdditionalDomains(string $orderId, int $addSan = 0, int $addWildcardSan = 0): array
    {
        if ($addSan <= 0 && $addWildcardSan <= 0) {
            return ['success' => false, 'message' => 'Must specify at least one AddSAN or AddWildcardSAN.'];
        }

        try {
            $data = ['OrderNumber' => (int)$orderId];
            if ($addSan > 0) $data['AddSAN'] = $addSan;
            if ($addWildcardSan > 0) $data['AddWildcardSAN'] = $addWildcardSan;

            $response = $this->apiCall('/queryservice/acme/PurchaseAdditionalDomain', $data);

            if ($response['code'] === 200 && isset($response['decoded'])) {
                $respData = $response['decoded'];
                $errNum = (int)($respData['Errors']['ErrorNumber'] ?? 0);

                if ($errNum !== 0) {
                    return ['success' => false,
                        'message' => $respData['Errors']['ErrorMessage'] ?? 'Error'];
                }

                return ['success' => true, 'message' => 'Additional domains purchased.'];
            }

            return ['success' => false, 'message' => 'HTTP ' . $response['code']];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // UNSUPPORTED OPERATIONS (Limited Tier)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Implementation plan: §2.3.10
     * These operations are NOT available in SSL2Buy's API.
     * Users must use the SSL2Buy portal directly.
     */

    public function downloadCertificate(string $orderId): array
    {
        throw new UnsupportedOperationException(
            'Certificate download is not supported by SSL2Buy API. Please use the SSL2Buy portal or configuration link.'
        );
    }

    public function reissueCertificate(string $orderId, array $params = []): array
    {
        throw new UnsupportedOperationException(
            'Certificate reissue is not supported by SSL2Buy API. Please use the SSL2Buy portal or configuration link.'
        );
    }

    public function renewCertificate(string $orderId, array $params = []): array
    {
        // Renewal in SSL2Buy = new order with IsRenew=true
        // Redirect to placeOrder with renewal flag
        $params['is_renew'] = true;
        return $this->placeOrder($params);
    }

    public function revokeCertificate(string $orderId, string $reason = ''): array
    {
        throw new UnsupportedOperationException(
            'Certificate revocation is not supported by SSL2Buy API. Please contact SSL2Buy support.'
        );
    }

    public function cancelOrder(string $orderId): array
    {
        throw new UnsupportedOperationException(
            'Order cancellation is not supported by SSL2Buy API. Please contact SSL2Buy support.'
        );
    }

    public function getDcvEmails(string $domain): array
    {
        throw new UnsupportedOperationException(
            'DCV email listing is not supported by SSL2Buy API. Approval emails are sent by the Certificate Authority.'
        );
    }

    public function changeDcvMethod(string $orderId, string $method, array $params = []): array
    {
        throw new UnsupportedOperationException(
            'DCV method change is not supported by SSL2Buy API. Please use the configuration link.'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Resolve brand route from stored order data
     *
     * Looks up brand in mod_aio_ssl_orders configdata or falls back
     * to mod_aio_ssl_products extra_data for the product.
     *
     * @param string $orderId
     * @return string Brand name (for routing)
     */
    private function resolveBrandFromOrder(string $orderId): string
    {
        try {
            // Try AIO orders table first
            $order = \WHMCS\Database\Capsule::table('mod_aio_ssl_orders')
                ->where('remote_id', $orderId)
                ->where('provider_slug', 'ssl2buy')
                ->first();

            if ($order) {
                $configdata = json_decode($order->configdata ?? '{}', true) ?: [];
                if (!empty($configdata['brand'])) {
                    return $configdata['brand'];
                }
                if (!empty($configdata['brand_name'])) {
                    return $configdata['brand_name'];
                }

                // Try to get brand from product extra_data
                if (!empty($order->product_code)) {
                    $product = \WHMCS\Database\Capsule::table('mod_aio_ssl_products')
                        ->where('product_code', $order->product_code)
                        ->where('provider_slug', 'ssl2buy')
                        ->first();

                    if ($product && !empty($product->extra_data)) {
                        $extra = json_decode($product->extra_data, true) ?: [];
                        if (!empty($extra['brand_name'])) {
                            return $extra['brand_name'];
                        }
                    }
                }
            }

            // Try legacy tblsslorders
            $legacyOrder = \WHMCS\Database\Capsule::table('tblsslorders')
                ->where('remoteid', $orderId)
                ->where('module', 'ssl2buy')
                ->first();

            if ($legacyOrder) {
                $configdata = json_decode($legacyOrder->configdata ?? '{}', true) ?: [];
                return $configdata['brand'] ?? $configdata['brand_name'] ?? '';
            }

        } catch (\Exception $e) {
            $this->log('warning', "SSL2Buy: Could not resolve brand for order #{$orderId}: " . $e->getMessage());
        }

        return ''; // Will default to 'comodo' in resolveBrandRoute()
    }

    /**
     * Get order status with explicit brand (for internal use / sync service)
     */
    public function getOrderStatusWithBrand(string $orderId, string $brand): array
    {
        $route = $this->resolveBrandRoute($brand);
        return $this->queryOrderByRoute($orderId, $route);
    }

    /**
     * Resolve brand name to query service route
     *
     * @param string $brand Brand name (e.g. 'Comodo', 'GlobalSign', 'DigiCert')
     * @return string Route slug (e.g. 'comodo', 'globalsign', 'symantec', 'prime')
     */
    private function resolveBrandRoute(string $brand): string
    {
        if (empty($brand)) {
            return 'comodo'; // Default fallback
        }

        // Direct match in route map
        if (isset(self::BRAND_QUERY_ROUTES[$brand])) {
            return self::BRAND_QUERY_ROUTES[$brand];
        }

        // Case-insensitive match
        foreach (self::BRAND_QUERY_ROUTES as $key => $route) {
            if (strcasecmp($key, $brand) === 0) {
                return $route;
            }
        }

        // If already a route slug, return as-is
        $validRoutes = ['comodo', 'globalsign', 'symantec', 'prime'];
        if (in_array(strtolower($brand), $validRoutes)) {
            return strtolower($brand);
        }

        return 'comodo'; // Safe fallback
    }

    /**
     * Extract error message from SSL2Buy API response
     */
    private function extractApiError(array $data): string
    {
        // Check APIError structure
        if (!empty($data['APIError']['ErrorMessage'])) {
            $msg = $data['APIError']['ErrorMessage'];
            $field = $data['APIError']['ErrorField'] ?? '';
            return $field ? "[{$field}] {$msg}" : $msg;
        }

        // Check Errors structure (used in some endpoints)
        if (!empty($data['Errors']['ErrorMessage'])) {
            $msg = $data['Errors']['ErrorMessage'];
            $field = $data['Errors']['ErrorField'] ?? '';
            return $field ? "[{$field}] {$msg}" : $msg;
        }

        // Fallback
        return $data['Message'] ?? $data['message'] ?? 'Unknown SSL2Buy API error';
    }

    /**
     * Generate unique PartnerOrderID for API requests
     *
     * @return string Max 50 chars, unique per request
     */
    private function generatePartnerOrderId(): string
    {
        return 'AIO-' . date('ymd-His') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    }
}