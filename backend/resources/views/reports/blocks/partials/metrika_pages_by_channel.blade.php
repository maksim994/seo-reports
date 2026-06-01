@php
    $formatters = $formatters ?? [];
    $formatCell = function (string $column, mixed $value) use ($formatters): string {
        if (($formatters[$column] ?? null) === 'percent') {
            return number_format((float) $value, 1, '.', ' ').' %';
        }
        if (is_numeric($value) && ! in_array($column, ['label'], true)) {
            return number_format((float) $value, str_contains((string) $value, '.') ? 1 : 0, '.', ' ');
        }

        return (string) $value;
    };
@endphp
<div class="block-details">
    @foreach ($rows as $page)
        <div class="page-channel-group">
            <h4 class="page-channel-title">
                <a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer">{{ $page['label'] }}</a>
            </h4>
            <table class="data-table data-table-compact">
                <thead>
                    <tr>
                        <th>Канал</th>
                        @foreach ($channelColumns as $column)
                            <th>{{ $channelHeaders[$column] ?? $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page['channels'] as $channel)
                        <tr>
                            <td>{{ $channel['label'] }}</td>
                            @foreach ($channelColumns as $column)
                                <td>{{ $formatCell($column, $channel[$column] ?? '—') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
