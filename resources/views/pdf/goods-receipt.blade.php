<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>GRN {{ $grn->grn_number }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; }
        h1, h2, h3 { margin: 0; }
        .header { display: table; width: 100%; border-bottom: 2px solid #104837; padding-bottom: 8px; margin-bottom: 12px; }
        .header .left { display: table-cell; width: 60%; vertical-align: top; }
        .header .right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .company-name { font-size: 14pt; font-weight: bold; color: #104837; }
        .grn-title { font-size: 16pt; font-weight: bold; letter-spacing: 1px; }
        .grn-number { font-family: DejaVu Sans Mono; font-size: 11pt; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 2px 4px; font-size: 9pt; vertical-align: top; }
        .meta .label { color: #666; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
        .box { border: 1px solid #ddd; padding: 8px; margin-bottom: 10px; }
        .box h3 { font-size: 9pt; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 5px 6px; font-size: 9pt; }
        table.items th { background: #f2f5f4; text-align: left; font-weight: bold; }
        table.items td.num { text-align: right; font-family: DejaVu Sans Mono; }
        .footer { margin-top: 30px; display: table; width: 100%; }
        .footer .sig { display: table-cell; width: 33%; text-align: center; padding-top: 50px; font-size: 9pt; }
        .footer .sig .line { border-top: 1px solid #999; padding-top: 4px; margin: 0 20px; }
        .small { font-size: 8pt; color: #777; }
        .discrepancy { background: #fff8e6; border-left: 3px solid #EC9800; padding: 6px 10px; margin: 10px 0; font-size: 9pt; }
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
        </div>
        <div class="right">
            <div class="grn-title">GOODS RECEIPT</div>
            <div class="grn-number">{{ $grn->grn_number }}</div>
            <div class="small">PO: <strong>{{ $grn->purchaseOrder?->po_number }}</strong></div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <div class="box">
                    <h3>Diterima dari Supplier</h3>
                    <strong>{{ $grn->supplier?->name }}</strong><br>
                    <span class="small">{{ $grn->supplier?->code }}</span>
                </div>
            </td>
            <td style="width:50%; vertical-align:top;">
                <div class="box">
                    <h3>Detail Penerimaan</h3>
                    <table style="width:100%;">
                        <tr>
                            <td class="label" style="width:45%;">Tanggal Terima</td>
                            <td>{{ $grn->received_date?->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">No. Surat Jalan Supplier</td>
                            <td>{{ $grn->supplier_delivery_no ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Status</td>
                            <td><strong>{{ strtoupper(str_replace('_', ' ', $grn->status)) }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    @if($grn->has_discrepancy)
        <div class="discrepancy">
            <strong>⚠ Discrepancy:</strong>
            {{ $grn->discrepancy_notes ?: 'Lihat detail per item — ada kondisi non-good, qty damaged, atau bonus mismatch.' }}
        </div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width:4%;">No</th>
                <th>Produk</th>
                <th style="width:8%;">Unit</th>
                <th style="width:14%;">Batch</th>
                <th style="width:9%;">Expired</th>
                <th style="width:7%;">Reg</th>
                <th style="width:6%;">Bonus</th>
                <th style="width:7%;">Rusak</th>
                <th style="width:8%;">Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grn->items as $i => $item)
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
                    <td>
                        <span class="small" style="font-family: DejaVu Sans Mono;">{{ $item->batch_code }}</span>
                        @if($item->production_date)
                            <br><span class="small">Prod: {{ $item->production_date->format('d/m/y') }}</span>
                        @endif
                    </td>
                    <td>{{ $item->expired_date?->format('d/m/y') ?: '—' }}</td>
                    <td class="num">{{ number_format($item->qty_reguler, 0, ',', '.') }}</td>
                    <td class="num">{{ $item->qty_bonus > 0 ? number_format($item->qty_bonus, 0, ',', '.') : '—' }}</td>
                    <td class="num">{{ $item->qty_damaged > 0 ? number_format($item->qty_damaged, 0, ',', '.') : '—' }}</td>
                    <td style="text-align:center;">{{ ['good' => 'Baik', 'damaged' => 'Rusak', 'mixed' => 'Campuran'][$item->condition] ?? ucfirst($item->condition) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($grn->notes)
        <div class="box" style="margin-top:14px;">
            <h3>Catatan</h3>
            <div style="white-space:pre-line; font-size:9pt;">{{ $grn->notes }}</div>
        </div>
    @endif

    <div class="footer">
        <div class="sig">
            <div class="line">Diterima oleh</div>
            <strong>{{ $grn->receiver?->name ?: '—' }}</strong>
        </div>
        <div class="sig">
            <div class="line">Diposting oleh</div>
            <strong>{{ $grn->poster?->name ?: '—' }}</strong>
            <div class="small">{{ $grn->posted_at?->format('d M Y') }}</div>
        </div>
        <div class="sig">
            <div class="line">Disetujui (Supplier)</div>
            <strong>{{ $grn->supplier?->name ?: 'Supplier' }}</strong>
        </div>
    </div>
</body>
</html>
