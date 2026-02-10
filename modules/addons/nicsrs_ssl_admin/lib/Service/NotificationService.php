<?php
/**
 * NotificationService.php - Updated to use WHMCS Local API
 * 
 * KEY CHANGES:
 * 1. sendMail() method now uses WHMCS Local API SendAdminEmail instead of mail()
 * 2. Added sendAdminNotification() method for custom admin emails
 * 3. Improved HTML email templates
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @version    1.2.2
 */

namespace NicsrsAdmin\Service;

use WHMCS\Database\Capsule;

class NotificationService
{
    /** @var array Module settings cache */
    private $settings = [];
    
    /** @var string System URL */
    private $systemUrl = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadSettings();
        $this->systemUrl = $this->getSystemUrl();
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
        } catch (\Exception $e) {
            // Use defaults
        }
    }

    /**
     * Send certificate issued notification to admin
     * 
     * @param object $cert Certificate record
     * @return bool Success status
     */
    public function sendCertificateIssuedNotification(object $cert): bool
    {
        if (empty($this->settings['email_on_issuance'])) {
            return false;
        }

        $configData = json_decode($cert->configdata, true) ?: [];
        
        // Get domain
        $domain = 'Unknown Domain';
        if (!empty($configData['domainInfo'][0]['domainName'])) {
            $domain = $configData['domainInfo'][0]['domainName'];
        } elseif (!empty($configData['applyReturn']['domain'])) {
            $domain = $configData['applyReturn']['domain'];
        }
        
        // Get certificate dates
        $beginDate = $configData['applyReturn']['beginDate'] ?? 'N/A';
        $endDate = $configData['applyReturn']['endDate'] ?? 'N/A';
        
        if ($beginDate !== 'N/A' && strtotime($beginDate)) {
            $beginDate = date('Y-m-d', strtotime($beginDate));
        }
        if ($endDate !== 'N/A' && strtotime($endDate)) {
            $endDate = date('Y-m-d', strtotime($endDate));
        }
        
        $clientInfo = $this->getClientInfo($cert->userid);
        $productName = $cert->certtype ?? 'SSL Certificate';
        
        $subject = "[HVN SSL] ✅ Certificate Issued - {$domain}";
        
        $body = $this->buildIssuedEmailBody([
            'order_id' => $cert->id,
            'remote_id' => $cert->remoteid,
            'domain' => $domain,
            'product' => $productName,
            'client_name' => $clientInfo['name'],
            'client_email' => $clientInfo['email'],
            'begin_date' => $beginDate,
            'end_date' => $endDate,
            'service_id' => $cert->serviceid,
        ]);
        
        return $this->sendAdminNotification($subject, $body);
    }

    /**
     * Send expiry warning notification
     * 
     * @param object $cert Certificate record
     * @param int $daysUntilExpiry Days until certificate expires
     * @return bool Success status
     */
    public function sendExpiryWarningNotification(object $cert, int $daysUntilExpiry): bool
    {
        if (empty($this->settings['email_on_expiry'])) {
            return false;
        }
        
        $configData = json_decode($cert->configdata, true) ?: [];
        
        $domain = $configData['domainInfo'][0]['domainName'] ?? 'Unknown Domain';
        $expiryDate = $configData['applyReturn']['endDate'] ?? 'Unknown';
        
        if ($expiryDate !== 'Unknown' && strtotime($expiryDate)) {
            $expiryDate = date('Y-m-d', strtotime($expiryDate));
        }
        
        $clientInfo = $this->getClientInfo($cert->userid);
        
        $urgency = $daysUntilExpiry <= 7 ? '🚨 URGENT: ' : '⚠️ ';
        $subject = "{$urgency}[HVN SSL] Certificate Expiring in {$daysUntilExpiry} Days - {$domain}";
        
        $body = $this->buildExpiryEmailBody([
            'order_id' => $cert->id,
            'remote_id' => $cert->remoteid,
            'domain' => $domain,
            'days_until_expiry' => $daysUntilExpiry,
            'expiry_date' => $expiryDate,
            'client_name' => $clientInfo['name'],
            'client_email' => $clientInfo['email'],
            'service_id' => $cert->serviceid,
        ]);
        
        return $this->sendAdminNotification($subject, $body);
    }

    /**
     * Send sync error notification
     * 
     * @param array $errors List of errors
     * @param int $errorCount Consecutive error count
     * @return bool Success status
     */
    public function sendSyncErrorNotification(array $errors, int $errorCount): bool
    {
        $subject = "[HVN SSL] ❌ Auto-Sync Error Alert";
        
        $errorList = '';
        foreach ($errors as $error) {
            $errorList .= "<li style='margin: 5px 0;'>" . htmlspecialchars($error) . "</li>";
        }
        
        $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #ff4d4f, #cf1322); color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 25px; background: #f9f9f9; }
        .error-box { background: #fff2f0; border: 1px solid #ffccc7; border-left: 4px solid #ff4d4f; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f0f0f0; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; padding: 12px 25px; background: #1890ff; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2 style='margin: 0;'>❌ Auto-Sync Error Alert</h2>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>NicSRS SSL Admin Module</p>
        </div>
        <div class='content'>
            <p>The automatic synchronization has encountered errors.</p>
            
            <table style='width: 100%; margin: 20px 0;'>
                <tr>
                    <td><strong>Consecutive Errors:</strong></td>
                    <td style='color: #ff4d4f; font-size: 24px; font-weight: bold;'>{$errorCount}</td>
                </tr>
                <tr>
                    <td><strong>Time:</strong></td>
                    <td>" . date('Y-m-d H:i:s') . "</td>
                </tr>
            </table>
            
            <div class='error-box'>
                <h4 style='margin: 0 0 10px 0; color: #cf1322;'>Error Details:</h4>
                <ul style='margin: 0; padding-left: 20px;'>{$errorList}</ul>
            </div>
            
            <p>Please check the module configuration and API credentials.</p>
            
            <p style='text-align: center; margin-top: 25px;'>
                <a href='{$this->systemUrl}admin/addonmodules.php?module=nicsrs_ssl_admin&action=settings' class='btn'>
                    Check Settings
                </a>
            </p>
        </div>
        <div class='footer'>
            <p>This is an automated message from <strong>HVN SSL Admin Module</strong></p>
            <p>Powered by <a href='https://hvn.vn' style='color: #1890ff;'>HVN GROUP</a></p>
        </div>
    </div>
</body>
</html>";
        
        return $this->sendAdminNotification($subject, $body);
    }

    /**
     * Send admin notification using WHMCS Local API
     * 
     * This is the PRIMARY method for sending admin emails.
     * Uses SendAdminEmail Local API instead of PHP mail()
     * 
     * @see https://developers.whmcs.com/api-reference/sendadminemail/
     * 
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $type Notification type (system|account|support)
     * @return bool Success status
     */
    public function sendAdminNotification(string $subject, string $body, string $type = 'system'): bool
    {
        try {
            // Use WHMCS Local API SendAdminEmail
            $command = 'SendAdminEmail';
            $postData = [
                'customsubject' => $subject,
                'custommessage' => $body,
                'type' => $type,
            ];
            
            $results = localAPI($command, $postData);
            
            // Log the attempt
            logModuleCall(
                'nicsrs_ssl_admin',
                'SendAdminEmail',
                [
                    'subject' => $subject,
                    'type' => $type,
                ],
                $results,
                ''
            );
            
            if ($results['result'] === 'success') {
                return true;
            }
            
            // Log error
            logModuleCall(
                'nicsrs_ssl_admin',
                'SendAdminEmail_Error',
                [
                    'subject' => $subject,
                ],
                $results['message'] ?? 'Unknown error',
                'ERROR'
            );
            
            return false;
            
        } catch (\Exception $e) {
            logModuleCall(
                'nicsrs_ssl_admin',
                'SendAdminEmail_Exception',
                [
                    'subject' => $subject,
                ],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            
            return false;
        }
    }

    /**
     * Build email body for certificate issued notification
     */
    private function buildIssuedEmailBody(array $data): string
    {
        $orderUrl = "{$this->systemUrl}admin/addonmodules.php?module=nicsrs_ssl_admin&action=order_detail&id={$data['order_id']}";
        $serviceUrl = $data['service_id'] 
            ? "{$this->systemUrl}admin/clientsservices.php?id={$data['service_id']}" 
            : '';
        
        $serviceButton = $serviceUrl 
            ? "<a href='{$serviceUrl}' style='display: inline-block; padding: 10px 20px; background: #595959; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;'>View Service</a>"
            : '';

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #52c41a, #389e0d); color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 25px; background: #f9f9f9; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; border-radius: 8px; overflow: hidden; }
        .info-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .info-table td:first-child { font-weight: 600; width: 140px; color: #666; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f0f0f0; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1890ff; color: white; text-decoration: none; border-radius: 4px; }
        .success-badge { display: inline-block; padding: 4px 12px; background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; border-radius: 4px; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2 style='margin: 0;'>✅ SSL Certificate Issued</h2>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Certificate is ready for download</p>
        </div>
        <div class='content'>
            <p>A new SSL certificate has been successfully issued:</p>
            
            <table class='info-table'>
                <tr>
                    <td>Order ID</td>
                    <td><a href='{$orderUrl}' style='color: #1890ff;'>#{$data['order_id']}</a></td>
                </tr>
                <tr>
                    <td>Domain</td>
                    <td><strong>" . htmlspecialchars($data['domain']) . "</strong></td>
                </tr>
                <tr>
                    <td>Product</td>
                    <td>" . htmlspecialchars($data['product']) . "</td>
                </tr>
                <tr>
                    <td>Client</td>
                    <td>" . htmlspecialchars($data['client_name']) . " ({$data['client_email']})</td>
                </tr>
                <tr>
                    <td>Certificate ID</td>
                    <td><code>{$data['remote_id']}</code></td>
                </tr>
                <tr>
                    <td>Valid From</td>
                    <td>{$data['begin_date']}</td>
                </tr>
                <tr>
                    <td>Valid Until</td>
                    <td>{$data['end_date']}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span class='success-badge'>Complete</span></td>
                </tr>
            </table>
            
            <p style='text-align: center; margin-top: 25px;'>
                <a href='{$orderUrl}' class='btn'>View Order Details</a>
                {$serviceButton}
            </p>
        </div>
        <div class='footer'>
            <p>This is an automated message from <strong>HVN SSL Admin Module</strong></p>
            <p>Powered by <a href='https://hvn.vn' style='color: #1890ff;'>HVN GROUP</a></p>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Build email body for expiry warning notification
     */
    private function buildExpiryEmailBody(array $data): string
    {
        $orderUrl = "{$this->systemUrl}admin/addonmodules.php?module=nicsrs_ssl_admin&action=order_detail&id={$data['order_id']}";
        
        // Determine urgency styling
        $headerColor = '#faad14';
        $urgencyText = 'Expiring Soon';
        
        if ($data['days_until_expiry'] <= 7) {
            $headerColor = '#ff4d4f';
            $urgencyText = '🚨 URGENT - Expiring Very Soon';
        } elseif ($data['days_until_expiry'] <= 14) {
            $headerColor = '#fa8c16';
            $urgencyText = '⚠️ Action Required';
        }

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, {$headerColor}, " . $this->darkenColor($headerColor) . "); color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 25px; background: #f9f9f9; }
        .countdown { font-size: 48px; text-align: center; color: {$headerColor}; margin: 20px 0; font-weight: bold; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; border-radius: 8px; overflow: hidden; }
        .info-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .info-table td:first-child { font-weight: 600; width: 140px; color: #666; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f0f0f0; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; padding: 12px 25px; background: #1890ff; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2 style='margin: 0;'>{$urgencyText}</h2>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>SSL Certificate Expiry Warning</p>
        </div>
        <div class='content'>
            <div class='countdown'>{$data['days_until_expiry']} days</div>
            <p style='text-align: center; color: #666;'>until certificate expires</p>
            
            <table class='info-table'>
                <tr>
                    <td>Order ID</td>
                    <td><a href='{$orderUrl}' style='color: #1890ff;'>#{$data['order_id']}</a></td>
                </tr>
                <tr>
                    <td>Domain</td>
                    <td><strong>" . htmlspecialchars($data['domain']) . "</strong></td>
                </tr>
                <tr>
                    <td>Client</td>
                    <td>" . htmlspecialchars($data['client_name']) . " ({$data['client_email']})</td>
                </tr>
                <tr>
                    <td>Certificate ID</td>
                    <td><code>{$data['remote_id']}</code></td>
                </tr>
                <tr>
                    <td>Expiry Date</td>
                    <td style='color: {$headerColor}; font-weight: bold;'>{$data['expiry_date']}</td>
                </tr>
            </table>
            
            <p><strong>Action Required:</strong> Please renew this certificate before it expires to avoid service interruption.</p>
            
            <p style='text-align: center; margin-top: 25px;'>
                <a href='{$orderUrl}' class='btn'>Renew Certificate</a>
            </p>
        </div>
        <div class='footer'>
            <p>This is an automated message from <strong>HVN SSL Admin Module</strong></p>
            <p>Powered by <a href='https://hvn.vn' style='color: #1890ff;'>HVN GROUP</a></p>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Darken a hex color
     */
    private function darkenColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = max(0, hexdec(substr($hex, 0, 2)) - 30);
        $g = max(0, hexdec(substr($hex, 2, 2)) - 30);
        $b = max(0, hexdec(substr($hex, 4, 2)) - 30);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Get admin email address
     */
    private function getAdminEmail(): ?string
    {
        if (!empty($this->settings['admin_email'])) {
            return $this->settings['admin_email'];
        }
        
        try {
            return Capsule::table('tbladmins')
                ->where('disabled', 0)
                ->orderBy('id', 'asc')
                ->value('email');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get client information
     */
    private function getClientInfo(?int $userId): array
    {
        $default = ['name' => 'Unknown', 'email' => 'N/A'];
        
        if (!$userId) {
            return $default;
        }
        
        try {
            $client = Capsule::table('tblclients')->where('id', $userId)->first();
            if ($client) {
                return [
                    'name' => trim($client->firstname . ' ' . $client->lastname),
                    'email' => $client->email,
                ];
            }
        } catch (\Exception $e) {}
        
        return $default;
    }

    /**
     * Get WHMCS system URL
     */
    private function getSystemUrl(): string
    {
        try {
            $url = Capsule::table('tblconfiguration')
                ->where('setting', 'SystemURL')
                ->value('value');
            if ($url) {
                return rtrim($url, '/') . '/';
            }
        } catch (\Exception $e) {}
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/";
    }

    /**
     * DEPRECATED: Send email using PHP mail()
     * 
     * This method is DEPRECATED. Use sendAdminNotification() instead.
     * Kept for backward compatibility only.
     * 
     * @deprecated Use sendAdminNotification() with WHMCS Local API
     */
    private function sendMail(string $to, string $subject, string $body): bool
    {
        // Redirect to WHMCS Local API method
        return $this->sendAdminNotification($subject, $body);
    }

    // =========================================================================
    // EXPIRY WARNING FUNCTIONS - Called from CRON
    // =========================================================================

    /**
     * Check and send expiry warnings for all certificates
     * 
     * Should be called from cron to check for expiring certificates.
     * This sends WARNING emails BEFORE certificates expire.
     * 
     * Different from SyncService.sendExpiryNotification() which notifies
     * AFTER certificates have already expired.
     * 
     * @return array Results
     */
    public function checkAndSendExpiryWarnings(): array
    {
        $results = [
            'checked' => 0,
            'warnings_sent' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        
        if (empty($this->settings['email_on_expiry'])) {
            return $results;
        }
        
        $warningDays = (int) ($this->settings['expiry_days'] ?? 30);
        
        try {
            // Get certificates that are 'complete' (active) - not expired/cancelled
            $activeCerts = Capsule::table('nicsrs_sslorders')
                ->where('status', 'complete')
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->get();
            
            foreach ($activeCerts as $cert) {
                $results['checked']++;
                
                $configData = json_decode($cert->configdata, true) ?: [];
                $endDate = $configData['applyReturn']['endDate'] ?? null;
                
                if (!$endDate) {
                    continue;
                }
                
                $expiryTimestamp = strtotime($endDate);
                if (!$expiryTimestamp) {
                    continue;
                }
                
                $daysUntilExpiry = (int) ceil(($expiryTimestamp - time()) / 86400);
                
                // Only process if within warning period and NOT already expired
                if ($daysUntilExpiry > 0 && $daysUntilExpiry <= $warningDays) {
                    
                    $lastWarning = $configData['lastExpiryWarning'] ?? null;
                    $warningInterval = $this->getWarningInterval($daysUntilExpiry);
                    
                    if ($this->shouldSendWarning($lastWarning, $warningInterval, $daysUntilExpiry)) {
                        
                        if ($this->sendExpiryWarningNotification($cert, $daysUntilExpiry)) {
                            $results['warnings_sent']++;
                            
                            // Update last warning timestamp to prevent spam
                            $configData['lastExpiryWarning'] = date('Y-m-d H:i:s');
                            $configData['lastExpiryWarningDays'] = $daysUntilExpiry;
                            
                            Capsule::table('nicsrs_sslorders')
                                ->where('id', $cert->id)
                                ->update(['configdata' => json_encode($configData)]);
                        }
                    } else {
                        $results['skipped']++;
                    }
                }
            }
            
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            
            logModuleCall(
                'nicsrs_ssl_admin',
                'checkAndSendExpiryWarnings_Error',
                [],
                $e->getMessage(),
                $e->getTraceAsString()
            );
        }
        
        return $results;
    }

    /**
     * Determine warning interval based on days until expiry
     * 
     * Intervals help control email frequency:
     * - final: Last day, send every 12 hours
     * - week: Last 7 days, send every 2 days  
     * - twoweeks: 8-14 days, send every 3 days
     * - month: 15-30 days, send every 7 days
     * 
     * @param int $days Days until expiry
     * @return string Interval key
     */
    private function getWarningInterval(int $days): string
    {
        if ($days <= 1) {
            return 'final';
        } elseif ($days <= 7) {
            return 'week';
        } elseif ($days <= 14) {
            return 'twoweeks';
        } else {
            return 'month';
        }
    }

    /**
     * Check if warning should be sent based on last warning time
     * 
     * Prevents email spam by enforcing minimum time between warnings
     * 
     * @param string|null $lastWarning Last warning timestamp
     * @param string $interval Warning interval key
     * @param int $daysUntilExpiry Days until expiry
     * @return bool True if warning should be sent
     */
    private function shouldSendWarning(?string $lastWarning, string $interval, int $daysUntilExpiry): bool
    {
        // Always send if never warned before
        if (!$lastWarning) {
            return true;
        }
        
        $lastWarningTime = strtotime($lastWarning);
        if (!$lastWarningTime) {
            return true;
        }
        
        // Define minimum hours between warnings for each interval
        $minHours = [
            'final' => 12,      // 12 hours for final day
            'week' => 48,       // 2 days for last week
            'twoweeks' => 72,   // 3 days for 2 weeks
            'month' => 168,     // 7 days for month
        ];
        
        $hours = $minHours[$interval] ?? 168;
        $nextAllowedTime = $lastWarningTime + ($hours * 3600);
        
        return time() >= $nextAllowedTime;
    }
}