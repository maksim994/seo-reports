<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Проект: {{ $resourceLabel }}</p>

    @if (empty($rows))
        <div class="alert">Нет данных о позициях за выбранный период.</div>
    @else
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ключевая фраза</th>
                        <th>Позиция</th>
                        <th>Пред. период</th>
                        <th>Δ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['keyword'] }}</td>
                            <td>{{ $row['position'] !== null ? number_format($row['position'], 0, '.', ' ') : '—' }}</td>
                            <td>{{ $row['previous'] !== null ? number_format($row['previous'], 0, '.', ' ') : '—' }}</td>
                            <td>
                                @if ($row['delta'] === null)
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
