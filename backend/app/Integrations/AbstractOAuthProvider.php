<?php

namespace App\Integrations;

use App\Contracts\IntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\User;
use RuntimeException;

abstract class AbstractOAuthProvider implements IntegrationProviderInterface
{
    abstract protected function configKey(): string;

    public function authType(): string
    {
        return 'oauth';
    }

    public function isConfigured(): bool
    {
        $config = config("integrations.providers.{$this->configKey()}");

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function getAuthorizationUrl(User $user, string $state): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OAuth credentials are not configured.');
        }

        return [
            'redirect_url' => $this->buildAuthorizationUrl($state),
        ];
    }

    abstract protected function buildAuthorizationUrl(string $state): string;

    public function exchangeCode(string $code): array
    {
        throw new RuntimeException('OAuth token exchange is not implemented yet.');
    }

    public function listResources(Integration $integration): array
    {
        return [];
    }
}
