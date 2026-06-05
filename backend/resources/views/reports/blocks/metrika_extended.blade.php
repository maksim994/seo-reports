@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $valueKey = $valueKey ?? 'visits';
    $chartItems = collect($rows ?? [])->map(fn (array $row) => [
        'label' => $row['label'] ?? '—',
        'value' => (float) ($row[$valueKey] ?? 0),
    ])->all();

    $donut = null;
    $bars = null;
    $timeseries = null;
    $comparison = null;
    $combo = null;
    $chartOptions = $chartOptions ?? [];
    $donutOptions = $donutOptions ?? ['title' => 'Структура', 'center_label' => 'Всего'];
    $chartType = $chartType ?? '';
    $tableMode = $tableMode ?? 'flat';

    if ($chartType === 'donut' && !empty($chartItems)) {
        $donut = $charts->donutChart($chartItems, $donutOptions);
    } elseif ($chartType === 'donut_bars' && !empty($chartItems)) {
        $donut = $charts->donutChart($chartItems, $donutOptions);
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => false]);
    } elseif ($chartType === 'bars' && !empty($chartItems)) {
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => true]);
    } elseif ($chartType === 'timeseries' && !empty($rows)) {
        $timeseries = $charts->timeSeriesChart($chartItems, array_merge(['title' => 'Динамика', 'chart_kind' => 'line'], $chartOptions));
    } elseif ($chartType === 'line_timeseries_multi' && !empty($lineSeries['categories'] ?? null)) {
        $timeseries = $charts->multiSeriesLineChart(
            $lineSeries['categories'],
            $lineSeries['series'] ?? [],
            array_merge(['title' => 'Динамика по каналам'], $chartOptions),
        );
    } elseif ($chartType === 'timeseries_compare' && !empty($rows)) {
        $timeseries = $charts->timeSeriesChart($chartItems, ['title' => 'Текущий период', 'chart_kind' => 'line']);
        if (!empty($previousSeries)) {
            $comparison = $charts->timeSeriesChart($previousSeries, ['title' => 'Сравниваемый период', 'chart_kind' => 'line']);
        }
    } elseif ($chartType === 'timeseries_overlay' && !empty($rows)) {
        $timeseries = $charts->compareTimeSeriesChart(
            $chartItems,
            collect($previousSeries ?? [])->map(fn (array $row) => [
                'label' => $row['label'] ?? '—',
                'value' => (float) ($row['value'] ?? 0),
            ])->all(),
            array_merge([
                'current_label' => 'Текущий период',
                'previous_label' => 'Сравниваемый период',
            ], $chartOptions),
        );
    } elseif ($chartType === 'combo' && !empty($rows)) {
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
        if ($value === null) {
            return '—';
        }
        if (($formatters[$column] ?? null) === 'percent') {
            return number_format((float) $value, 1, '.', ' ').' %';
        }
        if (($formatters[$column] ?? null) === 'signed_percent') {
            $number = (float) $value;
            $sign = $number > 0 ? '+' : '';

            return $sign.number_format($number, 1, '.', ' ').' %';
        }
        if (is_numeric($value) && ! in_array($column, ['label'], true)) {
            return number_format((float) $value, str_contains((string) $value, '.') ? 1 : 0, '.', ' ');
        }

        return (string) $value;
    };
    $cellClass = function (string $column, mixed $value) use ($formatters): string {
        if (($formatters[$column] ?? null) !== 'signed_percent' || $value === null) {
            return '';
        }

        $number = (float) $value;
        if (abs($number) < 0.0001) {
            return 'delta-neutral';
        }

        return $number > 0 ? 'delta-up' : 'delta-down';
    };
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Счётчик: {{ $counterLabel }}</p>

    @php
        $hasLineChart = !empty($lineSeries['categories'] ?? []) && !empty($lineSeries['series'] ?? []);
        $hasTableRows = !empty($rows);
    @endphp
    @if (!$hasLineChart && !$hasTableRows)
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @if ($tableMode !== 'by_channel')
            @include('reports.blocks.partials.visualization', compact('donut', 'bars', 'timeseries', 'comparison', 'combo'))

            @if (!empty($rows) && !empty($headers))
                <h3 class="viz-table-title">Детализация</h3>
            @endif
        @elseif (!empty($timeseries))
            @include('reports.blocks.partials.visualization', compact('timeseries'))
            <h3 class="viz-table-title">Детализация по каналам</h3>
        @endif

        @if ($tableMode === 'by_channel')
            @include('reports.blocks.partials.metrika_pages_by_channel', [
                'rows' => $rows,
                'channelColumns' => $channelColumns,
                'channelHeaders' => $channelHeaders,
                'formatters' => $formatters,
            ])
        @elseif (!empty($rows))
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
                                    <td>
                                        @if (($linkColumn ?? null) === $column && !empty($row['url'] ?? null))
                                            <a href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer">{{ $formatCell($column, $row[$column] ?? $row['label'] ?? '—') }}</a>
                                        @else
                                            @php($value = array_key_exists($column, $row) ? $row[$column] : null)
                                            @if ($cellClass($column, $value) !== '')
                                                <span class="{{ $cellClass($column, $value) }}">{{ $formatCell($column, $value) }}</span>
                                            @else
                                                {{ $formatCell($column, $value) }}
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
