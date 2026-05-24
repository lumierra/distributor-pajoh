<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Services\Setting\SettingManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfRenderer
{
    public const DISK = 'local';

    public function __construct(private readonly SettingManager $settings) {}

    public function generate(Invoice $invoice): string
    {
        $invoice->load(['customer', 'salesOrder', 'deliveryOrder', 'items', 'sales']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'inv' => $invoice,
            'company' => [
                'name' => $this->settings->get('company.name', ''),
                'address' => $this->settings->get('company.address', ''),
                'city' => $this->settings->get('company.city', ''),
                'phone' => $this->settings->get('company.phone', ''),
                'whatsapp' => $this->settings->get('company.whatsapp', ''),
                'npwp' => $this->settings->get('company.npwp', ''),
            ],
            'footer' => [
                'text' => $this->settings->get('company.invoice_text.footer_text', ''),
                'payment_instruction' => $this->settings->get('company.invoice_text.payment_instruction', ''),
            ],
        ])->setPaper('a4', 'portrait');

        $year = $invoice->fiscal_year;
        $safeName = str_replace(['/', '\\'], '_', $invoice->invoice_number);
        $relPath = "invoices/{$year}/{$safeName}.pdf";

        Storage::disk(self::DISK)->put($relPath, $pdf->output());

        $invoice->update([
            'pdf_path' => $relPath,
            'pdf_generated_at' => now(),
        ]);

        return $relPath;
    }
}
