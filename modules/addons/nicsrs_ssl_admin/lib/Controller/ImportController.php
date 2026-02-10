<?php
/**
 * Import Controller
 * Handles importing/linking external SSL certificates to WHMCS
 * 
 * File: lib/Controller/ImportController.php
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Controller;

use WHMCS\Database\Capsule;
use NicsrsAdmin\Service\NicsrsApiService;

class ImportController extends BaseController
{
    /**
     * @var NicsrsApiService API service
     */
    private $apiService;

    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->apiService = new NicsrsApiService($this->getApiToken());
    }

    /**
     * Render import page
     */
    public function render(string $action): void
    {
        // Get existing linked orders for reference
        $linkedOrders = $this->getLinkedOrders(20);

        $data = [
            'linkedOrders' => $linkedOrders,
        ];

        $this->includeTemplate('import', $data);
    }

    /**
     * Get recently linked orders
     */
    private function getLinkedOrders(int $limit = 20): array
    {
        return Capsule::table('nicsrs_sslorders as o')
            ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
            ->select(['o.*', 'c.firstname', 'c.lastname', 'c.email'])
            ->whereNotNull('o.remoteid')
            ->orderBy('o.id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                $config = json_decode($order->configdata, true) ?: [];
                $order->domain = $config['domainInfo'][0]['domainName'] ?? 'N/A';
                $order->end_date = $config['applyReturn']['endDate'] ?? null;
                return $order;
            })
            ->toArray();
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(array $post): string
    {
        $action = $post['ajax_action'] ?? '';

        switch ($action) {
            case 'lookup_cert':
                return $this->lookupCertificate($post);
            
            case 'import_cert':
                return $this->importCertificate($post);
            
            case 'link_existing':
                return $this->linkExistingService($post);
            
            case 'bulk_import':
                return $this->bulkImport($post);

            default:
                return $this->jsonError('Unknown action: ' . $action);
        }
    }

    /**
     * Lookup certificate from NicSRS by certId
     */
    private function lookupCertificate(array $post): string
    {
        $certId = trim($post['cert_id'] ?? '');

        if (empty($certId)) {
            return $this->jsonError('Certificate ID is required');
        }

        try {
            // Check if already imported
            $existing = Capsule::table('nicsrs_sslorders')
                ->where('remoteid', $certId)
                ->first();
            
            if ($existing) {
                return $this->jsonError("This certificate is already imported (Order #{$existing->id})");
            }

            // Lookup from API
            $result = $this->apiService->collect($certId);

            if ($result['code'] != 1 && $result['code'] != 2) {
                return $this->jsonError($result['msg'] ?? 'Certificate not found');
            }

            // Extract certificate info
            $certInfo = [
                'certId' => $certId,
                'status' => $result['status'] ?? 'unknown',
                'domain' => $result['data']['dcvList'][0]['domainName'] ?? 'N/A',
                'domains' => [],
                'beginDate' => $result['data']['beginDate'] ?? null,
                'endDate' => $result['data']['endDate'] ?? null,
                'hasCertificate' => !empty($result['data']['certificate']),
                'productType' => $result['data']['productType'] ?? 'Unknown',
            ];

            // Extract all domains
            if (!empty($result['data']['dcvList'])) {
                foreach ($result['data']['dcvList'] as $dcv) {
                    $certInfo['domains'][] = [
                        'domain' => $dcv['domainName'],
                        'verified' => ($dcv['is_verify'] ?? '') === 'verified',
                    ];
                }
            }

            return $this->jsonSuccess('Certificate found', ['certificate' => $certInfo]);

        } catch (\Exception $e) {
            return $this->jsonError('Lookup failed: ' . $e->getMessage());
        }
    }

    /**
     * Import certificate without linking to existing service
     * Creates a new SSL order record in nicsrs_sslorders
     */
    private function importCertificate(array $post): string
    {
        $certId = trim($post['cert_id'] ?? '');

        if (empty($certId)) {
            return $this->jsonError('Certificate ID is required');
        }

        try {
            // Check if already imported
            $existing = Capsule::table('nicsrs_sslorders')
                ->where('remoteid', $certId)
                ->first();
            
            if ($existing) {
                return $this->jsonError("This certificate is already imported (Order #{$existing->id})");
            }

            // Fetch certificate details from API
            $result = $this->apiService->collect($certId);
            
            if ($result['code'] != 1 && $result['code'] != 2) {
                return $this->jsonError($result['msg'] ?? 'Failed to fetch certificate');
            }

            $status = strtolower($result['status'] ?? 'pending');
            $certData = $result['data'] ?? [];

            // Build configdata
            $configData = [
                'domainInfo' => [],
                'applyReturn' => [
                    'certId' => $certId,
                    'beginDate' => $certData['beginDate'] ?? null,
                    'endDate' => $certData['endDate'] ?? null,
                    'certificate' => $certData['certificate'] ?? null,
                    'caCertificate' => $certData['caCertificate'] ?? null,
                ],
                'importedAt' => date('Y-m-d H:i:s'),
                'importedBy' => $this->adminId,
            ];

            // Extract domain info
            if (!empty($certData['dcvList'])) {
                foreach ($certData['dcvList'] as $dcv) {
                    $configData['domainInfo'][] = [
                        'domainName' => $dcv['domainName'],
                        'dcvMethod' => $dcv['dcvMethod'] ?? 'EMAIL',
                        'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                    ];
                }
            }

            $primaryDomain = $configData['domainInfo'][0]['domainName'] ?? 'imported-cert.com';
            $provisionDate = date('Y-m-d');
            
            // Set completiondate based on status and cert data
            $completionDate = null;
            if ($status === 'complete') {
                $completionDate = !empty($certData['beginDate']) 
                    ? $certData['beginDate'] . ' 00:00:00'
                    : date('Y-m-d H:i:s');
            }

            // Create SSL order record (without linking to service)
            $orderId = Capsule::table('nicsrs_sslorders')->insertGetId([
                'userid' => 0,
                'serviceid' => 0,
                'addon_id' => '',
                'remoteid' => $certId,
                'module' => 'nicsrs_ssl',
                'certtype' => $certData['productType'] ?? 'imported',
                'configdata' => json_encode($configData),
                'provisiondate' => $provisionDate,
                'completiondate' => $completionDate ?? date('Y-m-d H:i:s'),
                'status' => $status,
            ]);

            // Log activity
            $this->logger->log('import_cert', 'order', $orderId, null, json_encode([
                'certId' => $certId,
                'domain' => $primaryDomain,
                'linked' => false,
            ]));

            return $this->jsonSuccess("Certificate imported successfully! Order ID: #{$orderId}", [
                'order_id' => $orderId,
            ]);

        } catch (\Exception $e) {
            return $this->jsonError('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Link certificate to existing WHMCS service by Service ID
     */
    private function linkExistingService(array $post): string
    {
        $certId = trim($post['cert_id'] ?? '');
        $serviceId = (int) ($post['service_id'] ?? 0);

        if (empty($certId)) {
            return $this->jsonError('Certificate ID is required');
        }

        if (!$serviceId) {
            return $this->jsonError('Service ID is required');
        }

        try {
            // Check if certificate already imported
            $existingCert = Capsule::table('nicsrs_sslorders')
                ->where('remoteid', $certId)
                ->first();
            
            if ($existingCert) {
                return $this->jsonError("This certificate is already imported (Order #{$existingCert->id})");
            }

            // Check if service exists
            $service = Capsule::table('tblhosting as h')
                ->leftJoin('tblclients as c', 'h.userid', '=', 'c.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->select([
                    'h.id as serviceid',
                    'h.userid',
                    'h.domain',
                    'h.domainstatus',
                    'c.firstname',
                    'c.lastname',
                    'c.email',
                    'p.name as product_name',
                    'p.configoption1 as cert_type',
                    'p.servertype',
                ])
                ->where('h.id', $serviceId)
                ->first();

            if (!$service) {
                return $this->jsonError("Service #$serviceId not found");
            }

            // Verify it's an SSL product (optional but recommended)
            if ($service->servertype !== 'nicsrs_ssl') {
                return $this->jsonError("Service #$serviceId is not a NicSRS SSL product (servertype: {$service->servertype})");
            }

            // Check if service already has SSL order linked
            $existingOrder = Capsule::table('nicsrs_sslorders')
                ->where('serviceid', $serviceId)
                ->first();

            if ($existingOrder) {
                return $this->jsonError("Service #$serviceId is already linked to Order #{$existingOrder->id}");
            }

            // Fetch certificate from API
            $result = $this->apiService->collect($certId);
            
            if ($result['code'] != 1 && $result['code'] != 2) {
                return $this->jsonError($result['msg'] ?? 'Failed to fetch certificate from API');
            }

            $status = strtolower($result['status'] ?? 'pending');
            $certData = $result['data'] ?? [];

            // Build configdata
            $configData = [
                'domainInfo' => [],
                'applyReturn' => [
                    'certId' => $certId,
                    'beginDate' => $certData['beginDate'] ?? null,
                    'endDate' => $certData['endDate'] ?? null,
                    'certificate' => $certData['certificate'] ?? null,
                    'caCertificate' => $certData['caCertificate'] ?? null,
                ],
                'importedAt' => date('Y-m-d H:i:s'),
                'importedBy' => $this->adminId,
                'linkedServiceId' => $serviceId,
            ];

            // Extract domain info
            if (!empty($certData['dcvList'])) {
                foreach ($certData['dcvList'] as $dcv) {
                    $configData['domainInfo'][] = [
                        'domainName' => $dcv['domainName'],
                        'dcvMethod' => $dcv['dcvMethod'] ?? 'EMAIL',
                        'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                    ];
                }
            }

            $primaryDomain = $configData['domainInfo'][0]['domainName'] ?? $service->domain ?? 'imported-cert.com';
            $provisionDate = date('Y-m-d');
            
            // Set completiondate based on status
            $completionDate = null;
            if ($status === 'complete') {
                $completionDate = !empty($certData['beginDate']) 
                    ? $certData['beginDate'] . ' 00:00:00'
                    : date('Y-m-d H:i:s');
            }

            // Create SSL order record linked to service
            $orderId = Capsule::table('nicsrs_sslorders')->insertGetId([
                'userid' => $service->userid,
                'serviceid' => $serviceId,
                'addon_id' => '',
                'remoteid' => $certId,
                'module' => 'nicsrs_ssl',
                'certtype' => $service->cert_type ?? $certData['productType'] ?? 'imported',
                'configdata' => json_encode($configData),
                'provisiondate' => $provisionDate,
                'completiondate' => $completionDate ?? date('Y-m-d H:i:s'),
                'status' => $status,
            ]);

            // Update service domain if empty
            if (empty($service->domain) && !empty($primaryDomain)) {
                Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->update(['domain' => $primaryDomain]);
            }

            // Log activity
            $this->logger->log('import_link_cert', 'order', $orderId, null, json_encode([
                'certId' => $certId,
                'domain' => $primaryDomain,
                'serviceId' => $serviceId,
                'clientName' => trim($service->firstname . ' ' . $service->lastname),
            ]));

            return $this->jsonSuccess(
                "Certificate imported and linked successfully! Order ID: #{$orderId}, linked to Service #{$serviceId} ({$service->firstname} {$service->lastname})", 
                [
                    'order_id' => $orderId,
                    'service_id' => $serviceId,
                ]
            );

        } catch (\Exception $e) {
            return $this->jsonError('Link failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk import multiple certificates (without linking)
     */
    private function bulkImport(array $post): string
    {
        $certIds = $post['cert_ids'] ?? [];
        
        if (!is_array($certIds)) {
            $certIds = [$certIds];
        }

        $certIds = array_filter(array_map('trim', $certIds));

        if (empty($certIds)) {
            return $this->jsonError('No certificate IDs provided');
        }

        $imported = 0;
        $errors = [];

        foreach ($certIds as $certId) {
            try {
                // Check if already imported
                $existing = Capsule::table('nicsrs_sslorders')
                    ->where('remoteid', $certId)
                    ->first();
                
                if ($existing) {
                    $errors[] = "#{$certId}: Already imported (Order #{$existing->id})";
                    continue;
                }

                // Fetch from API
                $result = $this->apiService->collect($certId);
                
                if ($result['code'] != 1 && $result['code'] != 2) {
                    $errors[] = "#{$certId}: " . ($result['msg'] ?? 'Not found');
                    continue;
                }

                $status = strtolower($result['status'] ?? 'pending');
                $certData = $result['data'] ?? [];

                // Build configdata
                $configData = [
                    'domainInfo' => [],
                    'applyReturn' => [
                        'certId' => $certId,
                        'beginDate' => $certData['beginDate'] ?? null,
                        'endDate' => $certData['endDate'] ?? null,
                        'certificate' => $certData['certificate'] ?? null,
                        'caCertificate' => $certData['caCertificate'] ?? null,
                    ],
                    'importedAt' => date('Y-m-d H:i:s'),
                    'importedBy' => $this->adminId,
                    'bulkImport' => true,
                ];

                // Extract domain info
                if (!empty($certData['dcvList'])) {
                    foreach ($certData['dcvList'] as $dcv) {
                        $configData['domainInfo'][] = [
                            'domainName' => $dcv['domainName'],
                            'dcvMethod' => $dcv['dcvMethod'] ?? 'EMAIL',
                            'isVerified' => ($dcv['is_verify'] ?? '') === 'verified',
                        ];
                    }
                }

                $provisionDate = date('Y-m-d');
                $completionDate = ($status === 'complete') 
                    ? (!empty($certData['beginDate']) ? $certData['beginDate'] . ' 00:00:00' : date('Y-m-d H:i:s'))
                    : date('Y-m-d H:i:s');

                // Create SSL order record
                Capsule::table('nicsrs_sslorders')->insert([
                    'userid' => 0,
                    'serviceid' => 0,
                    'addon_id' => '',
                    'remoteid' => $certId,
                    'module' => 'nicsrs_ssl',
                    'certtype' => $certData['productType'] ?? 'imported',
                    'configdata' => json_encode($configData),
                    'provisiondate' => $provisionDate,
                    'completiondate' => $completionDate,
                    'status' => $status,
                ]);

                $imported++;

            } catch (\Exception $e) {
                $errors[] = "#{$certId}: " . $e->getMessage();
            }
        }

        // Log activity
        if ($imported > 0) {
            $this->logger->log('bulk_import', 'order', null, null, json_encode([
                'total' => count($certIds),
                'imported' => $imported,
                'errors' => count($errors),
            ]));
        }

        return $this->jsonSuccess(
            "Bulk import completed: {$imported} of " . count($certIds) . " certificates imported",
            [
                'imported' => $imported,
                'total' => count($certIds),
                'errors' => $errors,
            ]
        );
    }
}