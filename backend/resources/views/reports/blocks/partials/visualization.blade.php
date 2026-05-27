@if (!empty($donut) || !empty($bars) || !empty($comparison) || !empty($combo) || !empty($timeseries))
    <div class="viz-grid">
        @if (!empty($donut))
            <div class="viz-item">{!! $donut !!}</div>
        @endif
        @if (!empty($bars))
            <div class="viz-item">{!! $bars !!}</div>
        @endif
        @if (!empty($timeseries))
            <div class="viz-item viz-wide">{!! $timeseries !!}</div>
        @endif
        @if (!empty($comparison))
            <div class="viz-item viz-wide">{!! $comparison !!}</div>
        @endif
        @if (!empty($combo))
            <div class="viz-item viz-wide">{!! $combo !!}</div>
        @endif
    </div>
@endif
