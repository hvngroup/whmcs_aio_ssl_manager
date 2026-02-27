<?php
/**
 * OrderService — CRUD operations for mod_aio_ssl_orders
 *
 * This is the PRIMARY write table for all new AIO SSL orders.
 * Legacy tables (tblsslorders, nicsrs_sslorders) are READ-ONLY.
 *
 * Architecture constraint C4: Write → mod_aio_ssl_orders
 *
 * @package    AioSSL\Server
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 * @version    1.0.0
 */

namespace AioSSL\Server;

use WHMCS\Database\Capsule;

class OrderService
{
    const TABLE = 'mod_aio_ssl_orders';

    // ═══════════════════════════════════════════════════════════════
    // TABLE SAFETY
    // ═══════════════════════════════════════════════════════════════

    /**
     * Ensure table exists — safety net for edge cases
     * (Table should be created by addon _activate(), this is fallback)
     */
    public static function ensureTableExists(): void
    {
        if (Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }

        Capsule::schema()->create(self::TABLE, function ($table) {
            $table->increments('id');
            $table->integer('userid')->unsigned()->default(0);
            $table->integer('service_id')->unsigned();
            $table->string('provider_slug', 50);
            $table->string('remote_id', 100)->nullable();
            $table->string('canonical_id', 100)->nullable();
            $table->string('product_code', 100)->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('certtype', 255)->nullable();
            $table->string('status', 50)->default('Awaiting Configuration');
            $table->longText('configdata')->nullable();
            $table->dateTime('completiondate')->nullable();
            $table->dateTime('begin_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('legacy_table', 100)->nullable();
            $table->integer('legacy_order_id')->nullable();
            $table->string('legacy_module', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('userid', 'idx_userid');
            $table->index('service_id', 'idx_service');
            $table->index('provider_slug', 'idx_provider');
            $table->index('remote_id', 'idx_remote');
            $table->index('status', 'idx_status');
            $table->index('domain', 'idx_domain');
            $table->index('end_date', 'idx_end_date');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create new AIO SSL order
     *
     * @param array $data {
     *   @type int    $userid       Client ID
     *   @type int    $service_id   WHMCS hosting service ID
     *   @type string $provider_slug Provider slug (nicsrs, gogetssl, etc.)
     *   @type string $domain       Primary domain
     *   @type string $certtype     Certificate type/product code
     *   @type string $status       Initial status (default: Awaiting Configuration)
     *   @type array  $configdata   Configuration data (will be JSON encoded)
     *   @type string $remote_id    Provider order ID (optional)
     *   @type string $canonical_id Canonical product ID (optional)
     *   @type string $product_code Provider product code (optional)
     * }
     * @return int New order ID
     * @throws \RuntimeException on failure
     */
    public static function create(array $data): int
    {
        self::ensureTableExists();

        // Encode configdata if array
        if (isset($data['configdata']) && is_array($data['configdata'])) {
            $data['configdata'] = self::encodeConfigdata($data['configdata']);
        }

        // Set defaults
        $insert = [
            'userid'        => $data['userid'] ?? 0,
            'service_id'    => $data['service_id'] ?? 0,
            'provider_slug' => $data['provider_slug'] ?? '',
            'remote_id'     => $data['remote_id'] ?? null,
            'canonical_id'  => $data['canonical_id'] ?? null,
            'product_code'  => $data['product_code'] ?? null,
            'domain'        => $data['domain'] ?? null,
            'certtype'      => $data['certtype'] ?? null,
            'status'        => $data['status'] ?? 'Awaiting Configuration',
            'configdata'    => $data['configdata'] ?? null,
            'completiondate'=> $data['completiondate'] ?? null,
            'begin_date'    => $data['begin_date'] ?? null,
            'end_date'      => $data['end_date'] ?? null,
            'legacy_table'  => $data['legacy_table'] ?? null,
            'legacy_order_id' => $data['legacy_order_id'] ?? null,
            'legacy_module' => $data['legacy_module'] ?? null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $id = Capsule::table(self::TABLE)->insertGetId($insert);

        if (!$id) {
            throw new \RuntimeException('Failed to create AIO SSL order');
        }

        return (int) $id;
    }

    // ═══════════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get order by ID
     */
    public static function getById(int $id): ?object
    {
        return Capsule::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * Get order by service ID (most recent)
     */
    public static function getByServiceId(int $serviceId): ?object
    {
        self::ensureTableExists();

        return Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get order by remote ID + provider
     */
    public static function getByRemoteId(string $remoteId, string $providerSlug = ''): ?object
    {
        $query = Capsule::table(self::TABLE)->where('remote_id', $remoteId);

        if (!empty($providerSlug)) {
            $query->where('provider_slug', $providerSlug);
        }

        return $query->orderBy('id', 'desc')->first();
    }

    /**
     * Get orders by status with optional limit
     */
    public static function getByStatus(string $status, int $limit = 100): array
    {
        return Capsule::table(self::TABLE)
            ->where('status', $status)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get orders by provider slug
     */
    public static function getByProvider(string $providerSlug, int $limit = 100): array
    {
        return Capsule::table(self::TABLE)
            ->where('provider_slug', $providerSlug)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get order with full details (client + hosting + product)
     */
    public static function getWithDetails(int $id): ?object
    {
        return Capsule::table(self::TABLE . ' as o')
            ->leftJoin('tblhosting as h', 'o.service_id', '=', 'h.id')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->where('o.id', $id)
            ->select([
                'o.*',
                'h.domain as hosting_domain',
                'h.domainstatus as service_status',
                'h.billingcycle',
                'h.amount as service_amount',
                'c.firstname', 'c.lastname', 'c.companyname',
                'c.email as client_email',
                'p.name as whmcs_product_name',
                Capsule::raw("CONCAT(c.firstname, ' ', c.lastname) as client_name"),
            ])
            ->first();
    }

    /**
     * Check if order exists for service ID
     */
    public static function existsForService(int $serviceId): bool
    {
        self::ensureTableExists();

        return Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->exists();
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Update order by ID
     *
     * @param int   $id   Order ID
     * @param array $data Fields to update
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        // Encode configdata if array
        if (isset($data['configdata']) && is_array($data['configdata'])) {
            $data['configdata'] = self::encodeConfigdata($data['configdata']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update($data) >= 0;
    }

    /**
     * Update order by service ID (most recent order)
     */
    public static function updateByServiceId(int $serviceId, array $data): bool
    {
        if (isset($data['configdata']) && is_array($data['configdata'])) {
            $data['configdata'] = self::encodeConfigdata($data['configdata']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $order = self::getByServiceId($serviceId);
        if (!$order) {
            return false;
        }

        return Capsule::table(self::TABLE)
            ->where('id', $order->id)
            ->update($data) >= 0;
    }

    /**
     * Update order status
     */
    public static function updateStatus(int $id, string $status, ?string $completionDate = null): bool
    {
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];

        if ($completionDate) {
            $data['completiondate'] = $completionDate;
        } elseif (in_array($status, ['Completed', 'Issued', 'Active'])) {
            $data['completiondate'] = date('Y-m-d H:i:s');
        }

        return Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update($data) >= 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // CONFIGDATA HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Encode configdata array → JSON with error check
     *
     * @param array $data
     * @return string
     * @throws \RuntimeException on encoding failure
     */
    public static function encodeConfigdata(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $error = json_last_error_msg();
            throw new \RuntimeException("Failed to encode configdata: {$error}");
        }

        return $json;
    }

    /**
     * Decode configdata string → array
     * Handles both JSON and legacy serialized format (constraint C10)
     *
     * @param string|null $raw
     * @return array
     */
    public static function decodeConfigdata(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        // Try JSON first
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Fallback: try unserialize (WHMCS < 7.3 legacy format, constraint C10)
        // Suppress errors — invalid serialized data should return empty
        $unserialized = @unserialize($raw);
        if (is_array($unserialized)) {
            return $unserialized;
        }

        return [];
    }

    /**
     * Get decoded configdata for an order
     */
    public static function getConfigdata(int $id): array
    {
        $order = self::getById($id);
        if (!$order) {
            return [];
        }
        return self::decodeConfigdata($order->configdata);
    }

    /**
     * Merge new data into existing configdata
     *
     * @param int   $id      Order ID
     * @param array $newData Data to merge
     * @return bool
     */
    public static function mergeConfigdata(int $id, array $newData): bool
    {
        $existing = self::getConfigdata($id);
        $merged = array_merge($existing, $newData);
        return self::update($id, ['configdata' => $merged]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LEGACY ORDER LOOKUP (READ-ONLY)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Find ANY existing SSL order for a service ID
     * Searches: mod_aio_ssl_orders → tblsslorders → nicsrs_sslorders
     *
     * Constraints: C4 (dual-table read), C5 (NicSRS custom table)
     *
     * @param int $serviceId
     * @return object|null Order with added `_source_table` property
     */
    public static function findAnyOrderForService(int $serviceId): ?object
    {
        // 1. Check mod_aio_ssl_orders first
        $order = self::getByServiceId($serviceId);
        if ($order) {
            $order->_source_table = self::TABLE;
            return $order;
        }

        // 2. Check tblsslorders
        try {
            $order = Capsule::table('tblsslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($order) {
                $order->_source_table = 'tblsslorders';
                // Normalize field names
                $order->service_id = $order->serviceid;
                $order->remote_id = $order->remoteid ?? null;
                return $order;
            }
        } catch (\Exception $e) {
            // Table might not exist in fresh installs
        }

        // 3. Check nicsrs_sslorders (constraint C5)
        try {
            $order = Capsule::table('nicsrs_sslorders')
                ->where('serviceid', $serviceId)
                ->orderBy('id', 'desc')
                ->first();

            if ($order) {
                $order->_source_table = 'nicsrs_sslorders';
                $order->service_id = $order->serviceid;
                $order->remote_id = $order->remoteid ?? null;
                $order->module = 'nicsrs_ssl';
                return $order;
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // AGGREGATE QUERIES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Count orders by status
     */
    public static function countByStatus(): array
    {
        return Capsule::table(self::TABLE)
            ->select('status', Capsule::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    /**
     * Get orders expiring within N days
     */
    public static function getExpiring(int $days = 30, int $limit = 100): array
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        return Capsule::table(self::TABLE)
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $now)
            ->where('end_date', '<=', $future)
            ->whereNotIn('status', ['Cancelled', 'Revoked', 'Expired'])
            ->orderBy('end_date', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get orders needing status sync
     * (Pending/Processing orders that haven't been refreshed recently)
     */
    public static function getNeedingSync(int $batchSize = 50): array
    {
        $syncableStatuses = ['Pending', 'Processing', 'Awaiting Configuration'];

        return Capsule::table(self::TABLE)
            ->whereIn('status', $syncableStatuses)
            ->whereNotNull('remote_id')
            ->where('remote_id', '!=', '')
            ->orderBy('updated_at', 'asc')
            ->limit($batchSize)
            ->get()
            ->toArray();
    }
}