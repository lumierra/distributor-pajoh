<?php

namespace App\Exports\Reports;

use App\Services\Reports\SalesActivityReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesActivityExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly SalesActivityReportService $service,
        private readonly array $filters = [],
    ) {}

    public function collection()
    {
        return $this->service->getBySales($this->filters);
    }

    public function headings(): array
    {
        return ['Sales', 'SO Count', 'SO Value', 'SO Approved', 'SO Cancelled'];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->sales?->name,
            (int) $row->so_count,
            (float) $row->so_value,
            (int) $row->so_approved,
            (int) $row->so_cancelled,
        ];
    }
}
