@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['label'],
        'value' => $row['visits'],
    ])->all();
    $donut = $charts->donutChart($chartItems, [
        'title' => 'Структура трафика',
        'center_label' => 'Визиты',
    ]);
    $bars = $charts->horizontalBarChart($chartItems, [
        'title' => 'Топ источников',
        'show_share' => false,
    ]);
@endphp
<div>
    <h2>Яндекс.Метрика — источники трафика</h2>
    <p class="muted">Счётчик: {{ $counterLabel }}</p>

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
                    <th>Источник</th>
                    <th>Визиты</th>
                    <th>Пользователи</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ number_format($row['visits'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['users'], 0, '.', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
