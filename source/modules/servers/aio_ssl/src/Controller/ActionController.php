<?php
/**
 * ActionController — Handles all client area AJAX actions
 *
 * Actions: submitApply, saveDraft, generateCSR, decodeCsr,
 *          refreshStatus, downloadCert, reissue, renew,
 *          resendDcv, batchUpdateDcv, revoke, cancel, getDcvEmails
 *
 * @package    AioSSL\Server
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Server;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;
use AioSSL\Core\UnsupportedOperationException;

class ActionController
{
    // ═══════════════════════════════════════════════════════════════
    // CSR ACTIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Auto-generate CSR + private key
     */
    public static function generateCSR(array $params): array
    {
        try {
            $domain = $_POST['domain'] ?? '';

            if (empty($domain)) {
                // Try to get domain from service
                $hosting = Capsule::table('tblhosting')->find($params['serviceid']);
                $domain = $hosting ? $hosting->domain : '';
            }

            if (empty($domain)) {
                return ['success' => false, 'message' => 'Domain name is required.'];
            }

            // Generate private key
            $keyConfig = [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];
            $privateKey = openssl_pkey_new($keyConfig);
            if (!$privateKey) {
                return ['success' => false, 'message' => 'Failed to generate private key.'];
            }

            // Build CSR subject
            $dn = [
                'commonName'         => $domain,
                'organizationName'   => $params['clientsdetails']['companyname'] ?? '',
                'localityName'       => $params['clientsdetails']['city'] ?? '',
                'stateOrProvinceName'=> $params['clientsdetails']['state'] ?? '',
                'countryName'        => $params['clientsdetails']['country'] ?? '',
                'emailAddress'       => $params['clientsdetails']['email'] ?? '',
            ];

            // Remove empty fields
            $dn = array_filter($dn, function ($v) { return !empty($v); });

            // Generate CSR
            $csr = openssl_csr_new($dn, $privateKey);
            if (!$csr) {
                return ['success' => false, 'message' => 'Failed to generate CSR.'];
            }

            // Export
            openssl_csr_export($csr, $csrOut);
            openssl_pkey_export($privateKey, $keyOut);

            // Store private key in order configdata
            $order = ProviderBridge::getOrder($params['serviceid']);
            if ($order) {
                ProviderBridge::updateOrderConfig($order->id, [
                    'private_key' => $keyOut,
                    'csr'         => $csrOut,
                    'csr_domain'  => $domain,
                ]);
            }

            return [
                'success' => true,
                'csr'     => $csrOut,
                'domain'  => $domain,
                'message' => 'CSR generated successfully.',
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'CSR generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Decode CSR to extract domain and other info
     */
    public static function decodeCsr(array $params): array
    {
        $csr = $_POST['csr'] ?? '';

        if (empty($csr)) {
            return ['success' => false, 'message' => 'CSR is required.'];
        }

        // Validate CSR format
        if (strpos($csr, '-----BEGIN CERTIFICATE REQUEST-----') === false
            && strpos($csr, '-----BEGIN NEW CERTIFICATE REQUEST-----') === false) {
            return ['success' => false, 'message' => 'Invalid CSR format.'];
        }

        $parsed = openssl_csr_get_subject($csr, true);
        if (!$parsed) {
            return ['success' => false, 'message' => 'Cannot parse CSR.'];
        }

        return [
            'success' => true,
            'data'    => [
                'commonName'   => $parsed['CN'] ?? '',
                'organization' => $parsed['O'] ?? '',
                'country'      => $parsed['C'] ?? '',
                'state'        => $parsed['ST'] ?? '',
                'city'         => $parsed['L'] ?? '',
                'email'        => $parsed['emailAddress'] ?? '',
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // CERTIFICATE APPLICATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Submit certificate application to provider
     *
     * Flow: validate → resolve provider → build params → placeOrder → update order
     */
    public static function submitApply(array $params): array
    {
        try {
            $serviceId = $params['serviceid'];
            $order = ProviderBridge::getOrder($serviceId);

            if (!$order) {
                return ['success' => false, 'message' => 'No SSL order found.'];
            }

            $status = strtolower($order->status ?? '');
            if (!in_array($status, ['awaiting configuration', 'draft'])) {
                return ['success' => false, 'message' => 'Order is not in configurable state (current: ' . $order->status . ')'];
            }

            // Get form data
            $formData = $_POST['data'] ?? $_POST;
            if (is_string($formData)) {
                $formData = json_decode($formData, true) ?: [];
            }

            // ── Validate CSR ──
            $csr = $formData['csr'] ?? '';
            if (empty($csr)) {
                return ['success' => false, 'message' => 'CSR is required.'];
            }

            if (strpos($csr, '-----BEGIN CERTIFICATE REQUEST-----') === false &&
                strpos($csr, '-----BEGIN NEW CERTIFICATE REQUEST-----') === false) {
                return ['success' => false, 'message' => 'Invalid CSR format.'];
            }

            // ── Resolve provider ──
            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $slug = ProviderBridge::resolveSlugFromOrder($order);

            if (empty($slug)) {
                return ['success' => false, 'message' => 'Provider not configured.'];
            }

            $provider = ProviderRegistry::get($slug);

            // ── Build order params ──
            $productCode = $order->certtype ?? $order->product_code
                ?? $configdata['canonical'] ?? $params['configoption1'] ?? '';

            // Billing cycle → period mapping
            $hosting = Capsule::table('tblhosting')->find($serviceId);
            $billingCycle = $hosting ? $hosting->billingcycle : 'Annually';

            $periodMap = [
                'Free Account'  => 1,
                'One Time'      => 1,
                'Monthly'       => 1,
                'Quarterly'     => 1,
                'Semi-Annually' => 1,
                'Annually'      => 1,
                'Biennially'    => 2,
                'Triennially'   => 3,
            ];
            $period = $periodMap[$billingCycle] ?? 1;

            // DCV method
            $dcvMethod = $formData['dcv_method'] ?? $formData['dcvMethod'] ?? 'email';

            // Domain info
            $domains = $formData['domains'] ?? $formData['domainInfo'] ?? [];
            if (empty($domains)) {
                $domain = $order->domain ?? '';
                if (!empty($domain)) {
                    $domains = [['domainName' => $domain, 'dcvMethod' => $dcvMethod]];
                }
            }

            // Approver email (for EMAIL DCV)
            $approverEmail = $formData['approver_email'] ?? $formData['approveremail'] ?? '';

            // Contact info (OV/EV)
            $contacts = [];
            if (!empty($formData['Administrator']) || !empty($formData['admin'])) {
                $contacts['admin'] = $formData['Administrator'] ?? $formData['admin'] ?? [];
                $contacts['tech']  = $formData['tech'] ?? $formData['Administrator'] ?? $contacts['admin'];
            }

            // Organization info (OV/EV)
            $orgInfo = $formData['organizationInfo'] ?? $formData['org'] ?? [];

            // ── Build provider-specific params ──
            $orderParams = [
                'product_code'   => $productCode,
                'csr'            => $csr,
                'period'         => $period,
                'dcv_method'     => $dcvMethod,
                'domains'        => $domains,
                'approver_email' => $approverEmail,
                'contacts'       => $contacts,
                'org_info'       => $orgInfo,
                'server_count'   => -1,
            ];

            // Store private key if generated locally
            $privateKey = $formData['private_key'] ?? $configdata['private_key'] ?? '';

            // ── Validate with provider ──
            if (method_exists($provider, 'validateOrder')) {
                $validation = $provider->validateOrder($orderParams);
                if (!empty($validation['errors'])) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed: ' . implode(', ', $validation['errors']),
                    ];
                }
            }

            // ── Place order with provider ──
            $result = $provider->placeOrder($orderParams);

            if (empty($result['order_id']) && empty($result['success'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Provider failed to place order.',
                ];
            }

            // ── Update order record ──
            $remoteId = $result['order_id'] ?? $result['remote_id'] ?? '';

            $newConfigdata = array_merge($configdata, [
                'provider'        => $slug,
                'csr'             => $csr,
                'private_key'     => $privateKey,
                'dcv_method'      => $dcvMethod,
                'domains'         => $domains,
                'contacts'        => $contacts,
                'org_info'        => $orgInfo,
                'applyReturn'     => $result,
                'submitted_at'    => date('Y-m-d H:i:s'),
                'isDraft'         => false,
            ]);

            $updateData = [
                'status'     => 'Pending',
                'configdata' => json_encode($newConfigdata, JSON_UNESCAPED_UNICODE),
            ];

            // Set remote_id based on table
            if (($order->_source_table ?? '') === 'mod_aio_ssl_orders') {
                $updateData['remote_id'] = $remoteId;
            } else {
                $updateData['remoteid'] = $remoteId;
            }

            ProviderBridge::updateOrder($order, $updateData);

            logModuleCall('aio_ssl', 'submitApply', [
                'serviceid' => $serviceId,
                'provider'  => $slug,
                'product'   => $productCode,
                'remote_id' => $remoteId,
            ], 'Order placed successfully');

            return [
                'success'   => true,
                'message'   => 'Certificate order submitted successfully.',
                'order_id'  => $remoteId,
                'status'    => 'Pending',
            ];

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            logModuleCall('aio_ssl', 'submitApply_error', $params, $e->getMessage());
            return ['success' => false, 'message' => 'Submit failed: ' . $e->getMessage()];
        }
    }

    /**
     * Save application as draft
     */
    public static function saveDraft(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $configdata['draft'] = [
                'csr'        => $_POST['csr'] ?? '',
                'dcv_method' => $_POST['dcv_method'] ?? 'email',
                'dcv_email'  => $_POST['dcv_email'] ?? '',
                'step'       => (int)($_POST['step'] ?? 1),
                'saved_at'   => date('Y-m-d H:i:s'),
            ];

            // Save contact info if provided
            foreach (['admin_first_name', 'admin_last_name', 'admin_email', 'admin_phone',
                       'org_name', 'org_city', 'org_state', 'org_country'] as $field) {
                if (isset($_POST[$field])) {
                    $configdata['draft'][$field] = $_POST[$field];
                }
            }

            Capsule::table('tblsslorders')
                ->where('id', $order->id)
                ->update(['configdata' => json_encode($configdata)]);

            return ['success' => true, 'message' => 'Draft saved successfully.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Save failed: ' . $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STATUS & DOWNLOAD ACTIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Refresh certificate status from provider
     */
    public static function refreshStatus(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order) {
                return ['success' => false, 'message' => 'No SSL order found.'];
            }

            $remoteId = $order->remote_id ?? $order->remoteid ?? '';
            if (empty($remoteId)) {
                return ['success' => false, 'message' => 'No remote order ID. Certificate may not be submitted yet.'];
            }

            $slug = ProviderBridge::resolveSlugFromOrder($order);
            if (empty($slug)) {
                return ['success' => false, 'message' => 'Provider not resolved.'];
            }

            $provider = ProviderRegistry::get($slug);
            $status = $provider->getOrderStatus($remoteId);

            // Build update data
            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $updateData = ['status' => $status['status'] ?? $order->status];

            // Certificate data (if issued)
            $isCompleted = in_array($status['status'] ?? '', ['Completed', 'Issued', 'Active']);
            if ($isCompleted && !empty($status['certificate'])) {
                $configdata['cert']        = $status['certificate']['cert'] ?? $status['certificate']['crt_code'] ?? '';
                $configdata['ca']          = $status['certificate']['ca'] ?? $status['certificate']['ca_code'] ?? '';
                $configdata['private_key'] = $status['certificate']['private_key']
                    ?? $configdata['private_key'] ?? '';
                $updateData['completiondate'] = date('Y-m-d H:i:s');
            }

            // Dates
            if (!empty($status['begin_date'])) {
                $configdata['begin_date'] = $status['begin_date'];
                $updateData['begin_date'] = $status['begin_date'];
            }
            if (!empty($status['end_date'])) {
                $configdata['end_date'] = $status['end_date'];
                $updateData['end_date'] = $status['end_date'];
            }

            // DCV status
            if (!empty($status['domains']))    $configdata['domains'] = $status['domains'];
            if (!empty($status['dcv_status'])) $configdata['dcv_status'] = $status['dcv_status'];
            if (!empty($status['dcv_info']))   $configdata['dcv_info'] = $status['dcv_info'];

            $configdata['last_refresh'] = date('Y-m-d H:i:s');
            $configdata['api_status'] = $status['status'] ?? '';
            $updateData['configdata'] = json_encode($configdata, JSON_UNESCAPED_UNICODE);

            // Write to CORRECT table
            ProviderBridge::updateOrder($order, $updateData);

            return [
                'success'    => true,
                'status'     => $status['status'] ?? '',
                'message'    => 'Status refreshed: ' . ($status['status'] ?? 'Unknown'),
                'has_cert'   => !empty($configdata['cert']),
                'begin_date' => $configdata['begin_date'] ?? '',
                'end_date'   => $configdata['end_date'] ?? '',
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()];
        }
    }

    /**
     * Download certificate in specified format
     *
     * Formats: apache (CRT+CA+Key ZIP), nginx (PEM), key (private key only),
     *          all (complete ZIP with all formats)
     */
    public static function downloadCert(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }

            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $slug = ProviderBridge::resolveSlugFromOrder($order);
            $remoteId = $order->remote_id ?? $order->remoteid ?? '';

            // Check capability
            if (!empty($slug)) {
                try {
                    $provider = ProviderRegistry::get($slug);
                    $caps = $provider->getCapabilities();
                    if (!in_array('download', $caps)) {
                        return ['success' => false, 'message' => 'This provider does not support certificate download. Use the provider portal.'];
                    }
                } catch (\Exception $e) {}
            }

            // Get cert data — from configdata or from provider API
            $cert = $configdata['cert'] ?? $configdata['applyReturn']['certificate'] ?? '';
            $ca   = $configdata['ca'] ?? $configdata['applyReturn']['caCertificate'] ?? '';
            $key  = $configdata['private_key'] ?? '';

            // If no cert in configdata, try fetching from provider
            if (empty($cert) && !empty($remoteId) && !empty($slug)) {
                try {
                    $provider = ProviderRegistry::get($slug);
                    $certData = $provider->downloadCertificate($remoteId);
                    $cert = $certData['cert'] ?? $certData['crt_code'] ?? '';
                    $ca   = $certData['ca'] ?? $certData['ca_code'] ?? '';

                    // Save to configdata for future downloads
                    if (!empty($cert)) {
                        $configdata['cert'] = $cert;
                        $configdata['ca'] = $ca;
                        ProviderBridge::updateOrder($order, [
                            'configdata' => json_encode($configdata, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => 'Download failed: ' . $e->getMessage()];
                }
            }

            if (empty($cert)) {
                return ['success' => false, 'message' => 'Certificate not available yet.'];
            }

            // Determine format
            $format = $_REQUEST['format'] ?? $_POST['format'] ?? 'all';
            $domain = $order->domain ?? $configdata['domain'] ?? 'certificate';
            $safeDomain = preg_replace('/[^a-zA-Z0-9._-]/', '_', $domain);

            // Build download response
            switch (strtolower($format)) {
                case 'key':
                    if (empty($key)) {
                        return ['success' => false, 'message' => 'Private key not available.'];
                    }
                    return self::sendFileDownload("{$safeDomain}.key", $key, 'application/x-pem-file');

                case 'crt':
                case 'apache':
                    return self::buildZipDownload($safeDomain, [
                        "{$safeDomain}.crt" => $cert,
                        "{$safeDomain}.ca-bundle" => $ca,
                        "{$safeDomain}.key" => $key,
                    ]);

                case 'pem':
                case 'nginx':
                    $pem = trim($cert) . "\n" . trim($ca);
                    return self::buildZipDownload($safeDomain, [
                        "{$safeDomain}.pem" => $pem,
                        "{$safeDomain}.key" => $key,
                    ]);

                case 'all':
                default:
                    $pem = trim($cert) . "\n" . trim($ca);
                    return self::buildZipDownload($safeDomain, [
                        "{$safeDomain}.crt" => $cert,
                        "{$safeDomain}.ca-bundle" => $ca,
                        "{$safeDomain}.pem" => $pem,
                        "{$safeDomain}.key" => $key,
                    ]);
            }

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Download error: ' . $e->getMessage()];
        }
    }

    /**
     * Build ZIP file and send as download
     */
    private static function buildZipDownload(string $baseName, array $files): array
    {
        // Filter empty files
        $files = array_filter($files, function ($content) {
            return !empty(trim($content));
        });

        if (empty($files)) {
            return ['success' => false, 'message' => 'No certificate files available.'];
        }

        // If only one file, send directly
        if (count($files) === 1) {
            $filename = array_key_first($files);
            return self::sendFileDownload($filename, reset($files));
        }

        // Build ZIP
        $tmpFile = tempnam(sys_get_temp_dir(), 'aio_ssl_');
        $zip = new \ZipArchive();

        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Failed to create ZIP archive.'];
        }

        foreach ($files as $filename => $content) {
            $zip->addFromString($filename, $content);
        }
        $zip->close();

        $zipContent = file_get_contents($tmpFile);
        unlink($tmpFile);

        // Send download
        while (ob_get_level()) ob_end_clean();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $baseName . '.zip"');
        header('Content-Length: ' . strlen($zipContent));
        header('Cache-Control: no-cache, must-revalidate');
        echo $zipContent;
        exit;
    }

    /**
     * Send single file download
     */
    private static function sendFileDownload(string $filename, string $content, string $mimeType = 'application/octet-stream'): array
    {
        while (ob_get_level()) ob_end_clean();

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $content;
        exit;
    }

    /**
     * Send file download response
     */
    private static function sendFile(string $filename, string $content, string $mimeType): void
    {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $content;
        exit;
    }

    /**
     * Download certificate files as ZIP
     */
    private static function downloadAsZip(string $domain, string $cert, string $ca, string $key): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ssl_');
        $zip = new \ZipArchive();

        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Cannot create ZIP file.'];
        }

        if (!empty($cert)) $zip->addFromString("{$domain}.crt", $cert);
        if (!empty($ca))   $zip->addFromString("{$domain}.ca-bundle", $ca);
        if (!empty($key))  $zip->addFromString("{$domain}.key", $key);

        // PEM bundle
        $pem = $cert . "\n" . $ca;
        $zip->addFromString("{$domain}.pem", $pem);

        $zip->close();

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $domain . '_ssl.zip"');
        header('Content-Length: ' . filesize($tmpFile));
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    // ═══════════════════════════════════════════════════════════════
    // REISSUE / RENEW / REVOKE / CANCEL
    // ═══════════════════════════════════════════════════════════════

    /**
     * Submit certificate reissue
     */
    public static function submitReissue(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid ?? $order->remote_id ?? '')) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $slug = ProviderBridge::resolveSlugFromOrder($order);
            $provider = ProviderRegistry::get($slug);
            $remoteId = $order->remote_id ?? $order->remoteid ?? '';

            // Check capability
            $caps = $provider->getCapabilities();
            if (!in_array('reissue', $caps)) {
                return ['success' => false, 'message' => 'This provider does not support reissue.'];
            }

            $csr = $_POST['csr'] ?? '';
            if (empty($csr)) {
                return ['success' => false, 'message' => 'New CSR is required for reissue.'];
            }

            $dcvMethod = $_POST['dcv_method'] ?? 'email';
            $approverEmail = $_POST['approver_email'] ?? '';

            $result = $provider->reissueCertificate($remoteId, [
                'csr'            => $csr,
                'dcv_method'     => $dcvMethod,
                'approver_email' => $approverEmail,
            ]);

            // Update configdata
            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $configdata['csr'] = $csr;
            $configdata['reissue_date'] = date('Y-m-d H:i:s');
            $configdata['reissue_result'] = $result;

            // Store new private key if provided
            if (!empty($_POST['private_key'])) {
                $configdata['private_key'] = $_POST['private_key'];
            }

            ProviderBridge::updateOrder($order, [
                'status'     => 'Pending',
                'configdata' => json_encode($configdata, JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'message' => 'Reissue request submitted. Complete domain validation to receive new certificate.',
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Reissue failed: ' . $e->getMessage()];
        }
    }

    /**
     * Renew certificate
     */
    public static function renew(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order) {
                return ['success' => false, 'message' => 'No order found.'];
            }

            $slug = ProviderBridge::resolveSlugFromOrder($order);
            $provider = ProviderRegistry::get($slug);
            $remoteId = $order->remote_id ?? $order->remoteid ?? '';

            $caps = $provider->getCapabilities();
            if (!in_array('renew', $caps)) {
                return ['success' => false, 'message' => 'This provider does not support renewal.'];
            }

            $configdata = OrderService::decodeConfigdata($order->configdata ?? '');
            $csr = $_POST['csr'] ?? $configdata['csr'] ?? '';

            // Constraint C7: TheSSLStore renew = new order with isRenewalOrder flag
            $renewParams = [
                'csr'              => $csr,
                'dcv_method'       => $_POST['dcv_method'] ?? $configdata['dcv_method'] ?? 'email',
                'approver_email'   => $_POST['approver_email'] ?? '',
                'isRenewalOrder'   => true,
                'original_order_id'=> $remoteId,
            ];

            $result = $provider->renewCertificate($remoteId, $renewParams);

            $newRemoteId = $result['order_id'] ?? $result['remote_id'] ?? $remoteId;
            $configdata['renew_date'] = date('Y-m-d H:i:s');
            $configdata['renew_result'] = $result;
            $configdata['previous_remote_id'] = $remoteId;

            ProviderBridge::updateOrder($order, [
                'status'     => 'Pending',
                'configdata' => json_encode($configdata, JSON_UNESCAPED_UNICODE),
                ($order->_source_table === 'mod_aio_ssl_orders' ? 'remote_id' : 'remoteid') => $newRemoteId,
            ]);

            return [
                'success'   => true,
                'message'   => 'Renewal submitted successfully.',
                'order_id'  => $newRemoteId,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Renew failed: ' . $e->getMessage()];
        }
    }

    /**
     * Revoke certificate
     */
    public static function revoke(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $provider = ProviderRegistry::get($slug);

            $reason = $_POST['reason'] ?? '';
            $result = $provider->revokeCertificate($order->remoteid, $reason);

            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $order->id)->update(['status' => 'Revoked']);
                ActivityLogger::log('cert_revoked', 'order', (string)$order->id, "Revoked: {$reason}");
            }

            return $result;

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Revoke failed: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel order
     */
    public static function cancelOrder(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $provider = ProviderRegistry::get($slug);

            $result = $provider->cancelOrder($order->remoteid);

            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $order->id)->update(['status' => 'Cancelled']);
                ActivityLogger::log('order_cancelled', 'order', (string)$order->id, 'Order cancelled by client');
            }

            return $result;

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Cancel failed: ' . $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // DCV ACTIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get DCV email options for a domain
     */
    public static function getDcvEmails(array $params): array
    {
        try {
            $domain = $_POST['domain'] ?? '';
            if (empty($domain)) {
                return ['success' => false, 'message' => 'Domain is required.'];
            }

            $provider = ProviderBridge::getProvider($params['serviceid']);
            $emails = $provider->getDcvEmails($domain);

            return ['success' => true, 'emails' => $emails];

        } catch (UnsupportedOperationException $e) {
            // For providers that don't support DCV email listing, return defaults
            $domain = $_POST['domain'] ?? 'example.com';
            return [
                'success' => true,
                'emails'  => [
                    "admin@{$domain}",
                    "administrator@{$domain}",
                    "webmaster@{$domain}",
                    "hostmaster@{$domain}",
                    "postmaster@{$domain}",
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Resend DCV validation email
     */
    public static function resendDcvEmail(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $email = $_POST['email'] ?? '';

            $provider = ProviderRegistry::get($slug);
            return $provider->resendDcvEmail($order->remoteid, $email);

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Batch update DCV method for domains
     */
    public static function batchUpdateDcv(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $method = $_POST['method'] ?? 'email';

            $provider = ProviderRegistry::get($slug);
            return $provider->changeDcvMethod($order->remoteid, $method);

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get SSL2Buy configuration link
     */
    public static function getConfigLink(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $provider = ProviderRegistry::get($slug);

            return array_merge(['success' => true], $provider->getConfigurationLink($order->remoteid));

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}