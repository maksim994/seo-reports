<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\KeysSoDataService;
use App\Support\KeysSoBlockSettings;
use Illuminate\Support\Facades\View;
use Throwable;

class KeysSoExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'keys_so_site_queries' => 'Keys.so: запросы сайта',
        'keys_so_links' => 'Keys.so: ссылки',
        'keys_so_ai_mentions' => 'Keys.so: упоминания в ИИ-ответах',
    ];

    public function __construct(private KeysSoDataService $keysSo) {}

    public function type(): string
    {
        return 'keys_so_site_queries';
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
        $title = self::TITLES[$blockType] ?? 'Keys.so';

        $token = $this->resolveToken($context);
        if (! $token) {
            return $this->unavailable('Подключите Keys.so в разделе «Интеграции» (API-токен)', $title);
        }

        $domain = $this->keysSo->domainFromProject($context->project->domain);
        if (! $domain) {
            return $this->unavailable('Укажите домен проекта в настройках', $title);
        }

        $base = KeysSoBlockSettings::base($settings);
        $limit = KeysSoBlockSettings::limit($settings);

        try {
            $viewData = match ($blockType) {
                'keys_so_links' => [
                    'view' => 'reports.blocks.keys_so_links',
                    'data' => [
                        'summary' => $this->keysSo->fetchLinksDashboard($token, $domain),
                    ],
                ],
                'keys_so_ai_mentions' => [
                    'view' => 'reports.blocks.keys_so_ai_mentions',
                    'data' => [
                        'rows' => $this->keysSo->fetchDashboardAiMentions($token, $domain, $limit),
                    ],
                ],
                default => [
                    'view' => 'reports.blocks.keys_so_site_queries',
                    'data' => [
                        'summary' => $this->keysSo->fetchSiteQueriesDashboard($token, $domain, $base, $limit),
                    ],
                ],
            };

            $html = View::make($viewData['view'], array_merge([
                'title' => $title,
                'domainLabel' => $domain,
                'baseLabel' => KeysSoBlockSettings::BASE_OPTIONS[$base] ?? $base,
            ], $viewData['data']))->render();

            return new ReportBlockResult($html, $title);
        } catch (Throwable $exception) {
            return $this->unavailableFromThrowable(
                $exception,
                'Данные Keys.so временно недоступны. Проверьте API-токен и тариф.',
                $title,
            );
        }
    }

    protected function blockTitle(): string
    {
        return 'Keys.so';
    }

    private function resolveToken(ReportRenderContext $context): ?string
    {
        $context->project->loadMissing('user.integrations');

        $integration = $context->project->user->integrations
            ->first(fn (Integration $item) => $item->provider === IntegrationProvider::KeysSo
                && $item->status === IntegrationStatus::Active);

        if (! $integration) {
            return null;
        }

        $token = (string) ($integration->credentials['api_token']
            ?? $integration->credentials['access_token']
            ?? '');

        return $token !== '' ? $token : null;
    }
}
