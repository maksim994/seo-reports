@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $bars = !empty($rows)
        ? $charts->horizontalBarChart($rows, [
            'title' => 'Популярные запросы',
            'show_share' => false,
            'max_items' => 15,
        ])
        : null;
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">
        Фраза: «{{ $phrase }}» · последние 30 дней
        @if ($regionId)
            · регион ID {{ $regionId }}
        @else
            · вся Россия
        @endif
    </p>

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
                        <th>Число запросов</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ number_format($row['value'], 0, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
