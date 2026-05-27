@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $cards = $summary && $summary['visibility'] !== null ? $charts->summaryCards([
        ['label' => 'Видимость', 'value' => number_format($summary['visibility'], 1, '.', ' ')],
        ['label' => 'Средняя позиция', 'value' => $summary['avg'] !== null ? number_format($summary['avg'], 1, '.', ' ') : '—'],
        ['label' => 'Δ видимости', 'value' => $summary['visibility_dynamic'] !== null
            ? (($summary['visibility_dynamic'] >= 0 ? '+' : '').number_format($summary['visibility_dynamic'], 1, '.', ' '))
            : '—'],
    ]) : null;

    $comparison = null;
    if ($summary && $previousSummary && $summary['visibility'] !== null && $previousSummary['visibility'] !== null) {
        $comparison = $charts->comparisonChart([
            ['label' => 'Видимость', 'current' => $summary['visibility'], 'previous' => $previousSummary['visibility']],
            ['label' => 'Средняя поз.', 'current' => $summary['avg'] ?? 0, 'previous' => $previousSummary['avg'] ?? 0],
        ], ['title' => 'Сравнение периодов']);
    }
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Проект: {{ $resourceLabel }}</p>

    @if (empty($summary) || $summary['visibility'] === null)
        <div class="alert">Нет данных о видимости за выбранный период.</div>
    @else
        @if (!empty($cards))
            {!! $cards !!}
        @endif
        @if (!empty($comparison))
            <div class="viz-grid"><div class="viz-item viz-wide">{!! $comparison !!}</div></div>
        @endif
    @endif
</div>
