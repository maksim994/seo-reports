@php
    $metrics = [
        ['label' => 'В топ 1', 'value' => number_format($summary['top1'], 0, '.', ' ')],
        ['label' => 'В топ 3', 'value' => number_format($summary['top3'], 0, '.', ' ')],
        ['label' => 'В топ 5', 'value' => number_format($summary['top5'], 0, '.', ' ')],
        ['label' => 'В топ 10', 'value' => number_format($summary['top10'], 0, '.', ' ')],
        ['label' => 'В топ 50', 'value' => number_format($summary['top50'], 0, '.', ' ')],
        ['label' => 'Упоминания в ИИ-ответах Алисы', 'value' => number_format($summary['ai_mentions'], 0, '.', ' ')],
    ];
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Домен: {{ $domainLabel }} · {{ $baseLabel }}</p>

    @include('reports.blocks.partials.keys_so_kpi_cards', ['metrics' => $metrics])

    @if (!empty($summary['keywords']))
        <h3 style="margin-top: 20px; font-size: 14px; font-weight: 600; color: #334155;">Органическая выдача</h3>
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Запрос</th>
                        <th>Позиция</th>
                        <th>Частотность</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['keywords'] as $row)
                        <tr>
                            <td>{{ $row['keyword'] }}</td>
                            <td>{{ $row['position'] !== null ? number_format($row['position'], 0, '.', ' ') : '—' }}</td>
                            <td>{{ number_format($row['frequency'], 0, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
