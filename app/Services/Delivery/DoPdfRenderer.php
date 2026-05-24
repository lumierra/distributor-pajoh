<?php

namespace App\Services\Delivery;

use App\Models\DeliveryOrder;
use App\Services\Setting\SettingManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DoPdfRenderer
{
    public const DISK = 'local';

    public function __construct(private readonly SettingManager $settings) {}

    public function generate(DeliveryOrder $do): string
    {
        $do->load(['salesOrder', 'customer', 'items', 'driver', 'vehicle', 'packer']);

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'do' => $do,
            'company' => [
                'name' => $this->settings->get('company.name', ''),
                'address' => $this->settings->get('company.address', ''),
                'city' => $this->settings->get('company.city', ''),
                'phone' => $this->settings->get('company.phone', ''),
                'npwp' => $this->settings->get('company.npwp', ''),
            ],
        ])->setPaper([0, 0, 612, 792], 'portrait'); // dot-matrix continuous form ~ 8.5" × 11"

        $year = $do->fiscal_year;
        $relPath = "delivery_orders/{$year}/{$do->do_number}.pdf";

        Storage::disk(self::DISK)->put($relPath, $pdf->output());

        $do->update([
            'pdf_path' => $relPath,
            'pdf_generated_at' => now(),
        ]);

        return $relPath;
    }
}
