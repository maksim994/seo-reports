@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $ranges = $topDistribution['ranges'] ?? null;
    $checkDates = $topDistribution['check_dates'] ?? null;
    $periodLabel = is_array($checkDates) && count($checkDates) === 2
        ? $checkDates[0].' — '.$checkDates[1]
        : null;

    if ($ranges === null) {
        $tops = $summary['tops'] ?? [];
        $previousTops = $previousSummary['tops'] ?? [];
        $legacyLabels = [
            'top3' => 'TOP-3',
            'top10' => 'TOP-10',
            'top30' => 'TOP-30',
            'top100' => 'TOP-100',
        ];
        $ranges = [];
        foreach ($legacyLabels as $key => $label) {
            $count = (int) ($tops[$key] ?? 0);
            if ($count === 0 && ! isset($tops[$key])) {
                continue;
            }
            $delta = isset($previousTops[$key])
                ? $count - (int) ($previousTops[$key] ?? 0)
                : null;
            $ranges[] = [
                'label' => $label,
                'count' => $count,
                'percent' => null,
                'delta' => $delta,
            ];
        }
    }

    $hasData = collect($ranges)->contains(fn (array $row) => ($row['count'] ?? 0) > 0);
    $chartItems = collect($ranges)
        ->filter(fn (array $row) => ($row['count'] ?? 0) > 0)
        ->map(fn (array $row) => ['label' => $row['label'], 'value' => (float) $row['count']])
        ->values()
        ->all();
    $bars = $chartItems !== []
        ? $charts->horizontalBarChart($chartItems, [
            'title' => 'Распределение по ТОПам',
            'show_share' => true,
        ])
        : null;
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">
        Проект: {{ $resourceLabel }}
        @if ($periodLabel)
            · {{ $periodLabel }}
        @endif
    </p>

    @if (! $hasData)
        <div class="alert">Нет данных о ТОПах за выбранный период.</div>
    @else
        @if ($bars)
            @include('reports.blocks.partials.visualization', ['bars' => $bars])
        @endif
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Диапазон</th>
                        <th>Доля</th>
                        <th>Ключевых фраз</th>
                        <th>Динамика</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranges as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>
                                @if (($row['percent'] ?? null) !== null)
                                    {{ $row['percent'] }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format($row['count'] ?? 0, 0, '.', ' ') }}</td>
                            <td>
                                @if (($row['delta'] ?? null) === null)
                                    —
                                @elseif ($row['delta'] > 0)
                                    <span class="delta-up">+{{ number_format($row['delta'], 0, '.', ' ') }}</span>
                                @elseif ($row['delta'] < 0)
                                    <span class="delta-down">{{ number_format($row['delta'], 0, '.', ' ') }}</span>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
