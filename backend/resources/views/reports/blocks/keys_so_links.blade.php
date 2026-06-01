@php
    $metrics = [
        ['label' => 'Входящие ссылки', 'value' => number_format($summary['incoming'], 0, '.', ' ')],
        ['label' => 'Исходящие ссылки', 'value' => number_format($summary['outgoing'], 0, '.', ' ')],
        ['label' => 'DR', 'value' => $summary['dr'] !== null ? (string) $summary['dr'] : '—'],
        ['label' => 'Ссылающиеся домены', 'value' => number_format($summary['referring_domains'], 0, '.', ' ')],
        ['label' => 'Исходящие домены', 'value' => number_format($summary['outgoing_domains'], 0, '.', ' ')],
        ['label' => 'Ссылки по IP', 'value' => number_format($summary['links_by_ip'], 0, '.', ' ')],
        ['label' => 'Анкоров', 'value' => number_format($summary['anchors'], 0, '.', ' ')],
    ];
@endphp
<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Домен: {{ $domainLabel }}</p>

    @include('reports.blocks.partials.keys_so_kpi_cards', ['metrics' => $metrics])
</div>
