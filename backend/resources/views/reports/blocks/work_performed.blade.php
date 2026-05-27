<div>
    <h2>Проделанная работа</h2>
    <p class="muted">Период: {{ $periodLabel }}</p>

    @if ($items->isEmpty())
        <div class="alert">За выбранный период работы не добавлены.</div>
    @else
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Категория</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item->work_date->format('d.m.Y') }}</td>
                            <td>{{ $item->category->label() }}</td>
                            <td>{{ $item->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
