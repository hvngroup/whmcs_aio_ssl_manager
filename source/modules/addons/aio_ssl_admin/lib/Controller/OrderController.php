namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;

class OrderController extends BaseController
{
    /** @var string[] All SSL module names to query */
    private $modules = ['aio_ssl', 'nicsrs_ssl', 'SSLCENTERWHMCS', 'thesslstore_ssl', 'ssl2buy'];

    public function render(string $action = ''): void
    {
        switch ($action) {
            case 'detail':
                $this->renderDetail();
                break;
            default:
                $this->renderList();
                break;
        }
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'refresh_status':
                return $this->refreshOrderStatus();
            case 'resend_dcv':
                return $this->resendDcv();
            case 'revoke':
                return $this->revokeOrder();
            case 'cancel':
                return $this->cancelOrder();
            default:
                return parent::handleAjax($action);
        }
    }

    private function renderList(): void
    {
        $page = $this->getCurrentPage();
        $statusFilter = $this->input('status', '');
        $providerFilter = $this->input('provider', '');
        $search = $this->input('search', '');

        $q = Capsule::table('tblsslorders')
            ->leftJoin('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
            ->leftJoin('tblclients', 'tblsslorders.userid', '=', 'tblclients.id')
            ->whereIn('tblsslorders.module', $this->modules);

        if ($statusFilter) {
            $q->where('tblsslorders.status', $statusFilter);
        }
        if ($providerFilter) {
            $q->where('tblsslorders.module', $providerFilter);
        }
        if ($search) {
            $q->where(function ($q2) use ($search) {
                $q2->where('tblhosting.domain', 'LIKE', "%{$search}%")
                   ->orWhere('tblsslorders.remoteid', 'LIKE', "%{$search}%")
                   ->orWhere('tblsslorders.certtype', 'LIKE', "%{$search}%")
                   ->orWhereRaw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) LIKE ?", ["%{$search}%"]);
            });
        }

        $total = $q->count();
        $pagination = $this->paginate($total, $page);

        $orders = (clone $q)
            ->select([
                'tblsslorders.*',
                'tblhosting.domain',
                Capsule::raw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) as client_name"),
            ])
            ->orderBy('tblsslorders.id', 'desc')
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->get()
            ->toArray();

        $this->renderTemplate('orders.tpl', [
            'orders'     => $orders,
            'pagination' => $pagination,
            'filters'    => compact('statusFilter', 'providerFilter', 'search'),
        ]);
    }

    private function renderDetail(): void
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')
            ->leftJoin('tblhosting', 'tblsslorders.serviceid', '=', 'tblhosting.id')
            ->leftJoin('tblclients', 'tblsslorders.userid', '=', 'tblclients.id')
            ->where('tblsslorders.id', $id)
            ->select([
                'tblsslorders.*',
                'tblhosting.domain',
                Capsule::raw("CONCAT(tblclients.firstname, ' ', tblclients.lastname) as client_name"),
            ])
            ->first();

        if (!$order) {
            echo '<div class="alert alert-danger">Order not found.</div>';
            return;
        }

        $configdata = json_decode($order->configdata, true) ?: [];

        // Activity log for this order
        $activities = ActivityLogger::getRecent(20, 'order', (string)$order->id);

        $this->renderTemplate('order_detail.tpl', [
            'order'      => $order,
            'configdata' => $configdata,
            'activities' => $activities,
        ]);
    }

    private function refreshOrderStatus(): array
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'No remote ID found.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        if (empty($slug)) {
            // Determine provider from module name
            $moduleMap = [
                'nicsrs_ssl' => 'nicsrs', 'SSLCENTERWHMCS' => 'gogetssl',
                'thesslstore_ssl' => 'thesslstore', 'ssl2buy' => 'ssl2buy',
            ];
            $slug = $moduleMap[$order->module] ?? '';
        }

        if (empty($slug)) {
            return ['success' => false, 'message' => 'Cannot determine provider.'];
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $status = $provider->getOrderStatus($order->remoteid);

            $updateData = ['status' => $status['status']];

            if ($status['status'] === 'Completed' && !empty($status['certificate'])) {
                $configdata = array_merge($configdata, $status['certificate']);
                $updateData['completiondate'] = date('Y-m-d H:i:s');
            }

            if (!empty($status['end_date'])) $configdata['end_date'] = $status['end_date'];
            if (!empty($status['begin_date'])) $configdata['begin_date'] = $status['begin_date'];
            if (!empty($status['domains'])) $configdata['domains'] = $status['domains'];

            $updateData['configdata'] = json_encode($configdata);
            Capsule::table('tblsslorders')->where('id', $id)->update($updateData);

            ActivityLogger::log('order_status_refreshed', 'order', (string)$id, "Status: {$status['status']}");

            return ['success' => true, 'status' => $status['status'], 'message' => 'Status refreshed.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()];
        }
    }

    private function resendDcv(): array
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            return $provider->resendDcvEmail($order->remoteid);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function revokeOrder(): array
    {
        $id = (int)$this->input('id');
        $reason = $this->input('reason', '');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->revokeCertificate($order->remoteid, $reason);
            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $id)->update(['status' => 'Revoked']);
                ActivityLogger::log('order_revoked', 'order', (string)$id, "Order revoked: {$reason}");
            }
            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function cancelOrder(): array
    {
        $id = (int)$this->input('id');
        $order = Capsule::table('tblsslorders')->find($id);

        if (!$order || empty($order->remoteid)) {
            return ['success' => false, 'message' => 'Invalid order.'];
        }

        $configdata = json_decode($order->configdata, true) ?: [];
        $slug = $configdata['provider'] ?? '';

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->cancelOrder($order->remoteid);
            if ($result['success']) {
                Capsule::table('tblsslorders')->where('id', $id)->update(['status' => 'Cancelled']);
                ActivityLogger::log('order_cancelled', 'order', (string)$id, 'Order cancelled');
            }
            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}