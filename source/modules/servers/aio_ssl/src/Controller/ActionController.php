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
     * Submit SSL certificate application
     */
    public static function submitApply(array $params): array
    {
        try {
            $serviceId = $params['serviceid'];
            $order = ProviderBridge::getOrder($serviceId);

            if (!$order) {
                return ['success' => false, 'message' => 'SSL order not found.'];
            }

            if ($order->status !== 'Awaiting Configuration') {
                return ['success' => false, 'message' => 'Order is not awaiting configuration.'];
            }

            $csr = $_POST['csr'] ?? '';
            $dcvMethod = $_POST['dcv_method'] ?? 'email';
            $dcvEmail = $_POST['dcv_email'] ?? '';

            if (empty($csr)) {
                return ['success' => false, 'message' => 'CSR is required.'];
            }

            // Resolve provider
            $provider = ProviderBridge::getProvider($serviceId);
            $slug = $provider->getSlug();

            // Get provider-specific product code
            $configdata = json_decode($order->configdata, true) ?: [];
            $canonicalId = $configdata['canonical_id'] ?? '';
            $productCode = ProviderBridge::getProviderProductCode($canonicalId, $slug);

            if (empty($productCode)) {
                return ['success' => false, 'message' => "No product mapping found for {$canonicalId} on {$slug}."];
            }

            // Build order parameters
            $orderParams = [
                'product_code' => $productCode,
                'period'       => (int)($_POST['period'] ?? 12),
                'csr'          => $csr,
                'server_type'  => (int)($_POST['server_type'] ?? -1),
                'dcv_method'   => $dcvMethod,
                'dcv_email'    => $dcvEmail,
            ];

            // Domains from CSR
            $csrSubject = openssl_csr_get_subject($csr, true);
            $domain = $csrSubject['CN'] ?? '';
            if (!empty($domain)) {
                $orderParams['domains'] = [$domain];
            }

            // SAN domains
            $sanDomains = $_POST['san_domains'] ?? '';
            if (!empty($sanDomains)) {
                $sans = array_filter(array_map('trim', explode("\n", $sanDomains)));
                $orderParams['domains'] = array_merge($orderParams['domains'] ?? [], $sans);
            }

            // OV/EV contact info
            if (!empty($_POST['admin_first_name'])) {
                $orderParams['admin_contact'] = [
                    'first_name' => $_POST['admin_first_name'] ?? '',
                    'last_name'  => $_POST['admin_last_name'] ?? '',
                    'email'      => $_POST['admin_email'] ?? '',
                    'phone'      => $_POST['admin_phone'] ?? '',
                    'title'      => $_POST['admin_title'] ?? '',
                ];
            }

            if (!empty($_POST['org_name'])) {
                $orderParams['org_info'] = [
                    'name'    => $_POST['org_name'] ?? '',
                    'city'    => $_POST['org_city'] ?? '',
                    'state'   => $_POST['org_state'] ?? '',
                    'country' => $_POST['org_country'] ?? '',
                    'zip'     => $_POST['org_zip'] ?? '',
                    'phone'   => $_POST['org_phone'] ?? '',
                    'address' => $_POST['org_address'] ?? '',
                ];
            }

            // Validate order
            $validation = $provider->validateOrder($orderParams);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed: ' . implode(', ', $validation['errors']),
                ];
            }

            // Place order
            $result = $provider->placeOrder($orderParams);

            // Update tblsslorders
            $updateData = [
                'remoteid' => $result['order_id'],
                'status'   => 'Pending',
            ];

            $configdata['provider']     = $slug;
            $configdata['product_code'] = $productCode;
            $configdata['csr']          = $csr;
            $configdata['dcv_method']   = $dcvMethod;
            $configdata['dcv_email']    = $dcvEmail;
            $configdata['domains']      = $orderParams['domains'] ?? [$domain];
            $configdata['applied_at']   = date('Y-m-d H:i:s');
            $configdata['order_extra']  = $result['extra'] ?? [];

            if (isset($orderParams['admin_contact'])) {
                $configdata['admin_contact'] = $orderParams['admin_contact'];
            }
            if (isset($orderParams['org_info'])) {
                $configdata['org_info'] = $orderParams['org_info'];
            }

            $updateData['configdata'] = json_encode($configdata);
            Capsule::table('tblsslorders')->where('id', $order->id)->update($updateData);

            ActivityLogger::log('cert_applied', 'order', (string)$order->id,
                "Certificate applied via {$slug} (remote: {$result['order_id']})");

            return [
                'success'  => true,
                'message'  => 'Certificate application submitted successfully.',
                'order_id' => $result['order_id'],
                'status'   => $result['status'],
            ];

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Application failed: ' . $e->getMessage()];
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
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';

            if (empty($slug)) {
                return ['success' => false, 'message' => 'Provider not resolved.'];
            }

            $provider = ProviderRegistry::get($slug);
            $status = $provider->getOrderStatus($order->remoteid);

            $update = ['status' => $status['status']];

            if ($status['status'] === 'Completed' && !empty($status['certificate'])) {
                $configdata['cert']        = $status['certificate']['cert'] ?? '';
                $configdata['ca']          = $status['certificate']['ca'] ?? '';
                $configdata['private_key'] = $status['certificate']['private_key'] ?? ($configdata['private_key'] ?? '');
                $update['completiondate']  = date('Y-m-d H:i:s');
            }

            if (!empty($status['begin_date'])) $configdata['begin_date'] = $status['begin_date'];
            if (!empty($status['end_date']))   $configdata['end_date'] = $status['end_date'];
            if (!empty($status['domains']))    $configdata['domains'] = $status['domains'];
            if (!empty($status['dcv_status'])) $configdata['dcv_status'] = $status['dcv_status'];

            $configdata['last_refresh'] = date('Y-m-d H:i:s');
            $update['configdata'] = json_encode($configdata);

            Capsule::table('tblsslorders')->where('id', $order->id)->update($update);

            return [
                'success'    => true,
                'status'     => $status['status'],
                'message'    => 'Status refreshed: ' . $status['status'],
                'has_cert'   => !empty($configdata['cert']),
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()];
        }
    }

    /**
     * Download certificate (force download as ZIP or individual files)
     */
    public static function downloadCert(array $params): array
    {
        try {
            $order = ProviderBridge::getOrder($params['serviceid']);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $format = $_GET['format'] ?? $_POST['format'] ?? 'zip';

            // Check if cert is in configdata
            $cert = $configdata['cert'] ?? '';
            $ca = $configdata['ca'] ?? '';
            $privateKey = $configdata['private_key'] ?? '';

            // If no cert cached, try to download from provider
            if (empty($cert) && !empty($order->remoteid)) {
                $slug = $configdata['provider'] ?? '';
                if (!empty($slug)) {
                    try {
                        $provider = ProviderRegistry::get($slug);
                        $certData = $provider->downloadCertificate($order->remoteid);
                        $cert = $certData['cert'] ?? '';
                        $ca = $certData['ca'] ?? '';
                        if (!empty($certData['private_key'])) {
                            $privateKey = $certData['private_key'];
                        }

                        // Cache in configdata
                        $configdata['cert'] = $cert;
                        $configdata['ca'] = $ca;
                        Capsule::table('tblsslorders')
                            ->where('id', $order->id)
                            ->update(['configdata' => json_encode($configdata)]);
                    } catch (\Exception $e) {
                        return ['success' => false, 'message' => 'Download failed: ' . $e->getMessage()];
                    }
                }
            }

            if (empty($cert)) {
                return ['success' => false, 'message' => 'Certificate not yet available.'];
            }

            $domain = $configdata['domains'][0] ?? 'certificate';
            $safeDomain = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain);

            // Build file data based on format
            switch ($format) {
                case 'cert':
                    self::sendFile("{$safeDomain}.crt", $cert, 'application/x-x509-ca-cert');
                    return ['success' => true];
                case 'ca':
                    self::sendFile("{$safeDomain}.ca-bundle", $ca, 'application/x-x509-ca-cert');
                    return ['success' => true];
                case 'key':
                    if (empty($privateKey)) {
                        return ['success' => false, 'message' => 'Private key not available.'];
                    }
                    self::sendFile("{$safeDomain}.key", $privateKey, 'application/x-pem-file');
                    return ['success' => true];
                case 'pem':
                    $pem = $cert . "\n" . $ca;
                    self::sendFile("{$safeDomain}.pem", $pem, 'application/x-pem-file');
                    return ['success' => true];
                default: // zip
                    return self::downloadAsZip($safeDomain, $cert, $ca, $privateKey);
            }

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Download error: ' . $e->getMessage()];
        }
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
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $csr = $_POST['csr'] ?? '';
            if (empty($csr)) {
                return ['success' => false, 'message' => 'New CSR is required for reissue.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $provider = ProviderRegistry::get($slug);

            $result = $provider->reissueCertificate($order->remoteid, [
                'csr'        => $csr,
                'dcv_method' => $_POST['dcv_method'] ?? 'email',
                'dcv_email'  => $_POST['dcv_email'] ?? '',
            ]);

            if ($result['success']) {
                $configdata['reissue_csr'] = $csr;
                $configdata['reissue_at'] = date('Y-m-d H:i:s');
                Capsule::table('tblsslorders')->where('id', $order->id)->update([
                    'status'     => 'Processing',
                    'configdata' => json_encode($configdata),
                ]);
                ActivityLogger::log('cert_reissued', 'order', (string)$order->id, 'Reissue submitted');
            }

            return $result;

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
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
            if (!$order || empty($order->remoteid)) {
                return ['success' => false, 'message' => 'No active order found.'];
            }

            $configdata = json_decode($order->configdata, true) ?: [];
            $slug = $configdata['provider'] ?? '';
            $provider = ProviderRegistry::get($slug);

            $result = $provider->renewCertificate($order->remoteid, [
                'product_code' => $configdata['product_code'] ?? '',
                'csr'          => $_POST['csr'] ?? $configdata['csr'] ?? '',
                'period'       => (int)($_POST['period'] ?? 12),
                'dcv_method'   => $_POST['dcv_method'] ?? $configdata['dcv_method'] ?? 'email',
                'dcv_email'    => $_POST['dcv_email'] ?? '',
            ]);

            if (!empty($result['order_id'])) {
                $configdata['renewed_from'] = $order->remoteid;
                Capsule::table('tblsslorders')->where('id', $order->id)->update([
                    'remoteid'   => $result['order_id'],
                    'status'     => 'Pending',
                    'configdata' => json_encode($configdata),
                ]);
                ActivityLogger::log('cert_renewed', 'order', (string)$order->id, 'Renewed → ' . $result['order_id']);
            }

            return ['success' => true, 'message' => 'Renewal submitted.', 'order_id' => $result['order_id'] ?? ''];

        } catch (UnsupportedOperationException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Renewal failed: ' . $e->getMessage()];
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