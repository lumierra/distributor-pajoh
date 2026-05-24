<?php

namespace App\Exports\Reports;

use App\Models\ArAgingSnapshot;
use App\Services\Reports\ArAgingReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ArAgingExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly ArAgingReportService $service,
        private readonly array $filters = [],
    ) {}

    public function collection()
    {
        return $this->service->getTable($this->filters);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Customer', 'Code', '0-30', '31-60', '61-90', '>90', 'Total Outstanding'];
    }

    /**
     * @param  ArAgingSnapshot  $row
     */
    public function map($row): array
    {
        return [
            $row->snapshot_date?->toDateString(),
            $row->customer?->name,
            $row->customer?->code,
            (float) $row->bucket_0_30,
            (float) $row->bucket_31_60,
            (float) $row->bucket_61_90,
            (float) $row->bucket_over_90,
            (float) $row->total_outstanding,
        ];
    }
}
