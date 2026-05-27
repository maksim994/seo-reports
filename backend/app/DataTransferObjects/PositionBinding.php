<?php

namespace App\DataTransferObjects;

class PositionBinding
{
    public function __construct(
        public string $userId,
        public string $apiKey,
        public string $resourceId,
        public ?string $resourceLabel,
        /** @var array<string, mixed>|null */
        public ?array $config,
    ) {}
}
