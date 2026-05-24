<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>AR Aging</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #222; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 18pt; margin: 0; }
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
        td.over90 { color: #b91c1c; }
    </style>
</head>
<body>
<div class="header">
    <div class="company-name">{{ $company['name'] ?? 'Distributor Pajoh' }}</div>
    <h1>AR Aging ({{ $kpi['snapshot_date'] ?? '' }})</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>
</div>

<table class="kpi">
    <tr>
        <td><div class="kpi-label">Total Outstanding</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['total_outstanding'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">0-30</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['bucket_0_30'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">31-60</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['bucket_31_60'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">61-90</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['bucket_61_90'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">>90</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['bucket_over_90'] ?? 0), 0, ',', '.') }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Customer</th>
            <th>Code</th>
            <th class="num">0-30</th>
            <th class="num">31-60</th>
            <th class="num">61-90</th>
            <th class="num">>90</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ $r->customer?->name ?? '—' }}</td>
            <td>{{ $r->customer?->code ?? '—' }}</td>
            <td class="num">{{ number_format((float) $r->bucket_0_30, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->bucket_31_60, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $r->bucket_61_90, 0, ',', '.') }}</td>
            <td class="num over90">{{ number_format((float) $r->bucket_over_90, 0, ',', '.') }}</td>
            <td class="num"><strong>{{ number_format((float) $r->total_outstanding, 0, ',', '.') }}</strong></td>
        </tr>
        @endforeach
        @if($rows->isEmpty())
        <tr><td colspan="7" style="text-align:center; color:#888;">Tidak ada data.</td></tr>
        @endif
    </tbody>
</table>
</body>
</html>
