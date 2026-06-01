@php
    $charts = app(\App\Services\ReportChartBuilder::class)->forPdf($forPdf ?? false);
    $cards = $charts->summaryCards($metrics);
@endphp
@if (!empty($cards))
    {!! $cards !!}
@endif
