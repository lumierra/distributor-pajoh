<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PO {{ $po->po_number }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; }
        h1, h2, h3 { margin: 0; }
        .header { display: table; width: 100%; border-bottom: 2px solid #104837; padding-bottom: 8px; margin-bottom: 12px; }
        .header .left { display: table-cell; width: 60%; vertical-align: top; }
        .header .right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .company-name { font-size: 14pt; font-weight: bold; color: #104837; }
        .po-title { font-size: 16pt; font-weight: bold; letter-spacing: 1px; }
        .po-number { font-family: DejaVu Sans Mono; font-size: 11pt; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 2px 4px; font-size: 9pt; vertical-align: top; }
        .meta .label { color: #666; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
        .box { border: 1px solid #ddd; padding: 8px; margin-bottom: 10px; }
        .box h3 { font-size: 9pt; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 5px 6px; font-size: 9pt; }
        table.items th { background: #f2f5f4; text-align: left; font-weight: bold; }
        table.items td.num { text-align: right; font-family: DejaVu Sans Mono; }
        .totals { width: 50%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; font-size: 10pt; }
        .totals td.label { text-align: right; color: #666; }
        .totals td.value { text-align: right; font-family: DejaVu Sans Mono; }
        .totals tr.grand td { border-top: 2px solid #104837; font-weight: bold; font-size: 11pt; }
        .footer { margin-top: 30px; display: table; width: 100%; }
        .footer .sig { display: table-cell; width: 33%; text-align: center; padding-top: 50px; font-size: 9pt; }
        .footer .sig .line { border-top: 1px solid #999; padding-top: 4px; margin: 0 20px; }
        .small { font-size: 8pt; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <div class="company-name">{{ $company['name'] ?: 'Perusahaan' }}</div>
            <div class="small">{{ $company['address'] ?: '' }}{{ $company['city'] ? ', '.$company['city'] : '' }}</div>
            <div class="small">
                @if($company['phone']) Telp: {{ $company['phone'] }} @endif
                @if($company['email']) · {{ $company['email'] }} @endif
            </div>
            @if($company['npwp'])
                <div class="small">NPWP: {{ $company['npwp'] }}</div>
            @endif
        </div>
        <div class="right">
            <div class="po-title">PURCHASE ORDER</div>
            <div class="po-number">{{ $po->po_number }}</div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <div class="box">
                    <h3>Supplier</h3>
                    <strong>{{ $snapshot['name'] ?? $po->supplier->name }}</strong><br>
                    @if(!empty($snapshot['address'] ?? $po->supplier->address))
                        <span class="small">{{ $snapshot['address'] ?? $po->supplier->address }}</span><br>
                    @endif
                    @if(!empty($snapshot['city'] ?? $po->supplier->city))
                        <span class="small">{{ $snapshot['city'] ?? $po->supplier->city }}</span><br>
                    @endif
                    @if(!empty($snapshot['npwp'] ?? $po->supplier->npwp))
                        <span class="small">NPWP: {{ $snapshot['npwp'] ?? $po->supplier->npwp }}</span><br>
                    @endif
                    @if(!empty($snapshot['phone'] ?? $po->supplier->phone))
                        <span class="small">Telp: {{ $snapshot['phone'] ?? $po->supplier->phone }}</span>
                    @endif
                </div>
            </td>
            <td style="width:50%; vertical-align:top;">
                <div class="box">
                    <h3>Detail</h3>
                    <table style="width:100%;">
                        <tr>
                            <td class="label" style="width:40%;">Tanggal PO</td>
                            <td>{{ $po->po_date?->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">ETA</td>
                            <td>{{ $po->eta_date?->format('d M Y') ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Payment Term</td>
                            <td>{{ $po->payment_term_days ? $po->payment_term_days.' hari' : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Status</td>
                            <td><strong>{{ strtoupper(str_replace('_', ' ', $po->status)) }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%;">No</th>
                <th>Produk</th>
                <th style="width:8%;">Unit</th>
                <th style="width:8%;">Qty</th>
                <th style="width:6%;">Bonus</th>
                <th style="width:12%;">Harga</th>
                <th style="width:8%;">Z1/Z2</th>
                <th style="width:13%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $i => $item)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name_snapshot }}</strong><br>
                        <span class="small">{{ $item->product_sku_snapshot }}</span>
                        @if($item->notes)
                            <br><span class="small"><em>{{ $item->notes }}</em></span>
                        @endif
                    </td>
                    <td>{{ $item->product_unit_name_snapshot }}</td>
                    <td class="num">{{ number_format($item->qty_ordered, 0, ',', '.') }}</td>
                    <td class="num">{{ $item->bonus_qty > 0 ? number_format($item->bonus_qty, 0, ',', '.') : '—' }}</td>
                    <td class="num">{{ number_format((float) $item->cost_price, 0, ',', '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->discount_z1_pct, 2, ',', '.'), '0'), ',') }}%/{{ rtrim(rtrim(number_format((float) $item->discount_z2_pct, 2, ',', '.'), '0'), ',') }}%</td>
                    <td class="num">{{ number_format((float) $item->line_subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">Rp {{ number_format((float) $po->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if((float) $po->header_discount_amount > 0)
            <tr>
                <td class="label">
                    Diskon Header
                    @if($po->header_discount_type === 'percent')
                        ({{ rtrim(rtrim(number_format((float) $po->header_discount_value, 2, ',', '.'), '0'), ',') }}%)
                    @endif
                </td>
                <td class="value">− Rp {{ number_format((float) $po->header_discount_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">TOTAL</td>
            <td class="value">Rp {{ number_format((float) $po->total, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($po->notes)
        <div class="box" style="margin-top:14px;">
            <h3>Catatan</h3>
            <div style="white-space:pre-line; font-size:9pt;">{{ $po->notes }}</div>
        </div>
    @endif

    <div class="footer">
        <div class="sig">
            <div class="line">Disetujui oleh</div>
            <strong>{{ $po->approver?->name ?: '—' }}</strong>
            <div class="small">{{ $po->approved_at?->format('d M Y') }}</div>
        </div>
        <div class="sig">
            <div class="line">Diterima oleh</div>
            <strong>{{ $snapshot['contact_person_name'] ?? $po->supplier->contact_person_name ?: 'Supplier' }}</strong>
        </div>
        <div class="sig">
            <div class="line">Catatan</div>
            <span class="small">PO ini sah & berlaku setelah persetujuan tertulis kedua pihak.</span>
        </div>
    </div>
</body>
</html>
