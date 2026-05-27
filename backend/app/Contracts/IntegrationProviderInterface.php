<?php

namespace App\Contracts;

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\User;

interface IntegrationProviderInterface
{
    public function provider(): IntegrationProvider;

    public function authType(): string;

    public function isConfigured(): bool;

    /** @return array{redirect_url: string} */
    public function getAuthorizationUrl(User $user, string $state): array;

    /** @return array{account_label: string, credentials: array<string, mixed>, expires_at: ?\DateTimeInterface} */
    public function exchangeCode(string $code): array;

    /** @return list<array{id: string, label: string, meta?: array<string, mixed>}> */
    public function listResources(Integration $integration): array;
}
