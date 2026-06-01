<div>
    <h2>{{ $title }}</h2>
    <p class="muted">Домен: {{ $domainLabel }} · Яндекс (ИИ-ответы Алисы)</p>

    @if (empty($rows))
        <div class="alert">Нет упоминаний в ИИ-ответах или отчёт ещё строится в Keys.so.</div>
    @else
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Запрос</th>
                        <th>Дата</th>
                        <th>Ответ ИИ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="width: 28%; vertical-align: top;">{{ $row['query'] }}</td>
                            <td style="width: 12%; vertical-align: top; white-space: nowrap;">{{ $row['date'] }}</td>
                            <td style="vertical-align: top;">{!! nl2br(e($row['answer'])) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
