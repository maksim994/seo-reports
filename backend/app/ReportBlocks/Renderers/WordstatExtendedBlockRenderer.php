<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexWordstatDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Throwable;

class WordstatExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'wordstat_dynamics' => 'Вордстат: динамика спроса',
        'wordstat_top_requests' => 'Вордстат: популярные запросы',
        'wordstat_regions' => 'Вордстат: регионы',
    ];

    public function __construct(private YandexWordstatDataService $wordstat) {}

    public function type(): string
    {
        return 'wordstat_dynamics';
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
        $title = self::TITLES[$blockType] ?? 'Яндекс Вордстат';

        $resolved = $this->resolveBinding($context, IntegrationProvider::YandexWordstat);
        if (! $resolved) {
            return $this->unavailable('Яндекс Вордстат не привязан к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $settings = $this->resolveProjectSettings($blockType, $binding->config ?? []);
        $regionId = $this->resolveRegionId($settings, $binding->config ?? []);
        $endDate = Carbon::parse($context->job->period_end);

        try {
            if ($blockType === 'wordstat_dynamics') {
                $phrases = $this->parsePhrases((string) ($settings['phrases'] ?? ''));
                if ($phrases === []) {
                    return $this->unavailable(
                        'Укажите ключевые фразы в настройках проекта (раздел «Яндекс Вордстат»).',
                        $title,
                    );
                }
            }

            if (in_array($blockType, ['wordstat_top_requests', 'wordstat_regions'], true)) {
                $phrase = trim((string) ($settings['phrase'] ?? ''));
                if ($phrase === '') {
                    return $this->unavailable(
                        'Укажите ключевую фразу в настройках проекта (раздел «Яндекс Вордстат»).',
                        $title,
                    );
                }
            }

            $html = match ($blockType) {
                'wordstat_top_requests' => $this->renderTopRequests($title, $token, $settings, $regionId),
                'wordstat_regions' => $this->renderRegions($title, $token, $settings),
                default => $this->renderDynamics($title, $token, $settings, $regionId, $endDate),
            };

            return new ReportBlockResult($html, $title);
        } catch (Throwable $exception) {
            return $this->unavailableFromThrowable(
                $exception,
                'Данные Вордстата временно недоступны. Проверьте доступ к API и квоту.',
                $title,
            );
        }
    }

    /** @param  array<string, mixed>  $settings */
    private function renderDynamics(string $title, string $token, array $settings, ?int $regionId, Carbon $endDate): string
    {
        $phrases = $this->parsePhrases((string) ($settings['phrases'] ?? ''));
        $period = $this->normalizePeriod((string) ($settings['period'] ?? 'monthly'));
        $lookbackMonths = max(1, min(36, (int) ($settings['lookback_months'] ?? 24)));

        $seriesByPhrase = [];
        foreach ($phrases as $phrase) {
            $seriesByPhrase[] = [
                'phrase' => $phrase,
                'series' => $this->wordstat->fetchDynamicsSeries(
                    $token,
                    $phrase,
                    $period,
                    $endDate,
                    $lookbackMonths,
                    $regionId,
                ),
            ];
        }

        return View::make('reports.blocks.wordstat_dynamics', [
            'title' => $title,
            'periodLabel' => $this->periodLabel($period),
            'lookbackMonths' => $lookbackMonths,
            'regionId' => $regionId,
            'seriesByPhrase' => $seriesByPhrase,
        ])->render();
    }

    /** @param  array<string, mixed>  $settings */
    private function renderTopRequests(string $title, string $token, array $settings, ?int $regionId): string
    {
        $phrase = trim((string) ($settings['phrase'] ?? ''));
        $limit = max(1, min(25, (int) ($settings['limit'] ?? 10)));
        $rows = $this->wordstat->fetchTopRequests($token, $phrase, $regionId, $limit);

        return View::make('reports.blocks.wordstat_top_requests', [
            'title' => $title,
            'phrase' => $phrase,
            'regionId' => $regionId,
            'rows' => $rows,
        ])->render();
    }

    /** @param  array<string, mixed>  $settings */
    private function renderRegions(string $title, string $token, array $settings): string
    {
        $phrase = trim((string) ($settings['phrase'] ?? ''));
        $regionType = $this->normalizeRegionType((string) ($settings['region_type'] ?? 'all'));
        $limit = max(1, min(25, (int) ($settings['limit'] ?? 10)));
        $rows = $this->wordstat->fetchRegions($token, $phrase, $regionType, $limit);

        return View::make('reports.blocks.wordstat_regions', [
            'title' => $title,
            'phrase' => $phrase,
            'regionType' => $regionType,
            'rows' => $rows,
        ])->render();
    }

    /** @param  array<string, mixed>  $config */
    private function resolveProjectSettings(string $blockType, array $config): array
    {
        $wordstat = is_array($config['wordstat'] ?? null) ? $config['wordstat'] : [];

        $section = match ($blockType) {
            'wordstat_top_requests' => is_array($wordstat['top_requests'] ?? null) ? $wordstat['top_requests'] : [],
            'wordstat_regions' => is_array($wordstat['regions'] ?? null) ? $wordstat['regions'] : [],
            default => is_array($wordstat['dynamics'] ?? null) ? $wordstat['dynamics'] : [],
        };

        if (isset($config['region_id']) && ! array_key_exists('region_id', $section)) {
            $section['region_id'] = $config['region_id'];
        }

        return $section;
    }

    /** @param  array<string, mixed>  $config */
    private function resolveRegionId(array $settings, array $config): ?int
    {
        $raw = $settings['region_id'] ?? $config['region_id'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    /** @return list<string> */
    private function parsePhrases(string $raw): array
    {
        return collect(preg_split('/\R/u', $raw) ?: [])
            ->map(fn (string $phrase) => trim($phrase))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['monthly', 'weekly', 'daily'], true) ? $period : 'monthly';
    }

    private function normalizeRegionType(string $regionType): string
    {
        return in_array($regionType, ['all', 'cities', 'regions'], true) ? $regionType : 'all';
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'weekly' => 'по неделям',
            'daily' => 'по дням',
            default => 'по месяцам',
        };
    }

    protected function blockTitle(): string
    {
        return 'Яндекс Вордстат';
    }
}
