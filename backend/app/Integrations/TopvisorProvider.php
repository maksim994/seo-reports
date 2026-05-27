<?php

namespace App\Integrations;

use App\Contracts\ApiKeyIntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\User;
use App\Services\TopvisorDataService;
use RuntimeException;

class TopvisorProvider implements ApiKeyIntegrationProviderInterface
{
    public function __construct(private TopvisorDataService $topvisor) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Topvisor;
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
        throw new RuntimeException('Topvisor uses API key authentication.');
    }

    public function exchangeCode(string $code): array
    {
        throw new RuntimeException('Topvisor uses API key authentication.');
    }

    public function connectWithApiKey(User $user, string $userId, string $apiKey): array
    {
        $projects = $this->topvisor->listProjects($userId, $apiKey);
        $count = count($projects);

        return [
            'account_label' => 'Topvisor · User '.$userId.($count > 0 ? " · {$count} проект(ов)" : ''),
            'credentials' => [
                'user_id' => $userId,
                'api_key' => $apiKey,
                'access_token' => $apiKey,
            ],
            'expires_at' => null,
        ];
    }

    public function apiKeyFields(): array
    {
        return ['user_id', 'api_key'];
    }

    public function listResources(Integration $integration): array
    {
        $credentials = $integration->credentials;
        $userId = (string) ($credentials['user_id'] ?? '');
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if ($userId === '' || $apiKey === '') {
            throw new RuntimeException('Topvisor credentials are incomplete.');
        }

        return $this->topvisor->listProjectResources($userId, $apiKey);
    }
}
