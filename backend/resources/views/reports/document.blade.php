<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $project->name }} — отчёт</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.55;
            margin: 0;
            padding: 0;
            background: #eef2ff;
        }
        body.report-pdf {
            background: #ffffff;
        }
        .report-shell { padding: 24px; }
        body.report-pdf .report-shell { padding: 0; background: #ffffff; }
        .report-title-wrap,
        .section-card,
        .report-footer {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .report-title-wrap { margin-bottom: 24px; }
        .report-header {
            background: #1e3a8a;
            color: #ffffff;
            border-radius: 16px;
            padding: 28px 32px;
        }
        body.report-pdf .report-header {
            border-radius: 0;
            margin-bottom: 0;
        }
        .report-header h1 {
            font-size: 30px;
            font-weight: bold;
            margin: 0 0 8px;
            color: #ffffff;
            border: 0;
            padding: 0;
        }
        .report-logo-wrap { margin-bottom: 16px; }
        .report-logo {
            max-height: 64px;
            max-width: 220px;
            object-fit: contain;
            background: rgba(255,255,255,0.95);
            border-radius: 8px;
            padding: 8px 12px;
        }
        .report-header .meta {
            color: #dbeafe;
            font-size: 13px;
            margin: 4px 0;
        }
        .report-header .badge {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 11px;
            margin-top: 12px;
        }
        .section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 20px;
        }
        body.report-pdf .section-card {
            border-radius: 0;
            border-left: 0;
            border-right: 0;
            margin-bottom: 0;
            border-bottom: 0;
        }
        .section-card h2 {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 14px;
            color: #0f172a;
            border-bottom: 0;
            padding: 0 0 0 12px;
            border-left: 4px solid #2563eb;
        }
        .muted { color: #64748b; font-size: 12px; margin-bottom: 12px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 8px; page-break-inside: auto; }
        .data-table thead { display: table-header-group; }
        .data-table th, .data-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
        }
        .data-table th {
            background: #f8fafc;
            color: #334155;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .data-table tr { page-break-inside: avoid; }
        .data-table tr:nth-child(even) td { background: #fcfdff; }
        .block-details { page-break-inside: avoid; }
        .page-channel-group { margin-bottom: 20px; page-break-inside: avoid; }
        .page-channel-title { font-size: 14px; margin: 0 0 8px; color: #0f172a; }
        .page-channel-title a { color: #2563eb; text-decoration: none; word-break: break-all; }
        .data-table-compact th, .data-table-compact td { padding: 6px 10px; font-size: 12px; }
        .data-table a { color: #2563eb; text-decoration: none; word-break: break-all; }
        .viz-table-title {
            page-break-after: avoid;
            font-size: 13px;
            font-weight: bold;
            color: #475569;
            margin: 18px 0 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .delta-up { color: #059669; font-weight: bold; }
        .delta-down { color: #dc2626; font-weight: bold; }
        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 12px;
        }
        ul.toc { list-style: none; padding: 0; margin: 0; }
        ul.toc li {
            padding: 10px 0;
            border-bottom: 1px dotted #e2e8f0;
        }
        ul.toc a { color: #2563eb; text-decoration: none; font-weight: bold; }
        .viz-grid { margin: 8px 0 4px; page-break-inside: avoid; }
        .viz-item { margin-bottom: 14px; page-break-inside: avoid; }
        .chart-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .chart-box.chart-apex {
            background: #ffffff;
            padding: 12px 8px 4px;
        }
        .apex-chart {
            width: 100%;
        }
        .apexcharts-tooltip {
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08) !important;
        }
        body.report-pdf .chart-box {
            background: #ffffff;
            border-radius: 0;
        }
        .chart-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .chart-footnote { font-size: 11px; color: #64748b; margin-top: 8px; }
        .hbar-table { width: 100%; border-collapse: collapse; }
        .hbar-table td { padding: 7px 0; vertical-align: middle; border: 0; }
        .hbar-label { width: 34%; color: #334155; font-size: 12px; padding-right: 10px; }
        .hbar-track { width: 44%; }
        .hbar-value { width: 22%; text-align: right; color: #0f172a; font-size: 12px; font-weight: bold; white-space: nowrap; }
        .bar-track { border-collapse: collapse; height: 16px; }
        .bar-fill { height: 16px; }
        .bar-empty { height: 16px; }
        .share-summary td { vertical-align: top; border: 0; padding: 0; }
        .share-total { width: 120px; padding-right: 16px; }
        .share-total-value { font-size: 24px; font-weight: bold; color: #0f172a; line-height: 1.1; }
        .share-total-label { font-size: 11px; color: #64748b; margin-top: 4px; }
        .share-strip { border-collapse: collapse; height: 18px; margin-bottom: 12px; }
        .share-segment { height: 18px; padding: 0; }
        .share-legend td { border: 0; padding: 4px 8px 4px 0; font-size: 11px; vertical-align: middle; }
        .legend-swatch { width: 12px; height: 12px; padding: 0; }
        .legend-label { color: #334155; }
        .legend-value { color: #64748b; text-align: right; white-space: nowrap; }
        .kpi-grid { border-collapse: separate; border-spacing: 12px 0; margin: 4px 0 12px; }
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 16px 18px;
            vertical-align: top;
        }
        .kpi-value { font-size: 24px; font-weight: bold; color: #0f172a; line-height: 1.1; }
        .kpi-label { font-size: 11px; color: #64748b; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
        .cmp-chart td { vertical-align: bottom; border: 0; padding: 0 8px; }
        .cmp-bars { height: 130px; border-collapse: collapse; }
        .cmp-bar-wrap { height: 110px; vertical-align: bottom; padding-bottom: 0; }
        .cmp-bar-stack { border-collapse: collapse; height: 110px; }
        .cmp-bar { width: 22px; padding: 0; }
        .cmp-metric-label { font-size: 11px; color: #475569; margin-top: 8px; font-weight: bold; }
        .cmp-metric-values { font-size: 10px; margin-top: 4px; }
        .cmp-current { color: #2563eb; font-weight: bold; display: block; }
        .cmp-previous { color: #64748b; display: block; }
        .cmp-legend { margin-top: 10px; font-size: 11px; color: #64748b; }
        .cmp-legend-item { margin-right: 16px; }
        .cmp-dot { display: inline-block; width: 10px; height: 10px; margin-right: 4px; vertical-align: middle; }
        .cmp-dot-current { background: #2563eb; }
        .cmp-dot-previous { background: #cbd5e1; }
        .ts-chart td { vertical-align: bottom; border: 0; padding: 0 2px; }
        .ts-bar { border-collapse: collapse; height: 90px; }
        .ts-fill { width: 14px; padding: 0; }
        .ts-value { font-size: 9px; color: #475569; margin-top: 4px; font-weight: bold; }
        .ts-label { font-size: 9px; color: #64748b; margin-top: 2px; }
        .report-footer {
            text-align: center;
            color: #94a3b8;
            font-size: 11px;
            padding: 8px 0 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body @class(['report-pdf' => $forPdf ?? false])>
<div class="report-shell">
@foreach ($sections as $index => $section)
    @if ($index === 0)
        <div class="report-title-wrap" id="{{ $section['anchor'] }}">
            {!! $section['html'] !!}
        </div>
    @else
        <div class="section-card" id="{{ $section['anchor'] }}">
            {!! $section['html'] !!}
        </div>
    @endif
@endforeach
    <div class="report-footer">SEO Reports · {{ now()->format('d.m.Y H:i') }}</div>
</div>
@if (!($forPdf ?? false))
    @include('reports.partials.apexcharts-init')
@endif
</body>
</html>
