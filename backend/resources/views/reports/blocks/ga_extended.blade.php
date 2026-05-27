@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $valueKey = $valueKey ?? 'sessions';
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['label'] ?? '—',
        'value' => (float) ($row[$valueKey] ?? 0),
    ])->all();

    $donut = null;
    $bars = null;
    $chartOptions = $chartOptions ?? [];

    if (($chartType ?? '') === 'donut_bars' && !empty($chartItems)) {
        $donut = $charts->donutChart($chartItems, ['title' => 'Структура', 'center_label' => 'Всего']);
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => false]);
    } elseif (($chartType ?? '') === 'bars' && !empty($chartItems)) {
        $bars = $charts->horizontalBarChart($chartItems, ['title' => 'Топ', 'show_share' => true]);
    }

    $formatters = $formatters ?? [];
    $formatCell = function (string $column, mixed $value) use ($formatters): string {
        if (($formatters[$column] ?? null) === 'percent') {
            return number_format((float) $value, 1, '.', ' ').' %';
        }
        if (is_numeric($value) && $column !== 'label') {
            return number_format((float) $value, str_contains((string) $value, '.') ? 1 : 0, '.', ' ');
        }

        return (string) $value;
    };
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Ресурс: {{ $propertyLabel }}</p>

    @if (empty($rows))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('donut', 'bars'))

        @if (!empty($donut) || !empty($bars))
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
