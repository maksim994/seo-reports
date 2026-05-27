@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $clickItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['query'],
        'value' => $row['clicks'],
    ])->all();
    $bars = $charts->horizontalBarChart($clickItems, [
        'title' => 'Топ запросов по кликам',
        'max_items' => 10,
    ]);
@endphp
<div>
    <h2>Google Search Console — топ запросов</h2>
    <p class="muted">Сайт: {{ $siteLabel }}</p>

    @if (empty($rows))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('bars'))

        @if (!empty($bars))
            <h3 class="viz-table-title">Детализация</h3>
        @endif
        <div class="block-details">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Запрос</th>
                    <th>Клики</th>
                    <th>Показы</th>
                    <th>CTR</th>
                    <th>Позиция</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['query'] }}</td>
                        <td>{{ number_format($row['clicks'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['impressions'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['ctr'], 2, '.', ' ') }}%</td>
                        <td>{{ number_format($row['position'], 1, '.', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
