<?php

namespace App\Services\Delivery;

use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
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

    // Mode TERCEPAT: Draft quality, 1x sapuan per baris (tanpa bold yang
    // menyapu 2x, tanpa NLQ yang resolusi tinggi). Prioritas kecepatan cetak.
    private const DRAFT_ON = self::ESC.'x0';     // Draft quality (1x sapuan)

    private const FORM_FEED = "\x0C";            // maju ke lembar berikut

    // Lebar cetak (kolom karakter) untuk condensed continuous form 13".
    private const WIDTH = 136;

    // Maks baris item per halaman (sisanya lanjut halaman berikut, header diulang).
    private const ROWS_PER_PAGE = 15;

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
        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');

        // ── Susun semua baris item (reg + bonus) jadi satu daftar ──────────
        $cols = [3, 16, 48, 6, 6, 14, 9, 6, 16];
        $align = ['R', 'L', 'L', 'C', 'C', 'R', 'C', 'C', 'R'];
        $itemRows = [];
        foreach ($regItems as $it) {
            $soi = $it->soItem;
            $harga = (float) ($soi->unit_price ?? 0);
            $disc = ($soi && $soi->discount_type)
                ? ($soi->discount_type === 'percent'
                    ? rtrim(rtrim(number_format((float) $soi->discount_value, 2, ',', '.'), '0'), ',').'%'
                    : $rp($soi->discount_value))
                : '-';
            $lineTotal = (float) ($soi->unit_net_price ?? 0) * (int) $it->qty_planned;
            $itemRows[] = [
                $it->product_sku_snapshot, $it->product_name_snapshot,
                $it->product_unit_name_snapshot, number_format($it->qty_planned, 0, ',', '.'),
                $rp($harga), $disc, '-', $rp($lineTotal),
            ];
        }
        foreach ($bonusItems as $it) {
            $itemRows[] = [
                $it->product_sku_snapshot, $it->product_name_snapshot,
                $it->product_unit_name_snapshot, number_format($it->qty_planned, 0, ',', '.'),
                '-', '-', 'BONUS', '-',
            ];
        }

        // ── Paginasi: pecah item per halaman; header diulang tiap halaman ──
        $pages = array_chunk($itemRows, self::ROWS_PER_PAGE) ?: [[]];
        $totalPages = count($pages);
        $paymentLineHeader = $paymentLine;

        $header = fn (int $page): string => $this->pageHeader(
            $companyName, $company, $do, $so, $custName, $custAddr, $custCity, $custWa,
            $driver, $vehicle, $paymentLineHeader, $page, $totalPages, $cols, $align,
        );

        $out = self::INIT.self::DRAFT_ON.self::COND_ON;
        $no = 1;

        foreach ($pages as $pageIndex => $rows) {
            $pageNum = $pageIndex + 1;
            $out .= $header($pageNum);

            foreach ($rows as $r) {
                $out .= $this->row(array_merge([$no++], $r), $cols, $align)."\n";
            }
            $out .= str_repeat('-', self::WIDTH)."\n";

            $isLast = $pageNum === $totalPages;
            if (! $isLast) {
                // Halaman lanjutan: tanda bersambung, lalu form feed.
                $out .= $this->pad('...bersambung ke halaman '.($pageNum + 1).'...', self::WIDTH, 'R')."\n";
                $out .= self::FORM_FEED;

                continue;
            }

            // ── Halaman TERAKHIR: ringkasan qty + totals + tanda tangan ────
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

            $out .= "\n";
            $out .= $this->twoCol(
                ['Hormat kami,', '', '', '(............................)', 'Admin'],
                ['Penerima,', '', '', '(............................)', strtoupper($custName)],
            );
            $out .= self::FORM_FEED;
        }

        return $out;
    }

    /**
     * Blok header satu halaman (nama CV, info dokumen, judul kolom tabel).
     * Diulang di tiap halaman; nomor halaman "Hal : N / M" akurat.
     *
     * @param  array<string, string>  $company
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $vehicle
     * @param  array<int, int>  $cols
     * @param  array<int, string>  $align
     */
    private function pageHeader(
        string $companyName, array $company, DeliveryOrder $do, ?SalesOrder $so,
        string $custName, string $custAddr, string $custCity, string $custWa,
        array $driver, array $vehicle, string $paymentLine, int $page, int $totalPages,
        array $cols, array $align,
    ): string {
        $out = $this->center(strtoupper($companyName))."\n";
        $addrLine = $company['address'] ?: '';
        if ($company['phone']) {
            $addrLine .= ($addrLine !== '' ? ' - Telp: ' : 'Telp: ').$company['phone'];
        }
        if ($addrLine !== '') {
            $out .= $this->center($addrLine)."\n";
        }
        $out .= $this->center('FAKTUR PENJUALAN')."\n";
        $out .= str_repeat('=', self::WIDTH)."\n";

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
            'Hal      : '.$page.' / '.$totalPages,
        ];
        $out .= $this->twoCol($left, $right);
        $out .= str_repeat('-', self::WIDTH)."\n";

        // Judul kolom tabel
        $out .= $this->row(['No', 'Kode', 'Nama Barang', 'Unit', 'Qty', 'Harga', 'Diskon', 'Bonus', 'Total'], $cols, $align)."\n";
        $out .= str_repeat('-', self::WIDTH)."\n";

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
