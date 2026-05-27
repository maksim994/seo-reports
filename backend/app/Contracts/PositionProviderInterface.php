<?php

namespace App\Contracts;

use App\DataTransferObjects\PositionBinding;
use App\Enums\IntegrationProvider;

interface PositionProviderInterface
{
    public function provider(): IntegrationProvider;

    /** @return array{project_id: int, region_index: int|null} */
    public function parseResourceId(string $resourceId): array;

    public function resolveRegionIndex(PositionBinding $binding, ?int $preferred = null): int;

    /**
     * @return array{
     *     visibility: float|null,
     *     visibility_dynamic: float|null,
     *     tops: array<string, int>,
     *     avg: float|null
     * }
     */
    public function fetchSummary(PositionBinding $binding, string $dateFrom, string $dateTo): array;

    /** @return list<array{keyword: string, position: float|null, previous: float|null, delta: float|null}> */
    public function fetchPositionsTable(
        PositionBinding $binding,
        string $dateFrom,
        string $dateTo,
        int $limit = 50,
    ): array;
}
