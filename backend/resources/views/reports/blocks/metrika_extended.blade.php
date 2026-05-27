@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $valueKey = $valueKey ?? 'visits';
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['label'] ?? '—',
        'value' => (float) ($row[$valueKey] ?? 0),
    ])->all();

    $donut = null;
    $bars = null;
    $timeseries = null;
    $comparison = null;
    $combo = null;
    $chartOptions = $chartOptions ?? [];

    if (($chartType ?? '') === 'donut_bars' && !empty($chartItems)) {
        $donut = $charts->donutChart($chartItems, ['title' => 'Структура', 'center_label' => 'Всего']);
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => false]);
    } elseif (($chartType ?? '') === 'bars' && !empty($chartItems)) {
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => true]);
    } elseif (($chartType ?? '') === 'timeseries' && !empty($rows)) {
        $timeseries = $charts->timeSeriesChart($chartItems, array_merge(['title' => 'Динамика'], $chartOptions));
    } elseif (($chartType ?? '') === 'timeseries_compare' && !empty($rows)) {
        $timeseries = $charts->timeSeriesChart($chartItems, ['title' => 'Текущий период']);
        if (!empty($previousSeries)) {
            $comparison = $charts->timeSeriesChart($previousSeries, ['title' => 'Сравниваемый период']);
        }
    } elseif (($chartType ?? '') === 'combo' && !empty($rows)) {
        $comboItems = collect($rows)->map(fn (array $row) => [
            'label' => $row['label'] ?? '—',
            'primary' => (float) ($row[$valueKey] ?? 0),
            'secondary' => isset($secondaryKey) ? (float) ($row[$secondaryKey] ?? 0) : null,
            'secondary_suffix' => '%',
        ])->all();
        $combo = $charts->comboBarChart($comboItems, [
            'title' => 'Визиты и отказы',
            'primary_label' => 'Визиты',
            'secondary_label' => 'Отказы, %',
        ]);
    }

    $formatters = $formatters ?? [];
    $formatCell = function (string $column, mixed $value) use ($formatters): string {
        if (($formatters[$column] ?? null) === 'percent') {
            return number_format((float) $value, 1, '.', ' ').' %';
        }
        if (is_numeric($value) && ! in_array($column, ['label'], true)) {
            return number_format((float) $value, str_contains((string) $value, '.') ? 1 : 0, '.', ' ');
        }

        return (string) $value;
    };
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Счётчик: {{ $counterLabel }}</p>

    @if (empty($rows))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('donut', 'bars', 'timeseries', 'comparison', 'combo'))

        @if (!empty($donut) || !empty($bars) || !empty($timeseries) || !empty($comparison) || !empty($combo))
            <h3 class="viz-table-title">Детализация</h3>
        @endif
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td>{{ $formatCell($column, $row[$column] ?? '—') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
