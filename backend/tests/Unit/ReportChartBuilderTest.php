<?php

namespace Tests\Unit;

use App\Services\ReportChartBuilder;
use Tests\TestCase;

class ReportChartBuilderTest extends TestCase
{
    public function test_horizontal_bar_chart_renders_apex_for_html(): void
    {
        $html = app(ReportChartBuilder::class)->forPdf(false)->horizontalBarChart([
            ['label' => 'Organic', 'value' => 1200],
            ['label' => 'Direct', 'value' => 800],
        ], ['title' => 'Sources']);

        $this->assertStringContainsString('apex-chart', $html);
        $this->assertStringContainsString('Organic', $html);
        $this->assertStringContainsString('Sources', $html);
        $this->assertStringContainsString('data-config', $html);
    }

    public function test_horizontal_bar_chart_renders_legacy_bars_for_pdf(): void
    {
        $html = app(ReportChartBuilder::class)->forPdf(true)->horizontalBarChart([
            ['label' => 'Organic', 'value' => 1200],
            ['label' => 'Direct', 'value' => 800],
        ], ['title' => 'Sources']);

        $this->assertStringContainsString('hbar-table', $html);
        $this->assertStringContainsString('Organic', $html);
    }

    public function test_donut_chart_renders_apex_for_html(): void
    {
        $html = app(ReportChartBuilder::class)->forPdf(false)->donutChart([
            ['label' => 'Search', 'value' => 70],
            ['label' => 'Direct', 'value' => 30],
        ], ['title' => 'Traffic']);

        $this->assertStringContainsString('apex-chart', $html);
        $this->assertStringContainsString('Traffic', $html);
        $this->assertStringContainsString('donut', $html);
        $this->assertStringContainsString('position&quot;:&quot;right', $html);
    }

    public function test_donut_chart_renders_share_legend_for_pdf(): void
    {
        $html = app(ReportChartBuilder::class)->forPdf(true)->donutChart([
            ['label' => 'Search', 'value' => 70],
            ['label' => 'Direct', 'value' => 30],
        ], ['title' => 'Traffic']);

        $this->assertStringContainsString('share-legend', $html);
        $this->assertStringContainsString('Traffic', $html);
        $this->assertStringContainsString('%', $html);
    }

    public function test_compare_time_series_chart_renders_overlay_for_html(): void
    {
        $html = app(ReportChartBuilder::class)->forPdf(false)->compareTimeSeriesChart(
            [
                ['label' => '2026-04-01', 'value' => 100],
                ['label' => '2026-04-02', 'value' => 120],
            ],
            [
                ['label' => '2026-03-01', 'value' => 80],
                ['label' => '2026-03-02', 'value' => 90],
            ],
            ['title' => 'Поисковый трафик'],
        );

        $this->assertStringContainsString('apex-chart', $html);
        $this->assertStringContainsString('Текущий период', $html);
        $this->assertStringContainsString('Сравниваемый период', $html);
        $this->assertStringContainsString('position&quot;:&quot;right', $html);
    }

    public function test_comparison_chart_falls_back_to_kpi_cards_without_previous_period(): void
    {
        $html = app(ReportChartBuilder::class)->comparisonChart([
            ['label' => 'Visits', 'current' => 1000],
            ['label' => 'Users', 'current' => 900],
        ]);

        $this->assertStringContainsString('kpi-grid', $html);
        $this->assertStringContainsString('1 000', $html);
    }
}
