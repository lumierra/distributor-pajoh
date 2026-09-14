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

        // Continuous form: ukuran halaman TETAP (lebar ~13" = 936pt, tinggi
        // ~13.94cm = 395pt). Kalau item banyak, DomPDF otomatis pecah ke
        // halaman berikutnya dan judul kolom tabel (<thead>) diulang tiap
        // halaman. Total & tanda tangan mengalir di halaman terakhir.
        $pdf->setPaper([0, 0, 936, 395]);

        // "Hal x / y" akurat di pojok kanan BAWAH tiap halaman (footer, biar
        // tak menabrak judul kolom tabel yang diulang di halaman lanjutan).
        // Dihitung DomPDF setelah layout, jadi tahu jumlah halaman sebenarnya.
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(
            810, 382, 'Hal: {PAGE_NUM} / {PAGE_COUNT}',
            null, 8, [0, 0, 0],
        );

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
