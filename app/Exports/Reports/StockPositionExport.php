<?php

namespace App\Exports\Reports;

use App\Models\DailyStockPosition;
use App\Services\Reports\StockReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockPositionExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly StockReportService $service,
        private readonly array $filters = [],
    ) {}

    public function collection()
    {
        return $this->service->getTable($this->filters);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Produk', 'SKU', 'Batch', 'Qty On Hand', 'Avg Cost', 'Stock Value', 'Days Since Movement'];
    }

    /**
     * @param  DailyStockPosition  $row
     */
    public function map($row): array
    {
        return [
            $row->snapshot_date?->toDateString(),
            $row->product?->name,
            $row->product?->sku,
            $row->batch?->batch_code,
            (int) $row->qty_on_hand_base,
            (float) ($row->avg_cost ?? 0),
            (float) $row->stock_value,
            $row->days_since_last_movement,
        ];
    }
}
