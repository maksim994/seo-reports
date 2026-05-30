@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $chartItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['label'],
        'value' => $row['value'],
    ])->all();
    $bars = !empty($chartItems)
        ? $charts->horizontalBarChart($chartItems, [
            'title' => 'Распределение по регионам',
            'show_share' => false,
            'max_items' => 15,
        ])
        : null;
    $regionTypeLabel = match ($regionType) {
        'cities' => 'города',
        'regions' => 'регионы',
        default => 'все регионы',
    };
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Фраза: «{{ $phrase }}» · {{ $regionTypeLabel }} · последние 30 дней</p>

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
                        <th>Регион</th>
                        <th>Запросов</th>
                        <th>Доля, %</th>
                        <th>Индекс интереса</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ number_format($row['value'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($row['share'], 2, '.', ' ') }}</td>
                            <td>{{ number_format($row['affinity'], 1, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
