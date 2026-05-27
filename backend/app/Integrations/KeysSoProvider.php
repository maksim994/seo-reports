<?php

namespace App\Integrations;

use App\Contracts\ApiKeyIntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\User;
use App\Services\KeysSoDataService;
use RuntimeException;

class KeysSoProvider implements ApiKeyIntegrationProviderInterface
{
    public function __construct(private KeysSoDataService $keysSo) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::KeysSo;
    }

    public function authType(): string
    {
        return 'api_key';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getAuthorizationUrl(User $user, string $state): array
    {
        throw new RuntimeException('Keys.so uses API token authentication.');
    }

    public function exchangeCode(string $code): array
    {
        throw new RuntimeException('Keys.so uses API token authentication.');
    }

    public function connectWithApiKey(User $user, string $userId, string $apiKey): array
    {
        $token = trim($apiKey);
        if ($token === '') {
            throw new RuntimeException('API-токен не указан.');
        }

        $projects = $this->keysSo->listMonitoringProjects($token);
        $count = count($projects);

        return [
            'account_label' => 'Keys.so'.($count > 0 ? " · {$count} проект(ов) мониторинга" : ''),
            'credentials' => [
                'api_token' => $token,
                'access_token' => $token,
            ],
            'expires_at' => null,
        ];
    }

    public function apiKeyFields(): array
    {
        return ['api_key'];
    }

    public function listResources(Integration $integration): array
    {
        $token = (string) ($integration->credentials['api_token'] ?? $integration->credentials['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Keys.so credentials are incomplete.');
        }

        return $this->keysSo->listProjectResources($token);
    }
}
