<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #222; line-height: 1.35; }
        h1, h2, h3 { margin: 0; }
        h1 { font-size: 18pt; }
        h2 { font-size: 13pt; margin-top: 10px; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        .company-name { font-size: 13pt; font-weight: bold; }
        .meta { font-size: 9pt; color: #555; margin-top: 4px; }
        .filter-info { margin: 8px 0; font-size: 9pt; padding: 6px 8px; background: #f5f5f5; border-radius: 3px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #888; padding: 4px 6px; font-size: 9pt; }
        table.data th { background: #eee; font-weight: bold; text-align: left; }
        table.data td.num, table.data th.num { text-align: right; }
        .kpi-grid { margin: 8px 0; }
        .kpi-grid table { width: 100%; border-collapse: collapse; }
        .kpi-grid td { width: 25%; padding: 6px 8px; border: 1px solid #ccc; vertical-align: top; background: #fafafa; }
        .kpi-label { font-size: 8pt; text-transform: uppercase; color: #777; }
        .kpi-value { font-size: 13pt; font-weight: bold; margin-top: 2px; }
        .footer { margin-top: 18px; font-size: 8pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company['name'] ?? 'Distributor Pajoh' }}</div>
        <div class="meta">
            {{ $company['address'] ?? '' }} • {{ $company['city'] ?? '' }} • {{ $company['phone'] ?? '' }}
        </div>
        <h1 style="margin-top: 6px;">{{ $title ?? 'Laporan' }}</h1>
        <div class="meta">
            Generated: {{ $generatedAt ?? now()->format('d M Y H:i') }}
            @if(! empty($generatedBy)) • by {{ $generatedBy }} @endif
        </div>
    </div>

    @if(! empty($filterInfo))
        <div class="filter-info">
            <strong>Filter:</strong>
            @foreach($filterInfo as $k => $v)
                <span style="margin-right: 12px;">{{ $k }}: {{ $v }}</span>
            @endforeach
        </div>
    @endif

    {{ $slot ?? '' }}

    <div class="footer">
        — End of Report —
    </div>
</body>
</html>
