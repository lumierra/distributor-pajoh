<?php

namespace App\Exports\Reports;

use App\Services\Reports\SalesReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesSummaryExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly SalesReportService $service,
        private readonly array $filters = [],
    ) {}

    public function collection()
    {
        return $this->service->getTable($this->filters);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Invoice Count', 'Revenue', 'Cost (HPP)', 'Margin'];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->snapshot_date,
            (int) $row->invoice_count,
            (float) $row->revenue,
            (float) $row->cost_total,
            (float) $row->margin,
        ];
    }
}
