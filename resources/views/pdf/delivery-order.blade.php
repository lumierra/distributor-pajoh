<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan {{ $do->do_number }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'Courier New', monospace; font-size: 10pt; color: #000; line-height: 1.3; }
        h1, h2, h3 { margin: 0; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; vertical-align: top; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .border-bottom { border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .border-top { border-top: 1px solid #000; padding-top: 4px; margin-top: 6px; }
        .double-border { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { border: 1px solid #000; padding: 3px 5px; font-size: 9pt; }
        table.items th { background: #eee; font-weight: bold; text-align: left; }
        table.items td.num { text-align: right; }
        .sig-block { width: 33%; text-align: center; padding-top: 50px; font-size: 9pt; }
        .sig-line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; }
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
            <div class="bold">SURAT JALAN</div>
            <div>No: {{ $do->do_number }}</div>
            <div>SO: {{ $do->salesOrder?->so_number ?? '—' }}</div>
            <div>Tanggal: {{ $do->do_date?->format('d M Y') }}</div>
        </div>
    </div>

    <div style="margin-top:10px;">
        <div><strong>Kepada Yth:</strong></div>
        @php
            $addr = $do->delivery_address_snapshot ?? [];
        @endphp
        <div class="bold">{{ $addr['name'] ?? $do->customer?->name }}</div>
        @if(!empty($addr['address'] ?? $do->customer?->address))
            <div>{{ $addr['address'] ?? $do->customer->address }}</div>
        @endif
        @if(!empty($addr['city'] ?? $do->customer?->city))
            <div>{{ $addr['city'] ?? $do->customer->city }}{{ ($addr['province'] ?? null) ? ', '.$addr['province'] : '' }}</div>
        @endif
        @if(!empty($addr['phone'] ?? $do->customer?->phone))
            <div>Telp: {{ $addr['phone'] ?? $do->customer->phone }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:4%;">No</th>
                <th style="width:14%;">SKU</th>
                <th>Nama Produk</th>
                <th style="width:8%;">Unit</th>
                <th style="width:14%;">Batch</th>
                <th style="width:10%;" class="right">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($do->items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item->product_sku_snapshot }}</td>
                    <td>
                        {{ $item->product_name_snapshot }}
                        @if($item->is_bonus)
                            <em>(BONUS)</em>
                        @endif
                        @if($item->notes)
                            <br><small>{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td>{{ $item->product_unit_name_snapshot }}</td>
                    <td>{{ $item->batch_code_snapshot ?? '—' }}</td>
                    <td class="num">{{ number_format($item->qty_planned, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($do->notes)
        <div style="margin-top:10px;">
            <div class="bold">Catatan:</div>
            <div style="white-space:pre-line;">{{ $do->notes }}</div>
        </div>
    @endif

    @php
        $driver = $do->driver_snapshot ?? [];
        $vehicle = $do->vehicle_snapshot ?? [];
    @endphp
    <div style="margin-top:10px;">
        <div><strong>Driver:</strong> {{ $driver['name'] ?? '—' }} · SIM {{ $driver['license_no'] ?? '—' }}</div>
        <div><strong>Kendaraan:</strong> {{ $vehicle['plate'] ?? '—' }} ({{ $vehicle['type'] ?? '—' }}{{ ($vehicle['brand'] ?? null) ? ' '.$vehicle['brand'] : '' }})</div>
    </div>

    <div class="row" style="margin-top:30px;">
        <div class="col sig-block">
            <div>Hormat Kami,</div>
            <div class="sig-line">{{ $do->packer?->name ?? '—' }}</div>
            <div>(Operator Gudang)</div>
        </div>
        <div class="col sig-block">
            <div>Driver,</div>
            <div class="sig-line">{{ $driver['name'] ?? '—' }}</div>
        </div>
        <div class="col sig-block">
            <div>Penerima,</div>
            <div class="sig-line">.................................</div>
            <div>(Nama & Tanggal)</div>
        </div>
    </div>

    <div style="margin-top:15px; font-size:8pt; border-top: 1px dashed #000; padding-top:5px;">
        <em>Lembar 1 (Putih) — Arsip Pengirim · Lembar 2 (Merah) — Untuk Customer · Lembar 3 (Kuning) — Untuk Driver</em>
    </div>
</body>
</html>
