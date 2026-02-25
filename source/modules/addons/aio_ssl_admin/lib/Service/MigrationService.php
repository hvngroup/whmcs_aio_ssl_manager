<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class MigrationService
{
    /**
     * Legacy module names recognized in tblsslorders.module
     */
    private const LEGACY_MODULES_TBLSSL = [
        'SSLCENTERWHMCS',
        'thesslstore_ssl',
        'thesslstorefullv2',
        'ssl2buy',
    ];

    /**
     * All legacy module identifiers (for validation)
     */
    private const ALL_LEGACY_MODULES = [
        'nicsrs_ssl',
        'SSLCENTERWHMCS',
        'thesslstore_ssl',
        'thesslstorefullv2',
        'ssl2buy',
    ];

    /**
     * Module → provider slug mapping
     */
    private const PROVIDER_MAP = [
        'nicsrs_ssl'         => 'nicsrs',
        'SSLCENTERWHMCS'     => 'gogetssl',
        'thesslstore_ssl'    => 'thesslstore',
        'thesslstorefullv2'  => 'thesslstore',
        'ssl2buy'            => 'ssl2buy',
    ];

    /**
     * Get all legacy orders that can be migrated
     *
     * @return array
     */
    public function getLegacyOrders(): array
    {
        $orders = [];

        // ── 1) NicSRS legacy orders from nicsrs_sslorders ──
        try {
            $nicsrsOrders = Capsule::table('nicsrs_sslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->select([
                    'o.id',
                    'o.userid',
                    'o.serviceid',
                    'o.remoteid',
                    Capsule::raw("'nicsrs_ssl' as module"),
                    'o.certtype',
                    'o.configdata',
                    'o.status',
                    'o.completiondate',
                    Capsule::raw("NULL as authdata"),
                    Capsule::raw("NULL as created_at"),
                    Capsule::raw("NULL as updated_at"),
                    'h.domain',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                    Capsule::raw("'nicsrs_sslorders' as _source_table"),
                ])
                ->orderBy('o.id', 'desc')
                ->get()
                ->toArray();

            // Filter out already claimed
            foreach ($nicsrsOrders as $o) {
                if (!$this->isAlreadyClaimed('nicsrs_sslorders', $o->id)) {
                    $orders[] = $o;
                }
            }
        } catch (\Exception $e) {
        }

        // ── 2) tblsslorders legacy orders (GoGetSSL, TheSSLStore, SSL2Buy) ──
        try {
            $tblsslOrders = Capsule::table('tblsslorders as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->whereIn('o.module', self::LEGACY_MODULES_TBLSSL)
                ->select([
                    'o.id',
                    'o.userid',
                    'o.serviceid',
                    'o.remoteid',
                    'o.module',
                    'o.certtype',
                    'o.configdata',
                    'o.status',
                    'o.completiondate',
                    'o.authdata',
                    'o.created_at',
                    'o.updated_at',
                    'h.domain',
                    Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
                    Capsule::raw("'tblsslorders' as _source_table"),
                ])
                ->orderBy('o.id', 'desc')
                ->get()
                ->toArray();

            // Filter out already claimed
            foreach ($tblsslOrders as $o) {
                if (!$this->isAlreadyClaimed('tblsslorders', $o->id)) {
                    $orders[] = $o;
                }
            }
        } catch (\Exception $e) {
            // Silently skip
        }

        return $orders;
    }

    /**
     * Get legacy order counts per provider slug
     *
     * @return array ['nicsrs' => int, 'gogetssl' => int, 'thesslstore' => int, 'ssl2buy' => int]
     */
    public function getLegacyCounts(): array
    {
        $counts = [
            'nicsrs'      => 0,
            'gogetssl'    => 0,
            'thesslstore' => 0,
            'ssl2buy'     => 0,
        ];

        // NicSRS — separate table
        try {
            $counts['nicsrs'] = Capsule::table('nicsrs_sslorders')->count();
        } catch (\Exception $e) {}

        // GoGetSSL
        try {
            $counts['gogetssl'] = Capsule::table('tblsslorders')
                ->where('module', 'SSLCENTERWHMCS')->count();
        } catch (\Exception $e) {}

        // TheSSLStore
        try {
            $counts['thesslstore'] = Capsule::table('tblsslorders')
                ->whereIn('module', ['thesslstore_ssl', 'thesslstorefullv2'])->count();
        } catch (\Exception $e) {}

        // SSL2Buy
        try {
            $counts['ssl2buy'] = Capsule::table('tblsslorders')
                ->where('module', 'ssl2buy')->count();
        } catch (\Exception $e) {}

        return $counts;
    }

    /**
     * Get claimed counts per provider slug
     *
     * @return array
     */
    public function getClaimedCounts(): array
    {
        $counts = [
            'nicsrs'      => 0,
            'gogetssl'    => 0,
            'thesslstore' => 0,
            'ssl2buy'     => 0,
        ];

        try {
            $rows = Capsule::table('mod_aio_ssl_orders')
                ->whereNotNull('legacy_module')
                ->where('legacy_module', '!=', '')
                ->select([
                    'legacy_module',
                    Capsule::raw('COUNT(*) as cnt'),
                ])
                ->groupBy('legacy_module')
                ->get();

            foreach ($rows as $row) {
                $slug = self::PROVIDER_MAP[$row->legacy_module] ?? null;
                if ($slug && isset($counts[$slug])) {
                    $counts[$slug] += $row->cnt;
                }
            }
        } catch (\Exception $e) {}

        return $counts;
    }

    /**
     * Claim a legacy order — update module to 'aio_ssl'
     *
     * @param int    $orderId
     * @param string $sourceTable 'nicsrs_sslorders' or 'tblsslorders' (auto-detect if empty)
     * @return array
     */
    public function claimOrder(int $orderId, string $sourceTable = ''): array
    {
        // ── Resolve source table & fetch order ──
        $order = null;
        $resolvedTable = '';

        if ($sourceTable === 'nicsrs_sslorders' || empty($sourceTable)) {
            try {
                $order = Capsule::table('nicsrs_sslorders')->find($orderId);
                if ($order) {
                    $resolvedTable = 'nicsrs_sslorders';
                    // Inject module field (nicsrs_sslorders doesn't have it consistently)
                    $order->module = $order->module ?? 'nicsrs_ssl';
                }
            } catch (\Exception $e) {}
        }

        if (!$order && ($sourceTable === 'tblsslorders' || empty($sourceTable))) {
            try {
                $order = Capsule::table('tblsslorders')->find($orderId);
                if ($order) {
                    $resolvedTable = 'tblsslorders';
                }
            } catch (\Exception $e) {}
        }

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found in any legacy table.'];
        }

        // Validate it's a legacy module
        if (!in_array($order->module, self::ALL_LEGACY_MODULES)) {
            return ['success' => false, 'message' => "Not a recognized legacy module: {$order->module}"];
        }

        // Check if already claimed
        if ($this->isAlreadyClaimed($resolvedTable, $orderId)) {
            return ['success' => false, 'message' => 'Order already claimed.'];
        }

        // ── Normalize configdata ──
        $configdata = $this->normalizeConfigdata($order->module, $order->configdata ?? '');
        $providerSlug = self::PROVIDER_MAP[$order->module] ?? 'unknown';
        $configdata['provider'] = $providerSlug;
        $configdata['migrated_from'] = $order->module;
        $configdata['migrated_at'] = date('Y-m-d H:i:s');

        // ── Extract domain from configdata or hosting ──
        $domain = '';
        if (!empty($configdata['domains'])) {
            $firstDomain = is_array($configdata['domains'][0] ?? null)
                ? ($configdata['domains'][0]['domainName'] ?? '')
                : ($configdata['domains'][0] ?? '');
            $domain = $firstDomain;
        }
        if (empty($domain) && !empty($order->serviceid)) {
            try {
                $hosting = Capsule::table('tblhosting')->find($order->serviceid);
                $domain = $hosting->domain ?? '';
            } catch (\Exception $e) {}
        }

        // ── Insert into mod_aio_ssl_orders (non-destructive) ──
        // Column mapping matches actual table schema:
        //   service_id (not serviceid), provider_slug (not provider),
        //   remote_id (not remoteid)
        try {
            $newId = Capsule::table('mod_aio_ssl_orders')->insertGetId([
                'userid'           => $order->userid,
                'service_id'       => $order->serviceid ?? 0,
                'remote_id'        => $order->remoteid ?? '',
                'provider_slug'    => $providerSlug,
                'canonical_id'     => null,
                'product_code'     => $order->certtype ?? '',
                'domain'           => $domain,
                'certtype'         => $order->certtype ?? '',
                'status'           => $this->mapStatus($order->status ?? ''),
                'configdata'       => json_encode($configdata),
                'completiondate'   => $this->sanitizeDate($order->completiondate ?? null),
                'begin_date'       => $configdata['begin_date'] ?? null,
                'end_date'         => $configdata['end_date'] ?? null,
                'legacy_table'     => $resolvedTable,
                'legacy_order_id'  => $orderId,
                'legacy_module'    => $order->module,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'DB insert failed: ' . $e->getMessage()];
        }

        ActivityLogger::log('order_claimed', 'order', (string)$newId,
            "Claimed legacy {$order->module} #{$orderId} from {$resolvedTable} → AIO #{$newId} (provider: {$providerSlug})");

        return [
            'success'    => true,
            'message'    => "Order claimed successfully. New AIO order #{$newId}.",
            'provider'   => $providerSlug,
            'aio_id'     => $newId,
            'legacy_id'  => $orderId,
            'source'     => $resolvedTable,
        ];
    }

    /**
     * Bulk claim all legacy orders
     *
     * @return array ['claimed'=>int, 'failed'=>int, 'errors'=>array]
     */
    public function claimAll(): array
    {
        $allOrders = $this->getLegacyOrders();

        $claimed = 0;
        $failed = 0;
        $errors = [];

        foreach ($allOrders as $order) {
            $sourceTable = $order->_source_table ?? '';
            $result = $this->claimOrder($order->id, $sourceTable);
            if ($result['success']) {
                $claimed++;
            } else {
                $failed++;
                $errors[] = "#{$order->id} ({$sourceTable}): {$result['message']}";
            }
        }

        return compact('claimed', 'failed', 'errors');
    }

    // ─── Normalization ─────────────────────────────────────────────

    /**
     * Normalize legacy configdata formats to AIO standard
     *
     * Added 'thesslstorefullv2' match case
     */
    public function normalizeConfigdata(string $module, $configdata): array
    {
        $data = [];
        if (is_string($configdata) && !empty($configdata)) {
            $data = json_decode($configdata, true);
            // Fallback to unserialize for WHMCS < 7.3
            if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
                $data = @unserialize($configdata);
                if (!is_array($data)) {
                    $data = [];
                }
            }
        } elseif (is_array($configdata)) {
            $data = $configdata;
        }

        return match ($module) {
            'nicsrs_ssl'         => $this->normalizeNicsrs($data),
            'SSLCENTERWHMCS'     => $this->normalizeGoGetSSL($data),
            'thesslstore_ssl',
            'thesslstorefullv2'  => $this->normalizeTheSSLStore($data),
            'ssl2buy'            => $this->normalizeSSL2Buy($data),
            default              => $data,
        };
    }

    /**
     * Normalize NicSRS configdata
     *
     * Data is nested inside 'applyReturn' object.
     * Real structure:
     *   configdata.csr
     *   configdata.privateKey (camelCase!)
     *   configdata.domainInfo[].domainName, .dcvMethod
     *   configdata.applyReturn.certificate (NOT 'crt')
     *   configdata.applyReturn.caCertificate (NOT 'ca')
     *   configdata.applyReturn.beginDate
     *   configdata.applyReturn.endDate
     *   configdata.applyReturn.certId
     *   configdata.applyReturn.vendorId
     */
    private function normalizeNicsrs(array $data): array
    {
        $apply = $data['applyReturn'] ?? [];

        // Extract DCV method from domainInfo
        $dcvMethod = 'email';
        $domainInfo = $data['domainInfo'] ?? [];
        if (!empty($domainInfo) && is_array($domainInfo)) {
            $firstDomain = $domainInfo[0] ?? [];
            $dcvMethod = $firstDomain['dcvMethod'] ?? $firstDomain['dcv_method'] ?? 'email';
            // NicSRS stores email as dcvMethod value like "admin@example.com"
            if (filter_var($dcvMethod, FILTER_VALIDATE_EMAIL)) {
                $dcvMethod = 'email';
            }
        }

        return [
            'csr'          => $data['csr'] ?? '',
            'cert'         => $apply['certificate'] ?? $data['crt'] ?? $data['cert'] ?? '',
            'ca'           => $apply['caCertificate'] ?? $data['ca'] ?? '',
            'private_key'  => $data['privateKey'] ?? $data['private_key'] ?? '',
            'domains'      => $domainInfo,
            'begin_date'   => $apply['beginDate'] ?? $data['beginDate'] ?? $data['begin_date'] ?? null,
            'end_date'     => $apply['endDate'] ?? $data['endDate'] ?? $data['end_date'] ?? null,
            'dcv_method'   => $dcvMethod,
            'remote_id'    => $apply['certId'] ?? '',
            'vendor_id'    => $apply['vendorId'] ?? '',
            'due_date'     => $apply['dueDate'] ?? null,
            'original'     => $data,
        ];
    }

    /**
     * Normalize GoGetSSL (SSLCENTERWHMCS) configdata
     *
     * Real configdata structure from tblsslorders:
     *   configdata.csr
     *   configdata.crt (certificate PEM)
     *   configdata.ca (CA bundle PEM)
     *   configdata.private_key (encoded key — present!)
     *   configdata.domain (primary domain string)
     *   configdata.san_details[].san_name (SAN domains)
     *   configdata.valid_from / valid_till
     *   configdata.partner_order_id
     *   configdata.ssl_status
     *   configdata.dcv_method
     *   configdata.product_id / product_brand
     *   configdata.approver_method / approveremail
     */
    private function normalizeGoGetSSL(array $data): array
    {
        // Extract domains from multiple sources
        $domains = [];
        if (!empty($data['domain'])) {
            $domains[] = $data['domain'];
        }
        if (!empty($data['san_details']) && is_array($data['san_details'])) {
            foreach ($data['san_details'] as $san) {
                $sanName = $san['san_name'] ?? '';
                if (!empty($sanName) && !in_array($sanName, $domains)) {
                    $domains[] = $sanName;
                }
            }
        } elseif (!empty($data['san'])) {
            $sanList = is_string($data['san']) ? explode(',', $data['san']) : (array)$data['san'];
            foreach ($sanList as $s) {
                $s = trim($s);
                if (!empty($s) && !in_array($s, $domains)) {
                    $domains[] = $s;
                }
            }
        }

        return [
            'csr'               => $data['csr'] ?? '',
            'cert'              => $data['crt'] ?? $data['crt_code'] ?? '',
            'ca'                => $data['ca'] ?? $data['ca_code'] ?? '',
            'private_key'       => $data['private_key'] ?? '',
            'domains'           => $domains,
            'begin_date'        => $data['valid_from'] ?? $data['begin_date'] ?? null,
            'end_date'          => $data['valid_till'] ?? $data['end_date'] ?? null,
            'dcv_method'        => $data['dcv_method'] ?? $data['approver_method'] ?? $data['approvalmethod'] ?? 'email',
            'partner_order_id'  => $data['partner_order_id'] ?? '',
            'ssl_status'        => $data['ssl_status'] ?? $data['order_status_description'] ?? '',
            'product_id'        => $data['product_id'] ?? '',
            'product_brand'     => $data['product_brand'] ?? '',
            'original'          => $data,
        ];
    }

    /**
     * Normalize TheSSLStore configdata
     *
     * Also handles 'thesslstorefullv2' module format.
     * thesslstorefullv2 stores:
     *   configdata.csr
     *   configdata.domain (primary domain)
     *   configdata.pvtKeyID (private key reference, not actual key)
     *   configdata.fields.sslDCVAuthMethod.{domain} = method
     *   No crt/ca at initial stage — fetched after issuance
     */
    private function normalizeTheSSLStore(array $data): array
    {
        // Extract domain(s)
        $domains = [];
        if (!empty($data['domain'])) {
            $domains[] = $data['domain'];
        }
        if (!empty($data['domains']) && is_array($data['domains'])) {
            $domains = array_merge($domains, $data['domains']);
        }
        // TheSSLStore SAN handling
        if (!empty($data['fields']['sslAdditionalSan'])) {
            $sans = array_map('trim', explode(',', $data['fields']['sslAdditionalSan']));
            $domains = array_merge($domains, $sans);
        }
        if (!empty($data['fields']['sslAdditionalWildCardSan'])) {
            $wSans = array_map('trim', explode(',', $data['fields']['sslAdditionalWildCardSan']));
            $domains = array_merge($domains, $wSans);
        }
        $domains = array_filter(array_unique($domains));

        // Extract DCV method
        $dcvMethod = 'email';
        if (!empty($data['fields']['sslDCVAuthMethod']) && is_array($data['fields']['sslDCVAuthMethod'])) {
            $dcvMethod = reset($data['fields']['sslDCVAuthMethod']) ?: 'email';
        }

        return [
            'csr'                    => $data['csr'] ?? '',
            'cert'                   => $data['crt_code'] ?? $data['crt'] ?? '',
            'ca'                     => $data['ca_code'] ?? $data['ca'] ?? '',
            'private_key'            => $data['private_key'] ?? '',
            'domains'                => array_values($domains),
            'begin_date'             => $data['CertificateStartDate'] ?? $data['begin_date'] ?? $data['valid_from'] ?? null,
            'end_date'               => $data['CertificateEndDate'] ?? $data['end_date'] ?? $data['valid_till'] ?? null,
            'dcv_method'             => $dcvMethod,
            'thesslstore_order_id'   => $data['TheSSLStoreOrderID'] ?? '',
            'pvt_key_id'             => $data['pvtKeyID'] ?? '',
            'original'               => $data,
        ];
    }

    /**
     * Normalize SSL2Buy configdata
     */
    private function normalizeSSL2Buy(array $data): array
    {
        return [
            'csr'              => $data['csr'] ?? '',
            'cert'             => $data['crt'] ?? '',
            'ca'               => $data['ca'] ?? '',
            'private_key'      => '',
            'domains'          => $data['domains'] ?? [],
            'begin_date'       => null,
            'end_date'         => null,
            'dcv_method'       => 'email',
            'ssl2buy_order_id' => $data['orderId'] ?? '',
            'brand'            => $data['brand_name'] ?? '',
            'config_pin'       => $data['pin'] ?? '',
            'config_link'      => $data['link'] ?? '',
            'original'         => $data,
        ];
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Check if a legacy order has already been claimed
     */
    private function isAlreadyClaimed(string $table, int $orderId): bool
    {
        try {
            return Capsule::table('mod_aio_ssl_orders')
                ->where('legacy_table', $table)
                ->where('legacy_order_id', $orderId)
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Map legacy status strings to AIO standard
     */
    private function mapStatus(string $status): string
    {
        $map = [
            'complete'                  => 'active',
            'Complete'                  => 'active',
            'Completed'                 => 'active',
            'Configuration Submitted'   => 'active',
            'active'                    => 'active',
            'pending'                   => 'pending',
            'Pending'                   => 'pending',
            'processing'                => 'pending',
            'Processing'                => 'pending',
            'Awaiting Configuration'    => 'awaiting',
            'awaiting'                  => 'awaiting',
            'cancelled'                 => 'cancelled',
            'Cancelled'                 => 'cancelled',
            'revoked'                   => 'revoked',
            'Revoked'                   => 'revoked',
            'expired'                   => 'expired',
            'Expired'                   => 'expired',
        ];

        return $map[$status] ?? strtolower($status);
    }

    /**
     * Sanitize date for database insert
     */
    private function sanitizeDate($date): ?string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') {
            return null;
        }
        return $date;
    }
}