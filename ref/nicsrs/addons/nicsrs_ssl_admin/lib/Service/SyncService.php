<?php
/**
 * Sync Service
 * 
 * Handles automatic synchronization of SSL certificates and products
 * with the NicSRS API. Supports scheduled sync via WHMCS cron and
 * manual sync triggers from admin interface.
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 * @version    1.2.2
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;

class SyncService
{
    /**
     * @var array Module settings cache
     */
    private $settings = [];
    
    /**
     * @var NicsrsApiService|null API service instance
     */
    private $apiService = null;
    
    /**
     * @var ActivityLogger|null Logger instance
     */
    private $logger = null;
    
    /**
     * @var int Batch size for processing certificates
     */
    private $batchSize = 50;
    
    /**
     * @var int Maximum API retries
     */
    private $maxRetries = 3;
    
    /**
     * @var array Certificate statuses that need syncing (pending → complete)
     */
    private const PENDING_STATUSES = [
        'pending',
        'processing',
        'awaiting_issuance',
        'awaiting',
        'draft',
        'awaiting configuration',
    ];
    
    /**
     * @var array Certificate statuses that need expiry check
     */
    private const ACTIVE_STATUSES = [
        'complete',
        'active',
        'issued',
    ];
    
    /**
     * @var array Vendor list for product sync
     */
    private const VENDORS = [
        'Sectigo', 
        'Positive',
        'DigiCert', 
        'GlobalSign', 
        'GeoTrust', 
        'Thawte',
        'RapidSSL',
        'sslTrus', 
        'Entrust',
        'BaiduTrust',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadSettings();
        $this->initializeServices();
    }

    /**
     * Load module settings from database
     */
    private function loadSettings(): void
    {
        try {
            $rows = Capsule::table('mod_nicsrs_settings')->get();
            
            foreach ($rows as $row) {
                $this->settings[$row->setting_key] = $row->setting_value;
            }
            
            // Set batch size from settings
            $this->batchSize = (int) ($this->settings['sync_batch_size'] ?? 50);
            $this->batchSize = max(10, min(200, $this->batchSize));
            
        } catch (\Exception $e) {
            $this->logError('Failed to load settings: ' . $e->getMessage());
        }
    }

    /**
     * Initialize required services
     * 
     * Note: When running from cron, there's no admin session.
     * We use adminId = 0 for system/cron operations.
     */
    private function initializeServices(): void
    {
        $basePath = __DIR__ . '/';
        
        // Load API Service
        if (!class_exists(NicsrsApiService::class) && file_exists($basePath . 'NicsrsApiService.php')) {
            require_once $basePath . 'NicsrsApiService.php';
        }
        
        // Load Activity Logger
        if (!class_exists(ActivityLogger::class) && file_exists($basePath . 'ActivityLogger.php')) {
            require_once $basePath . 'ActivityLogger.php';
        }
        
        // Load API Config
        $configPath = dirname($basePath) . '/Config/ApiConfig.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        
        // Initialize API Service
        try {
            $apiToken = '';
            
            // Try to get API token from ApiConfig class
            if (class_exists(\NicsrsAdmin\Config\ApiConfig::class)) {
                $apiToken = \NicsrsAdmin\Config\ApiConfig::getApiToken();
            }
            
            // Fallback: get from settings
            if (empty($apiToken)) {
                $apiToken = $this->settings['api_token'] ?? '';
            }
            
            if (!empty($apiToken)) {
                $this->apiService = new NicsrsApiService($apiToken);
            } else {
                $this->logError('API token not configured');
            }
            
        } catch (\Exception $e) {
            $this->logError('Failed to initialize API service: ' . $e->getMessage());
        }
        
        // Initialize Activity Logger
        // IMPORTANT: When running from cron, there's no admin session
        // Use adminId = 0 for system/cron operations
        try {
            // Try to get admin ID from session (if available)
            $adminId = 0;
            
            if (isset($_SESSION['adminid'])) {
                $adminId = (int) $_SESSION['adminid'];
            }
            
            // Create logger with admin ID (0 = system/cron)
            $this->logger = new ActivityLogger($adminId);
            
        } catch (\Exception $e) {
            // If ActivityLogger fails, set to null and continue
            // Logging is not critical for sync operations
            $this->logger = null;
            $this->logError('Failed to initialize ActivityLogger: ' . $e->getMessage());
        }
    }

    /**
     * Get API token from tbladdonmodules (PRIMARY)
     * 
     * @return string|null
     */
    private function getApiToken(): ?string
    {
        // 1. PRIMARY: Get from tbladdonmodules
        try {
            $addonToken = Capsule::table('tbladdonmodules')
                ->where('module', 'nicsrs_ssl_admin')
                ->where('setting', 'api_token')
                ->value('value');
            
            if (!empty($addonToken)) {
                return $addonToken;
            }
        } catch (\Exception $e) {
            $this->logError('Failed to get API token from tbladdonmodules: ' . $e->getMessage());
        }
        
        // 2. FALLBACK: Try tblservers (server module)
        try {
            $serverConfig = Capsule::table('tblservers')
                ->where('type', 'nicsrs_ssl')
                ->where('disabled', 0)
                ->first();
            
            if ($serverConfig && !empty($serverConfig->password)) {
                return decrypt($serverConfig->password);
            }
        } catch (\Exception $e) {
            $this->logError('Failed to get API token from tblservers: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Run scheduled sync based on settings
     * 
     * @return array Sync results
     */
    public function runScheduledSync(): array
    {
        $results = [
            'status_sync' => null,
            'product_sync' => null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (empty($this->settings['auto_sync_status'])) {
            return $results;
        }

        if (!$this->apiService) {
            $this->logError('Scheduled sync skipped: API service not available');
            return $results;
        }

        if ($this->shouldRunStatusSync()) {
            $results['status_sync'] = $this->syncCertificateStatuses();
        }

        if ($this->shouldRunProductSync()) {
            $results['product_sync'] = $this->syncProducts();
        }

        return $results;
    }

    /**
     * Force sync now (manual trigger)
     * 
     * @param string $type Sync type: 'status', 'products', or 'all'
     * @return array Sync results
     */
    public function forceSyncNow(string $type = 'all'): array
    {
        $results = [
            'status_sync' => null,
            'product_sync' => null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (!$this->apiService) {
            $tokenExists = $this->getApiToken() !== null;
            
            return [
                'error' => 'API service not available. ' . 
                    ($tokenExists 
                        ? 'Failed to initialize API service.' 
                        : 'API token not configured.'),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        try {
            set_time_limit(300);
            
            if ($type === 'status' || $type === 'all') {
                $results['status_sync'] = $this->syncCertificateStatuses();
            }

            if ($type === 'products' || $type === 'all') {
                $results['product_sync'] = $this->syncProducts();
            }
            
        } catch (\Exception $e) {
            $this->logError('Force sync exception: ' . $e->getMessage());
            
            return [
                'error' => 'Sync failed: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        return $results;
    }

    /**
     * Check if status sync should run
     */
    private function shouldRunStatusSync(): bool
    {
        $intervalHours = (int) ($this->settings['sync_interval_hours'] ?? 6);
        $lastSync = $this->settings['last_status_sync'] ?? null;
        
        if (empty($lastSync)) {
            return true;
        }
        
        $lastSyncTime = strtotime($lastSync);
        if (!$lastSyncTime) {
            return true;
        }
        
        return time() >= ($lastSyncTime + ($intervalHours * 3600));
    }

    /**
     * Check if product sync should run
     */
    private function shouldRunProductSync(): bool
    {
        $intervalHours = (int) ($this->settings['product_sync_hours'] ?? 24);
        $lastSync = $this->settings['last_product_sync'] ?? null;
        
        if (empty($lastSync)) {
            return true;
        }
        
        $lastSyncTime = strtotime($lastSync);
        if (!$lastSyncTime) {
            return true;
        }
        
        return time() >= ($lastSyncTime + ($intervalHours * 3600));
    }

    /**
     * Sync certificate statuses from NicSRS API
     * 
     * This syncs PENDING certificates to check if they became COMPLETE
     * 
     * @return array Sync results
     */
    public function syncCertificateStatuses(): array
    {
        $startTime = microtime(true);
        
        $results = [
            'total' => 0,
            'updated' => 0,
            'completed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'skipped' => 0,
            'expired_checked' => 0,
            'expired_updated' => 0,
            'errors' => [],
        ];

        try {
            // PART 1: Sync pending certificates (check if complete)
            $pendingCerts = $this->getPendingCertificates();
            $results['total'] = count($pendingCerts);
            
            foreach ($pendingCerts as $cert) {
                if (empty($cert->remoteid)) {
                    $results['skipped']++;
                    continue;
                }
                
                try {
                    $syncResult = $this->syncSingleCertificate($cert);
                    
                    if ($syncResult['success']) {
                        if ($syncResult['status_changed']) {
                            $results['updated']++;
                            
                            if ($syncResult['new_status'] === 'complete') {
                                $results['completed']++;
                                $this->sendCompletionNotification($cert, $syncResult);
                            }
                        } else {
                            $results['unchanged']++;
                        }
                    } else {
                        $results['failed']++;
                        if (!empty($syncResult['error'])) {
                            $results['errors'][] = "Order #{$cert->id}: " . $syncResult['error'];
                        }
                    }
                    
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Order #{$cert->id}: " . $e->getMessage();
                }
                
                usleep(100000); // 100ms delay
            }

            // PART 2: Check expired certificates
            $expiryResults = $this->checkExpiredCertificates();
            $results['expired_checked'] = $expiryResults['checked'];
            $results['expired_updated'] = $expiryResults['updated'];
            $results['expired_no_enddate'] = $expiryResults['no_enddate'] ?? 0;
            
            if (!empty($expiryResults['errors'])) {
                $results['errors'] = array_merge($results['errors'], $expiryResults['errors']);
            }

            // Update timestamps
            $this->updateSetting('last_status_sync', date('Y-m-d H:i:s'));
            $this->updateSetting('sync_error_count', '0');

            // Log activity
            $duration = round(microtime(true) - $startTime, 2);
            
            if ($this->logger) {
                $this->logger->log('auto_sync_status', 'cron', null, null, json_encode([
                    'pending_total' => $results['total'],
                    'updated' => $results['updated'],
                    'completed' => $results['completed'],
                    'failed' => $results['failed'],
                    'expired_checked' => $results['expired_checked'],
                    'expired_updated' => $results['expired_updated'],
                    'duration_seconds' => $duration,
                ]));
            }

        } catch (\Exception $e) {
            $this->incrementErrorCount();
            $this->logError('Status sync failed: ' . $e->getMessage());
            $results['errors'][] = 'Sync process error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Get PENDING certificates that need status sync
     * 
     * @return array Array of certificate records
     */
    private function getPendingCertificates(): array
    {
        try {
            return Capsule::table('nicsrs_sslorders')
                ->whereIn('status', self::PENDING_STATUSES)
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->orderBy('id', 'asc')
                ->limit($this->batchSize)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->logError('Failed to get pending certificates: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check and update EXPIRED certificates
     * 
     * Certificates that are 'complete' but endDate has passed
     * 
     * @return array Check results
     */
    private function checkExpiredCertificates(): array
    {
        $results = [
            'checked' => 0,
            'updated' => 0,
            'already_expired' => 0,
            'no_enddate' => 0,
            'errors' => [],
        ];
        
        try {
            // Get active certificates - check ALL that are not already 'expired', 'cancelled', 'revoked'
            $excludeStatuses = ['expired', 'cancelled', 'revoked', 'refunded'];
            
            $activeCerts = Capsule::table('nicsrs_sslorders')
                ->whereNotIn('status', $excludeStatuses)
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->limit($this->batchSize)
                ->get();
            
            // Convert to array if needed
            if (!is_array($activeCerts)) {
                $activeCerts = $activeCerts->toArray();
            }
            
            $results['checked'] = count($activeCerts);
            $today = date('Y-m-d');
            $expiredCerts = [];
            
            // Debug log
            $this->logDebug('checkExpiredCertificates', [
                'total_checked' => $results['checked'],
                'today' => $today,
                'exclude_statuses' => $excludeStatuses,
            ]);
            
            foreach ($activeCerts as $cert) {
                // Handle both object and array
                $certId = is_object($cert) ? $cert->id : $cert['id'];
                $certStatus = is_object($cert) ? $cert->status : $cert['status'];
                $configDataRaw = is_object($cert) ? $cert->configdata : $cert['configdata'];
                
                $configData = json_decode($configDataRaw, true) ?: [];
                
                // Try multiple locations for endDate
                $endDate = $configData['applyReturn']['endDate'] 
                    ?? $configData['endDate'] 
                    ?? null;
                
                if (!$endDate) {
                    $results['no_enddate']++;
                    continue;
                }
                
                // Extract date part (API returns Y-m-d H:i:s)
                $endDateOnly = substr($endDate, 0, 10);
                
                // Debug log for each certificate
                $this->logDebug('checkExpiredCertificates_cert', [
                    'cert_id' => $certId,
                    'current_status' => $certStatus,
                    'end_date' => $endDateOnly,
                    'today' => $today,
                    'is_expired' => ($endDateOnly < $today),
                ]);
                
                // Check if certificate has expired (endDate < today)
                if ($endDateOnly < $today) {
                    // Skip if already marked as expired
                    if ($certStatus === 'expired') {
                        $results['already_expired']++;
                        continue;
                    }
                    
                    try {
                        // Update configdata with expiry info
                        $configData['expiredAt'] = date('Y-m-d H:i:s');
                        $configData['lastAutoSync'] = date('Y-m-d H:i:s');
                        $configData['previousStatus'] = $certStatus;
                        
                        // Update status to expired
                        Capsule::table('nicsrs_sslorders')
                            ->where('id', $certId)
                            ->update([
                                'status' => 'expired',
                                'configdata' => json_encode($configData),
                            ]);
                        
                        $results['updated']++;
                        
                        // Store for notification
                        $expiredCerts[] = (object) [
                            'id' => $certId,
                            'remoteid' => is_object($cert) ? $cert->remoteid : $cert['remoteid'],
                            'configdata' => json_encode($configData),
                            'old_status' => $certStatus,
                        ];
                        
                        // Log the expiry
                        if ($this->logger) {
                            $this->logger->log('certificate_expired', 'order', $certId, $certStatus, 'expired');
                        }
                        
                        $this->logDebug('certificate_marked_expired', [
                            'cert_id' => $certId,
                            'old_status' => $certStatus,
                            'end_date' => $endDateOnly,
                        ]);
                        
                    } catch (\Exception $e) {
                        $results['errors'][] = "Expiry update #{$certId}: " . $e->getMessage();
                        $this->logError("Failed to update expired cert #{$certId}: " . $e->getMessage());
                    }
                }
            }
            
            // Send expiry notification if any certificates expired
            if (!empty($expiredCerts)) {
                $this->sendExpiryNotification($expiredCerts);
            }
            
        } catch (\Exception $e) {
            $this->logError('Failed to check expired certificates: ' . $e->getMessage());
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Send expiry notification email to admin
     * 
     * @param array $expiredCerts Array of expired certificate records
     */
    private function sendExpiryNotification(array $expiredCerts): void
    {
        if (empty($expiredCerts) || empty($this->settings['email_on_expiry'])) {
            return;
        }
        
        try {
            $subject = '[NicSRS SSL] ⚠️ Certificates Expired - ' . date('Y-m-d');
            $totalExpired = count($expiredCerts);
            
            // Get system URL for links
            $systemUrl = \WHMCS\Database\Capsule::table('tblconfiguration')
                ->where('setting', 'SystemURL')
                ->value('value');
            $systemUrl = rtrim($systemUrl ?? '', '/');
            
            // Build HTML rows for expired certificates
            $tableRows = '';
            foreach ($expiredCerts as $cert) {
                $configData = json_decode($cert->configdata, true) ?: [];
                $domain = $configData['domainInfo'][0]['domainName'] ?? 'Unknown';
                $endDate = $configData['applyReturn']['endDate'] ?? 'Unknown';
                $certId = is_object($cert) ? $cert->id : $cert['id'];
                $remoteId = is_object($cert) ? $cert->remoteid : $cert['remoteid'];
                
                $orderUrl = "{$systemUrl}/admin/addonmodules.php?module=nicsrs_ssl_admin&action=order_detail&id={$certId}";
                
                $tableRows .= sprintf('
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <a href="%s" style="color: #1890ff; text-decoration: none;">#%d</a>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 500;">%s</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; color: #666;">%s</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; color: #ff4d4f; font-weight: 500;">%s</td>
                    </tr>',
                    htmlspecialchars($orderUrl),
                    $certId,
                    htmlspecialchars($domain),
                    htmlspecialchars($remoteId),
                    htmlspecialchars($endDate)
                );
            }
            
            // Build complete HTML email body
            $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 700px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #ff4d4f, #cf1322); color: white; padding: 25px; text-align: center; }
            .header h2 { margin: 0; font-size: 24px; }
            .content { padding: 25px; background: #f9f9f9; }
            .alert-box { background: #fff2f0; border: 1px solid #ffccc7; border-radius: 8px; padding: 15px; margin: 20px 0; }
            .alert-box p { margin: 0; color: #cf1322; }
            .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #fafafa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #eee; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f0f0f0; }
            .btn { display: inline-block; padding: 10px 25px; background: #1890ff; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>⚠️ Expired Certificates Alert</h2>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>NicSRS SSL Admin Auto-Sync</p>
            </div>
            <div class='content'>
                <div class='alert-box'>
                    <p><strong>⚠️ {$totalExpired} certificate(s) have expired!</strong></p>
                    <p style='margin-top: 8px;'>Please review and take necessary actions.</p>
                </div>
                
                <div class='table-container'>
                    <table>
                        <thead>
                            <tr>
                                <th style='width: 80px;'>Order ID</th>
                                <th>Domain</th>
                                <th>Remote ID</th>
                                <th style='width: 120px;'>Expired Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$tableRows}
                        </tbody>
                    </table>
                </div>
                
                <p style='text-align: center;'>
                    <a href='{$systemUrl}/admin/addonmodules.php?module=nicsrs_ssl_admin&action=orders&status=expired' class='btn'>
                        View All Expired Certificates
                    </a>
                </p>
            </div>
            <div class='footer'>
                <p>This is an automated message from <strong>NicSRS SSL Admin Module</strong></p>
                <p>Powered by <a href='https://hvn.vn' style='color: #1890ff;'>HVN GROUP</a></p>
            </div>
        </div>
    </body>
    </html>";
            
            // Use WHMCS Local API SendAdminEmail
            $results = localAPI('SendAdminEmail', [
                'customsubject' => $subject,
                'custommessage' => $body,
                'type' => 'system',
            ]);
            
            if ($results['result'] !== 'success') {
                $this->logError('SendAdminEmail (expiry) failed: ' . ($results['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->logError('Failed to send expiry notification: ' . $e->getMessage());
        }
    }

    /**
     * Refresh a single certificate from API (can be called for any status)
     * 
     * This is useful for manually refreshing a specific certificate
     * regardless of its current status.
     * 
     * @param int $certId Certificate order ID
     * @return array Sync result
     */
    public function refreshCertificate(int $certId): array
    {
        try {
            $cert = Capsule::table('nicsrs_sslorders')->find($certId);
            
            if (!$cert) {
                return ['success' => false, 'error' => 'Certificate not found'];
            }
            
            if (empty($cert->remoteid)) {
                return ['success' => false, 'error' => 'No remote ID'];
            }
            
            return $this->syncSingleCertificate($cert);
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a single certificate with NicSRS API
     * 
     * Based on OrderController::refreshStatus() logic - EXACT MATCH
     * 
     * @param object $cert Certificate record
     * @return array Sync result
     */
    private function syncSingleCertificate(object $cert): array
    {
        $result = [
            'success' => false,
            'status_changed' => false,
            'old_status' => $cert->status,
            'new_status' => $cert->status,
            'error' => null,
        ];
        
        $certId = $cert->remoteid;
        
        // Call NicSRS API - use collect() method
        $apiResult = $this->callApiWithRetry('collect', $certId);
        
        if (!$apiResult) {
            $result['error'] = 'API call failed after retries';
            return $result;
        }
        
        // Check API response code
        if (!isset($apiResult['code'])) {
            $result['error'] = 'Invalid API response format';
            return $result;
        }
        
        // Code 2 = Certificate still being processed (not a failure)
        if ($apiResult['code'] == 2) {
            $result['success'] = true;
            return $result;
        }
        
        if ($apiResult['code'] != 1) {
            $result['error'] = $apiResult['msg'] ?? 'API error code: ' . $apiResult['code'];
            return $result;
        }
        
        // Parse existing config data
        $configData = json_decode($cert->configdata, true) ?: [];
        
        // Initialize applyReturn if not exists
        if (!isset($configData['applyReturn'])) {
            $configData['applyReturn'] = [];
        }
        
        // Merge API response data into configdata (SAME as OrderController::refreshStatus)
        if (isset($apiResult['data']) && is_array($apiResult['data'])) {
            $data = $apiResult['data'];
            
            // Core certificate data
            $configData['applyReturn']['certId'] = $certId;
            
            // Vendor tracking fields
            if (!empty($data['vendorId'])) {
                $configData['applyReturn']['vendorId'] = $data['vendorId'];
            }
            if (!empty($data['vendorCertId'])) {
                $configData['applyReturn']['vendorCertId'] = $data['vendorCertId'];
            }
            
            // Certificate dates
            if (!empty($data['beginDate'])) {
                $configData['applyReturn']['beginDate'] = $data['beginDate'];
            }
            if (!empty($data['endDate'])) {
                $configData['applyReturn']['endDate'] = $data['endDate'];
            }
            if (!empty($data['dueDate'])) {
                $configData['applyReturn']['dueDate'] = $data['dueDate'];
            }
            if (!empty($data['applyTime'])) {
                $configData['applyReturn']['applyTime'] = $data['applyTime'];
            }
            
            // Certificate content
            if (!empty($data['certificate'])) {
                $configData['applyReturn']['certificate'] = $data['certificate'];
            }
            if (!empty($data['caCertificate'])) {
                $configData['applyReturn']['caCertificate'] = $data['caCertificate'];
            }
            if (!empty($data['rsaPrivateKey'])) {
                $configData['applyReturn']['rsaPrivateKey'] = $data['rsaPrivateKey'];
            }
            
            // JKS and PKCS12 data
            if (!empty($data['jks'])) {
                $configData['applyReturn']['jks'] = $data['jks'];
            }
            if (!empty($data['pkcs12'])) {
                $configData['applyReturn']['pkcs12'] = $data['pkcs12'];
            }
            if (!empty($data['jksPass'])) {
                $configData['applyReturn']['jksPass'] = $data['jksPass'];
            }
            if (!empty($data['pkcsPass'])) {
                $configData['applyReturn']['pkcsPass'] = $data['pkcsPass'];
            }
            
            // DCV information
            if (!empty($data['DCVfileName'])) {
                $configData['applyReturn']['DCVfileName'] = $data['DCVfileName'];
            }
            if (!empty($data['DCVfileContent'])) {
                $configData['applyReturn']['DCVfileContent'] = $data['DCVfileContent'];
            }
            if (!empty($data['DCVfilePath'])) {
                $configData['applyReturn']['DCVfilePath'] = $data['DCVfilePath'];
            }
            if (!empty($data['DCVdnsHost'])) {
                $configData['applyReturn']['DCVdnsHost'] = $data['DCVdnsHost'];
            }
            if (!empty($data['DCVdnsValue'])) {
                $configData['applyReturn']['DCVdnsValue'] = $data['DCVdnsValue'];
            }
            if (!empty($data['DCVdnsType'])) {
                $configData['applyReturn']['DCVdnsType'] = $data['DCVdnsType'];
            }
            
            // DCV list (domain validation status)
            if (!empty($data['dcvList']) && is_array($data['dcvList'])) {
                $configData['dcvList'] = [];
                foreach ($data['dcvList'] as $dcv) {
                    $configData['dcvList'][] = [
                        'domainName' => $dcv['domainName'] ?? '',
                        'dcvMethod' => $dcv['dcvMethod'] ?? 'EMAIL',
                        'dcvEmail' => $dcv['dcvEmail'] ?? '',
                        'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                        'is_verify' => $dcv['is_verify'] ?? '',
                    ];
                }
            }
        }
        
        // Add lastRefresh timestamp (same key as OrderController uses)
        $configData['lastRefresh'] = date('Y-m-d H:i:s');
        
        // Also keep lastAutoSync for tracking auto-sync vs manual refresh
        $configData['lastAutoSync'] = date('Y-m-d H:i:s');
        
        // Determine new status from API response
        $newStatus = $cert->status;
        if (isset($apiResult['status'])) {
            $newStatus = strtolower($apiResult['status']);
        } elseif (isset($apiResult['certStatus'])) {
            $newStatus = strtolower($apiResult['certStatus']);
        }
        
        $result['new_status'] = $newStatus;
        $result['status_changed'] = ($newStatus !== $cert->status);
        
        // Build update data
        $updateData = [
            'status' => $newStatus,
            'configdata' => json_encode($configData),
        ];
        
        // Set provisiondate if empty (same logic as OrderController)
        if (!$this->isValidDate($cert->provisiondate)) {
            $updateData['provisiondate'] = date('Y-m-d');
        }
        
        // Set completiondate when status is complete
        if ($newStatus === 'complete') {
            if (!$this->isValidDate($cert->completiondate)) {
                // Use certificate begin date or current date
                $completionDate = $configData['applyReturn']['beginDate'] ?? date('Y-m-d H:i:s');
                // Ensure it's datetime format
                if (strlen($completionDate) === 10) {
                    $completionDate .= ' 00:00:00';
                }
                $updateData['completiondate'] = $completionDate;
            }
        }
        
        // Update database
        try {
            Capsule::table('nicsrs_sslorders')
                ->where('id', $cert->id)
                ->update($updateData);
            
            $result['success'] = true;
            
        } catch (\Exception $e) {
            $result['error'] = 'Database update failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if date is valid (not empty or 0000-00-00)
     * Same logic as OrderController::isValidDate()
     * 
     * @param mixed $date Date value
     * @return bool
     */
    private function isValidDate($date): bool
    {
        return !empty($date) && $date !== '0000-00-00' && $date !== '0000-00-00 00:00:00';
    }

    /**
     * Call API with retry mechanism
     * 
     * @param string $method API method name (e.g., 'collect', 'productList')
     * @param mixed $param Parameter for API call
     * @return array|null API response or null on failure
     */
    private function callApiWithRetry(string $method, $param): ?array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $result = $this->apiService->$method($param);
                return $result;
                
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Exponential backoff
                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }
        
        if ($lastException) {
            $this->logError("API call {$method} failed after {$this->maxRetries} retries: " . $lastException->getMessage());
        }
        
        return null;
    }

    /**
     * Sync products from NicSRS API
     * 
     * Based on ProductService::syncFromApi() logic
     * 
     * @return array Sync results
     */
    public function syncProducts(): array
    {
        $startTime = microtime(true);
        
        $results = [
            'vendors' => [],
            'total_products' => 0,
            'updated' => 0,
            'inserted' => 0,
            'price_changes' => [],
            'errors' => [],
        ];

        foreach (self::VENDORS as $vendor) {
            try {
                // Use productList() method - NOT getProductList()
                $apiResult = $this->apiService->productList($vendor);
                
                // Debug log API response
                $this->logDebug("Product sync for {$vendor}", [
                    'code' => $apiResult['code'] ?? 'null',
                    'data_count' => isset($apiResult['data']) ? count($apiResult['data']) : 0,
                ]);
                
                if (!isset($apiResult['code']) || $apiResult['code'] != 1) {
                    $results['errors'][] = "{$vendor}: " . ($apiResult['msg'] ?? 'Unknown error');
                    continue;
                }
                
                $products = $apiResult['data'] ?? [];
                
                if (empty($products)) {
                    $results['vendors'][$vendor] = 0;
                    continue;
                }
                
                $vendorResults = $this->saveProducts($products, $vendor);
                
                $results['vendors'][$vendor] = $vendorResults['total'];
                $results['total_products'] += $vendorResults['total'];
                $results['updated'] += $vendorResults['updated'];
                $results['inserted'] += $vendorResults['inserted'];
                
                // Collect price changes for notification
                if (!empty($vendorResults['price_changes'])) {
                    $results['price_changes'] = array_merge($results['price_changes'], $vendorResults['price_changes']);
                }
                
            } catch (\Exception $e) {
                $results['errors'][] = "{$vendor}: " . $e->getMessage();
                $this->logError("Product sync error for {$vendor}: " . $e->getMessage());
            }
            
            usleep(500000); // 500ms delay between vendors
        }

        // Update last sync timestamp
        $this->updateSetting('last_product_sync', date('Y-m-d H:i:s'));

        // Send price change notification if any
        if (!empty($results['price_changes'])) {
            $this->sendPriceChangeNotification($results['price_changes']);
        }

        // Log activity
        $duration = round(microtime(true) - $startTime, 2);
        
        if ($this->logger) {
            $this->logger->log('auto_sync_products', 'cron', null, null, json_encode([
                'total_products' => $results['total_products'],
                'updated' => $results['updated'],
                'inserted' => $results['inserted'],
                'price_changes_count' => count($results['price_changes']),
                'vendors' => $results['vendors'],
                'duration_seconds' => $duration,
            ]));
        }

        return $results;
    }

    /**
     * Save products to database
     * 
     * Based on ProductService::syncFromApi() logic
     * API response format:
     * {
     *   "code": "sectigo-ov",
     *   "productName": "Sectigo OV SSL",
     *   "supportWildcard": "N",
     *   "supportSan": "Y",
     *   "validationType": "ov",
     *   "maxDomain": 5,
     *   "maxYear": 2,
     *   "price": {
     *     "basePrice": { "price012": 59.00, "price024": 99.00 },
     *     "sanPrice": { "price012": 15.00, "price024": 25.00 }
     *   }
     * }
     * 
     * @param array $products Product list from API
     * @param string $vendor Vendor name
     * @return array Save results
     */
    private function saveProducts(array $products, string $vendor): array
    {
        $results = [
            'total' => 0,
            'updated' => 0,
            'inserted' => 0,
            'price_changes' => [],
        ];
        
        $now = date('Y-m-d H:i:s');
        
        foreach ($products as $product) {
            // API returns 'code' as product_code (not 'product_code' or 'productCode')
            $productCode = $product['code'] ?? null;
            
            if (!$productCode) {
                continue;
            }
            
            // Extract prices from nested structure
            $price = $product['price'] ?? [];
            $basePrice = $price['basePrice'] ?? [];
            
            $price1Year = $this->extractPrice($basePrice, 'price012');
            $price2Year = $this->extractPrice($basePrice, 'price024');
            $price3Year = $this->extractPrice($basePrice, 'price036');
            
            $data = [
                'product_code' => $productCode,
                'product_name' => $product['productName'] ?? $productCode,
                'vendor' => $vendor,
                'validation_type' => $this->normalizeValidationType($product['validationType'] ?? 'dv'),
                'support_wildcard' => $this->normalizeBoolean($product['supportWildcard'] ?? 'N'),
                'support_san' => $this->normalizeBoolean($product['supportSan'] ?? 'N'),
                'max_domains' => (int) ($product['maxDomain'] ?? 1),
                'max_years' => (int) ($product['maxYear'] ?? 1),
                'price_data' => json_encode($price),
                'last_sync' => $now,
                'updated_at' => $now,
            ];
            
            try {
                // Check if product exists
                $existing = Capsule::table('mod_nicsrs_products')
                    ->where('product_code', $productCode)
                    ->where('vendor', $vendor)
                    ->first();
                
                if ($existing) {
                    // Check for price changes before updating
                    $oldPriceData = json_decode($existing->price_data, true) ?: [];
                    $oldBasePrice = $oldPriceData['basePrice'] ?? [];
                    
                    $oldPrice1Year = $this->extractPrice($oldBasePrice, 'price012');
                    
                    // Detect price change
                    if ($oldPrice1Year !== null && $price1Year !== null && $oldPrice1Year != $price1Year) {
                        $results['price_changes'][] = [
                            'product_code' => $productCode,
                            'product_name' => $data['product_name'],
                            'vendor' => $vendor,
                            'old_price' => $oldPrice1Year,
                            'new_price' => $price1Year,
                            'change' => $price1Year - $oldPrice1Year,
                            'change_percent' => $oldPrice1Year > 0 
                                ? round((($price1Year - $oldPrice1Year) / $oldPrice1Year) * 100, 2) 
                                : 0,
                        ];
                    }
                    
                    Capsule::table('mod_nicsrs_products')
                        ->where('id', $existing->id)
                        ->update($data);
                    $results['updated']++;
                } else {
                    $data['created_at'] = $now;
                    Capsule::table('mod_nicsrs_products')->insert($data);
                    $results['inserted']++;
                }
                
                $results['total']++;
                
            } catch (\Exception $e) {
                $this->logError("Failed to save product {$productCode}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Extract price from API price array
     * 
     * @param array $priceArray Price array from API
     * @param string $key Price key (e.g., 'price012', 'price024')
     * @return float|null
     */
    private function extractPrice(array $priceArray, string $key): ?float
    {
        if (!isset($priceArray[$key])) {
            return null;
        }
        
        $value = $priceArray[$key];
        
        if ($value === null || $value === '') {
            return null;
        }
        
        return (float) $value;
    }

    /**
     * Normalize validation type
     */
    private function normalizeValidationType(string $type): string
    {
        $type = strtolower(trim($type));
        
        $mapping = [
            'dv' => 'dv',
            'domain' => 'dv',
            'domain validation' => 'dv',
            'ov' => 'ov',
            'organization' => 'ov',
            'organization validation' => 'ov',
            'ev' => 'ev',
            'extended' => 'ev',
            'extended validation' => 'ev',
        ];
        
        return $mapping[$type] ?? 'dv';
    }

    /**
     * Normalize boolean value from API
     */
    private function normalizeBoolean($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['Y', 'YES', 'TRUE', '1']) ? 1 : 0;
    }

    /**
     * Send price change notification email to admin
     * 
     * Uses WHMCS Local API SendAdminEmail
     * @see https://developers.whmcs.com/api-reference/sendadminemail/
     * 
     * @param array $priceChanges Array of price change records
     */
    private function sendPriceChangeNotification(array $priceChanges): void
    {
        if (empty($priceChanges)) {
            return;
        }
        
        try {
            $subject = '[NicSRS SSL] Product Price Changes - ' . date('Y-m-d');
            $syncTime = date('Y-m-d H:i:s');
            $totalChanges = count($priceChanges);
            
            // Build HTML rows for price changes
            $tableRows = '';
            foreach ($priceChanges as $change) {
                $isUp = $change['change'] > 0;
                $arrow = $isUp ? '↑' : '↓';
                $color = $isUp ? '#ff4d4f' : '#52c41a';
                $direction = $isUp ? 'UP' : 'DOWN';
                
                $tableRows .= sprintf('
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">%s</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">%s</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">$%.2f</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">$%.2f</td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                            <span style="color: %s; font-weight: bold;">%s %.2f%% %s</span>
                        </td>
                    </tr>',
                    htmlspecialchars($change['product_name']),
                    htmlspecialchars($change['vendor']),
                    $change['old_price'],
                    $change['new_price'],
                    $color,
                    $arrow,
                    abs($change['change_percent']),
                    $direction
                );
            }
            
            // Build complete HTML email body
            $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 700px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #1890ff, #096dd9); color: white; padding: 25px; text-align: center; }
            .header h2 { margin: 0; font-size: 24px; }
            .content { padding: 25px; background: #f9f9f9; }
            .stats-box { display: flex; justify-content: space-around; margin: 20px 0; }
            .stat { text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 120px; }
            .stat-value { font-size: 28px; font-weight: bold; color: #1890ff; }
            .stat-label { font-size: 12px; color: #666; margin-top: 5px; }
            .table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #fafafa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #eee; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f0f0f0; }
            .footer a { color: #1890ff; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📊 Product Price Changes Detected</h2>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>NicSRS SSL Admin Auto-Sync</p>
            </div>
            <div class='content'>
                <table style='width: 100%; margin-bottom: 20px;'>
                    <tr>
                        <td><strong>Sync Time:</strong> {$syncTime}</td>
                        <td style='text-align: right;'><strong>Total Changes:</strong> <span style='color: #1890ff; font-size: 18px;'>{$totalChanges}</span></td>
                    </tr>
                </table>
                
                <div class='table-container'>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Vendor</th>
                                <th style='text-align: right;'>Old Price</th>
                                <th style='text-align: right;'>New Price</th>
                                <th style='text-align: center;'>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$tableRows}
                        </tbody>
                    </table>
                </div>
                
                <p style='color: #666; font-size: 13px;'>
                    <em>Note: These price changes are from the NicSRS API. 
                    WHMCS product prices are not automatically updated.</em>
                </p>
            </div>
            <div class='footer'>
                <p>This is an automated message from <strong>NicSRS SSL Admin Module</strong></p>
                <p>Powered by <a href='https://hvn.vn'>HVN GROUP</a></p>
            </div>
        </div>
    </body>
    </html>";
            
            // Use WHMCS Local API SendAdminEmail
            $results = localAPI('SendAdminEmail', [
                'customsubject' => $subject,
                'custommessage' => $body,
                'type' => 'system',
            ]);
            
            if ($results['result'] === 'success') {
                if ($this->logger) {
                    $this->logger->log('price_change_notification', 'product', null, null, json_encode([
                        'changes_count' => count($priceChanges),
                        'status' => 'sent',
                    ]));
                }
            } else {
                $this->logError('SendAdminEmail failed: ' . ($results['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->logError('Failed to send price change notification: ' . $e->getMessage());
        }
    }

    /**
     * Send completion notification email
     */
    private function sendCompletionNotification(object $cert, array $syncResult): void
    {
        if (empty($this->settings['email_on_issuance'])) {
            return;
        }

        try {
            $notificationPath = __DIR__ . '/NotificationService.php';
            
            if (!file_exists($notificationPath)) {
                return;
            }
            
            require_once $notificationPath;
            
            $notifier = new NotificationService();
            $notifier->sendCertificateIssuedNotification($cert);
            
        } catch (\Exception $e) {
            $this->logError('Failed to send notification: ' . $e->getMessage());
        }
    }

    /**
     * Update a setting value in database
     */
    private function updateSetting(string $key, $value): void
    {
        try {
            Capsule::table('mod_nicsrs_settings')
                ->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_value' => (string) $value,
                        'setting_type' => 'string',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            
            $this->settings[$key] = $value;
            
        } catch (\Exception $e) {
            $this->logError("Failed to update setting {$key}: " . $e->getMessage());
        }
    }

    /**
     * Increment error count
     */
    private function incrementErrorCount(): void
    {
        $count = (int) ($this->settings['sync_error_count'] ?? 0);
        $this->updateSetting('sync_error_count', $count + 1);
    }

    /**
     * Log error message
     */
    private function logError(string $message): void
    {
        logModuleCall(
            'nicsrs_ssl_admin',
            'SyncService_Error',
            [],
            $message,
            'ERROR'
        );
    }

    /**
     * Log debug message
     */
    private function logDebug(string $action, array $data): void
    {
        logModuleCall(
            'nicsrs_ssl_admin',
            'SyncService_Debug',
            $data,
            $action,
            'DEBUG'
        );
    }

    /**
     * Get sync status for display
     */
    public function getSyncStatus(): array
    {
        $statusSyncInterval = (int) ($this->settings['sync_interval_hours'] ?? 6);
        $productSyncInterval = (int) ($this->settings['product_sync_hours'] ?? 24);
        
        $lastStatusSync = $this->settings['last_status_sync'] ?? null;
        $lastProductSync = $this->settings['last_product_sync'] ?? null;
        
        $nextStatusSync = null;
        $nextProductSync = null;
        
        if ($lastStatusSync) {
            $nextStatusSync = date('Y-m-d H:i:s', strtotime($lastStatusSync) + ($statusSyncInterval * 3600));
        }
        
        if ($lastProductSync) {
            $nextProductSync = date('Y-m-d H:i:s', strtotime($lastProductSync) + ($productSyncInterval * 3600));
        }
        
        // Count pending certificates (need status sync)
        $pendingCount = 0;
        try {
            $pendingCount = Capsule::table('nicsrs_sslorders')
                ->whereIn('status', self::PENDING_STATUSES)
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->count();
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Count active certificates (for expiry check)
        $activeCount = 0;
        try {
            $activeCount = Capsule::table('nicsrs_sslorders')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->count();
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Count expiring soon (within configured days)
        $expiringCount = 0;
        $expiryDays = (int) ($this->settings['expiry_days'] ?? 30);
        try {
            $expiringCount = $this->countExpiringSoon($expiryDays);
        } catch (\Exception $e) {
            // Ignore
        }
        
        $apiConnected = false;
        if ($this->apiService) {
            try {
                $apiConnected = $this->apiService->testConnection();
            } catch (\Exception $e) {
                // Ignore
            }
        }
        
        return [
            'enabled' => !empty($this->settings['auto_sync_status']),
            'api_connected' => $apiConnected,
            'status_sync' => [
                'interval_hours' => $statusSyncInterval,
                'last_sync' => $lastStatusSync,
                'next_sync' => $nextStatusSync,
                'pending_certificates' => $pendingCount,
                'active_certificates' => $activeCount,
                'expiring_soon' => $expiringCount,
                'expiry_days' => $expiryDays,
            ],
            'product_sync' => [
                'interval_hours' => $productSyncInterval,
                'last_sync' => $lastProductSync,
                'next_sync' => $nextProductSync,
            ],
            'error_count' => (int) ($this->settings['sync_error_count'] ?? 0),
            'batch_size' => $this->batchSize,
        ];
    }
    
    /**
     * Count certificates expiring within X days
     * 
     * @param int $days Number of days
     * @return int Count
     */
    private function countExpiringSoon(int $days): int
    {
        $count = 0;
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));
        
        try {
            $activeCerts = Capsule::table('nicsrs_sslorders')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNotNull('remoteid')
                ->get(['id', 'configdata']);
            
            foreach ($activeCerts as $cert) {
                $configData = json_decode($cert->configdata, true) ?: [];
                $endDate = $configData['applyReturn']['endDate'] ?? null;
                
                if (!$endDate) {
                    continue;
                }
                
                $endDateOnly = substr($endDate, 0, 10);
                
                // Check if expiring within the period (not already expired)
                if ($endDateOnly >= $today && $endDateOnly <= $futureDate) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return $count;
    }
    
    /**
     * Get list of certificates expiring soon
     * 
     * @param int $days Number of days to look ahead
     * @param int $limit Maximum results
     * @return array List of expiring certificates
     */
    public function getExpiringSoonList(int $days = 30, int $limit = 50): array
    {
        $results = [];
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));
        
        try {
            $activeCerts = Capsule::table('nicsrs_sslorders as o')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->whereIn('o.status', self::ACTIVE_STATUSES)
                ->whereNotNull('o.remoteid')
                ->select(['o.*', 'c.firstname', 'c.lastname', 'c.email', 'c.companyname'])
                ->get();
            
            foreach ($activeCerts as $cert) {
                $configData = json_decode($cert->configdata, true) ?: [];
                $endDate = $configData['applyReturn']['endDate'] ?? null;
                
                if (!$endDate) {
                    continue;
                }
                
                $endDateOnly = substr($endDate, 0, 10);
                
                if ($endDateOnly >= $today && $endDateOnly <= $futureDate) {
                    $daysLeft = (strtotime($endDateOnly) - strtotime($today)) / 86400;
                    
                    $results[] = [
                        'id' => $cert->id,
                        'remoteid' => $cert->remoteid,
                        'domain' => $configData['domainInfo'][0]['domainName'] ?? 'Unknown',
                        'product_code' => $configData['productCode'] ?? 'Unknown',
                        'end_date' => $endDateOnly,
                        'days_left' => (int) $daysLeft,
                        'client_name' => trim(($cert->firstname ?? '') . ' ' . ($cert->lastname ?? '')),
                        'client_email' => $cert->email ?? '',
                        'company' => $cert->companyname ?? '',
                    ];
                }
            }
            
            // Sort by days_left ascending
            usort($results, function($a, $b) {
                return $a['days_left'] - $b['days_left'];
            });
            
            // Limit results
            $results = array_slice($results, 0, $limit);
            
        } catch (\Exception $e) {
            $this->logError('Failed to get expiring certificates: ' . $e->getMessage());
        }
        
        return $results;
    }
}