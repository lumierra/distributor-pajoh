<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;
use App\Services\Setting\SettingManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PoPdfRenderer
{
    public const DISK = 'local';

    public function __construct(private readonly SettingManager $settings) {}

    public function generate(PurchaseOrder $po): string
    {
        $po->load(['supplier', 'items', 'approver']);

        $pdf = Pdf::loadView('pdf.purchase-order', [
            'po' => $po,
            'snapshot' => $po->supplier_snapshot ?? [],
            'company' => [
                'name' => $this->settings->get('company.name', ''),
                'address' => $this->settings->get('company.address', ''),
                'city' => $this->settings->get('company.city', ''),
                'phone' => $this->settings->get('company.phone', ''),
                'npwp' => $this->settings->get('company.npwp', ''),
                'email' => $this->settings->get('company.email', ''),
            ],
        ])->setPaper('a4', 'portrait');

        $year = $po->fiscal_year;
        $relPath = "purchase_orders/{$year}/{$po->po_number}.pdf";

        Storage::disk(self::DISK)->put($relPath, $pdf->output());

        $po->update([
            'pdf_path' => $relPath,
            'pdf_generated_at' => now(),
        ]);

        return $relPath;
    }
}
