<?php

namespace Tests\Unit;

use App\Services\ReportChartBuilder;
use Tests\TestCase;

class ReportChartBuilderTest extends TestCase
{
    public function test_horizontal_bar_chart_renders_html_bars(): void
    {
        $html = app(ReportChartBuilder::class)->horizontalBarChart([
            ['label' => 'Organic', 'value' => 1200],
            ['label' => 'Direct', 'value' => 800],
        ], ['title' => 'Sources']);

        $this->assertStringContainsString('hbar-table', $html);
        $this->assertStringContainsString('Organic', $html);
        $this->assertStringContainsString('Sources', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_donut_chart_renders_share_legend(): void
    {
        $html = app(ReportChartBuilder::class)->donutChart([
            ['label' => 'Search', 'value' => 70],
            ['label' => 'Direct', 'value' => 30],
        ], ['title' => 'Traffic']);

        $this->assertStringContainsString('share-legend', $html);
        $this->assertStringContainsString('Traffic', $html);
        $this->assertStringContainsString('%', $html);
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
