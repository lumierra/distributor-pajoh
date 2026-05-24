@extends('pdf.reports._layout', [
    'title' => 'Laporan Penjualan',
    'company' => $company,
    'generatedAt' => $generatedAt,
    'generatedBy' => $generatedBy,
    'filterInfo' => $filterInfo,
])

@section('content')
@endsection

@php
    // Render directly into the layout's $slot via output buffering pattern
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #222; line-height: 1.35; }
        h1 { font-size: 18pt; margin: 0; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        .company-name { font-size: 13pt; font-weight: bold; }
        .meta { font-size: 9pt; color: #555; }
        .filter-info { margin: 8px 0; font-size: 9pt; padding: 6px 8px; background: #f5f5f5; }
        table.kpi { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.kpi td { width: 25%; padding: 6px 8px; border: 1px solid #ccc; vertical-align: top; background: #fafafa; }
        .kpi-label { font-size: 8pt; text-transform: uppercase; color: #777; }
        .kpi-value { font-size: 13pt; font-weight: bold; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #888; padding: 4px 6px; font-size: 9pt; }
        table.data th { background: #eee; text-align: left; }
        td.num, th.num { text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <div class="company-name">{{ $company['name'] ?? 'Distributor Pajoh' }}</div>
    <div class="meta">{{ $company['address'] ?? '' }} • {{ $company['city'] ?? '' }} • {{ $company['phone'] ?? '' }}</div>
    <h1 style="margin-top: 6px;">Laporan Penjualan</h1>
    <div class="meta">Generated: {{ $generatedAt }} @if(! empty($generatedBy)) • by {{ $generatedBy }} @endif</div>
</div>

@if(! empty($filterInfo))
<div class="filter-info">
    <strong>Filter:</strong>
    @foreach($filterInfo as $k => $v)
        <span style="margin-right: 12px;">{{ $k }}: {{ $v }}</span>
    @endforeach
</div>
@endif

<table class="kpi">
    <tr>
        <td><div class="kpi-label">Invoices</div><div class="kpi-value">{{ $kpi['invoices'] ?? 0 }}</div></td>
        <td><div class="kpi-label">Revenue</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['revenue'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Cost (HPP)</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['cost'] ?? 0), 0, ',', '.') }}</div></td>
        <td><div class="kpi-label">Margin ({{ $kpi['margin_percent'] ?? 0 }}%)</div><div class="kpi-value">Rp {{ number_format((float) ($kpi['margin'] ?? 0), 0, ',', '.') }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th class="num">Invoices</th>
            <th class="num">Revenue</th>
            <th class="num">Cost</th>
            <th class="num">Margin</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($r->snapshot_date)->format('d M Y') }}</td>
            <td class="num">{{ (int) $r->invoice_count }}</td>
            <td class="num">Rp {{ number_format((float) $r->revenue, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((float) $r->cost_total, 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((float) $r->margin, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        @if($rows->isEmpty())
        <tr><td colspan="5" style="text-align:center; color:#888;">Tidak ada data.</td></tr>
        @endif
    </tbody>
</table>
</body>
</html>
