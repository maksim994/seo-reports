<?php

namespace App\Services;

class ReportChartBuilder
{
    /** @var list<string> */
    private const COLORS = [
        '#2563EB', '#7C3AED', '#DB2777', '#EA580C', '#D97706',
        '#16A34A', '#0891B2', '#4F46E5', '#BE185D', '#64748B',
    ];

    private bool $forPdf = false;

    private static int $globalChartSeq = 0;

    public function forPdf(bool $forPdf = true): self
    {
        $this->forPdf = $forPdf;
        self::$globalChartSeq = 0;

        return $this;
    }

    /**
     * @param  list<array{label: string, value: float, meta?: string}>  $items
     */
    public function horizontalBarChart(array $items, array $options = []): string
    {
        if ($this->forPdf) {
            return $this->legacyHorizontalBarChart($items, $options);
        }

        $title = (string) ($options['title'] ?? '');
        $maxItems = (int) ($options['max_items'] ?? 8);
        $valueSuffix = (string) ($options['value_suffix'] ?? '');
        $showShare = (bool) ($options['show_share'] ?? true);

        $items = array_slice($items, 0, $maxItems);
        if ($items === []) {
            return '';
        }

        $total = array_sum(array_column($items, 'value'));
        $labels = array_map(fn (array $item) => $this->truncate((string) $item['label'], 36), $items);
        $values = array_map(fn (array $item) => round((float) $item['value'], 2), $items);
        $height = min(420, max(220, count($items) * 42 + 60));

        return $this->apexChart('bar', [
            'chart' => ['height' => $height],
            'series' => [['data' => $values]],
            'xaxis' => ['categories' => $labels],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'borderRadius' => 6,
                    'barHeight' => '62%',
                    'distributed' => true,
                    'dataLabels' => ['position' => 'center'],
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'style' => ['fontSize' => '11px', 'fontWeight' => 600, 'colors' => ['#ffffff']],
            ],
            'tooltip' => [
                'y' => [
                    'title' => ['formatter' => '__LABEL__'],
                ],
                'customMeta' => array_map(function (array $item) use ($total, $showShare, $valueSuffix) {
                    $value = (float) $item['value'];
                    $share = ($total > 0 && $showShare) ? round(($value / $total) * 100, 1).'%' : null;

                    return trim($this->formatNumber($value).$valueSuffix.($share ? ' · '.$share : ''));
                }, $items),
            ],
            'grid' => ['padding' => ['left' => 8, 'right' => 16]],
        ], $title, 'bar');
    }

    /**
     * @param  list<array{label: string, value: float}>  $items
     */
    public function donutChart(array $items, array $options = []): string
    {
        if ($this->forPdf) {
            return $this->legacyDonutChart($items, $options);
        }

        $title = (string) ($options['title'] ?? '');
        $centerLabel = (string) ($options['center_label'] ?? 'Всего');
        $maxItems = (int) ($options['max_items'] ?? 6);
        $valueSuffix = (string) ($options['value_suffix'] ?? '');

        $items = array_values(array_filter($items, fn (array $item) => ($item['value'] ?? 0) > 0));
        $items = array_slice($items, 0, $maxItems);
        if ($items === []) {
            return '';
        }

        $total = array_sum(array_column($items, 'value'));
        $labels = array_map(fn (array $item) => $this->truncate((string) $item['label'], 28), $items);
        $values = array_map(fn (array $item) => round((float) $item['value'], 2), $items);

        $legendPosition = (string) ($options['legend_position'] ?? 'right');

        return $this->apexChart('donut', [
            'chart' => ['height' => (int) ($options['height'] ?? 240)],
            'series' => $values,
            'labels' => $labels,
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '68%',
                        'labels' => [
                            'show' => true,
                            'name' => ['show' => true, 'fontSize' => '11px'],
                            'value' => ['show' => true, 'fontSize' => '15px', 'fontWeight' => 700],
                            'total' => [
                                'show' => true,
                                'label' => $centerLabel,
                                'fontSize' => '11px',
                                'color' => '#64748b',
                                'formatter' => '__TOTAL__',
                            ],
                        ],
                    ],
                ],
            ],
            'legend' => [
                'position' => $legendPosition,
                'horizontalAlign' => $legendPosition === 'bottom' ? 'center' : 'left',
                'fontSize' => '11px',
                'offsetY' => 8,
                'itemMargin' => [
                    'horizontal' => 6,
                    'vertical' => 3,
                ],
            ],
            'dataLabels' => ['enabled' => false],
            'tooltip' => [
                'customMeta' => [
                    'totalText' => $this->formatNumber($total).$valueSuffix,
                ],
            ],
        ], $title, 'donut');
    }

    /**
     * @param  list<array{label: string, current: float, previous?: float|null, suffix?: string}>  $metrics
     */
    public function comparisonChart(array $metrics, array $options = []): string
    {
        $hasPrevious = collect($metrics)->contains(fn (array $m) => isset($m['previous']) && $m['previous'] !== null);
        if (! $hasPrevious) {
            return $this->kpiCards($metrics);
        }

        if ($this->forPdf) {
            return $this->legacyComparisonChart($metrics, $options);
        }

        $title = (string) ($options['title'] ?? 'Сравнение периодов');
        $labels = array_map(fn (array $metric) => (string) $metric['label'], $metrics);
        $current = array_map(fn (array $metric) => round((float) $metric['current'], 2), $metrics);
        $previous = array_map(fn (array $metric) => round((float) ($metric['previous'] ?? 0), 2), $metrics);
        $suffixes = array_map(fn (array $metric) => (string) ($metric['suffix'] ?? ''), $metrics);

        return $this->apexChart('bar', [
            'chart' => ['height' => 300],
            'series' => [
                ['name' => 'Текущий период', 'data' => $current],
                ['name' => 'Предыдущий период', 'data' => $previous],
            ],
            'xaxis' => ['categories' => $labels],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 6,
                    'columnWidth' => '48%',
                ],
            ],
            'colors' => ['#2563EB', '#CBD5E1'],
            'dataLabels' => ['enabled' => false],
            'legend' => [
                'position' => 'top',
                'horizontalAlign' => 'right',
            ],
            'tooltip' => [
                'customMeta' => [
                    'suffixes' => $suffixes,
                ],
            ],
        ], $title, 'comparison wide');
    }

    /**
     * @param  list<array{label: string, current: float, suffix?: string}>  $metrics
     */
    public function kpiCards(array $metrics): string
    {
        if ($metrics === []) {
            return '';
        }

        $cards = '';
        foreach ($metrics as $index => $metric) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $suffix = (string) ($metric['suffix'] ?? '');
            $cards .= '<td class="kpi-card" style="border-top: 3px solid '.$color.'">'
                .'<div class="kpi-value">'.e($this->formatNumber((float) $metric['current']).$suffix).'</div>'
                .'<div class="kpi-label">'.e((string) $metric['label']).'</div>'
                .'</td>';
        }

        return '<table class="kpi-grid" width="100%"><tr>'.$cards.'</tr></table>';
    }

    /**
     * @param  list<array{label: string, primary: float, secondary?: float|null, primary_suffix?: string, secondary_suffix?: string}>  $items
     */
    public function comboBarChart(array $items, array $options = []): string
    {
        if ($this->forPdf) {
            return $this->legacyComboBarChart($items, $options);
        }

        $title = (string) ($options['title'] ?? '');
        $primaryLabel = (string) ($options['primary_label'] ?? '');
        $secondaryLabel = (string) ($options['secondary_label'] ?? '');
        $items = array_slice($items, 0, 8);
        if ($items === []) {
            return '';
        }

        $labels = array_map(fn (array $item) => $this->truncate((string) $item['label'], 34), $items);
        $values = array_map(fn (array $item) => round((float) $item['primary'], 2), $items);
        $height = min(420, max(220, count($items) * 42 + 60));
        $footer = trim($primaryLabel.($secondaryLabel !== '' ? ' · '.$secondaryLabel : ''));

        $meta = array_map(function (array $item) {
            $primary = (float) $item['primary'];
            $secondary = isset($item['secondary']) ? (float) $item['secondary'] : null;
            $suffix = (string) ($item['primary_suffix'] ?? '');
            $secondarySuffix = (string) ($item['secondary_suffix'] ?? '%');
            $text = $this->formatNumber($primary).$suffix;
            if ($secondary !== null) {
                $text .= ' · '.number_format($secondary, 2, '.', '').$secondarySuffix;
            }

            return $text;
        }, $items);

        return $this->apexChart('bar', [
            'chart' => ['height' => $height],
            'series' => [['data' => $values]],
            'xaxis' => ['categories' => $labels],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'borderRadius' => 6,
                    'barHeight' => '62%',
                    'distributed' => true,
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'style' => ['fontSize' => '11px', 'fontWeight' => 600, 'colors' => ['#ffffff']],
            ],
            'tooltip' => ['customMeta' => $meta],
        ], $title, 'bar wide', $footer !== '' ? $footer : null);
    }

    /**
     * @param  list<array{label: string, value: float}>  $points
     */
    public function timeSeriesChart(array $points, array $options = []): string
    {
        if ($this->forPdf) {
            return $this->legacyTimeSeriesChart($points, $options);
        }

        $title = (string) ($options['title'] ?? '');
        $valueSuffix = (string) ($options['value_suffix'] ?? '');
        $maxPoints = (int) ($options['max_points'] ?? 31);
        $chartKind = (string) ($options['chart_kind'] ?? 'line');

        $points = array_slice($points, 0, $maxPoints);
        if ($points === []) {
            return '';
        }

        $labels = array_map(fn (array $point) => $this->truncate((string) $point['label'], 12), $points);
        $values = array_map(fn (array $point) => round((float) $point['value'], 2), $points);

        $config = [
            'chart' => ['height' => 280],
            'series' => [['name' => trim($title) !== '' ? $title : 'Значение', 'data' => $values]],
            'xaxis' => ['categories' => $labels],
            'stroke' => ['curve' => 'smooth', 'width' => $chartKind === 'line' ? 2 : 3],
            'dataLabels' => ['enabled' => false],
            'markers' => ['size' => $chartKind === 'line' ? 3 : 0, 'hover' => ['size' => 5]],
            'tooltip' => [
                'customMeta' => [
                    'suffix' => $valueSuffix,
                ],
            ],
        ];

        if ($chartKind === 'line') {
            $config['colors'] = ['#2563EB'];
        } else {
            $config['fill'] = [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 0.35,
                    'opacityFrom' => 0.45,
                    'opacityTo' => 0.05,
                    'stops' => [0, 90, 100],
                ],
            ];
            $config['colors'] = ['#2563EB'];
        }

        return $this->apexChart($chartKind === 'line' ? 'line' : 'area', $config, $title, 'timeseries wide');
    }

    /**
     * @param  list<string>  $categories
     * @param  list<array{name: string, data: list<float>}>  $series
     */
    public function multiSeriesLineChart(array $categories, array $series, array $options = []): string
    {
        $title = (string) ($options['title'] ?? '');
        $maxPoints = (int) ($options['max_points'] ?? 31);

        $categories = array_slice($categories, 0, $maxPoints);
        if ($categories === [] || $series === []) {
            return '';
        }

        $apexSeries = [];
        foreach ($series as $item) {
            $apexSeries[] = [
                'name' => $this->truncate((string) ($item['name'] ?? '—'), 28),
                'data' => array_slice(array_map(fn ($v) => round((float) $v, 2), $item['data'] ?? []), 0, $maxPoints),
            ];
        }

        if ($this->forPdf) {
            return $this->legacyMultiSeriesLineChart($categories, $apexSeries, $title);
        }

        return $this->apexChart('line', [
            'chart' => ['height' => 320],
            'series' => $apexSeries,
            'xaxis' => ['categories' => array_map(fn (string $c) => $this->truncate($c, 12), $categories)],
            'stroke' => ['curve' => 'smooth', 'width' => 2],
            'dataLabels' => ['enabled' => false],
            'markers' => ['size' => 0, 'hover' => ['size' => 4]],
            'legend' => [
                'position' => 'bottom',
                'horizontalAlign' => 'center',
            ],
        ], $title, 'timeseries wide');
    }

    /**
     * Два периода на одном графике (наложение линий).
     *
     * @param  list<array{label: string, value: float}>  $current
     * @param  list<array{label: string, value: float}>  $previous
     */
    public function compareTimeSeriesChart(array $current, array $previous, array $options = []): string
    {
        if ($current === []) {
            return '';
        }

        $title = (string) ($options['title'] ?? 'Динамика');
        $maxPoints = (int) ($options['max_points'] ?? 62);
        $currentLabel = (string) ($options['current_label'] ?? 'Текущий период');
        $previousLabel = (string) ($options['previous_label'] ?? 'Сравниваемый период');

        $current = array_slice($current, 0, $maxPoints);
        $previous = array_slice($previous, 0, $maxPoints);
        $len = count($current);
        if ($previous !== []) {
            $len = min($len, count($previous));
        }

        $categories = array_map(
            fn (array $point) => $this->truncate((string) ($point['label'] ?? '—'), 12),
            array_slice($current, 0, $len),
        );
        $currentValues = array_map(
            fn (array $point) => round((float) ($point['value'] ?? 0), 2),
            array_slice($current, 0, $len),
        );

        $series = [
            ['name' => $currentLabel, 'data' => $currentValues],
        ];

        if ($previous !== []) {
            $previousValues = array_map(
                fn (array $point) => round((float) ($point['value'] ?? 0), 2),
                array_slice($previous, 0, $len),
            );
            $series[] = ['name' => $previousLabel, 'data' => $previousValues];
        }

        if ($this->forPdf) {
            $html = $this->timeSeriesChart(array_slice($current, 0, $len), [
                'title' => $currentLabel,
                'chart_kind' => 'line',
                'max_points' => $maxPoints,
            ]);
            if ($previous !== [] && $html !== '') {
                $html .= $this->timeSeriesChart(
                    array_slice($previous, 0, $len),
                    ['title' => $previousLabel, 'chart_kind' => 'line', 'max_points' => $maxPoints],
                );
            }

            return $html;
        }

        return $this->apexChart('line', [
            'chart' => ['height' => 300],
            'series' => $series,
            'xaxis' => ['categories' => $categories],
            'colors' => ['#2563EB', '#94A3B8'],
            'stroke' => ['curve' => 'smooth', 'width' => 2],
            'dataLabels' => ['enabled' => false],
            'markers' => ['size' => 3, 'hover' => ['size' => 5]],
            'legend' => [
                'position' => 'right',
                'fontSize' => '11px',
                'offsetY' => 8,
            ],
            'tooltip' => [
                'shared' => true,
                'intersect' => false,
            ],
        ], $title, 'timeseries wide');
    }

    /**
     * @param  list<array{label: string, clicks: float, impressions: float, ctr: float}>  $rows
     */
    public function summaryCards(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $cards = '';
        foreach ($rows as $index => $row) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $cards .= '<td class="kpi-card" style="border-top: 3px solid '.$color.'">'
                .'<div class="kpi-value">'.e((string) $row['value']).'</div>'
                .'<div class="kpi-label">'.e((string) $row['label']).'</div>'
                .'</td>';
        }

        return '<table class="kpi-grid" width="100%"><tr>'.$cards.'</tr></table>';
    }

    private function apexChart(string $type, array $config, string $title = '', string $class = '', ?string $footnote = null): string
    {
        $id = $this->nextChartId();
        $height = (int) ($config['chart']['height'] ?? 280);
        $options = array_replace_recursive($this->baseApexOptions($type), $config);
        unset($options['chart']['height']);

        $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if ($json === false) {
            return '';
        }

        $html = ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<div id="'.e($id).'" class="apex-chart" style="min-height:'.$height.'px" data-config="'.e($json).'"></div>'
            .($footnote !== null && $footnote !== '' ? '<div class="chart-footnote">'.e($footnote).'</div>' : '');

        return $this->wrapChart($html, 'apex '.$class);
    }

    /** @return array<string, mixed> */
    private function baseApexOptions(string $type): array
    {
        return [
            'chart' => [
                'type' => $type,
                'fontFamily' => 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
                'animations' => ['enabled' => true, 'speed' => 500],
            ],
            'colors' => self::COLORS,
            'grid' => [
                'borderColor' => '#e2e8f0',
                'strokeDashArray' => 4,
            ],
            'legend' => [
                'fontSize' => '12px',
                'labels' => ['colors' => '#64748b'],
            ],
            'xaxis' => [
                'labels' => ['style' => ['colors' => '#64748b', 'fontSize' => '11px']],
                'axisBorder' => ['show' => false],
                'axisTicks' => ['show' => false],
            ],
            'yaxis' => [
                'labels' => ['style' => ['colors' => '#64748b', 'fontSize' => '11px']],
            ],
            'tooltip' => ['theme' => 'light'],
        ];
    }

    /**
     * @param  list<array{label: string, value: float, meta?: string}>  $items
     */
    private function legacyHorizontalBarChart(array $items, array $options = []): string
    {
        $title = (string) ($options['title'] ?? '');
        $maxItems = (int) ($options['max_items'] ?? 8);
        $valueSuffix = (string) ($options['value_suffix'] ?? '');
        $showShare = (bool) ($options['show_share'] ?? true);

        $items = array_slice($items, 0, $maxItems);
        if ($items === []) {
            return '';
        }

        $total = array_sum(array_column($items, 'value'));
        $maxValue = max(array_column($items, 'value')) ?: 1;

        $rows = '';
        foreach ($items as $index => $item) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $value = (float) $item['value'];
            $width = max(2, (int) round(($value / $maxValue) * 100));
            $share = ($total > 0 && $showShare) ? round(($value / $total) * 100, 1) : null;
            $valueLabel = $this->formatNumber($value).$valueSuffix;
            if ($share !== null) {
                $valueLabel .= ' · '.$share.'%';
            }

            $rows .= '<tr>'
                .'<td class="hbar-label">'.e($this->truncate((string) $item['label'], 32)).'</td>'
                .'<td class="hbar-track">'.$this->barCell($width, $color).'</td>'
                .'<td class="hbar-value">'.e($valueLabel).'</td>'
                .'</tr>';
        }

        return $this->wrapChart(
            ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<table class="hbar-table">'.$rows.'</table>',
            'bar'
        );
    }

    /**
     * @param  list<array{label: string, value: float}>  $items
     */
    private function legacyDonutChart(array $items, array $options = []): string
    {
        $title = (string) ($options['title'] ?? '');
        $centerLabel = (string) ($options['center_label'] ?? 'Всего');
        $maxItems = (int) ($options['max_items'] ?? 6);
        $valueSuffix = (string) ($options['value_suffix'] ?? '');

        $items = array_values(array_filter($items, fn (array $item) => ($item['value'] ?? 0) > 0));
        $items = array_slice($items, 0, $maxItems);
        if ($items === []) {
            return '';
        }

        $total = array_sum(array_column($items, 'value'));
        $strip = '';
        $legend = '';

        foreach ($items as $index => $item) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $value = (float) $item['value'];
            $width = max(1, (int) round(($value / $total) * 100));
            $share = round(($value / $total) * 100, 1);
            $strip .= '<td class="share-segment" width="'.$width.'%" bgcolor="'.$color.'"></td>';
            $legend .= '<tr>'
                .'<td class="legend-swatch" bgcolor="'.$color.'"></td>'
                .'<td class="legend-label">'.e($this->truncate((string) $item['label'], 28)).'</td>'
                .'<td class="legend-value">'.e($share.'% · '.$this->formatNumber($value).$valueSuffix).'</td>'
                .'</tr>';
        }

        $html = ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<table class="share-summary" width="100%"><tr>'
            .'<td class="share-total"><div class="share-total-value">'.e($this->formatNumber($total).$valueSuffix).'</div>'
            .'<div class="share-total-label">'.e($centerLabel).'</div></td>'
            .'<td class="share-body"><table class="share-strip" width="100%"><tr>'.$strip.'</tr></table>'
            .'<table class="share-legend" width="100%">'.$legend.'</table></td>'
            .'</tr></table>';

        return $this->wrapChart($html, 'donut');
    }

    /**
     * @param  list<array{label: string, current: float, previous?: float|null, suffix?: string}>  $metrics
     */
    private function legacyComparisonChart(array $metrics, array $options = []): string
    {
        $title = (string) ($options['title'] ?? 'Сравнение периодов');
        $maxValue = 1.0;
        foreach ($metrics as $metric) {
            $maxValue = max($maxValue, (float) $metric['current'], (float) ($metric['previous'] ?? 0));
        }

        $columns = '';
        foreach ($metrics as $metric) {
            $current = (float) $metric['current'];
            $previous = (float) ($metric['previous'] ?? 0);
            $suffix = (string) ($metric['suffix'] ?? '');
            $currentHeight = max(4, (int) round(($current / $maxValue) * 100));
            $previousHeight = max(4, (int) round(($previous / $maxValue) * 100));

            $columns .= '<td class="cmp-col" align="center">'
                .'<table class="cmp-bars" align="center"><tr>'
                .'<td class="cmp-bar-wrap" valign="bottom">'
                .'<table class="cmp-bar-stack" align="center"><tr>'
                .'<td class="cmp-bar cmp-bar-current" height="'.$currentHeight.'" bgcolor="#2563EB"></td>'
                .'<td class="cmp-bar cmp-bar-previous" height="'.$previousHeight.'" bgcolor="#CBD5E1"></td>'
                .'</tr></table></td></tr></table>'
                .'<div class="cmp-metric-label">'.e((string) $metric['label']).'</div>'
                .'<div class="cmp-metric-values">'
                .'<span class="cmp-current">'.e($this->formatNumber($current).$suffix).'</span>'
                .'<span class="cmp-previous">'.e($this->formatNumber($previous).$suffix).'</span>'
                .'</div></td>';
        }

        $html = '<div class="chart-title">'.e($title).'</div>'
            .'<table class="cmp-chart" width="100%"><tr>'.$columns.'</tr></table>'
            .'<div class="cmp-legend">'
            .'<span class="cmp-legend-item"><span class="cmp-dot cmp-dot-current"></span> Текущий период</span>'
            .'<span class="cmp-legend-item"><span class="cmp-dot cmp-dot-previous"></span> Предыдущий период</span>'
            .'</div>';

        return $this->wrapChart($html, 'comparison wide');
    }

    /**
     * @param  list<array{label: string, primary: float, secondary?: float|null, primary_suffix?: string, secondary_suffix?: string}>  $items
     */
    private function legacyComboBarChart(array $items, array $options = []): string
    {
        $title = (string) ($options['title'] ?? '');
        $primaryLabel = (string) ($options['primary_label'] ?? '');
        $secondaryLabel = (string) ($options['secondary_label'] ?? '');
        $items = array_slice($items, 0, 8);
        if ($items === []) {
            return '';
        }

        $maxPrimary = max(array_column($items, 'primary')) ?: 1;
        $rows = '';

        foreach ($items as $index => $item) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $primary = (float) $item['primary'];
            $secondary = isset($item['secondary']) ? (float) $item['secondary'] : null;
            $width = max(2, (int) round(($primary / $maxPrimary) * 100));
            $suffix = (string) ($item['primary_suffix'] ?? '');
            $secondarySuffix = (string) ($item['secondary_suffix'] ?? '%');
            $valueText = $this->formatNumber($primary).$suffix;
            if ($secondary !== null) {
                $valueText .= ' · '.number_format($secondary, 2, '.', '').$secondarySuffix;
            }

            $rows .= '<tr>'
                .'<td class="hbar-label">'.e($this->truncate((string) $item['label'], 34)).'</td>'
                .'<td class="hbar-track">'.$this->barCell($width, $color).'</td>'
                .'<td class="hbar-value">'.e($valueText).'</td>'
                .'</tr>';
        }

        $footer = trim($primaryLabel.($secondaryLabel !== '' ? ' · '.$secondaryLabel : ''));

        return $this->wrapChart(
            ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<table class="hbar-table">'.$rows.'</table>'
            .($footer !== '' ? '<div class="chart-footnote">'.e($footer).'</div>' : ''),
            'bar wide'
        );
    }

    /**
     * @param  list<array{label: string, value: float}>  $points
     */
    /**
     * @param  list<string>  $categories
     * @param  list<array{name: string, data: list<float>}>  $series
     */
    private function legacyMultiSeriesLineChart(array $categories, array $series, string $title = ''): string
    {
        $rows = '';
        foreach ($series as $index => $item) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $total = array_sum($item['data']);
            $rows .= '<tr>'
                .'<td class="legend-swatch" bgcolor="'.$color.'"></td>'
                .'<td class="legend-label">'.e($item['name']).'</td>'
                .'<td class="legend-value">'.e($this->formatNumber($total).' визитов').'</td>'
                .'</tr>';
        }

        $html = ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<table class="share-legend" width="100%">'.$rows.'</table>'
            .'<p class="muted" style="margin-top:8px">'.e(implode(' · ', array_slice($categories, 0, 6))
                .(count($categories) > 6 ? ' …' : '')).'</p>';

        return $this->wrapChart($html, 'timeseries wide');
    }

    private function legacyTimeSeriesChart(array $points, array $options = []): string
    {
        $title = (string) ($options['title'] ?? '');
        $valueSuffix = (string) ($options['value_suffix'] ?? '');
        $maxPoints = (int) ($options['max_points'] ?? 31);

        $points = array_slice($points, 0, $maxPoints);
        if ($points === []) {
            return '';
        }

        $maxValue = max(array_column($points, 'value')) ?: 1;
        $columns = '';

        foreach ($points as $index => $point) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $value = (float) $point['value'];
            $height = max(4, (int) round(($value / $maxValue) * 80));
            $columns .= '<td class="ts-col" align="center" valign="bottom">'
                .'<table class="ts-bar" align="center"><tr>'
                .'<td class="ts-fill" height="'.$height.'" bgcolor="'.$color.'"></td>'
                .'</tr></table>'
                .'<div class="ts-value">'.e($this->formatNumber($value).$valueSuffix).'</div>'
                .'<div class="ts-label">'.e($this->truncate((string) $point['label'], 10)).'</div>'
                .'</td>';
        }

        return $this->wrapChart(
            ($title !== '' ? '<div class="chart-title">'.e($title).'</div>' : '')
            .'<table class="ts-chart" width="100%"><tr>'.$columns.'</tr></table>',
            'timeseries wide'
        );
    }

    private function barCell(int $widthPercent, string $color): string
    {
        $rest = max(0, 100 - $widthPercent);

        return '<table class="bar-track" width="100%"><tr>'
            .'<td class="bar-fill" width="'.$widthPercent.'%" bgcolor="'.$color.'"></td>'
            .'<td class="bar-empty" width="'.$rest.'%"></td>'
            .'</tr></table>';
    }

    private function wrapChart(string $html, string $class = ''): string
    {
        return '<div class="chart-box'.($class !== '' ? ' chart-'.$class : '').'">'.$html.'</div>';
    }

    private function nextChartId(): string
    {
        return 'report-chart-'.(++self::$globalChartSeq);
    }

    private function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return number_format($value, 0, '.', ' ');
        }

        return number_format($value, 1, '.', ' ');
    }

    private function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1).'…';
    }
}
