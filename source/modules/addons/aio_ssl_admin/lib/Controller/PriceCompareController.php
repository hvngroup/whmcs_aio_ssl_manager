<?php
/**
 * Price Compare Controller — Search, compare, export
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Service\PriceCompareService;

class PriceCompareController extends BaseController
{
    public function render(string $action = ''): void
    {
        $service = new PriceCompareService();

        // Get all canonical products for dropdown
        $canonicals = Capsule::table('mod_aio_ssl_product_map')
            ->where('is_active', 1)
            ->orderBy('vendor')
            ->orderBy('canonical_name')
            ->get()
            ->toArray();

        $selectedId = $this->input('canonical_id', '');
        $comparison = null;

        if ($selectedId) {
            $comparison = $service->compare($selectedId);
        }

        $this->renderTemplate('price_compare.php', [
            'canonicals' => $canonicals,
            'selectedId' => $selectedId,
            'comparison' => $comparison,
        ]);
    }

    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'compare':
                return $this->ajaxCompare();
            case 'compare_all':
                return $this->ajaxCompareAll();
            case 'export_csv':
                return $this->ajaxExportCsv();
            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    private function ajaxCompare(): array
    {
        $id = $this->input('canonical_id');
        if (empty($id)) {
            return ['success' => false, 'message' => 'Select a product.'];
        }

        $service = new PriceCompareService();
        $result = $service->compare($id);

        return ['success' => true, 'data' => $result];
    }

    private function ajaxCompareAll(): array
    {
        $service = new PriceCompareService();
        return ['success' => true, 'data' => $service->compareAll()];
    }

    private function ajaxExportCsv(): array
    {
        $service = new PriceCompareService();
        $csv = $service->exportCsv();

        return [
            'success'  => true,
            'csv'      => $csv,
            'filename' => 'aio_ssl_price_comparison_' . date('Ymd') . '.csv',
        ];
    }
}