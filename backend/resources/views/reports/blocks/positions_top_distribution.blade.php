@php
    $charts = app(\App\Services\ReportChartBuilder::class);
    $tops = $summary['tops'] ?? [];
    $chartItems = collect([
        ['label' => 'TOP-3', 'value' => (float) ($tops['top3'] ?? 0)],
        ['label' => 'TOP-10', 'value' => (float) ($tops['top10'] ?? 0)],
        ['label' => 'TOP-30', 'value' => (float) ($tops['top30'] ?? 0)],
        ['label' => 'TOP-100', 'value' => (float) ($tops['top100'] ?? 0)],
    ])->filter(fn (array $item) => $item['value'] > 0)->values()->all();
    $bars = $charts->horizontalBarChart($chartItems, [
        'title' => 'Распределение по TOP-N',
        'show_share' => true,
    ]);
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Проект: {{ $resourceLabel }}</p>

    @if (empty($chartItems))
        <div class="alert">Нет данных о TOP-N за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('bars'))
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Диапазон</th>
                        <th>Ключевых фраз</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chartItems as $item)
                        <tr>
                            <td>{{ $item['label'] }}</td>
                            <td>{{ number_format($item['value'], 0, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
