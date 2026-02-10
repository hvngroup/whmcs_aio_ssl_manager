<?php
/**
 * Dashboard Controller
 * Handles dashboard statistics and overview display
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\NicsrsApiService;

class DashboardController extends BaseController
{
    /**
     * @var NicsrsApiService API service instance
     */
    private $apiService;

    /**
     * Constructor
     * 
     * @param array $vars Module variables
     */
    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->apiService = new NicsrsApiService($this->getApiToken());
    }

    /**
     * Render dashboard page
     * 
     * @param string $action Current action
     * @return void
     */
    public function render(string $action): void
    {
        $data = [
            'statistics' => $this->getStatistics(),
            'recentOrders' => $this->getRecentOrders(10),
            'expiringCertificates' => $this->getExpiringCertificates(30, 20),
            'statusDistribution' => $this->getStatusDistribution(),
            'monthlyOrders' => $this->getMonthlyOrders(6),
            'apiConnected' => $this->testApiConnection(),
        ];

        $this->includeTemplate('dashboard', $data);
    }

    /**
     * Get dashboard statistics
     * 
     * @return array Statistics data
     */
    private function getStatistics(): array
    {
        // Total orders
        $totalOrders = Capsule::table('nicsrs_sslorders')->count();
        
        // Pending orders (awaiting, draft, pending)
        $pendingOrders = Capsule::table('nicsrs_sslorders')
            ->whereIn('status', ['awaiting', 'draft', 'pending'])
            ->count();
        
        // Issued/Complete certificates
        $issuedCerts = Capsule::table('nicsrs_sslorders')
            ->where('status', 'complete')
            ->count();
        
        // Expiring within 30 days
        $expiringCount = $this->countExpiringCertificates(30);

        // Calculate month-over-month trend
        $thisMonthStart = date('Y-m-01');
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

        $lastMonthTotal = Capsule::table('nicsrs_sslorders')
            ->whereBetween('provisiondate', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $thisMonthTotal = Capsule::table('nicsrs_sslorders')
            ->where('provisiondate', '>=', $thisMonthStart)
            ->count();

        $trend = 0;
        if ($lastMonthTotal > 0) {
            $trend = round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1);
        }

        // Cancelled orders
        $cancelledOrders = Capsule::table('nicsrs_sslorders')
            ->where('status', 'cancelled')
            ->count();

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'issued_certs' => $issuedCerts,
            'expiring_soon' => $expiringCount,
            'cancelled_orders' => $cancelledOrders,
            'this_month' => $thisMonthTotal,
            'trend' => $trend,
        ];
    }

    /**
     * Count certificates expiring within days
     * 
     * @param int $days Number of days
     * @return int Count
     */
    private function countExpiringCertificates(int $days): int
    {
        $count = 0;
        $orders = Capsule::table('nicsrs_sslorders')
            ->where('status', 'complete')
            ->get();

        $now = time();
        $threshold = strtotime("+{$days} days");

        foreach ($orders as $order) {
            $config = json_decode($order->configdata, true);
            if (isset($config['applyReturn']['endDate'])) {
                $endDate = strtotime($config['applyReturn']['endDate']);
                if ($endDate && $endDate > $now && $endDate <= $threshold) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get recent orders with full details
     * 
     * @param int $limit Number of orders to return
     * @return array Orders
     */
    private function getRecentOrders(int $limit = 10): array
    {
        $orders = Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select([
                'o.id',
                'o.userid',
                'o.serviceid',
                'o.remoteid',
                'o.certtype',
                'o.status',
                'o.provisiondate',
                'o.configdata',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
                'h.domain as service_domain',
                'p.name as service_product_name',
            ])
            ->orderBy('o.id', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            $config = json_decode($order->configdata, true);
            
            // Get domain from config
            $domain = 'N/A';
            if (isset($config['domainInfo'][0]['domainName'])) {
                $domain = $config['domainInfo'][0]['domainName'];
            }
            
            // Build client name
            $clientName = trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? ''));
            if (empty($clientName)) {
                $clientName = 'Unknown';
            }

            // Get product name from mod_nicsrs_products if available
            $productName = null;
            if ($order->certtype) {
                $product = Capsule::table('mod_nicsrs_products')
                    ->where('product_code', $order->certtype)
                    ->orWhere('product_name', $order->certtype)
                    ->first();
                if ($product) {
                    $productName = $product->product_name;
                }
            }

            $result[] = [
                'id' => $order->id,
                'userid' => $order->userid,
                'serviceid' => $order->serviceid,
                'remoteid' => $order->remoteid,
                'domain' => $domain,
                'certtype' => $order->certtype,
                'product_name' => $productName ?: $order->service_product_name,
                'status' => $order->status ?: 'unknown',
                'provisiondate' => $order->provisiondate,
                'client_name' => $clientName,
                'companyname' => $order->companyname,
                'email' => $order->email,
                'service_domain' => $order->service_domain,
                'service_product_name' => $order->service_product_name,
            ];
        }

        return $result;
    }

    /**
     * Get certificates expiring within days with full details
     * 
     * @param int $days Number of days
     * @param int $limit Maximum results
     * @return array Expiring certificates
     */
    private function getExpiringCertificates(int $days = 30, int $limit = 20): array
    {
        $result = [];
        
        $orders = Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select([
                'o.*', 
                'c.firstname', 
                'c.lastname', 
                'c.companyname',
                'c.email',
                'h.domain as service_domain',
                'p.name as service_product_name',
            ])
            ->where('o.status', 'complete')
            ->get();

        $now = time();
        $threshold = strtotime("+{$days} days");

        foreach ($orders as $order) {
            $config = json_decode($order->configdata, true);
            
            if (isset($config['applyReturn']['endDate'])) {
                $endDate = strtotime($config['applyReturn']['endDate']);
                
                if ($endDate && $endDate > $now && $endDate <= $threshold) {
                    // Get domain
                    $domain = isset($config['domainInfo'][0]['domainName']) 
                        ? $config['domainInfo'][0]['domainName'] 
                        : 'N/A';
                    
                    // Calculate days left
                    $daysLeft = (int) ceil(($endDate - $now) / 86400);
                    
                    // Build client name
                    $clientName = trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? ''));
                    if (empty($clientName)) {
                        $clientName = 'Unknown';
                    }

                    // Get product name
                    $productName = null;
                    if ($order->certtype) {
                    $product = Capsule::table('mod_nicsrs_products')
                        ->where('product_code', $order->certtype)
                        ->orWhere('product_name', $order->certtype)
                        ->first();
                        if ($product) {
                            $productName = $product->product_name;
                        }
                    }
                    
                    $result[] = [
                        'id' => $order->id,
                        'userid' => $order->userid,
                        'serviceid' => $order->serviceid,
                        'domain' => $domain,
                        'certtype' => $order->certtype,
                        'product_name' => $productName ?: $order->service_product_name,
                        'end_date' => $config['applyReturn']['endDate'],
                        'days_left' => $daysLeft,
                        'client_name' => $clientName,
                        'companyname' => $order->companyname,
                        'client_email' => $order->email,
                        'remoteid' => $order->remoteid,
                        'service_domain' => $order->service_domain,
                        'service_product_name' => $order->service_product_name,
                    ];
                }
            }
        }

        // Sort by days left ascending (most urgent first)
        usort($result, function ($a, $b) {
            return $a['days_left'] - $b['days_left'];
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Get status distribution for chart
     * 
     * @return array Status counts
     */
    private function getStatusDistribution(): array
    {
        $statuses = Capsule::table('nicsrs_sslorders')
            ->selectRaw('LOWER(status) as status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($statuses as $status) {
            $statusKey = $status->status ?: 'unknown';
            $result[$statusKey] = (int) $status->count;
        }

        return $result;
    }

    /**
     * Get monthly order counts for chart - FIXED
     * 
     * @param int $months Number of months to include
     * @return array Monthly data
     */
    private function getMonthlyOrders(int $months = 6): array
    {
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
            
            $count = Capsule::table('nicsrs_sslorders')
                ->whereNotNull('provisiondate')
                ->where('provisiondate', '!=', '0000-00-00')
                ->whereBetween('provisiondate', [$monthStart, $monthEnd])
                ->count();
            
            $data[] = [
                'month' => date('M Y', strtotime($monthStart)),
                'short' => date('M', strtotime($monthStart)),
                'year' => date('Y', strtotime($monthStart)),
                'count' => (int) $count,
            ];
        }
        
        return $data;
    }

    /**
     * Test API connection
     * 
     * @return bool Connection status
     */
    private function testApiConnection(): bool
    {
        if (empty($this->getApiToken())) {
            return false;
        }
        
        return $this->apiService->testConnection();
    }

    /**
     * Handle AJAX requests
     * 
     * @param array $post POST data
     * @return string JSON response
     */
    public function handleAjax(array $post): string
    {
        $action = isset($post['ajax_action']) ? $post['ajax_action'] : '';

        switch ($action) {
            case 'refresh_statistics':
                return $this->jsonSuccess('Statistics refreshed', [
                    'data' => $this->getStatistics(),
                ]);

            case 'test_api':
                $connected = $this->testApiConnection();
                if ($connected) {
                    return $this->jsonSuccess('API connection successful');
                }
                return $this->jsonError('API connection failed. Please check your API token.');

            case 'get_expiring':
                $days = isset($post['days']) ? (int) $post['days'] : 30;
                return $this->jsonSuccess('', [
                    'data' => $this->getExpiringCertificates($days, 50),
                ]);

            default:
                return $this->jsonError('Unknown action');
        }
    }
}