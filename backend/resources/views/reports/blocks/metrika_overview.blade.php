@php
    $delta = function (?float $current, ?float $previous): ?float {
        if ($current === null || $previous === null || $previous == 0) {
            return null;
        }
        return (($current - $previous) / $previous) * 100;
    };
    $formatDelta = function (?float $value): string {
        if ($value === null) {
            return '—';
        }
        $sign = $value > 0 ? '+' : '';
        return $sign . number_format($value, 1, '.', '') . '%';
    };
    $comparison = null;
    if ($current) {
        $charts = app(\App\Services\ReportChartBuilder::class);
        $comparison = $charts->comparisonChart([
            ['label' => 'Визиты', 'current' => $current['visits'] ?? 0, 'previous' => $previous['visits'] ?? null],
            ['label' => 'Пользователи', 'current' => $current['users'] ?? 0, 'previous' => $previous['users'] ?? null],
            ['label' => 'Отказы', 'current' => $current['bounce_rate'] ?? 0, 'previous' => $previous['bounce_rate'] ?? null, 'suffix' => '%'],
            ['label' => 'Время', 'current' => $current['avg_duration'] ?? 0, 'previous' => $previous['avg_duration'] ?? null, 'suffix' => 'с'],
        ], ['title' => 'Динамика ключевых метрик']);
    }
@endphp
<div>
    <h2>Яндекс.Метрика — обзор</h2>
    <p class="muted">Счётчик: {{ $counterLabel }}</p>

    @if (!$current)
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @include('reports.blocks.partials.visualization', ['comparison' => $comparison])

        @if (!empty($comparison))
            <h3 class="viz-table-title">Детализация</h3>
        @endif
        <div class="block-details">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Метрика</th>
                    <th>Текущий период</th>
                    @if ($previous)
                        <th>Предыдущий период</th>
                        <th>Δ</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Визиты</td>
                    <td>{{ number_format($current['visits'], 0, '.', ' ') }}</td>
                    @if ($previous)
                        <td>{{ number_format($previous['visits'], 0, '.', ' ') }}</td>
                        <td>{{ $formatDelta($delta($current['visits'], $previous['visits'])) }}</td>
                    @endif
                </tr>
                <tr>
                    <td>Пользователи</td>
                    <td>{{ number_format($current['users'], 0, '.', ' ') }}</td>
                    @if ($previous)
                        <td>{{ number_format($previous['users'], 0, '.', ' ') }}</td>
                        <td>{{ $formatDelta($delta($current['users'], $previous['users'])) }}</td>
                    @endif
                </tr>
                <tr>
                    <td>Отказы</td>
                    <td>{{ number_format($current['bounce_rate'], 1, '.', ' ') }}%</td>
                    @if ($previous)
                        <td>{{ number_format($previous['bounce_rate'], 1, '.', ' ') }}%</td>
                        <td>{{ $formatDelta($delta($current['bounce_rate'], $previous['bounce_rate'])) }}</td>
                    @endif
                </tr>
                <tr>
                    <td>Время на сайте (сек)</td>
                    <td>{{ number_format($current['avg_duration'], 0, '.', ' ') }}</td>
                    @if ($previous)
                        <td>{{ number_format($previous['avg_duration'], 0, '.', ' ') }}</td>
                        <td>{{ $formatDelta($delta($current['avg_duration'], $previous['avg_duration'])) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>
        </div>
    @endif
</div>
