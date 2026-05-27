<?php

namespace App\Integrations\Positions;

use App\Contracts\PositionProviderInterface;
use App\DataTransferObjects\PositionBinding;
use App\Enums\IntegrationProvider;
use App\Services\TopvisorDataService;

class TopvisorPositionProvider implements PositionProviderInterface
{
    public function __construct(private TopvisorDataService $topvisor) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Topvisor;
    }

    public function parseResourceId(string $resourceId): array
    {
        return $this->topvisor->parseBindingResourceId($resourceId);
    }

    public function resolveRegionIndex(PositionBinding $binding, ?int $preferred = null): int
    {
        $parsed = $this->parseResourceId($binding->resourceId);
        $regionIndex = $binding->config['region_index'] ?? $parsed['region_index'];

        return $this->topvisor->resolveRegionIndex(
            $binding->userId,
            $binding->apiKey,
            $parsed['project_id'],
            is_numeric($regionIndex) ? (int) $regionIndex : $preferred,
        );
    }

    public function fetchSummary(PositionBinding $binding, string $dateFrom, string $dateTo): array
    {
        $parsed = $this->parseResourceId($binding->resourceId);
        $regionIndex = $this->resolveRegionIndex($binding, $parsed['region_index']);

        return $this->topvisor->fetchSummary(
            $binding->userId,
            $binding->apiKey,
            $parsed['project_id'],
            $regionIndex,
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
        $parsed = $this->parseResourceId($binding->resourceId);
        $regionIndex = $this->resolveRegionIndex($binding, $parsed['region_index']);

        return $this->topvisor->fetchPositionsTable(
            $binding->userId,
            $binding->apiKey,
            $parsed['project_id'],
            $regionIndex,
            $dateFrom,
            $dateTo,
            $limit,
        );
    }
}
