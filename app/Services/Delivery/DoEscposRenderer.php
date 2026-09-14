<?php

namespace App\Services\Delivery;

use App\Models\DeliveryOrder;
use App\Services\Setting\SettingManager;

/**
 * Render Surat Jalan / Faktur Penjualan menjadi teks ESC/P untuk dot-matrix
 * (Epson LX/LQ). Mode CONDENSED 17 CPI (~136 kolom di continuous form ~13").
 * Berbeda dari PDF (raster): ini teks mentah + kode kontrol → cetak tajam,
 * cepat, presisi. Data & angka mengikuti template `pdf.delivery-order`.
 */
class DoEscposRenderer
{
    // Kode kontrol ESC/P Epson.
    private const ESC = "\x1B";

    private const INIT = self::ESC.'@';          // reset printer

    private const COND_ON = "\x0F";              // SI = condensed (17 CPI)

    // Kualitas SEDANG: mode Draft (cepat) + Bold (tebal) → cepat tapi tetap
    // jelas terbaca, tidak berbayang seperti draft polos, dan jauh lebih cepat
    // daripada NLQ (yang menyapu 2x per baris).
    private const DRAFT_ON = self::ESC.'x0';     // Draft quality (cepat)

    private const BOLD_ON = self::ESC.'E';       // Emphasized/bold ON

    private const FORM_FEED = "\x0C";            // maju ke lembar berikut

    // Lebar cetak (kolom karakter) untuk condensed continuous form 13".
    private const WIDTH = 136;

    public function __construct(private readonly SettingManager $settings) {}

    public function render(DeliveryOrder $do): string
    {
        $do->loadMissing([
            'salesOrder.sales:id,name',
            'customer',
            'items.soItem',
            'driver',
            'vehicle',
        ]);

        $company = [
            'name' => (string) $this->settings->get('company.name', ''),
            'legal_form' => (string) $this->settings->get('company.legal_form', ''),
            'address' => (string) $this->settings->get('company.address', ''),
            'phone' => (string) $this->settings->get('company.phone', ''),
        ];

        $so = $do->salesOrder;
        $addr = $do->delivery_address_snapshot ?? [];
        $custName = $addr['name'] ?? $do->customer?->name ?? '-';
        $custAddr = $addr['address'] ?? $do->customer?->address ?? '';
        $custCity = $addr['city'] ?? $do->customer?->city ?? '';
        $custWa = $addr['whatsapp'] ?? $do->customer?->whatsapp ?? '-';
        $driver = $do->driver_snapshot ?? [];
        $vehicle = $do->vehicle_snapshot ?? [];

        // Nama perusahaan + prefix legal form (CV/PT) kalau belum diawali.
        $companyName = trim($company['name'] ?: 'PERUSAHAAN');
        if ($company['legal_form'] && stripos($companyName, $company['legal_form']) !== 0) {
            $companyName = $company['legal_form'].' '.$companyName;
        }

        // ── Perhitungan totals (mirror blade) ──────────────────────────────
        $regItems = $do->items->where('is_bonus', false);
        $bonusItems = $do->items->where('is_bonus', true);

        $subtotalKotor = 0.0;
        foreach ($regItems as $it) {
            $subtotalKotor += (float) ($it->soItem->unit_price ?? 0) * (int) $it->qty_planned;
        }
        $subtotalNet = (float) ($so->subtotal ?? 0);
        $discItem = max(0, $subtotalKotor - $subtotalNet);
        $headerDisc = (float) ($so->header_discount_amount ?? 0);
        $cashback = (float) ($so->cashback ?? 0);
        $grandTotal = (float) ($so->total ?? max(0, $subtotalNet - $headerDisc - $cashback));

        $paymentLine = ((int) ($so->payment_term_days ?? 0) > 0)
            ? 'HUTANG, '.$so->payment_term_days.' Hari'.($so->due_date ? ' / '.$so->due_date->format('d-m-Y') : '')
            : 'TUNAI';

        // ── Susun output ───────────────────────────────────────────────────
        $out = self::INIT;
        $out .= self::DRAFT_ON;   // cepat
        $out .= self::BOLD_ON;    // tebal → tetap jelas
        $out .= self::COND_ON;

        $out .= $this->center(strtoupper($companyName))."\n";
        // Alamat + Telp digabung satu baris (hemat ruang).
        $addrLine = $company['address'] ?: '';
        if ($company['phone']) {
            $addrLine .= ($addrLine !== '' ? ' - Telp: ' : 'Telp: ').$company['phone'];
        }
        if ($addrLine !== '') {
            $out .= $this->center($addrLine)."\n";
        }
        $out .= $this->center('FAKTUR PENJUALAN')."\n";
        $out .= str_repeat('=', self::WIDTH)."\n";

        // Header 2 kolom (kiri: dokumen/pengiriman + bayar, kanan: customer).
        $left = [
            'No Fak : '.$do->do_number.'   SO: '.($so?->so_number ?? '-'),
            'Supir  : '.($driver['name'] ?? '-'),
            'Angkut : '.($vehicle['plate'] ?? '-').(($vehicle['type'] ?? null) ? ' ('.$vehicle['type'].')' : ''),
            'Sales  : '.($so?->sales?->name ?? '-'),
            'Bayar  : '.$paymentLine,
        ];
        $right = [
            'Tanggal  : '.(($do->delivered_at ?? $do->do_date)?->format('d-m-Y') ?? '-'),
            'Customer : '.strtoupper($custName),
            'Alamat   : '.trim($custAddr.($custCity ? ', '.$custCity : ''), ', '),
            'No. HP   : '.$custWa,
            'Hal      : 1 / 1',
        ];
        $out .= $this->twoCol($left, $right);
        $out .= str_repeat('-', self::WIDTH)."\n";

        // ── Tabel item ─────────────────────────────────────────────────────
        // Kolom: No(3) Kode(16) Nama(48) Unit(6) Qty(6) Harga(14) Diskon(9) Bonus(6) Total(16)
        $cols = [3, 16, 48, 6, 6, 14, 9, 6, 16];
        $out .= $this->row(['No', 'Kode', 'Nama Barang', 'Unit', 'Qty', 'Harga', 'Diskon', 'Bonus', 'Total'], $cols,
            ['R', 'L', 'L', 'C', 'C', 'R', 'C', 'C', 'R'])."\n";
        $out .= str_repeat('-', self::WIDTH)."\n";

        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
        $no = 1;

        foreach ($regItems as $it) {
            $soi = $it->soItem;
            $harga = (float) ($soi->unit_price ?? 0);
            $disc = ($soi && $soi->discount_type)
                ? ($soi->discount_type === 'percent'
                    ? rtrim(rtrim(number_format((float) $soi->discount_value, 2, ',', '.'), '0'), ',').'%'
                    : $rp($soi->discount_value))
                : '-';
            $lineTotal = (float) ($soi->unit_net_price ?? 0) * (int) $it->qty_planned;
            $out .= $this->row([
                $no++, $it->product_sku_snapshot, $it->product_name_snapshot,
                $it->product_unit_name_snapshot, number_format($it->qty_planned, 0, ',', '.'),
                $rp($harga), $disc, '-', $rp($lineTotal),
            ], $cols, ['R', 'L', 'L', 'C', 'C', 'R', 'C', 'C', 'R'])."\n";
        }
        foreach ($bonusItems as $it) {
            $out .= $this->row([
                $no++, $it->product_sku_snapshot, $it->product_name_snapshot,
                $it->product_unit_name_snapshot, number_format($it->qty_planned, 0, ',', '.'),
                '-', '-', 'BONUS', '-',
            ], $cols, ['R', 'L', 'L', 'C', 'C', 'R', 'C', 'C', 'R'])."\n";
        }

        $out .= str_repeat('-', self::WIDTH)."\n";

        // ── Ringkasan qty (kiri) — mirror PDF: Jumlah Barang & Rincian Satuan.
        $totalUnits = (int) $do->items->sum('qty_planned');
        $perUnit = [];
        foreach ($do->items as $it) {
            $u = $it->product_unit_name_snapshot ?: '-';
            $perUnit[$u] = ($perUnit[$u] ?? 0) + (int) $it->qty_planned;
        }
        $rincian = [];
        foreach ($perUnit as $u => $q) {
            $rincian[] = number_format($q, 0, ',', '.').' '.$u;
        }
        $out .= 'Jumlah Barang  = '.number_format($totalUnits, 0, ',', '.')."\n";
        $out .= 'Rincian Satuan = '.(implode(' / ', $rincian) ?: '-')."\n";

        // ── Totals (rata kanan) ────────────────────────────────────────────
        $out .= $this->totalLine('Subtotal', $rp($subtotalKotor));
        if ($discItem > 0) {
            $out .= $this->totalLine('Diskon', '- '.$rp($discItem));
        }
        if ($headerDisc > 0) {
            $out .= $this->totalLine('Diskon Header', '- '.$rp($headerDisc));
        }
        $out .= $this->totalLine('Total', $rp($subtotalNet - $headerDisc));
        if ($cashback > 0) {
            $out .= $this->totalLine('Cashback', '- '.$rp($cashback));
        }
        $out .= $this->totalLine('GRAND TOTAL', 'Rp '.$rp($grandTotal));

        // ── Tanda tangan ───────────────────────────────────────────────────
        $out .= "\n";
        $out .= $this->twoCol(
            ['Hormat kami,', '', '', '(............................)', 'Admin'],
            ['Penerima,', '', '', '(............................)', strtoupper($custName)],
        );

        // Form feed → kertas maju ke lembar berikut (continuous form).
        $out .= self::FORM_FEED;

        return $out;
    }

    /**
     * Susun 1 baris tabel dengan lebar & alignment per kolom.
     *
     * @param  array<int, string|int>  $cells
     * @param  array<int, int>  $widths
     * @param  array<int, string>  $align  'L'|'C'|'R'
     */
    private function row(array $cells, array $widths, array $align): string
    {
        $line = '';
        foreach ($cells as $i => $cell) {
            $w = $widths[$i];
            $line .= $this->pad((string) $cell, $w, $align[$i] ?? 'L').' ';
        }

        return rtrim($line);
    }

    /**
     * Pad/potong teks ke lebar tetap dengan alignment.
     */
    private function pad(string $text, int $width, string $align = 'L'): string
    {
        if (mb_strlen($text) > $width) {
            $text = mb_substr($text, 0, $width);
        }
        $space = $width - mb_strlen($text);
        if ($align === 'R') {
            return str_repeat(' ', $space).$text;
        }
        if ($align === 'C') {
            $l = intdiv($space, 2);

            return str_repeat(' ', $l).$text.str_repeat(' ', $space - $l);
        }

        return $text.str_repeat(' ', $space);
    }

    private function center(string $text): string
    {
        return $this->pad($text, self::WIDTH, 'C');
    }

    /**
     * Baris total: label + nilai rata kanan di lebar penuh.
     */
    private function totalLine(string $label, string $value): string
    {
        $labelW = self::WIDTH - 22;

        return $this->pad($label, $labelW, 'R').' = '.$this->pad($value, 19, 'R')."\n";
    }

    /**
     * Cetak dua kolom berdampingan (kiri 60% / kanan 40%).
     *
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function twoCol(array $left, array $right): string
    {
        $lw = 78;
        $rw = self::WIDTH - $lw;
        $rows = max(count($left), count($right));
        $out = '';
        for ($i = 0; $i < $rows; $i++) {
            $out .= $this->pad($left[$i] ?? '', $lw, 'L').$this->pad($right[$i] ?? '', $rw, 'L')."\n";
        }

        return $out;
    }
}
