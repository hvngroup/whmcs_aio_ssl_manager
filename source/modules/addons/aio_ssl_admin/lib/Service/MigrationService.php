<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;
use AioSSL\Core\ActivityLogger;

class MigrationService
{
    /** @var array Legacy module names */
    private const LEGACY_MODULES = ['nicsrs_ssl', 'SSLCENTERWHMCS', 'thesslstore_ssl', 'ssl2buy'];

    /**
     * Get all legacy orders that can be migrated
     *
     * @return array
     */
    public function getLegacyOrders(): array
    {
        return Capsule::table('tblsslorders')
            ->leftJoin('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
            ->leftJoin('tblclients', 'tblsslorders.userid', '=', 'tblclients.id')
            ->whereIn('tblsslorders.module', self::LEGACY_MODULES)
            ->select([
                'tblsslorders.*',
                'tblhosting.domain',
                Capsule::raw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) as client_name"),
            ])
            ->orderBy('tblsslorders.module')
            ->orderBy('tblsslorders.id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Claim a legacy order — update module to 'aio_ssl'
     *
     * @param int $orderId
     * @return array
     */
    public function claimOrder(int $orderId): array
    {
        $order = Capsule::table('tblsslorders')->find($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        if ($order->module === 'aio_ssl') {
            return ['success' => false, 'message' => 'Order already belongs to AIO SSL.'];
        }

        if (!in_array($order->module, self::LEGACY_MODULES)) {
            return ['success' => false, 'message' => 'Not a recognized legacy module.'];
        }

        // Normalize configdata
        $configdata = $this->normalizeConfigdata($order->module, $order->configdata);

        // Determine provider slug from module
        $providerMap = [
            'nicsrs_ssl'      => 'nicsrs',
            'SSLCENTERWHMCS'  => 'gogetssl',
            'thesslstore_ssl' => 'thesslstore',
            'ssl2buy'         => 'ssl2buy',
        ];
        $providerSlug = $providerMap[$order->module] ?? 'unknown';
        $configdata['provider'] = $providerSlug;
        $configdata['migrated_from'] = $order->module;
        $configdata['migrated_at'] = date('Y-m-d H:i:s');

        Capsule::table('tblsslorders')->where('id', $orderId)->update([
            'module'     => 'aio_ssl',
            'configdata' => json_encode($configdata),
        ]);

        ActivityLogger::log('order_claimed', 'order', (string)$orderId,
            "Claimed from {$order->module} to aio_ssl (provider: {$providerSlug})");

        return ['success' => true, 'message' => 'Order claimed successfully.', 'provider' => $providerSlug];
    }

    /**
     * Bulk claim all legacy orders
     *
     * @return array ['claimed'=>int, 'failed'=>int, 'errors'=>array]
     */
    public function claimAll(): array
    {
        $orders = Capsule::table('tblsslorders')
            ->whereIn('module', self::LEGACY_MODULES)
            ->pluck('id');

        $claimed = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $id) {
            $result = $this->claimOrder($id);
            if ($result['success']) {
                $claimed++;
            } else {
                $failed++;
                $errors[] = "Order #{$id}: {$result['message']}";
            }
        }

        return compact('claimed', 'failed', 'errors');
    }

    /**
     * Normalize legacy configdata formats to AIO standard
     */
    public function normalizeConfigdata(string $module, $configdata): array
    {
        $data = is_string($configdata) ? json_decode($configdata, true) : (array)$configdata;

        // Fallback to unserialize for old format
        if (empty($data) && is_string($configdata)) {
            $data = @unserialize($configdata);
            if (!is_array($data)) $data = [];
        }

        return match ($module) {
            'nicsrs_ssl'      => $this->normalizeNicsrs($data),
            'SSLCENTERWHMCS'  => $this->normalizeGoGetSSL($data),
            'thesslstore_ssl' => $this->normalizeTheSSLStore($data),
            'ssl2buy'         => $this->normalizeSSL2Buy($data),
            default           => $data,
        };
    }

    private function normalizeNicsrs(array $data): array
    {
        return [
            'csr'          => $data['csr'] ?? '',
            'cert'         => $data['crt'] ?? $data['cert'] ?? '',
            'ca'           => $data['ca'] ?? '',
            'private_key'  => $data['private_key'] ?? '',
            'domains'      => $data['domainInfo'] ?? $data['domains'] ?? [],
            'begin_date'   => $data['beginDate'] ?? $data['begin_date'] ?? null,
            'end_date'     => $data['endDate'] ?? $data['end_date'] ?? null,
            'dcv_method'   => $data['dcv_method'] ?? 'email',
            'original'     => $data,
        ];
    }

    private function normalizeGoGetSSL(array $data): array
    {
        return [
            'csr'          => $data['csr'] ?? '',
            'cert'         => $data['crt'] ?? $data['crt_code'] ?? '',
            'ca'           => $data['ca'] ?? $data['ca_code'] ?? '',
            'private_key'  => '',
            'domains'      => isset($data['san']) ? explode(',', $data['san']) : ($data['domains'] ?? []),
            'begin_date'   => $data['valid_from'] ?? null,
            'end_date'     => $data['valid_till'] ?? null,
            'dcv_method'   => $data['approver_method'] ?? 'email',
            'original'     => $data,
        ];
    }

    private function normalizeTheSSLStore(array $data): array
    {
        return [
            'csr'          => $data['csr'] ?? '',
            'cert'         => $data['crt_code'] ?? '',
            'ca'           => $data['ca_code'] ?? '',
            'private_key'  => '',
            'domains'      => $data['domains'] ?? [],
            'begin_date'   => $data['CertificateStartDate'] ?? null,
            'end_date'     => $data['CertificateEndDate'] ?? null,
            'dcv_method'   => 'email',
            'thesslstore_order_id' => $data['TheSSLStoreOrderID'] ?? '',
            'original'     => $data,
        ];
    }

    private function normalizeSSL2Buy(array $data): array
    {
        return [
            'csr'          => $data['csr'] ?? '',
            'cert'         => '',
            'ca'           => '',
            'private_key'  => '',
            'domains'      => $data['domains'] ?? [],
            'begin_date'   => null,
            'end_date'     => null,
            'dcv_method'   => 'email',
            'ssl2buy_order_id' => $data['orderId'] ?? '',
            'brand'        => $data['brand_name'] ?? '',
            'original'     => $data,
        ];
    }
}