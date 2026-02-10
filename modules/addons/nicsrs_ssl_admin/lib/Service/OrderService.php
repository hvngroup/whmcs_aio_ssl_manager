<?php
/**
 * Order Service
 * Handles order-related business logic and data operations
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;

class OrderService
{
    /**
     * @var NicsrsApiService API service
     */
    private $apiService;

    /**
     * Constructor
     * 
     * @param NicsrsApiService $apiService API service instance
     */
    public function __construct(NicsrsApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Get order by ID
     * 
     * @param int $orderId Order ID
     * @return object|null Order object or null
     */
    public function getById(int $orderId)
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('id', $orderId)
            ->first();
    }

    /**
     * Get order by remote ID (certificate ID)
     * 
     * @param string $remoteId Remote certificate ID
     * @return object|null Order object or null
     */
    public function getByRemoteId(string $remoteId)
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('remoteid', $remoteId)
            ->first();
    }

    /**
     * Get order by service ID
     * 
     * @param int $serviceId WHMCS service ID
     * @return object|null Order object or null
     */
    public function getByServiceId(int $serviceId)
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('serviceid', $serviceId)
            ->first();
    }

    /**
     * Get orders by user ID
     * 
     * @param int $userId User ID
     * @param int $limit Max results
     * @return array Orders
     */
    public function getByUserId(int $userId, int $limit = 100): array
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get orders by status
     * 
     * @param string $status Status filter
     * @param int $limit Max results
     * @return array Orders
     */
    public function getByStatus(string $status, int $limit = 100): array
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('status', $status)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get order with full details (client, service)
     * 
     * @param int $orderId Order ID
     * @return object|null Order with details
     */
    public function getOrderWithDetails(int $orderId)
    {
        return Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->select([
                'o.*',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email as client_email',
                'c.phonenumber',
                'c.address1',
                'c.city',
                'c.state',
                'c.postcode',
                'c.country',
                'h.domainstatus as service_status',
                'h.domain as service_domain',
            ])
            ->where('o.id', $orderId)
            ->first();
    }

    /**
     * Update order status
     * 
     * @param int $orderId Order ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('id', $orderId)
            ->update(['status' => $status]) > 0;
    }

    /**
     * Update order config data
     * 
     * @param int $orderId Order ID
     * @param array $configData Config data array
     * @return bool Success
     */
    public function updateConfigData(int $orderId, array $configData): bool
    {
        return Capsule::table('nicsrs_sslorders')
            ->where('id', $orderId)
            ->update(['configdata' => json_encode($configData)]) > 0;
    }

    /**
     * Refresh order status from API
     * 
     * @param int $orderId Order ID
     * @return array Result with status and data
     * @throws \Exception On error
     */
    public function refreshStatus(int $orderId): array
    {
        $order = $this->getById($orderId);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }
        
        if (empty($order->remoteid)) {
            throw new \Exception('No remote certificate ID');
        }

        $result = $this->apiService->collect($order->remoteid);
        
        if ($result['code'] == 1 || $result['code'] == 2) {
            $configData = json_decode($order->configdata, true) ?: [];
            
            if (isset($result['data'])) {
                $configData['applyReturn'] = array_merge(
                    $configData['applyReturn'] ?? [],
                    $result['data']
                );
            }

            $newStatus = isset($result['status']) ? strtolower($result['status']) : $order->status;
            
            $updateData = [
                'status' => $newStatus,
                'configdata' => json_encode($configData),
            ];
            
            if ($newStatus === 'complete' && 
                (empty($order->completiondate) || $order->completiondate === '0000-00-00 00:00:00')
            ) {
                $updateData['completiondate'] = date('Y-m-d H:i:s');
            }

            Capsule::table('nicsrs_sslorders')
                ->where('id', $orderId)
                ->update($updateData);

            return [
                'success' => true,
                'old_status' => $order->status,
                'new_status' => $newStatus,
                'data' => $result['data'] ?? null,
            ];
        }

        throw new \Exception($result['msg'] ?? 'API error');
    }

    /**
     * Get expiring certificates
     * 
     * @param int $days Days until expiry
     * @param int $limit Max results
     * @return array Expiring orders
     */
    public function getExpiringCertificates(int $days = 30, int $limit = 100): array
    {
        $result = [];
        $orders = Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->select(['o.*', 'c.firstname', 'c.lastname', 'c.email'])
            ->where('o.status', 'complete')
            ->get();

        $now = time();
        $threshold = strtotime("+{$days} days");

        foreach ($orders as $order) {
            $config = json_decode($order->configdata, true);
            if (isset($config['applyReturn']['endDate'])) {
                $endDate = strtotime($config['applyReturn']['endDate']);
                if ($endDate && $endDate > $now && $endDate <= $threshold) {
                    $domain = isset($config['domainInfo'][0]['domainName']) 
                        ? $config['domainInfo'][0]['domainName'] 
                        : 'N/A';
                    
                    $daysLeft = ceil(($endDate - $now) / 86400);
                    
                    $result[] = [
                        'id' => $order->id,
                        'domain' => $domain,
                        'certtype' => $order->certtype,
                        'end_date' => $config['applyReturn']['endDate'],
                        'days_left' => $daysLeft,
                        'client_name' => trim($order->firstname . ' ' . $order->lastname),
                        'client_email' => $order->email,
                        'remoteid' => $order->remoteid,
                    ];
                }
            }
        }

        // Sort by days left
        usort($result, function ($a, $b) {
            return $a['days_left'] - $b['days_left'];
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Get orders statistics
     * 
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        $total = Capsule::table('nicsrs_sslorders')->count();
        
        $byStatus = Capsule::table('nicsrs_sslorders')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $thisMonth = Capsule::table('nicsrs_sslorders')
            ->where('provisiondate', '>=', date('Y-m-01'))
            ->count();

        $lastMonth = Capsule::table('nicsrs_sslorders')
            ->whereBetween('provisiondate', [
                date('Y-m-01', strtotime('-1 month')),
                date('Y-m-t', strtotime('-1 month'))
            ])
            ->count();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'pending' => ($byStatus['awaiting'] ?? 0) + ($byStatus['draft'] ?? 0) + ($byStatus['pending'] ?? 0),
            'complete' => $byStatus['complete'] ?? 0,
            'cancelled' => $byStatus['cancelled'] ?? 0,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'trend' => $lastMonth > 0 
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Search orders
     * 
     * @param string $query Search query
     * @param int $limit Max results
     * @return array Matching orders
     */
    public function search(string $query, int $limit = 50): array
    {
        return Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->select(['o.*', 'c.firstname', 'c.lastname', 'c.email', 'c.companyname'])
            ->where(function ($q) use ($query) {
                $q->where('o.remoteid', 'like', "%{$query}%")
                  ->orWhere('o.certtype', 'like', "%{$query}%")
                  ->orWhere('c.firstname', 'like', "%{$query}%")
                  ->orWhere('c.lastname', 'like', "%{$query}%")
                  ->orWhere('c.email', 'like', "%{$query}%")
                  ->orWhere('c.companyname', 'like', "%{$query}%");
            })
            ->orderBy('o.id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get monthly order counts
     * 
     * @param int $months Number of months
     * @return array Monthly data
     */
    public function getMonthlyOrders(int $months = 12): array
    {
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
            
            $count = Capsule::table('nicsrs_sslorders')
                ->whereBetween('provisiondate', [$monthStart, $monthEnd])
                ->count();
            
            $data[] = [
                'month' => date('M Y', strtotime($monthStart)),
                'short' => date('M', strtotime($monthStart)),
                'year' => date('Y', strtotime($monthStart)),
                'count' => $count,
            ];
        }
        
        return $data;
    }

    /**
     * Count orders by certificate type
     * 
     * @return array Type => count mapping
     */
    public function countByCertType(): array
    {
        return Capsule::table('nicsrs_sslorders')
            ->selectRaw('certtype, COUNT(*) as count')
            ->groupBy('certtype')
            ->orderBy('count', 'desc')
            ->pluck('count', 'certtype')
            ->toArray();
    }
}