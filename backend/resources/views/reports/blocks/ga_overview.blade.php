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
            ['label' => 'Сессии', 'current' => $current['sessions'] ?? 0, 'previous' => $previous['sessions'] ?? null],
            ['label' => 'Пользователи', 'current' => $current['users'] ?? 0, 'previous' => $previous['users'] ?? null],
            ['label' => 'Вовлечённость', 'current' => $current['engagement_rate'] ?? 0, 'previous' => $previous['engagement_rate'] ?? null, 'suffix' => '%'],
        ], ['title' => 'Динамика GA4']);
    }
@endphp
<div>
    <h2>Google Analytics 4 — обзор</h2>
    <p class="muted">Ресурс: {{ $propertyLabel }}</p>

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
                    <td>Сессии</td>
                    <td>{{ number_format($current['sessions'], 0, '.', ' ') }}</td>
                    @if ($previous)
                        <td>{{ number_format($previous['sessions'], 0, '.', ' ') }}</td>
                        <td>{{ $formatDelta($delta($current['sessions'], $previous['sessions'])) }}</td>
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
                    <td>Вовлечённость</td>
                    <td>{{ number_format($current['engagement_rate'], 1, '.', ' ') }}%</td>
                    @if ($previous)
                        <td>{{ number_format($previous['engagement_rate'], 1, '.', ' ') }}%</td>
                        <td>{{ $formatDelta($delta($current['engagement_rate'], $previous['engagement_rate'])) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>
        </div>
    @endif
</div>
