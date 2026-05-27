@php
    $charts = app(\App\Services\ReportChartBuilder::class);
    $cards = $summary ? $charts->summaryCards([
        ['label' => 'Клики', 'value' => number_format($summary['clicks'], 0, '.', ' ')],
        ['label' => 'Показы', 'value' => number_format($summary['impressions'], 0, '.', ' ')],
        ['label' => 'CTR', 'value' => number_format($summary['ctr'], 2, '.', ' ').' %'],
        ['label' => 'Средняя позиция', 'value' => number_format($summary['position'], 1, '.', ' ')],
    ]) : null;

    $comparison = null;
    if ($summary && $previous) {
        $comparison = $charts->comparisonChart([
            ['label' => 'Клики', 'current' => $summary['clicks'], 'previous' => $previous['clicks']],
            ['label' => 'Показы', 'current' => $summary['impressions'], 'previous' => $previous['impressions']],
            ['label' => 'CTR', 'current' => $summary['ctr'], 'previous' => $previous['ctr'], 'suffix' => ' %'],
        ], ['title' => 'Сравнение периодов']);
    }
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Сайт: {{ $siteLabel }}</p>

    @if (empty($summary))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @if (!empty($cards))
            {!! $cards !!}
        @endif
        @if (!empty($comparison))
            <div class="viz-grid"><div class="viz-item viz-wide">{!! $comparison !!}</div></div>
        @endif
    @endif
</div>
