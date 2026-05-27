@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $showItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['query'],
        'value' => $row['shows'],
    ])->all();
    $bars = $charts->horizontalBarChart($showItems, [
        'title' => 'Топ запросов по показам',
        'max_items' => 10,
    ]);
    $donut = $charts->donutChart(
        collect($showItems)->filter(fn (array $item) => $item['value'] > 0)->take(6)->values()->all(),
        ['title' => 'Доля показов', 'center_label' => 'Показы']
    );
@endphp
<div>
    <h2>Яндекс.Вебмастер — поисковые запросы</h2>
    <p class="muted">Сайт: {{ $hostLabel }}</p>

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
                    <th>Запрос</th>
                    <th>Показы</th>
                    <th>Клики</th>
                    <th>CTR</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['query'] }}</td>
                        <td>{{ number_format($row['shows'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['clicks'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['ctr'], 2, '.', ' ') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
