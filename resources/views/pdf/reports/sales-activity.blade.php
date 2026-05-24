<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sales Activity</title>
    <style>
        @page { margin: 12mm 10mm; size: A4 landscape; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #222; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 16pt; margin: 0; }
        .company-name { font-size: 12pt; font-weight: bold; }
        .meta { font-size: 8pt; color: #555; }
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
    <h1>Sales Activity</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>
</div>

<table class="kpi">
    <tr>
        <td><div class="kpi-label">Total Sales</div><div class="kpi-value">{{ $kpi['sales'] ?? 0 }}</div></td>
        <td><div class="kpi-label">Total Visits</div><div class="kpi-value">{{ $kpi['total_visits'] ?? 0 }}</div></td>
        <td><div class="kpi-label">Valid Visits</div><div class="kpi-value">{{ $kpi['valid_visits'] ?? 0 }}</div></td>
        <td><div class="kpi-label">SO Count</div><div class="kpi-value">{{ $kpi['so_count'] ?? 0 }}</div></td>
        <td><div class="kpi-label">SO Value</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['so_value'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Avg Conversion</div><div class="kpi-value">{{ $kpi['avg_conversion'] ?? 0 }}%</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Sales</th>
            <th class="num">Total Visits</th>
            <th class="num">Valid</th>
            <th class="num">Unique Cust</th>
            <th class="num">SO Count</th>
            <th class="num">SO Value</th>
            <th class="num">Approved</th>
            <th class="num">Cancelled</th>
            <th class="num">Conversion %</th>
        </tr>
    </thead>
    <tbody>
        @foreach($bySales as $r)
        <tr>
            <td>{{ $r->sales?->name ?? '—' }}</td>
            <td class="num">{{ (int) ($r->total_visits ?? 0) }}</td>
            <td class="num">{{ (int) ($r->valid_visits ?? 0) }}</td>
            <td class="num">{{ (int) ($r->unique_customers ?? 0) }}</td>
            <td class="num">{{ (int) $r->so_count }}</td>
            <td class="num">{{ number_format((float) $r->so_value, 0, ',', '.') }}</td>
            <td class="num">{{ (int) $r->so_approved }}</td>
            <td class="num">{{ (int) $r->so_cancelled }}</td>
            <td class="num">{{ (float) ($r->conversion_rate ?? 0) }}%</td>
        </tr>
        @endforeach
        @if($bySales->isEmpty())
        <tr><td colspan="9" style="text-align:center; color:#888;">Tidak ada data.</td></tr>
        @endif
    </tbody>
</table>
</body>
</html>
