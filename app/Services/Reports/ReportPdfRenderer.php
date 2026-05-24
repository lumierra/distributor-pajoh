<?php

namespace App\Services\Reports;

use App\Services\Setting\SettingManager;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfRenderer
{
    public function __construct(
        private readonly SettingManager $settings,
        private readonly SalesReportService $sales,
        private readonly StockReportService $stock,
        private readonly ArAgingReportService $arAging,
        private readonly MarginReportService $margin,
        private readonly SalesActivityReportService $salesActivity,
    ) {}

    /**
     * Render a report to PDF (raw output, caller streams it).
     *
     * @param  array<string, mixed>  $filters
     */
    public function render(string $reportType, array $filters, ?string $generatedBy = null): string
    {
        $company = [
            'name' => $this->settings->get('company.name', 'Distributor Pajoh'),
            'address' => $this->settings->get('company.address', ''),
            'city' => $this->settings->get('company.city', ''),
            'phone' => $this->settings->get('company.phone', ''),
        ];

        $generatedAt = now()->format('d M Y H:i');
        $filterInfo = $this->buildFilterInfo($filters);

        $view = match ($reportType) {
            'sales_summary' => 'pdf.reports.sales-summary',
            'stock_position' => 'pdf.reports.stock-position',
            'ar_aging' => 'pdf.reports.ar-aging',
            'margin' => 'pdf.reports.margin',
            'sales_activity' => 'pdf.reports.sales-activity',
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}"),
        };

        $data = match ($reportType) {
            'sales_summary' => [
                'kpi' => $this->sales->getSummary($filters),
                'rows' => $this->sales->getTable($filters),
            ],
            'stock_position' => [
                'kpi' => $this->stock->getSummary($filters),
                'rows' => $this->stock->getTable($filters),
            ],
            'ar_aging' => [
                'kpi' => $this->arAging->getSummary($filters),
                'rows' => $this->arAging->getTable($filters),
            ],
            'margin' => [
                'kpi' => $this->margin->getSummary($filters),
                'byProduct' => $this->margin->getByProduct($filters),
                'byCustomer' => $this->margin->getByCustomer($filters),
            ],
            'sales_activity' => [
                'kpi' => $this->salesActivity->getSummary($filters),
                'bySales' => $this->salesActivity->getBySales($filters),
            ],
        };

        $orientation = in_array($reportType, ['stock_position', 'sales_activity'], true)
            ? 'landscape'
            : 'portrait';

        $pdf = Pdf::loadView($view, array_merge($data, [
            'company' => $company,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'filterInfo' => $filterInfo,
        ]))->setPaper('a4', $orientation);

        return $pdf->output();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function buildFilterInfo(array $filters): array
    {
        $info = [];
        foreach (['from', 'to', 'date', 'sales_id', 'customer_id', 'product_id'] as $key) {
            if (! empty($filters[$key])) {
                $info[$key] = (string) $filters[$key];
            }
        }

        return $info;
    }
}
