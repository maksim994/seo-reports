@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">
        Динамика {{ $periodLabel }} · период: {{ $lookbackMonths }} мес.
        @if ($regionId)
            · регион ID {{ $regionId }}
        @else
            · вся Россия
        @endif
    </p>

    @if (empty($seriesByPhrase))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @foreach ($seriesByPhrase as $item)
            @php
                $series = $item['series'] ?? [];
                $timeseries = !empty($series)
                    ? $charts->timeSeriesChart($series, [
                        'title' => $item['phrase'],
                        'max_points' => 36,
                    ])
                    : null;
            @endphp
            <div class="wordstat-phrase-block">
                <h3>{{ $item['phrase'] }}</h3>

                @if (empty($series))
                    <div class="alert">Нет данных по фразе «{{ $item['phrase'] }}».</div>
                @else
                    @include('reports.blocks.partials.visualization', compact('timeseries'))

                    @if (!empty($timeseries))
                        <h4 class="viz-table-title">Детализация</h4>
                    @endif
                    <div class="block-details">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Период</th>
                                    <th>Запросов</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($series as $point)
                                    <tr>
                                        <td>{{ $point['label'] }}</td>
                                        <td>{{ number_format($point['value'], 0, '.', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
