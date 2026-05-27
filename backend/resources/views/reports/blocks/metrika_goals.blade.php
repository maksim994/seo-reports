@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $comboItems = collect($rows)->map(fn (array $row) => [
        'label' => $row['label'],
        'primary' => $row['reaches'],
        'secondary' => $row['conversion'],
        'secondary_suffix' => '%',
    ])->all();
    $combo = $charts->comboBarChart($comboItems, [
        'title' => 'Достижения целей и конверсия',
        'primary_label' => 'Достижения',
        'secondary_label' => 'Конверсия',
    ]);
@endphp
<div>
    <h2>Яндекс.Метрика — цели</h2>
    <p class="muted">Счётчик: {{ $counterLabel }}</p>

    @if (empty($rows))
        <div class="alert">Нет данных по целям за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', compact('combo'))

        @if (!empty($combo))
            <h3 class="viz-table-title">Детализация</h3>
        @endif
        <div class="block-details">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Цель</th>
                    <th>Достижения</th>
                    <th>Конверсия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ number_format($row['reaches'], 0, '.', ' ') }}</td>
                        <td>{{ number_format($row['conversion'], 2, '.', ' ') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
