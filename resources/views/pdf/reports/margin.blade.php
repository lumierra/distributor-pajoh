<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Margin</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #222; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 18pt; margin: 0; }
        h2 { font-size: 12pt; margin-top: 12px; }
        .company-name { font-size: 13pt; font-weight: bold; }
        .meta { font-size: 9pt; color: #555; }
        table.kpi { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.kpi td { padding: 5px 7px; border: 1px solid #ccc; background: #fafafa; }
        .kpi-label { font-size: 7pt; text-transform: uppercase; color: #777; }
        .kpi-value { font-size: 12pt; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #888; padding: 4px 6px; font-size: 9pt; }
        table.data th { background: #eee; text-align: left; }
        td.num, th.num { text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <div class="company-name">{{ $company['name'] ?? 'Distributor Pajoh' }}</div>
    <h1>Laporan Margin</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>
</div>

<table class="kpi">
    <tr>
        <td><div class="kpi-label">Revenue</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['revenue'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Cost</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['cost'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Margin</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['margin'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Margin %</div><div class="kpi-value">{{ $kpi['margin_percent'] ?? 0 }}%</div></td>
    </tr>
</table>

<h2>Top by Product</h2>
<table class="data">
    <thead>
        <tr><th>Produk</th><th>SKU</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Margin</th></tr>
    </thead>
    <tbody>
        @foreach($byProduct as $r)
        <tr>
            <td>{{ $r->product?->name ?? '—' }}</td>
            <td>{{ $r->product?->sku ?? '—' }}</td>
            <td class="num">{{ number_format((float) $r->revenue, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->cost, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->margin, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<h2>Top by Customer</h2>
<table class="data">
    <thead>
        <tr><th>Customer</th><th class="num">Revenue</th><th class="num">Margin</th></tr>
    </thead>
    <tbody>
        @foreach($byCustomer as $r)
        <tr>
            <td>{{ $r->customer?->name ?? '—' }}</td>
            <td class="num">{{ number_format((float) $r->revenue, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->margin, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
