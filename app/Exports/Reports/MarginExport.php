<?php

namespace App\Exports\Reports;

use App\Services\Reports\MarginReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MarginExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly MarginReportService $service,
        private readonly array $filters = [],
    ) {}

    public function collection()
    {
        return $this->service->getByProduct($this->filters);
    }

    public function headings(): array
    {
        return ['Produk', 'SKU', 'Revenue', 'Cost', 'Margin'];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->product?->name,
            $row->product?->sku,
            (float) $row->revenue,
            (float) $row->cost,
            (float) $row->margin,
        ];
    }
}
