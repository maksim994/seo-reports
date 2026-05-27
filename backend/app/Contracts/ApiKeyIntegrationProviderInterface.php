<?php

namespace App\Contracts;

use App\Models\User;

interface ApiKeyIntegrationProviderInterface extends IntegrationProviderInterface
{
    /** @return array{account_label: string, credentials: array<string, mixed>, expires_at: ?\DateTimeInterface} */
    public function connectWithApiKey(User $user, string $userId, string $apiKey): array;
}
