<?php
/**
 * NicSRS SSL Module - Order Repository
 * Database operations for SSL orders
 * 
 * @package    nicsrs_ssl
 * @version    2.0.0
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;

class OrderRepository
{
    /**
     * Table name
     */
    const TABLE = 'nicsrs_sslorders';

    /**
     * Ensure table exists
     */
    public static function ensureTableExists(): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            Capsule::schema()->create(self::TABLE, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned();
                $table->integer('serviceid')->unsigned();
                $table->text('addon_id')->nullable();
                $table->text('remoteid')->nullable();
                $table->text('module')->nullable();
                $table->text('certtype')->nullable();
                $table->longText('configdata')->nullable();
                $table->date('provisiondate')->nullable();
                $table->datetime('completiondate')->default('0000-00-00 00:00:00');
                $table->string('status', 50)->default('Awaiting Configuration');
                
                $table->index('userid');
                $table->index('serviceid');
            });
        }
    }

    /**
     * Get order by ID
     */
    public static function getById(int $id): ?object
    {
        return Capsule::table(self::TABLE)
            ->where('id', $id)
            ->first();
    }

    /**
     * Get order by service ID
     */
    public static function getByServiceId(int $serviceId): ?object
    {
        self::ensureTableExists();
        
        return Capsule::table(self::TABLE)
            ->where('serviceid', $serviceId)
            ->first();
    }

    /**
     * Get order by remote ID (certificate ID)
     */
    public static function getByRemoteId(string $remoteId): ?object
    {
        return Capsule::table(self::TABLE)
            ->where('remoteid', $remoteId)
            ->first();
    }

    /**
     * Get orders by user ID
     */
    public static function getByUserId(int $userId, ?string $status = null): array
    {
        $query = Capsule::table(self::TABLE)
            ->where('userid', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->toArray();
    }

    /**
     * Get orders by status
     */
    public static function getByStatus(string $status, int $limit = 100): array
    {
        return Capsule::table(self::TABLE)
            ->where('status', $status)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Create new order
     */
    public static function create(array $data): int
    {
        self::ensureTableExists();

        // Set defaults
        $data['provisiondate'] = $data['provisiondate'] ?? date('Y-m-d');
        $data['completiondate'] = $data['completiondate'] ?? '0000-00-00 00:00:00';
        $data['status'] = $data['status'] ?? 'Awaiting Configuration';
        $data['module'] = $data['module'] ?? 'nicsrs_ssl';

        return Capsule::table(self::TABLE)->insertGetId($data);
    }

    /**
     * Update order by ID
     */
    public static function update(int $id, array $data): bool
    {
        return Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Update order by service ID
     */
    public static function updateByServiceId(int $serviceId, array $data): bool
    {
        return Capsule::table(self::TABLE)
            ->where('serviceid', $serviceId)
            ->update($data) > 0;
    }

    /**
     * Update order status by service ID
     */
    public static function updateStatusByServiceId(int $serviceId, string $status): bool
    {
        return self::updateByServiceId($serviceId, ['status' => $status]);
    }

    /**
     * Update configdata
     */
    public static function updateConfigData(int $id, array $configdata): bool
    {
        return self::update($id, [
            'configdata' => json_encode($configdata),
        ]);
    }

    /**
     * Merge into configdata
     */
    public static function mergeConfigData(int $id, array $newData): bool
    {
        $order = self::getById($id);
        if (!$order) {
            return false;
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $configdata = array_merge($configdata, $newData);

        return self::updateConfigData($id, $configdata);
    }

    /**
     * Delete order by ID
     */
    public static function delete(int $id): bool
    {
        return Capsule::table(self::TABLE)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Delete order by service ID
     */
    public static function deleteByServiceId(int $serviceId): bool
    {
        return Capsule::table(self::TABLE)
            ->where('serviceid', $serviceId)
            ->delete() > 0;
    }

    /**
     * Get order with service and client details
     */
    public static function getWithDetails(int $serviceId): ?object
    {
        return Capsule::table(self::TABLE . ' as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblclients as c', 'o.userid', '=', 'c.id')
            ->join('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->where('o.serviceid', $serviceId)
            ->select([
                'o.*',
                'h.domain as service_domain',
                'h.domainstatus as service_status',
                'h.regdate as service_regdate',
                'h.nextduedate as service_nextdue',
                'c.firstname',
                'c.lastname',
                'c.email',
                'c.companyname',
                'c.address1',
                'c.address2',
                'c.city',
                'c.state',
                'c.postcode',
                'c.country',
                'c.phonenumber',
                'c.language',
                'p.name as product_name',
                'p.configoption1 as product_certtype',
                'p.configoption2 as product_apitoken',
            ])
            ->first();
    }

    /**
     * Get expiring certificates
     */
    public static function getExpiring(int $daysAhead = 30): array
    {
        $orders = Capsule::table(self::TABLE)
            ->whereIn('status', ['Complete', 'Issued'])
            ->get();

        $expiring = [];
        $threshold = strtotime("+{$daysAhead} days");

        foreach ($orders as $order) {
            $configdata = json_decode($order->configdata, true) ?: [];
            $endDate = $configdata['applyReturn']['endDate'] ?? null;

            if ($endDate) {
                $expiry = strtotime($endDate);
                if ($expiry && $expiry <= $threshold && $expiry >= time()) {
                    $order->daysLeft = (int) floor(($expiry - time()) / 86400);
                    $order->expiryDate = $endDate;
                    $expiring[] = $order;
                }
            }
        }

        // Sort by days left
        usort($expiring, function($a, $b) {
            return $a->daysLeft - $b->daysLeft;
        });

        return $expiring;
    }

    /**
     * Get order statistics
     */
    public static function getStatistics(): array
    {
        $total = Capsule::table(self::TABLE)->count();
        
        $byStatus = Capsule::table(self::TABLE)
            ->select('status', Capsule::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => $total,
            'awaiting' => $byStatus['Awaiting Configuration'] ?? 0,
            'pending' => ($byStatus['Pending'] ?? 0) + ($byStatus['Processing'] ?? 0),
            'complete' => ($byStatus['Complete'] ?? 0) + ($byStatus['Issued'] ?? 0),
            'cancelled' => $byStatus['Cancelled'] ?? 0,
            'revoked' => $byStatus['Revoked'] ?? 0,
            'expired' => $byStatus['Expired'] ?? 0,
            'byStatus' => $byStatus,
        ];
    }

    /**
     * Search orders
     */
    public static function search(string $query, int $limit = 50): array
    {
        return Capsule::table(self::TABLE . ' as o')
            ->join('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->join('tblclients as c', 'o.userid', '=', 'c.id')
            ->where(function($q) use ($query) {
                $q->where('o.remoteid', 'LIKE', "%{$query}%")
                  ->orWhere('h.domain', 'LIKE', "%{$query}%")
                  ->orWhere('c.email', 'LIKE', "%{$query}%")
                  ->orWhere('c.firstname', 'LIKE', "%{$query}%")
                  ->orWhere('c.lastname', 'LIKE', "%{$query}%");
            })
            ->select('o.*', 'h.domain', 'c.firstname', 'c.lastname', 'c.email')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Legacy method alias for backward compatibility
     */
    public static function GetSSLProduct(int $serviceId): ?object
    {
        return self::getByServiceId($serviceId);
    }
}