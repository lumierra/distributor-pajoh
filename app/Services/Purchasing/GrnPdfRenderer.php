<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceipt;
use App\Services\Setting\SettingManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GrnPdfRenderer
{
    public const DISK = 'local';

    public function __construct(private readonly SettingManager $settings) {}

    public function generate(GoodsReceipt $grn): string
    {
        $grn->load(['purchaseOrder', 'supplier', 'items', 'poster', 'submitter']);

        $pdf = Pdf::loadView('pdf.goods-receipt', [
            'grn' => $grn,
            'company' => [
                'name' => $this->settings->get('company.name', ''),
                'address' => $this->settings->get('company.address', ''),
                'city' => $this->settings->get('company.city', ''),
                'phone' => $this->settings->get('company.phone', ''),
                'email' => $this->settings->get('company.email', ''),
                'npwp' => $this->settings->get('company.npwp', ''),
            ],
        ])->setPaper('a4', 'portrait');

        $year = $grn->fiscal_year;
        $relPath = "grn/{$year}/{$grn->grn_number}.pdf";

        Storage::disk(self::DISK)->put($relPath, $pdf->output());

        $grn->update([
            'pdf_path' => $relPath,
            'pdf_generated_at' => now(),
        ]);

        return $relPath;
    }
}
