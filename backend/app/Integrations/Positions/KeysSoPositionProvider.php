<?php

namespace App\Integrations\Positions;

use App\Contracts\PositionProviderInterface;
use App\DataTransferObjects\PositionBinding;
use App\Enums\IntegrationProvider;
use App\Services\KeysSoDataService;

class KeysSoPositionProvider implements PositionProviderInterface
{
    public function __construct(private KeysSoDataService $keysSo) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::KeysSo;
    }

    public function parseResourceId(string $resourceId): array
    {
        $parsed = $this->keysSo->parseBindingResourceId($resourceId);

        return [
            'project_id' => $parsed['project_id'],
            'region_index' => $parsed['region_id'],
        ];
    }

    public function resolveRegionIndex(PositionBinding $binding, ?int $preferred = null): int
    {
        $settings = $this->resolveSearchSettings($binding);

        return $settings['regionId'];
    }

    public function fetchSummary(PositionBinding $binding, string $dateFrom, string $dateTo): array
    {
        $parsed = $this->keysSo->parseBindingResourceId($binding->resourceId);
        $searchSettings = $this->resolveSearchSettings($binding);

        return $this->keysSo->fetchSummary(
            $binding->apiKey,
            $parsed['project_id'],
            $searchSettings,
            $dateFrom,
            $dateTo,
        );
    }

    public function fetchPositionsTable(
        PositionBinding $binding,
        string $dateFrom,
        string $dateTo,
        int $limit = 50,
    ): array {
        $parsed = $this->keysSo->parseBindingResourceId($binding->resourceId);
        $searchSettings = $this->resolveSearchSettings($binding);
        $entity = $this->keysSo->fetchProjectEntity($binding->apiKey, $parsed['project_id']);
        $trackingDomain = (string) ($entity['trackingItem'] ?? $entity['tracking_item'] ?? '');

        return $this->keysSo->fetchPositionsTable(
            $binding->apiKey,
            $parsed['project_id'],
            $searchSettings,
            $dateFrom,
            $dateTo,
            $limit,
            $trackingDomain !== '' ? $trackingDomain : null,
        );
    }

    /** @return array{regionId: int, engine: int} */
    private function resolveSearchSettings(PositionBinding $binding): array
    {
        $parsed = $this->keysSo->parseBindingResourceId($binding->resourceId);
        $preferredRegion = $binding->config['region_id'] ?? $parsed['region_id'];
        $preferredEngine = $binding->config['engine'] ?? $parsed['engine'];

        if ($preferredRegion !== null) {
            return [
                'regionId' => (int) $preferredRegion,
                'engine' => (int) ($preferredEngine ?? 0),
            ];
        }

        $entity = $this->keysSo->fetchProjectEntity(
            $binding->apiKey,
            $parsed['project_id'],
        );

        $searchSettings = [];
        foreach ($entity['searchSettings'] ?? [] as $setting) {
            if (! is_array($setting)) {
                continue;
            }

            $searchSettings[] = [
                'region_id' => (int) ($setting['regionId'] ?? $setting['region_id'] ?? 0),
                'search_engine' => (int) ($setting['engine'] ?? $setting['search_engine'] ?? 0),
                'engine_name' => (string) ($setting['regionName'] ?? $setting['region_name'] ?? ''),
                'region_name' => (string) ($setting['regionName'] ?? $setting['region_name'] ?? ''),
            ];
        }

        return $this->keysSo->resolveSearchSettings(
            $searchSettings,
            is_numeric($preferredRegion) ? (int) $preferredRegion : null,
            is_numeric($preferredEngine) ? (int) $preferredEngine : null,
        );
    }
}
