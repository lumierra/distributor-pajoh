<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur {{ $inv->invoice_number }}</title>
    <style>
        @page { margin: 15mm 12mm; }
        body { font-family: 'Courier New', monospace; font-size: 10pt; color: #000; line-height: 1.3; }
        h1, h2, h3 { margin: 0; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .border-bottom { border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .double-border { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { border: 1px solid #000; padding: 3px 5px; font-size: 9pt; }
        table.items th { background: #eee; font-weight: bold; text-align: left; }
        table.items td.num { text-align: right; }
        .totals { width: 50%; margin-left: auto; margin-top: 8px; border-collapse: collapse; }
        .totals td { padding: 3px 6px; font-size: 10pt; }
        .totals td.label { text-align: right; }
        .totals td.value { text-align: right; }
        .totals tr.grand td { border-top: 2px solid #000; font-weight: bold; font-size: 11pt; }
        .footer { margin-top: 20px; }
        .sig-block { width: 33%; text-align: center; padding-top: 45px; font-size: 9pt; }
        .sig-line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; }
        .small { font-size: 8pt; }
    </style>
</head>
<body>
    <div class="row double-border">
        <div class="col" style="width:60%;">
            <div class="bold">{{ strtoupper($company['name'] ?: 'PERUSAHAAN') }}</div>
            <div>{{ $company['address'] ?: '' }}</div>
            <div>{{ $company['city'] ?: '' }}{{ $company['phone'] ? ' · Telp: '.$company['phone'] : '' }}</div>
            @if($company['npwp'])
                <div>NPWP: {{ $company['npwp'] }}</div>
            @endif
        </div>
        <div class="col right" style="width:40%;">
            <div class="bold" style="font-size:13pt;">FAKTUR PENJUALAN</div>
            <div>No: {{ $inv->invoice_number }}</div>
            <div>Tanggal: {{ $inv->invoice_date?->format('d M Y') }}</div>
            <div>Jatuh Tempo: {{ $inv->due_date?->format('d M Y') }}</div>
        </div>
    </div>

    @php
        $snap = $inv->customer_snapshot ?? [];
    @endphp
    <div style="margin-top:10px;">
        <div><strong>Kepada Yth:</strong></div>
        <div class="bold">{{ $snap['name'] ?? $inv->customer?->name }}</div>
        @if(!empty($snap['address'] ?? $inv->customer?->address))
            <div>{{ $snap['address'] ?? $inv->customer->address }}</div>
        @endif
        @if(!empty($snap['city'] ?? $inv->customer?->city))
            <div>{{ $snap['city'] ?? $inv->customer->city }}{{ ($snap['province'] ?? null) ? ', '.$snap['province'] : '' }}</div>
        @endif
        @if(!empty($snap['npwp'] ?? $inv->customer?->npwp))
            <div class="small">NPWP: {{ $snap['npwp'] ?? $inv->customer->npwp }}</div>
        @endif
        @if($inv->is_cash)
            <div style="margin-top:4px;"><strong>Jenis: TUNAI</strong></div>
        @else
            <div style="margin-top:4px;"><strong>Jenis: KREDIT ({{ $inv->payment_term_days }} hari)</strong></div>
        @endif
    </div>

    @php
        $regularItems = $inv->items->where('is_bonus', false);
        $bonusItems = $inv->items->where('is_bonus', true);
    @endphp

    <table class="items">
        <thead>
            <tr>
                <th style="width:4%;">No</th>
                <th style="width:12%;">SKU</th>
                <th>Nama Produk</th>
                <th style="width:8%;">Unit</th>
                <th style="width:8%;" class="right">Qty</th>
                <th style="width:13%;" class="right">Harga</th>
                <th style="width:9%;" class="right">Disc</th>
                <th style="width:14%;" class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($regularItems as $item)
                <tr>
                    <td class="center">{{ $no++ }}</td>
                    <td>{{ $item->product_sku_snapshot }}</td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->product_unit_name_snapshot }}</td>
                    <td class="num">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                    <td class="num">@if($item->discount_type === 'percent'){{ rtrim(rtrim(number_format((float) $item->discount_value, 2, ',', '.'), '0'), ',') }}%@elseif($item->discount_type === 'rp'){{ number_format((float) $item->discount_value, 0, ',', '.') }}@else—@endif</td>
                    <td class="num">{{ number_format((float) $item->line_subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            @if($bonusItems->count() > 0)
                <tr><td colspan="8" style="background:#f5f5f5; font-style:italic;">— BONUS —</td></tr>
                @foreach($bonusItems as $item)
                    <tr>
                        <td class="center">{{ $no++ }}</td>
                        <td>{{ $item->product_sku_snapshot }}</td>
                        <td>{{ $item->product_name_snapshot }}</td>
                        <td>{{ $item->product_unit_name_snapshot }}</td>
                        <td class="num">{{ number_format($item->qty, 0, ',', '.') }}</td>
                        <td class="num">—</td>
                        <td class="num">—</td>
                        <td class="num">0</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">Rp {{ number_format((float) $inv->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if((float) $inv->header_discount_amount > 0)
            <tr>
                <td class="label">Diskon</td>
                <td class="value">− Rp {{ number_format((float) $inv->header_discount_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        @if((float) $inv->cashback_amount > 0)
            <tr>
                <td class="label">Cashback</td>
                <td class="value">− Rp {{ number_format((float) $inv->cashback_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">TOTAL</td>
            <td class="value">Rp {{ number_format((float) $inv->total, 0, ',', '.') }}</td>
        </tr>
        @if((float) $inv->paid_amount > 0)
            <tr>
                <td class="label">Telah dibayar</td>
                <td class="value">Rp {{ number_format((float) $inv->paid_amount, 0, ',', '.') }}</td>
            </tr>
            <tr class="grand">
                <td class="label">SISA</td>
                <td class="value">Rp {{ number_format((float) $inv->outstanding, 0, ',', '.') }}</td>
            </tr>
        @endif
    </table>

    @if($footer['payment_instruction'])
        <div style="margin-top:14px; padding:8px; border:1px solid #000;">
            <div class="bold">Instruksi Pembayaran:</div>
            <div class="small" style="white-space:pre-line;">{{ $footer['payment_instruction'] }}</div>
        </div>
    @endif

    @if($inv->notes)
        <div style="margin-top:10px;">
            <div class="bold">Catatan:</div>
            <div class="small" style="white-space:pre-line;">{{ $inv->notes }}</div>
        </div>
    @endif

    <div class="row footer">
        <div class="col sig-block">
            <div>Hormat Kami,</div>
            <div class="sig-line">.................................</div>
            <div>{{ $inv->sales_name_snapshot ?? '(Sales)' }}</div>
        </div>
        <div class="col sig-block">
            <div>Pengantar,</div>
            <div class="sig-line">.................................</div>
            <div>{{ $inv->driver_name_snapshot ?? '(Driver)' }}</div>
        </div>
        <div class="col sig-block">
            <div>Penerima,</div>
            <div class="sig-line">.................................</div>
            <div>(Customer)</div>
        </div>
    </div>

    @if($footer['text'])
        <div style="margin-top:15px; font-size:8pt; border-top: 1px dashed #000; padding-top:5px; text-align:center;">
            <em>{{ $footer['text'] }}</em>
        </div>
    @endif
</body>
</html>
