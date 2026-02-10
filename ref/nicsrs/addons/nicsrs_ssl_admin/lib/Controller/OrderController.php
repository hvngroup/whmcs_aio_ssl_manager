<?php
/**
 * Order Controller
 * Handles SSL orders management and certificate operations
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\NicsrsApiService;
use NicsrsAdmin\Service\OrderService;
use NicsrsAdmin\Helper\Pagination;

class OrderController extends BaseController
{
    /**
     * Days threshold for "expiring soon" filter
     */
    const EXPIRING_DAYS = 30;
    
    /**
     * @var NicsrsApiService API service
     */
    private $apiService;
    
    /**
     * @var OrderService Order service
     */
    private $orderService;

    /**
     * Constructor
     * 
     * @param array $vars Module variables
     */
    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->apiService = new NicsrsApiService($this->getApiToken());
        $this->orderService = new OrderService($this->apiService);
    }

    /**
     * Render page based on action
     * 
     * @param string $action Current action
     * @return void
     */
    public function render(string $action): void
    {
        if ($action === 'order' && isset($_GET['id'])) {
            $this->renderDetail((int) $_GET['id']);
        } else {
            $this->renderList();
        }
    }

    /**
     * Render orders list
     * 
     * @return void
     */
    private function renderList(): void
    {
        $page = $this->getCurrentPage();
        $perPage = $this->getItemsPerPage();
        $status = isset($_GET['status']) ? $this->sanitize($_GET['status']) : '';
        $search = isset($_GET['search']) ? $this->sanitize($_GET['search']) : '';

        // Check if filtering by "expiring" (special dynamic filter)
        $isExpiringFilter = ($status === 'expiring');
        
        // Build query
        $query = Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->select([
                'o.id',
                'o.userid',
                'o.serviceid',
                'o.remoteid',
                'o.certtype',
                'o.status',
                'o.provisiondate',
                'o.completiondate',
                'o.configdata',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
                'h.domainstatus as service_status',
            ]);

        // Apply status filter (but not for 'expiring' - that's handled separately)
        if ($status && !$isExpiringFilter) {
            // Map short status names to actual database values
            $statusMap = [
                'awaiting' => 'Awaiting Configuration',
                'draft' => 'draft',
                'pending' => 'pending',
                'complete' => 'complete',
                'cancelled' => 'cancelled',
                'revoked' => 'revoked',
                'expired' => 'expired',
            ];
            
            // Check if it's a short name that needs mapping
            if (isset($statusMap[strtolower($status)])) {
                $query->where('o.status', $statusMap[strtolower($status)]);
            } else {
                // Direct match (for full status names like "Awaiting Configuration")
                $query->where('o.status', $status);
            }
        }

        // For expiring filter, only show complete certificates
        if ($isExpiringFilter) {
            $query->where('o.status', 'complete');
        }

        if ($search) {
            // Escape special characters for LIKE query
            $searchEscaped = addcslashes($search, '%_');
            
            $query->where(function ($q) use ($search, $searchEscaped) {
                // Search by Order ID (exact or partial)
                if (is_numeric($search)) {
                    $q->where('o.id', $search)
                      ->orWhere('o.id', 'like', "%{$search}%");
                }
                
                // Search by Remote ID
                $q->orWhere('o.remoteid', 'like', "%{$searchEscaped}%");
                
                // Search by Client info
                $q->orWhere('c.firstname', 'like', "%{$searchEscaped}%")
                  ->orWhere('c.lastname', 'like', "%{$searchEscaped}%")
                  ->orWhere('c.email', 'like', "%{$searchEscaped}%")
                  ->orWhere('c.companyname', 'like', "%{$searchEscaped}%");
                
                // Search by Certificate type / Product code
                $q->orWhere('o.certtype', 'like', "%{$searchEscaped}%");
                
                // Search by Domain (stored in configdata JSON)
                // Domain is at: configdata->domainInfo[0]->domainName
                $q->orWhere('o.configdata', 'like', '%"domainName":"' . $searchEscaped . '%')
                  ->orWhere('o.configdata', 'like', '%"domainName":"%' . $searchEscaped . '%');
                
                // Also search for domain in applyReturn (some certs store it there)
                $q->orWhere('o.configdata', 'like', '%"domain":"' . $searchEscaped . '%')
                  ->orWhere('o.configdata', 'like', '%"domain":"%' . $searchEscaped . '%');
            });
        }

        // Get all orders for special filtering
        $allOrders = $query->orderBy('o.id', 'desc')->get();
        
        // Process orders and apply expiring filter if needed
        $processedOrders = [];
        $now = time();
        $expiringThreshold = strtotime('+' . self::EXPIRING_DAYS . ' days');
        
        foreach ($allOrders as $order) {
            $config = json_decode($order->configdata, true) ?: [];
            
            $domain = 'N/A';
            if (isset($config['domainInfo'][0]['domainName'])) {
                $domain = $config['domainInfo'][0]['domainName'];
            }
            
            $endDate = null;
            $endDateTimestamp = null;
            if (isset($config['applyReturn']['endDate'])) {
                $endDate = $config['applyReturn']['endDate'];
                $endDateTimestamp = strtotime($endDate);
            }

            // For expiring filter, check if certificate is expiring within threshold
            if ($isExpiringFilter) {
                // Skip if no end date or already expired or not expiring soon
                if (!$endDateTimestamp || $endDateTimestamp <= $now || $endDateTimestamp > $expiringThreshold) {
                    continue;
                }
            }

            $clientName = trim($order->firstname . ' ' . $order->lastname);
            
            $processedOrders[] = [
                'id' => $order->id,
                'userid' => $order->userid,
                'serviceid' => $order->serviceid,
                'remoteid' => $order->remoteid,
                'domain' => $domain,
                'certtype' => $order->certtype,
                'status' => $order->status,
                'provisiondate' => $this->formatDbDate($order->provisiondate),
                'completiondate' => $this->formatDbDate($order->completiondate),
                'end_date' => $endDate,
                'end_date_timestamp' => $endDateTimestamp,
                'client_name' => $clientName ?: 'Unknown',
                'client_email' => $order->email,
                'companyname' => $order->companyname,
                'service_status' => $order->service_status,
            ];
        }

        // Sort by expiring date for expiring filter
        if ($isExpiringFilter) {
            usort($processedOrders, function($a, $b) {
                return ($a['end_date_timestamp'] ?? 0) - ($b['end_date_timestamp'] ?? 0);
            });
        }

        // Get total (after filtering)
        $total = count($processedOrders);
        
        // Apply pagination manually for expiring filter
        $processedOrders = array_slice($processedOrders, ($page - 1) * $perPage, $perPage);

        // Get status counts for filters
        $rawStatusCounts = Capsule::table('nicsrs_sslorders')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Normalize status keys
        $statusCounts = [];
        foreach ($rawStatusCounts as $dbStatus => $count) {
            $normalizedStatus = $this->normalizeStatus($dbStatus);
            if (isset($statusCounts[$normalizedStatus])) {
                $statusCounts[$normalizedStatus] += $count;
            } else {
                $statusCounts[$normalizedStatus] = $count;
            }
        }

        // Calculate expiring count (certificates expiring within 30 days)
        $expiringCount = $this->getExpiringCount(self::EXPIRING_DAYS);

        // Create pagination
        $paginationParams = [];
        if ($status) $paginationParams['status'] = $status;
        if ($search) $paginationParams['search'] = $search;
        
        $pagination = new Pagination(
            $total, 
            $perPage, 
            $page, 
            $this->modulelink . '&action=orders',
            $paginationParams
        );

        $data = [
            'orders' => $processedOrders,
            'statusCounts' => $statusCounts,
            'expiringCount' => $expiringCount,
            'currentStatus' => $status,
            'search' => $search,
            'pagination' => $pagination,
            'total' => $total,
        ];

        $this->includeTemplate('orders/list', $data);
    }

    /**
     * Get count of certificates expiring within specified days
     * 
     * @param int $days Number of days
     * @return int Count of expiring certificates
     */
    private function getExpiringCount(int $days = 30): int
    {
        $count = 0;
        $now = time();
        $threshold = strtotime("+{$days} days");
        
        $orders = Capsule::table('nicsrs_sslorders')
            ->where('status', 'complete')
            ->select(['configdata'])
            ->get();
            
        foreach ($orders as $order) {
            $config = json_decode($order->configdata, true);
            if (isset($config['applyReturn']['endDate'])) {
                $endDate = strtotime($config['applyReturn']['endDate']);
                // Count only if: has end date, not yet expired, and within threshold
                if ($endDate && $endDate > $now && $endDate <= $threshold) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Normalize status string for consistent display and filtering
     * Maps database values to short lowercase keys
     * 
     * @param string $status Status from database
     * @return string Normalized status key
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        
        // Map full names to short names
        $normalizeMap = [
            'awaiting configuration' => 'awaiting',
            'awaiting' => 'awaiting',
            'pending validation' => 'pending',
            'pending' => 'pending',
            'complete' => 'complete',
            'completed' => 'complete',
            'issued' => 'complete',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'revoked' => 'revoked',
            'draft' => 'draft',
            'expired' => 'expired',
        ];
        
        return $normalizeMap[$status] ?? $status;
    }

    /**
     * Get database status value from normalized key
     * 
     * @param string $normalizedStatus Normalized status key
     * @return string Database status value
     */
    private function getDatabaseStatus(string $normalizedStatus): string
    {
        $dbStatusMap = [
            'awaiting' => 'Awaiting Configuration',
            'pending' => 'pending',
            'complete' => 'complete',
            'cancelled' => 'cancelled',
            'revoked' => 'revoked',
            'draft' => 'draft',
            'expired' => 'expired',
        ];
        
        return $dbStatusMap[strtolower($normalizedStatus)] ?? $normalizedStatus;
    }

    /**
     * Render order detail view
     * 
     * @param int $orderId Order ID
     * @return void
     */
    private function renderDetail(int $orderId): void
    {
        // Get order with client info AND full service details
        $order = Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select([
                // SSL Order fields
                'o.*',
                
                // Client fields
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
                
                // WHMCS Service fields (tblhosting) - UPDATED
                'h.domain as service_domain',
                'h.domainstatus as service_status',
                'h.regdate as service_regdate',
                'h.firstpaymentamount as service_firstpaymentamount',
                'h.amount as service_amount',
                'h.billingcycle as service_billingcycle',
                'h.nextduedate as service_nextduedate',
                'h.nextinvoicedate as service_nextinvoicedate',
                'h.paymentmethod as service_paymentmethod',
                'h.notes as service_notes',
                
                // WHMCS Product fields (tblproducts)
                'p.name as whmcs_product_name',
                'p.configoption1 as whmcs_product_code',
            ])
            ->where('o.id', $orderId)
            ->first();

        if (!$order) {
            echo '<div class="alert alert-danger">Order not found</div>';
            echo '<a href="' . $this->modulelink . '&action=orders" class="btn btn-default">Back to Orders</a>';
            return;
        }

        // Parse config data
        $configData = json_decode($order->configdata, true) ?: [];
        
        // Get activity logs for this order
        $activityLogs = Capsule::table('mod_nicsrs_activity_log as l')
            ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
            ->select(['l.*', 'a.username', 'a.firstname as admin_firstname', 'a.lastname as admin_lastname'])
            ->where('l.entity_type', 'order')
            ->where('l.entity_id', $orderId)
            ->orderBy('l.created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        // Extract domain info
        $domains = [];
        if (isset($configData['domainInfo']) && is_array($configData['domainInfo'])) {
            foreach ($configData['domainInfo'] as $domainInfo) {
                $domains[] = [
                    'domain' => $domainInfo['domainName'] ?? 'N/A',
                    'dcv_method' => $domainInfo['dcvMethod'] ?? 'N/A',
                    'dcv_email' => $domainInfo['dcvEmail'] ?? '',
                    'is_verified' => isset($domainInfo['isVerified']) ? $domainInfo['isVerified'] : false,
                ];
            }
        }

        // Extract certificate info
        $certInfo = [];
        if (isset($configData['applyReturn'])) {
            $certInfo = [
                'cert_id' => $configData['applyReturn']['certId'] ?? '',
                'vendor_id' => $configData['applyReturn']['vendorId'] ?? '',
                'vendor_cert_id' => $configData['applyReturn']['vendorCertId'] ?? '',
                'begin_date' => $configData['applyReturn']['beginDate'] ?? null,
                'end_date' => $configData['applyReturn']['endDate'] ?? null,
                'has_certificate' => !empty($configData['applyReturn']['certificate']),
                'has_ca' => !empty($configData['applyReturn']['caCertificate']),
                'has_jks' => !empty($configData['applyReturn']['jks']),
                'has_pkcs12' => !empty($configData['applyReturn']['pkcs12']),
            ];
        }

        // Build client name
        $clientName = trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? ''));
        if (empty($clientName)) {
            $clientName = 'Unknown';
        }

        // Get primary domain
        $primaryDomain = '-';
        if (!empty($domains)) {
            $primaryDomain = $domains[0]['domain'];
        } elseif (!empty($order->service_domain)) {
            $primaryDomain = $order->service_domain;
        }

        // Calculate renewal due (30 days before expiry)
        $renewalDue = null;
        if (!empty($certInfo['end_date'])) {
            $endTimestamp = strtotime($certInfo['end_date']);
            if ($endTimestamp) {
                $renewalDue = date('Y-m-d', $endTimestamp - (30 * 86400));
            }
        }

        $data = [
            'order' => $order,
            'config' => $configData,
            'domains' => $domains,
            'certInfo' => $certInfo,
            'activityLogs' => $activityLogs,
            'clientName' => $clientName,
            'primaryDomain' => $primaryDomain,
            'renewalDue' => $renewalDue,
        ];

        $this->includeTemplate('orders/detail', $data);
    }
    
    /**
     * Helper to format database date (handle 0000-00-00)
     */
    private function formatDbDate($date): ?string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }
        return $date;
    }

    /**
     * Check if date is valid (not empty or 0000-00-00)
     */
    private function isValidDate($date): bool
    {
        return !empty($date) && $date !== '0000-00-00' && $date !== '0000-00-00 00:00:00';
    }

    /**
     * Fix dates for existing orders
     */
    private function fixOrderDates(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $configData = json_decode($order->configdata, true) ?: [];
            $updateData = [];
            $changes = [];

            // Fix provisiondate
            if (!$this->isValidDate($order->provisiondate)) {
                // Try to get from configdata or use current date
                $provDate = date('Y-m-d');
                if (isset($configData['importedAt'])) {
                    $provDate = date('Y-m-d', strtotime($configData['importedAt']));
                } elseif (isset($configData['linkedAt'])) {
                    $provDate = date('Y-m-d', strtotime($configData['linkedAt']));
                }
                $updateData['provisiondate'] = $provDate;
                $changes[] = "provisiondate set to {$provDate}";
            }

            // Fix completiondate for complete orders
            if ($order->status === 'complete' && !$this->isValidDate($order->completiondate)) {
                $compDate = date('Y-m-d H:i:s');
                if (isset($configData['applyReturn']['beginDate'])) {
                    $compDate = $configData['applyReturn']['beginDate'];
                    if (strlen($compDate) === 10) {
                        $compDate .= ' 00:00:00';
                    }
                }
                $updateData['completiondate'] = $compDate;
                $changes[] = "completiondate set to {$compDate}";
            }

            if (empty($updateData)) {
                return $this->jsonSuccess('No date fixes needed');
            }

            Capsule::table('nicsrs_sslorders')
                ->where('id', $orderId)
                ->update($updateData);

            $this->logger->log('fix_dates', 'order', $orderId, null, json_encode($changes));

            return $this->jsonSuccess('Dates fixed: ' . implode(', ', $changes), [
                'changes' => $changes,
            ]);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
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
        $orderId = isset($post['order_id']) ? (int) $post['order_id'] : 0;

        switch ($action) {
            case 'refresh_status':
                return $this->refreshStatus($orderId);

            case 'edit_order':
                $remoteId = isset($post['remote_id']) ? trim($post['remote_id']) : null;
                $serviceId = isset($post['service_id']) ? (int) $post['service_id'] : null;
                return $this->editOrder($orderId, $remoteId, $serviceId);

            case 'delete_order':
                return $this->deleteOrder($orderId);

            case 'cancel':
                $reason = isset($post['reason']) ? $this->sanitize($post['reason']) : '';
                return $this->cancelOrder($orderId, $reason);

            case 'revoke':
                $reason = isset($post['reason']) ? $this->sanitize($post['reason']) : '';
                return $this->revokeOrder($orderId, $reason);

            case 'reissue':
                return $this->reissueOrder($orderId, $post);

            case 'renew':
                return $this->renewOrder($orderId);

            case 'resend_dcv':
                $domain = isset($post['domain']) ? $this->sanitize($post['domain']) : '';
                return $this->resendDcv($orderId, $domain);
            
            case 'download_cert':
                return $this->downloadCertificate($orderId);

            case 'download_jks':
                return $this->downloadJks($orderId);

            case 'download_pkcs12':
                return $this->downloadPkcs12($orderId);

            default:
                return $this->jsonError('Unknown action');
        }
    }

    /**
     * Refresh order status from API
     * 
     * @param int $orderId Order ID
     * @return string JSON response
     */
    private function refreshStatus(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            $result = $this->apiService->collect($order->remoteid);
            
            if ($result['code'] == 1 || $result['code'] == 2) {
                $configData = json_decode($order->configdata, true) ?: [];
                
                // Properly merge API response data
                if (isset($result['data'])) {
                    if (!isset($configData['applyReturn'])) {
                        $configData['applyReturn'] = [];
                    }
                    
                    // Preserve existing data and merge new data
                    $apiData = $result['data'];
                    
                    // Update certificate core data
                    $configData['applyReturn']['certId'] = $order->remoteid;
                    
                    // Vendor tracking fields
                    if (!empty($apiData['vendorId'])) {
                        $configData['applyReturn']['vendorId'] = $apiData['vendorId'];
                    }
                    if (!empty($apiData['vendorCertId'])) {
                        $configData['applyReturn']['vendorCertId'] = $apiData['vendorCertId'];
                    }
                    
                    // Certificate dates
                    if (!empty($apiData['beginDate'])) {
                        $configData['applyReturn']['beginDate'] = $apiData['beginDate'];
                    }
                    if (!empty($apiData['endDate'])) {
                        $configData['applyReturn']['endDate'] = $apiData['endDate'];
                    }
                    if (!empty($apiData['dueDate'])) {
                        $configData['applyReturn']['dueDate'] = $apiData['dueDate'];
                    }
                    if (!empty($apiData['applyTime'])) {
                        $configData['applyReturn']['applyTime'] = $apiData['applyTime'];
                    }
                    
                    // Certificate content
                    if (!empty($apiData['certificate'])) {
                        $configData['applyReturn']['certificate'] = $apiData['certificate'];
                    }
                    if (!empty($apiData['caCertificate'])) {
                        $configData['applyReturn']['caCertificate'] = $apiData['caCertificate'];
                    }
                    if (!empty($apiData['privateKey'])) {
                        $configData['applyReturn']['privateKey'] = $apiData['privateKey'];
                    }
                    
                    // JKS and PKCS12 data (important for download)
                    if (!empty($apiData['jks'])) {
                        $configData['applyReturn']['jks'] = $apiData['jks'];
                    }
                    if (!empty($apiData['jksPass'])) {
                        $configData['applyReturn']['jksPass'] = $apiData['jksPass'];
                    }
                    if (!empty($apiData['pkcs12'])) {
                        $configData['applyReturn']['pkcs12'] = $apiData['pkcs12'];
                    }
                    if (!empty($apiData['pkcsPass'])) {
                        $configData['applyReturn']['pkcsPass'] = $apiData['pkcsPass'];
                    }
                    
                    // DCV validation fields
                    if (!empty($apiData['DCVfileName'])) {
                        $configData['applyReturn']['DCVfileName'] = $apiData['DCVfileName'];
                    }
                    if (!empty($apiData['DCVfileContent'])) {
                        $configData['applyReturn']['DCVfileContent'] = $apiData['DCVfileContent'];
                    }
                    if (!empty($apiData['DCVfilePath'])) {
                        $configData['applyReturn']['DCVfilePath'] = $apiData['DCVfilePath'];
                    }
                    if (!empty($apiData['DCVdnsHost'])) {
                        $configData['applyReturn']['DCVdnsHost'] = $apiData['DCVdnsHost'];
                    }
                    if (!empty($apiData['DCVdnsValue'])) {
                        $configData['applyReturn']['DCVdnsValue'] = $apiData['DCVdnsValue'];
                    }
                    if (!empty($apiData['DCVdnsType'])) {
                        $configData['applyReturn']['DCVdnsType'] = $apiData['DCVdnsType'];
                    }
                    
                    // Process status tracking
                    if (!empty($apiData['application'])) {
                        $configData['applyReturn']['application'] = $apiData['application'];
                    }
                    if (!empty($apiData['dcv'])) {
                        $configData['applyReturn']['dcv'] = $apiData['dcv'];
                    }
                    if (!empty($apiData['issued'])) {
                        $configData['applyReturn']['issued'] = $apiData['issued'];
                    }
                    
                    // Update DCV list if available
                    if (!empty($apiData['dcvList'])) {
                        $configData['domainInfo'] = [];
                        foreach ($apiData['dcvList'] as $dcv) {
                            $configData['domainInfo'][] = [
                                'domainName' => $dcv['domainName'] ?? '',
                                'dcvMethod' => $dcv['dcvMethod'] ?? 'EMAIL',
                                'dcvEmail' => $dcv['dcvEmail'] ?? '',
                                'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                                'is_verify' => $dcv['is_verify'] ?? '',
                            ];
                        }
                    }
                }

                // Add last refresh timestamp
                $configData['lastRefresh'] = date('Y-m-d H:i:s');

                // Determine new status
                $newStatus = $order->status;
                if (isset($result['status'])) {
                    $newStatus = strtolower($result['status']);
                } elseif (isset($result['certStatus'])) {
                    $newStatus = strtolower($result['certStatus']);
                }
                
                // Build update data with proper date handling
                $updateData = [
                    'status' => $newStatus,
                    'configdata' => json_encode($configData),
                ];
                
                // Set provisiondate if empty
                if (!$this->isValidDate($order->provisiondate)) {
                    $updateData['provisiondate'] = date('Y-m-d');
                }
                
                // Set completiondate when status is complete
                if ($newStatus === 'complete') {
                    if (!$this->isValidDate($order->completiondate)) {
                        // Use certificate begin date or current date
                        $completionDate = $configData['applyReturn']['beginDate'] ?? date('Y-m-d H:i:s');
                        // Ensure it's datetime format
                        if (strlen($completionDate) === 10) {
                            $completionDate .= ' 00:00:00';
                        }
                        $updateData['completiondate'] = $completionDate;
                    }
                }

                Capsule::table('nicsrs_sslorders')
                    ->where('id', $orderId)
                    ->update($updateData);

                $this->logger->log('refresh_status', 'order', $orderId, $order->status, $newStatus);

                // Prepare response data
                $responseData = [
                    'status' => $newStatus,
                    'old_status' => $order->status,
                    'lastRefresh' => $configData['lastRefresh'],
                ];
                
                // Add cert status info if available
                if (!empty($configData['applyReturn'])) {
                    $responseData['has_certificate'] = !empty($configData['applyReturn']['certificate']);
                    $responseData['has_jks'] = !empty($configData['applyReturn']['jks']);
                    $responseData['has_pkcs12'] = !empty($configData['applyReturn']['pkcs12']);
                    $responseData['vendor_id'] = $configData['applyReturn']['vendorId'] ?? null;
                    $responseData['vendor_cert_id'] = $configData['applyReturn']['vendorCertId'] ?? null;
                }

                return $this->jsonSuccess('Status refreshed successfully', $responseData);
            }

            throw new \Exception($result['msg'] ?? 'API error (code: ' . $result['code'] . ')');
            
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Edit order - update remote ID and/or service ID
     * 
     * @param int $orderId Order ID
     * @param string|null $newRemoteId New remote ID (null to skip)
     * @param int|null $newServiceId New service ID (null to skip)
     * @return string JSON response
     */
    private function editOrder(int $orderId, ?string $newRemoteId, ?int $newServiceId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $updateData = [];
            $changes = [];

            // Process remote ID change
            if ($newRemoteId !== null) {
                $newRemoteId = trim($newRemoteId);
                
                // Allow empty string to clear the remote ID
                if ($newRemoteId !== ($order->remoteid ?? '')) {
                    // Validate format if not empty
                    if (!empty($newRemoteId) && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $newRemoteId)) {
                        throw new \Exception('Invalid Remote ID format. Only alphanumeric, dash, underscore, and dot allowed.');
                    }
                    
                    // Check if remote ID already exists for another order
                    if (!empty($newRemoteId)) {
                        $existingOrder = Capsule::table('nicsrs_sslorders')
                            ->where('remoteid', $newRemoteId)
                            ->where('id', '!=', $orderId)
                            ->first();
                        
                        if ($existingOrder) {
                            throw new \Exception("Remote ID already used by Order #{$existingOrder->id}");
                        }
                    }
                    
                    $updateData['remoteid'] = $newRemoteId ?: null;
                    $changes['remoteid'] = [
                        'old' => $order->remoteid ?: '(empty)',
                        'new' => $newRemoteId ?: '(empty)'
                    ];
                }
            }

            // Process service ID change
            if ($newServiceId !== null && $newServiceId != (int) $order->serviceid) {
                if ($newServiceId > 0) {
                    // Validate service exists
                    $service = Capsule::table('tblhosting as h')
                        ->leftJoin('tblclients as c', 'h.userid', '=', 'c.id')
                        ->select(['h.*', 'c.firstname', 'c.lastname'])
                        ->where('h.id', $newServiceId)
                        ->first();
                    
                    if (!$service) {
                        throw new \Exception("WHMCS Service ID #{$newServiceId} not found");
                    }
                    
                    // Check if service already linked to another order
                    $existingOrder = Capsule::table('nicsrs_sslorders')
                        ->where('serviceid', $newServiceId)
                        ->where('id', '!=', $orderId)
                        ->first();
                    
                    if ($existingOrder) {
                        throw new \Exception("Service #{$newServiceId} is already linked to Order #{$existingOrder->id}");
                    }
                    
                    // Update user ID from service
                    $updateData['userid'] = $service->userid;
                } else {
                    // Setting to 0 means unlinking
                    $updateData['userid'] = 0;
                }
                
                $updateData['serviceid'] = $newServiceId;
                $changes['serviceid'] = [
                    'old' => $order->serviceid ?: 0,
                    'new' => $newServiceId
                ];
            }

            // Check if there are changes
            if (empty($updateData)) {
                return $this->jsonSuccess('No changes to save');
            }

            // Perform update
            Capsule::table('nicsrs_sslorders')
                ->where('id', $orderId)
                ->update($updateData);

            // Log activity
            $this->logger->log('edit_order', 'order', $orderId, null, json_encode([
                'changes' => $changes,
                'updated_by' => $this->adminId
            ]));

            return $this->jsonSuccess('Order updated successfully', [
                'changes' => $changes,
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Delete order from local database
     * 
     * WARNING: This only deletes the local record, NOT the certificate on NicSRS
     * 
     * @param int $orderId Order ID
     * @return string JSON response
     */
    private function deleteOrder(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Get config data for logging
            $configData = json_decode($order->configdata, true) ?: [];

            // Store complete order info for audit log
            $orderInfo = [
                'id' => $order->id,
                'remoteid' => $order->remoteid,
                'serviceid' => $order->serviceid,
                'userid' => $order->userid,
                'certtype' => $order->certtype,
                'status' => $order->status,
                'domain' => $configData['domainInfo'][0]['domainName'] ?? 
                        ($configData['applyReturn']['domain'] ?? 'N/A'),
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $this->adminId
            ];

            // Also delete related activity logs (optional - comment out to keep logs)
            // Capsule::table('mod_nicsrs_activity_log')
            //     ->where('entity_type', 'order')
            //     ->where('entity_id', $orderId)
            //     ->delete();

            // Delete the order
            $deleted = Capsule::table('nicsrs_sslorders')
                ->where('id', $orderId)
                ->delete();

            if (!$deleted) {
                throw new \Exception('Failed to delete order');
            }

            // Log activity (this creates a log entry even though the order is deleted)
            $this->logger->log('delete_order', 'order', $orderId, $order->status, json_encode($orderInfo));

            return $this->jsonSuccess('Order deleted successfully', [
                'deleted_order' => $orderInfo
            ]);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Cancel certificate order
     * 
     * @param int $orderId Order ID
     * @param string $reason Cancellation reason
     * @return string JSON response
     */
    private function cancelOrder(int $orderId, string $reason): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            // Check if order can be cancelled (not already cancelled/revoked/terminated)
            $terminalStatuses = ['cancelled', 'revoked', 'terminated', 'expired'];
            if (in_array(strtolower($order->status), $terminalStatuses)) {
                throw new \Exception('Order is already in terminal state: ' . $order->status);
            }

            // Set default reason if empty (API requires reason)
            if (empty($reason)) {
                $reason = 'Cancelled by administrator';
            }

            $result = $this->apiService->cancel($order->remoteid, $reason);
            
            if ($result['code'] == 1) {
                Capsule::table('nicsrs_sslorders')
                    ->where('id', $orderId)
                    ->update([
                        'status' => 'cancelled',
                        'configdata' => json_encode(array_merge(
                            json_decode($order->configdata, true) ?: [],
                            [
                                'cancelledAt' => date('Y-m-d H:i:s'),
                                'cancelledBy' => $_SESSION['adminid'] ?? 'admin',
                                'cancelReason' => $reason,
                            ]
                        )),
                    ]);

                $this->logger->log('cancel', 'order', $orderId, $order->status, 'cancelled');

                return $this->jsonSuccess('Certificate order cancelled successfully');
            }

            // API returned error
            $errorMsg = $result['msg'] ?? $result['errors'] ?? 'Cancel failed';
            if (is_array($errorMsg)) {
                $errorMsg = implode(', ', $errorMsg);
            }
            
            throw new \Exception($errorMsg);
            
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Revoke certificate
     * 
     * @param int $orderId Order ID
     * @param string $reason Revocation reason
     * @return string JSON response
     */
    private function revokeOrder(int $orderId, string $reason): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            // Only issued certificates can be revoked
            if (strtolower($order->status) !== 'complete') {
                throw new \Exception('Only issued certificates can be revoked. Current status: ' . $order->status);
            }

            // Set default reason if empty (API requires reason)
            if (empty($reason)) {
                $reason = 'Revoked by administrator';
            }

            $result = $this->apiService->revoke($order->remoteid, $reason);
            
            if ($result['code'] == 1) {
                Capsule::table('nicsrs_sslorders')
                    ->where('id', $orderId)
                    ->update([
                        'status' => 'revoked',
                        'configdata' => json_encode(array_merge(
                            json_decode($order->configdata, true) ?: [],
                            [
                                'revokedAt' => date('Y-m-d H:i:s'),
                                'revokedBy' => $_SESSION['adminid'] ?? 'admin',
                                'revokeReason' => $reason,
                            ]
                        )),
                    ]);

                $this->logger->log('revoke', 'order', $orderId, 'complete', 'revoked');

                return $this->jsonSuccess('Certificate revoked successfully');
            }

            // API returned error
            $errorMsg = $result['msg'] ?? $result['errors'] ?? 'Revoke failed';
            if (is_array($errorMsg)) {
                $errorMsg = implode(', ', $errorMsg);
            }
            
            throw new \Exception($errorMsg);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Reissue certificate
     * 
     * @param int $orderId Order ID
     * @param array $data Reissue data (csr, domainInfo)
     * @return string JSON response
     */
    private function reissueOrder(int $orderId, array $data): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            // Only complete certificates can be reissued
            if ($order->status !== 'complete') {
                throw new \Exception('Only issued certificates can be reissued');
            }

            // Prepare reissue data
            $reissueData = [];
            
            if (!empty($data['csr'])) {
                $reissueData['csr'] = $data['csr'];
            }
            
            if (!empty($data['domainInfo'])) {
                $domainInfo = json_decode($data['domainInfo'], true);
                if ($domainInfo) {
                    $reissueData['domainInfo'] = $domainInfo;
                }
            }

            $result = $this->apiService->reissue($order->remoteid, $reissueData);
            
            if ($result['code'] == 1) {
                // Update config data
                $configData = json_decode($order->configdata, true) ?: [];
                $configData['replaceTimes'] = ($configData['replaceTimes'] ?? 0) + 1;
                
                if (!empty($reissueData['csr'])) {
                    $configData['csr'] = $reissueData['csr'];
                }
                
                Capsule::table('nicsrs_sslorders')
                    ->where('id', $orderId)
                    ->update([
                        'status' => 'pending',
                        'configdata' => json_encode($configData),
                    ]);

                $this->logger->log('reissue', 'order', $orderId, 'complete', 'pending');

                return $this->jsonSuccess('Reissue request submitted successfully');
            }

            throw new \Exception($result['msg'] ?? 'Reissue failed');
            
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Renew certificate
     * 
     * @param int $orderId Order ID
     * @return string JSON response
     */
    private function renewOrder(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            $result = $this->apiService->renew($order->remoteid);
            
            if ($result['code'] == 1) {
                $this->logger->log('renew', 'order', $orderId, null, json_encode([
                    'newCertId' => $result['data']['certId'] ?? null,
                ]));

                return $this->jsonSuccess('Renewal submitted successfully', [
                    'newCertId' => $result['data']['certId'] ?? null,
                ]);
            }

            throw new \Exception($result['msg'] ?? 'Renewal failed');
            
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Resend DCV email
     * 
     * @param int $orderId Order ID
     * @param string $domain Domain name
     * @return string JSON response
     */
    private function resendDcv(int $orderId, string $domain): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            if (empty($order->remoteid)) {
                throw new \Exception('No remote certificate ID');
            }

            if (empty($domain)) {
                throw new \Exception('Domain is required');
            }

            $result = $this->apiService->resendDcv($order->remoteid, $domain);
            
            if ($result['code'] == 1) {
                $this->logger->log('resend_dcv', 'order', $orderId, null, json_encode([
                    'domain' => $domain,
                ]));

                return $this->jsonSuccess('DCV email resent successfully');
            }

            throw new \Exception($result['msg'] ?? 'Resend DCV failed');
            
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Download certificate as ZIP file
     * Creates ZIP with Apache, Nginx, IIS, Tomcat formats
     * 
     * @param int $orderId Order ID
     * @return string JSON response with base64 encoded ZIP
     */
    private function downloadCertificate(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $configData = json_decode($order->configdata, true) ?: [];
            
            // Check if certificate exists
            if (empty($configData['applyReturn']['certificate'])) {
                throw new \Exception('Certificate not yet issued');
            }

            $certificate = $configData['applyReturn']['certificate'];
            $caCertificate = $configData['applyReturn']['caCertificate'] ?? '';
            $privateKey = $configData['applyReturn']['privateKey'] ?? '';
            
            // Get primary domain for filename
            $primaryDomain = 'certificate';
            if (!empty($configData['domainInfo'][0]['domainName'])) {
                $primaryDomain = $configData['domainInfo'][0]['domainName'];
            }
            
            // Create ZIP file
            $result = $this->createCertificateZip($primaryDomain, $certificate, $caCertificate, $privateKey);
            
            if ($result['status'] === 1) {
                $this->logger->log('download_cert', 'order', $orderId, null, 'Certificate downloaded');
                
                return $this->jsonSuccess('Certificate ready', [
                    'content' => $result['data']['content'],
                    'name' => $result['data']['name'],
                ]);
            }
            
            throw new \Exception($result['msg'] ?? 'Failed to create certificate package');

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Download certificate as JKS file
     * 
     * @param int $orderId Order ID
     * @return string JSON response with base64 encoded JKS and password
     */
    private function downloadJks(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $configData = json_decode($order->configdata, true) ?: [];
            
            // Check if JKS exists
            if (empty($configData['applyReturn']['jks'])) {
                throw new \Exception('JKS file not available for this certificate');
            }

            $jksData = $configData['applyReturn']['jks'];
            $jksPass = $configData['applyReturn']['jksPass'] ?? '';
            
            // Get primary domain for filename
            $primaryDomain = 'certificate';
            if (!empty($configData['domainInfo'][0]['domainName'])) {
                $primaryDomain = $configData['domainInfo'][0]['domainName'];
            }
            
            // Sanitize filename
            $filename = str_replace('*', 'wildcard', str_replace('.', '_', $primaryDomain)) . '.jks';
            
            $this->logger->log('download_jks', 'order', $orderId, null, 'JKS downloaded');

            return $this->jsonSuccess('JKS ready', [
                'content' => $jksData, // Already base64 encoded from API
                'name' => $filename,
                'password' => $jksPass,
                'format' => 'jks',
            ]);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Download certificate as PKCS12/PFX file
     * 
     * @param int $orderId Order ID
     * @return string JSON response with base64 encoded PKCS12 and password
     */
    private function downloadPkcs12(int $orderId): string
    {
        try {
            $order = Capsule::table('nicsrs_sslorders')->find($orderId);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $configData = json_decode($order->configdata, true) ?: [];
            
            // Check if PKCS12 exists
            if (empty($configData['applyReturn']['pkcs12'])) {
                throw new \Exception('PKCS12/PFX file not available for this certificate');
            }

            $pkcs12Data = $configData['applyReturn']['pkcs12'];
            $pkcsPass = $configData['applyReturn']['pkcsPass'] ?? '';
            
            // Get primary domain for filename
            $primaryDomain = 'certificate';
            if (!empty($configData['domainInfo'][0]['domainName'])) {
                $primaryDomain = $configData['domainInfo'][0]['domainName'];
            }
            
            // Sanitize filename
            $filename = str_replace('*', 'wildcard', str_replace('.', '_', $primaryDomain)) . '.pfx';
            
            $this->logger->log('download_pkcs12', 'order', $orderId, null, 'PKCS12 downloaded');

            return $this->jsonSuccess('PKCS12 ready', [
                'content' => $pkcs12Data, // Already base64 encoded from API
                'name' => $filename,
                'password' => $pkcsPass,
                'format' => 'pkcs12',
            ]);

        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Create ZIP file with certificate in multiple formats
     * Similar to nicsrsFunc::zipCert() in server module
     * 
     * @param string $primaryDomain Primary domain name
     * @param string $certificate Certificate content
     * @param string $caCertificate CA Certificate content
     * @param string $privateKey Private key (if available)
     * @return array Result with status and data
     */
    private function createCertificateZip(string $primaryDomain, string $certificate, string $caCertificate, string $privateKey = ''): array
    {
        // Sanitize filename
        $certFilename = str_replace('*', 'WILDCARD', str_replace('.', '_', $primaryDomain));
        $tempDir = sys_get_temp_dir() . '/nicsrs_cert_' . sha1(time() . $primaryDomain);
        $zipFilename = $certFilename . '.zip';
        $zipPath = $tempDir . '.zip';

        try {
            // Create temp directories
            if (!mkdir($tempDir, 0777, true)) {
                throw new \Exception('Cannot create temp directory');
            }
            mkdir($tempDir . '/Apache', 0777, true);
            mkdir($tempDir . '/Nginx', 0777, true);

            // === Apache Format ===
            // .crt file (certificate only)
            file_put_contents($tempDir . '/Apache/' . $certFilename . '.crt', trim($certificate));
            
            // .ca-bundle file (CA certificate)
            if (!empty($caCertificate)) {
                file_put_contents($tempDir . '/Apache/' . $certFilename . '.ca-bundle', trim($caCertificate));
            }
            
            // .key file (private key if available)
            if (!empty($privateKey)) {
                file_put_contents($tempDir . '/Apache/' . $certFilename . '.key', trim($privateKey));
            }

            // === Nginx Format ===
            // .pem file (certificate + CA certificate combined)
            $pemContent = trim($certificate) . PHP_EOL;
            if (!empty($caCertificate)) {
                $pemContent .= trim($caCertificate);
            }
            file_put_contents($tempDir . '/Nginx/' . $certFilename . '.pem', $pemContent);
            
            // .key file for Nginx
            if (!empty($privateKey)) {
                file_put_contents($tempDir . '/Nginx/' . $certFilename . '.key', trim($privateKey));
            }

            // === Create ZIP ===
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Cannot create ZIP file');
            }

            // Add Apache files
            $zip->addEmptyDir('Apache');
            $zip->addFile($tempDir . '/Apache/' . $certFilename . '.crt', 'Apache/' . $certFilename . '.crt');
            if (!empty($caCertificate)) {
                $zip->addFile($tempDir . '/Apache/' . $certFilename . '.ca-bundle', 'Apache/' . $certFilename . '.ca-bundle');
            }
            if (!empty($privateKey)) {
                $zip->addFile($tempDir . '/Apache/' . $certFilename . '.key', 'Apache/' . $certFilename . '.key');
            }

            // Add Nginx files
            $zip->addEmptyDir('Nginx');
            $zip->addFile($tempDir . '/Nginx/' . $certFilename . '.pem', 'Nginx/' . $certFilename . '.pem');
            if (!empty($privateKey)) {
                $zip->addFile($tempDir . '/Nginx/' . $certFilename . '.key', 'Nginx/' . $certFilename . '.key');
            }

            // Add README
            $readme = "SSL Certificate Package for: {$primaryDomain}\n";
            $readme .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $readme .= "=== Apache Installation ===\n";
            $readme .= "SSLCertificateFile /path/to/{$certFilename}.crt\n";
            $readme .= "SSLCertificateKeyFile /path/to/{$certFilename}.key\n";
            $readme .= "SSLCertificateChainFile /path/to/{$certFilename}.ca-bundle\n\n";
            $readme .= "=== Nginx Installation ===\n";
            $readme .= "ssl_certificate /path/to/{$certFilename}.pem;\n";
            $readme .= "ssl_certificate_key /path/to/{$certFilename}.key;\n\n";
            $readme .= "Generated by HVN GROUP SSL System -  (https://hvn.vn)\n";
            
            $zip->addFromString('README.txt', $readme);
            
            $zip->close();

            // Read ZIP content
            $zipContent = file_get_contents($zipPath);
            $base64Content = base64_encode($zipContent);

            // Cleanup
            $this->deleteDirectory($tempDir);
            @unlink($zipPath);

            return [
                'status' => 1,
                'data' => [
                    'content' => $base64Content,
                    'name' => $zipFilename,
                ]
            ];

        } catch (\Exception $e) {
            // Cleanup on error
            $this->deleteDirectory($tempDir);
            @unlink($zipPath);
            
            return [
                'status' => 0,
                'msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recursively delete directory
     * 
     * @param string $dir Directory path
     * @return bool
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

}