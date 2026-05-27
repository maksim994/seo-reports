@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['channel'],
        'value' => $row['sessions'],
    ])->all();
    $donut = $charts->donutChart($chartItems, [
        'title' => 'Распределение сессий',
        'center_label' => 'Сессии',
    ]);
    $bars = $charts->horizontalBarChart($chartItems, [
        'title' => 'Каналы привлечения',
        'show_share' => false,
    ]);
@endphp
<div>
    <h2>Google Analytics 4 — каналы</h2>
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
                    <th>Канал</th>
                    <th>Сессии</th>
                    <th>Пользователи</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['channel'] }}</td>
                        <td>{{ number_format($row['sessions'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['users'], 0, '.', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
