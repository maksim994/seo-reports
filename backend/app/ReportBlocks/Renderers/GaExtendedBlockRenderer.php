<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleAnalyticsDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class GaExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'ga_devices' => 'GA4: устройства',
        'ga_geo' => 'GA4: география',
        'ga_landing_pages' => 'GA4: посадочные страницы',
    ];

    public function __construct(private GoogleAnalyticsDataService $analytics) {}

    public function type(): string
    {
        return 'ga_devices';
    }

    public function supportedTypes(): array
    {
        return array_keys(self::TITLES);
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        return $this->renderBlock($this->type(), $context, $settings);
    }

    public function renderBlock(string $blockType, ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $title = self::TITLES[$blockType] ?? 'Google Analytics';

        $resolved = $this->resolveBinding($context, IntegrationProvider::GoogleAnalytics);
        if (! $resolved) {
            return $this->unavailable('Google Analytics не привязан к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $propertyId = $this->analytics->propertyIdFromBinding($binding);
        if (! $propertyId) {
            return $this->unavailable('Ресурс GA4 не указан', $title);
        }

        try {
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            $payload = match ($blockType) {
                'ga_devices' => $this->payloadDevices($token, $propertyId, $from, $to),
                'ga_geo' => $this->payloadGeo($token, $propertyId, $from, $to),
                default => $this->payloadLandingPages($token, $propertyId, $from, $to),
            };

            $html = View::make('reports.blocks.ga_extended', array_merge($payload, [
                'title' => $title,
                'propertyLabel' => $binding->external_resource_label ?? $propertyId,
            ]))->render();

            return new ReportBlockResult($html, $title);
        } catch (Throwable) {
            return $this->unavailable('Данные GA4 временно недоступны', $title);
        }
    }

    protected function blockTitle(): string
    {
        return 'Google Analytics';
    }

    /** @return array<string, mixed> */
    private function payloadDevices(string $token, string $propertyId, string $from, string $to): array
    {
        $rows = $this->analytics->fetchDevices($token, $propertyId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Устройство', 'Сессии', 'Пользователи'],
            'columns' => ['label', 'sessions', 'users'],
            'chartType' => 'donut_bars',
            'valueKey' => 'sessions',
        ];
    }

    /** @return array<string, mixed> */
    private function payloadGeo(string $token, string $propertyId, string $from, string $to): array
    {
        $rows = $this->analytics->fetchGeo($token, $propertyId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Страна', 'Сессии', 'Пользователи'],
            'columns' => ['label', 'sessions', 'users'],
            'chartType' => 'bars',
            'valueKey' => 'sessions',
        ];
    }

    /** @return array<string, mixed> */
    private function payloadLandingPages(string $token, string $propertyId, string $from, string $to): array
    {
        $rows = $this->analytics->fetchLandingPages($token, $propertyId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Страница', 'Сессии', 'Пользователи', 'Вовлечённость, %'],
            'columns' => ['label', 'sessions', 'users', 'engagement_rate'],
            'chartType' => 'bars',
            'valueKey' => 'sessions',
            'formatters' => ['engagement_rate' => 'percent'],
        ];
    }
}
