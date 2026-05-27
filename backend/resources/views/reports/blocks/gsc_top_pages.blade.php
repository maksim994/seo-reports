@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['page'] ?? '—',
        'value' => (float) ($row['clicks'] ?? 0),
    ])->all();
    $bars = $charts->horizontalBarChart($chartItems, [
        'title' => 'Топ страниц по кликам',
        'show_share' => false,
    ]);
@endphp
<div>
    <h2>{{ $title }}</h2>
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
                        <th>Страница</th>
                        <th>Клики</th>
                        <th>Показы</th>
                        <th>CTR, %</th>
                        <th>Позиция</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['page'] }}</td>
                            <td>{{ number_format($row['clicks'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($row['impressions'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($row['ctr'], 2, '.', ' ') }}</td>
                            <td>{{ number_format($row['position'], 1, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
