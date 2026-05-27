<?php

namespace App\Services;

class ReportChartBuilder
{
    /** @var list<string> */
    private const COLORS = [
        '#2563EB', '#7C3AED', '#DB2777', '#EA580C', '#D97706',
        '#16A34A', '#0891B2', '#4F46E5', '#BE185D', '#64748B',
    ];

    /**
     * @param  list<array{label: string, value: float, meta?: string}>  $items
     */
    public function horizontalBarChart(array $items, array $options = []): string
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
    public function donutChart(array $items, array $options = []): string
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
    public function comparisonChart(array $metrics, array $options = []): string
    {
        $title = (string) ($options['title'] ?? 'Сравнение периодов');
        $hasPrevious = collect($metrics)->contains(fn (array $m) => isset($m['previous']) && $m['previous'] !== null);
        if (! $hasPrevious) {
            return $this->kpiCards($metrics);
        }

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
    public function timeSeriesChart(array $points, array $options = []): string
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
