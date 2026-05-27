@php
    $charts = app(\App\Services\ReportChartBuilder::class);
    $compareItems = [];
    if (!empty($yandex)) {
        $compareItems[] = [
            'label' => 'Яндекс',
            'primary' => (float) ($yandex['clicks'] ?? 0),
            'secondary' => (float) ($yandex['ctr'] ?? 0),
            'primary_suffix' => ' кликов',
            'secondary_suffix' => ' % CTR',
        ];
    }
    if (!empty($google)) {
        $compareItems[] = [
            'label' => 'Google',
            'primary' => (float) ($google['clicks'] ?? 0),
            'secondary' => (float) ($google['ctr'] ?? 0),
            'primary_suffix' => ' кликов',
            'secondary_suffix' => ' % CTR',
        ];
    }
    $combo = $compareItems !== []
        ? $charts->comboBarChart($compareItems, [
            'title' => 'Клики и CTR в поиске',
            'primary_label' => 'Клики',
            'secondary_label' => 'CTR',
        ])
        : null;
@endphp
<div>
    <h2>Сравнение: Яндекс vs Google</h2>
    <p class="muted">Яндекс: {{ $hostLabel }} · Google: {{ $siteLabel }}</p>

    @if (empty($yandex) && empty($google))
        <div class="alert">Нет данных за выбранный период.</div>
    @else
        @if (!empty($combo))
            <div class="viz-grid"><div class="viz-item viz-wide">{!! $combo !!}</div></div>
        @endif
        <div class="block-details">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Поисковая система</th>
                        <th>Клики</th>
                        <th>Показы</th>
                        <th>CTR, %</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!empty($yandex))
                        <tr>
                            <td>Яндекс</td>
                            <td>{{ number_format($yandex['clicks'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($yandex['shows'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($yandex['ctr'], 2, '.', ' ') }}</td>
                        </tr>
                    @endif
                    @if (!empty($google))
                        <tr>
                            <td>Google</td>
                            <td>{{ number_format($google['clicks'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($google['impressions'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($google['ctr'], 2, '.', ' ') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif
</div>
