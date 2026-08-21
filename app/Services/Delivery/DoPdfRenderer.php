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
        $do->load([
            'salesOrder.sales:id,name',
            'customer',
            'items.soItem',
            'driver',
            'vehicle',
            'packer',
        ]);

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'do' => $do,
            'so' => $do->salesOrder,
            'company' => [
                'name' => $this->settings->get('company.name', ''),
                'legal_form' => $this->settings->get('company.legal_form', ''),
                'address' => $this->settings->get('company.address', ''),
                'city' => $this->settings->get('company.city', ''),
                'phone' => $this->settings->get('company.phone', ''),
                'whatsapp' => $this->settings->get('company.whatsapp', ''),
                'npwp' => $this->settings->get('company.npwp', ''),
            ],
        ]);

        // Dot-matrix continuous form: lebar tetap (~13" = 936pt), TINGGI mengikuti
        // konten (mepet ke bawah, tanpa ruang kosong). DomPDF butuh ukuran kertas,
        // jadi tinggi dihitung dari jumlah item — pendek untuk faktur singkat, dan
        // bertambah kalau item banyak (tanpa memaksa 1 halaman F4 penuh).
        $rowCount = $do->items->count();
        $baseHeight = 430;              // header + total + blok tanda tangan
        $paperHeight = $baseHeight + ($rowCount * 16);
        $pdf->setPaper([0, 0, 936, $paperHeight]);

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
