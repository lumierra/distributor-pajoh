<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Posisi Stok</title>
    <style>
        @page { margin: 12mm 10mm; size: A4 landscape; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9pt; color: #222; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 16pt; margin: 0; }
        .company-name { font-size: 12pt; font-weight: bold; }
        .meta { font-size: 8pt; color: #555; }
        table.kpi { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.kpi td { padding: 5px 7px; border: 1px solid #ccc; background: #fafafa; }
        .kpi-label { font-size: 7pt; text-transform: uppercase; color: #777; }
        .kpi-value { font-size: 12pt; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #888; padding: 3px 5px; font-size: 8pt; }
        table.data th { background: #eee; text-align: left; }
        td.num, th.num { text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <div class="company-name">{{ $company['name'] ?? 'Distributor Pajoh' }}</div>
    <h1>Posisi Stok ({{ $kpi['snapshot_date'] ?? '' }})</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>
</div>

<table class="kpi">
    <tr>
        <td><div class="kpi-label">Produk</div><div class="kpi-value">{{ $kpi['products'] ?? 0 }}</div></td>
        <td><div class="kpi-label">Total Qty (base)</div><div class="kpi-value">{{ number_format($kpi['qty_total'] ?? 0, 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Total Value</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['total_value'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Stale Batches (>60d)</div><div class="kpi-value">{{ $kpi['stale_batches'] ?? 0 }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Produk</th>
            <th>SKU</th>
            <th>Batch</th>
            <th class="num">Qty</th>
            <th class="num">Reserved</th>
            <th class="num">Avg Cost</th>
            <th class="num">Stock Value</th>
            <th class="num">Days Idle</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ $r->product?->name ?? '—' }}</td>
            <td>{{ $r->product?->sku ?? '—' }}</td>
            <td>{{ $r->batch?->batch_code ?? '—' }}</td>
            <td class="num">{{ (int) $r->qty_on_hand_base }}</td>
            <td class="num">{{ (int) $r->qty_reserved_base }}</td>
            <td class="num">{{ number_format((float) ($r->avg_cost ?? 0), 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->stock_value, 0, ',', '.') }}</td>
            <td class="num">{{ $r->days_since_last_movement ?? '—' }}</td>
        </tr>
        @endforeach
        @if($rows->isEmpty())
        <tr><td colspan="8" style="text-align:center; color:#888;">Tidak ada data.</td></tr>
        @endif
    </tbody>
</table>
</body>
</html>
