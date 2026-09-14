<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Penjualan {{ $do->do_number }}</title>
    <style>
        @page { margin: 6mm 8mm; }
        body { font-family: 'Courier New', monospace; font-size: 9.5pt; color: #000; line-height: 1.25; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .title { font-size: 12pt; font-weight: bold; letter-spacing: 1px; }

        /* Header: 2 kolom (kiri identitas perusahaan+faktur, kanan customer) */
        table.head { width: 100%; border-collapse: collapse; }
        table.head td { vertical-align: top; padding: 0; }
        .lbl { display: inline-block; width: 78px; }
        .lbl-r { display: inline-block; width: 92px; }

        /* Tabel item — thead diulang tiap halaman (multi-halaman kalau item banyak) */
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        table.items th, table.items td { border: 1px solid #000; padding: 2px 5px; font-size: 9pt; }
        table.items th { font-weight: bold; text-align: center; }
        table.items td.num { text-align: right; }
        table.items td.c { text-align: center; }
        .dotted { border-bottom: 1px dashed #000; }

        /* Footer totals */
        table.foot { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.foot td { vertical-align: top; padding: 0; font-size: 9pt; }
        /* Box total: nempel kanan, lebar mengikuti isi (label + = + angka) */
        table.totbox { border-collapse: collapse; margin-left: auto; }
        table.totbox td { padding: 1px 0; font-size: 9pt; white-space: nowrap; }
        table.totbox td.tl { text-align: left; padding-right: 6px; }
        table.totbox td.eq { text-align: center; padding-right: 6px; }
        table.totbox td.tv { text-align: right; }
        table.totbox tr.grandrow td { font-size: 11pt; font-weight: bold; border-top: 1px solid #000; padding-top: 2px; }

        .sig { width: 33%; text-align: center; padding-top: 40px; font-size: 9pt; }
        .sig-line { border-top: 1px solid #000; margin: 0 25px; padding-top: 2px; }
        /* Tanda tangan 2 kolom dengan ruang lega untuk paraf/ttd */
        .sig2 { width: 50%; text-align: center; vertical-align: top; font-size: 9pt; padding-top: 8px; }
        .sig2 .name-top { font-weight: bold; }
        .sig2 .space { height: 48px; }
        .sig2 .line { border-top: 1px solid #000; margin: 0 40px; padding-top: 2px; }
    </style>
    @php
        // Ringkasan qty per satuan (mis. "77 KRT / 2 PACK") + total unit.
        $regItems = $do->items->where('is_bonus', false);
        $bonusItems = $do->items->where('is_bonus', true);
        $totalUnits = $do->items->sum('qty_planned');
        $perUnit = [];
        foreach ($do->items as $it) {
            $u = $it->product_unit_name_snapshot ?: '-';
            $perUnit[$u] = ($perUnit[$u] ?? 0) + (int) $it->qty_planned;
        }
        $satuanRingkas = collect($perUnit)->map(fn ($q, $u) => number_format($q, 0, ',', '.').' '.$u)->implode(' / ');

        // Totals dari SO.
        $subtotalKotor = 0.0;   // Σ harga penuh × qty (sebelum diskon item)
        $subtotalNet = (float) ($so->subtotal ?? 0);   // sudah net diskon item
        foreach ($regItems as $it) {
            $subtotalKotor += (float) ($it->soItem->unit_price ?? 0) * (int) $it->qty_planned;
        }
        $discItem = max(0, $subtotalKotor - $subtotalNet);
        $headerDisc = (float) ($so->header_discount_amount ?? 0);
        $cashback = (float) ($so->cashback ?? 0);
        $grandTotal = (float) ($so->total ?? max(0, $subtotalNet - $headerDisc - $cashback));

        $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
        $addr = $do->delivery_address_snapshot ?? [];
        $custName = $addr['name'] ?? $do->customer?->name;
        $custAddr = $addr['address'] ?? $do->customer?->address;
        $custCity = $addr['city'] ?? $do->customer?->city;
        $custNpwp = $do->customer?->npwp ?? null;
        // No. HP customer = nomor WhatsApp dari data customer.
        $custWhatsapp = $addr['whatsapp'] ?? $do->customer?->whatsapp ?? null;
        // Nama pemilik usaha (untuk tanda tangan penerima).
        $custOwner = $addr['owner_name'] ?? $do->customer?->owner_name ?? null;
        $driver = $do->driver_snapshot ?? [];
        $vehicle = $do->vehicle_snapshot ?? [];
    @endphp
</head>
<body>
    @php
        $companyName = trim($company['name'] ?: 'PERUSAHAAN');
        // Prefix legal form (CV/PT) hanya kalau nama belum diawali dengannya.
        if ($company['legal_form'] && stripos($companyName, $company['legal_form']) !== 0) {
            $companyName = $company['legal_form'].' '.$companyName;
        }
    @endphp

    {{-- Identitas perusahaan + judul: rata tengah, sama urutan dgn cetak ESC/P.
         Telp disambung dengan alamat biar hemat baris. --}}
    <div class="center bold" style="font-size:11pt;">{{ strtoupper($companyName) }}</div>
    @if($company['address'] || $company['phone'])
        <div class="center">{{ $company['address'] ?: '' }}{{ ($company['address'] && $company['phone']) ? ' - Telp: ' : '' }}{{ (! $company['address'] && $company['phone']) ? 'Telp: ' : '' }}{{ $company['phone'] ?: '' }}</div>
    @endif
    <div class="title center">FAKTUR PENJUALAN</div>

    <table class="head">
        <tr>
            <td style="width:58%;">
                <div><span class="lbl">No Faktur</span>: {{ $do->do_number }} <span style="margin-left:12px;">SO: {{ $so?->so_number ?? '-' }}</span></div>
                <div><span class="lbl">Supir</span>: {{ $driver['name'] ?? '-' }}</div>
                <div><span class="lbl">Angkutan</span>: {{ $vehicle['plate'] ?? '-' }}{{ ($vehicle['type'] ?? null) ? ' ('.$vehicle['type'].')' : '' }}</div>
                <div><span class="lbl">Sales</span>: {{ $so?->sales?->name ?? '-' }}</div>
                <div><span class="lbl">Bayar</span>:
                    @if(($so?->payment_term_days ?? 0) > 0)
                        HUTANG, {{ $so->payment_term_days }} Hari{{ $so->due_date ? ' / '.$so->due_date->format('d-m-Y') : '' }}
                    @else
                        TUNAI
                    @endif
                </div>
            </td>
            <td style="width:42%;">
                <div><span class="lbl-r">Tanggal</span>: {{ ($do->delivered_at ?? $do->do_date)?->format('d-m-Y') }}</div>
                <div><span class="lbl-r">Customer</span>: <span class="bold">{{ strtoupper($custName ?? '-') }}</span></div>
                <div><span class="lbl-r">Alamat</span>: {{ $custAddr ?: '-' }}{{ $custCity ? ', '.$custCity : '' }}</div>
                <div><span class="lbl-r">No. HP</span>: {{ $custWhatsapp ?: '-' }}</div>
                @if($custNpwp)
                    <div><span class="lbl-r">NPWP</span>: {{ $custNpwp }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:3%;">No</th>
                <th style="width:13%;">Kode</th>
                <th style="text-align:left;">Nama Barang</th>
                <th style="width:7%;">Unit</th>
                <th style="width:5%;">Qty</th>
                <th style="width:10%;">Harga</th>
                <th style="width:7%;">Diskon</th>
                <th style="width:7%;">Bonus</th>
                <th style="width:13%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($regItems as $item)
                @php
                    $so_i = $item->soItem;
                    $harga = (float) ($so_i->unit_price ?? 0);
                    $disc = ($so_i && $so_i->discount_type)
                        ? ($so_i->discount_type === 'percent'
                            ? rtrim(rtrim(number_format((float) $so_i->discount_value, 2, ',', '.'), '0'), ',').'%'
                            : $rp($so_i->discount_value))
                        : '-';
                    $lineTotal = (float) ($so_i->unit_net_price ?? 0) * (int) $item->qty_planned;
                @endphp
                <tr>
                    <td class="c">{{ $no++ }}</td>
                    <td class="c">{{ $item->product_sku_snapshot }}</td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td class="c">{{ $item->product_unit_name_snapshot }}</td>
                    <td class="c">{{ number_format($item->qty_planned, 0, ',', '.') }}</td>
                    <td class="num">{{ $rp($harga) }}</td>
                    <td class="num">{{ $disc }}</td>
                    <td class="c">-</td>
                    <td class="num">{{ $rp($lineTotal) }}</td>
                </tr>
            @endforeach
            @foreach($bonusItems as $item)
                <tr>
                    <td class="c">{{ $no++ }}</td>
                    <td class="c">{{ $item->product_sku_snapshot }}</td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td class="c">{{ $item->product_unit_name_snapshot }}</td>
                    <td class="c">{{ number_format($item->qty_planned, 0, ',', '.') }}</td>
                    <td class="num">-</td>
                    <td class="num">-</td>
                    <td class="c bold">BONUS</td>
                    <td class="num">-</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="foot">
        <tr>
            <td style="width:55%; vertical-align:bottom;">
                <div>Jumlah Barang = {{ number_format($totalUnits, 0, ',', '.') }}</div>
                <div>Rincian Satuan = {{ $satuanRingkas ?: '-' }}</div>
            </td>
            <td style="width:45%;">
                {{-- Tabel total ringkas, nempel kanan; garis hanya selebar isinya --}}
                <table class="totbox">
                    <tr><td class="tl">Subtotal</td><td class="eq">=</td><td class="tv">{{ $rp($subtotalKotor) }}</td></tr>
                    @if($discItem > 0)
                        <tr><td class="tl">Diskon</td><td class="eq">=</td><td class="tv">- {{ $rp($discItem) }}</td></tr>
                    @endif
                    @if($headerDisc > 0)
                        <tr><td class="tl">Diskon Header</td><td class="eq">=</td><td class="tv">- {{ $rp($headerDisc) }}</td></tr>
                    @endif
                    <tr><td class="tl">Total</td><td class="eq">=</td><td class="tv">{{ $rp($subtotalNet - $headerDisc) }}</td></tr>
                    @if($cashback > 0)
                        <tr><td class="tl">Cashback</td><td class="eq">=</td><td class="tv">- {{ $rp($cashback) }}</td></tr>
                    @endif
                    <tr class="grandrow">
                        <td class="tl">TOTAL</td><td class="eq">=</td><td class="tv">Rp {{ $rp($grandTotal) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($do->notes)
        <div style="margin-top:6px;"><span class="bold">Catatan:</span> {{ $do->notes }}</div>
    @endif

    <table style="width:100%; margin-top:24px;">
        <tr>
            <td class="sig2">
                <div>Admin,</div>
                <div class="name-top">&nbsp;</div>
                <div class="space"></div>
                <div class="line">{{ $do->packer?->name ?? '-' }}</div>
            </td>
            <td class="sig2">
                <div>Penerima,</div>
                <div class="name-top">{{ strtoupper($custName ?? '-') }}</div>
                <div class="space"></div>
                <div class="line">{{ strtoupper($custOwner ?: ($custName ?? '-')) }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
