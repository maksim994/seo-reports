@php
    $charts = app(\App\Services\ReportChartBuilder::class);
    $timeseries = !empty($series)
        ? $charts->timeSeriesChart($series, ['title' => $valueLabel, 'max_points' => 31])
        : null;
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Сайт: {{ $hostLabel }}</p>

    @if (empty($series))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('timeseries'))

        @if (!empty($timeseries))
            <h3 class="viz-table-title">Детализация</h3>
        @endif
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>{{ $valueLabel }}</th>
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
