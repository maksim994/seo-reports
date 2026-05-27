<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;

class YandexWebmasterProvider extends YandexOAuthProvider
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::YandexWebmaster;
    }

    protected function configKey(): string
    {
        return 'yandex_webmaster';
    }

    protected function oauthScopes(): array
    {
        return config('integrations.providers.yandex_webmaster.scopes', [
            'webmaster:verify',
            'webmaster:hostinfo',
        ]);
    }

    public function listResources(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return [];
        }

        $user = $this->yandexGet($token, 'https://api.webmaster.yandex.net/v4/user');
        $userId = $user['user_id'] ?? null;
        if (! $userId) {
            return [];
        }

        $data = $this->yandexGet($token, "https://api.webmaster.yandex.net/v4/user/{$userId}/hosts");

        return collect($data['hosts'] ?? [])
            ->map(fn (array $host) => [
                'id' => (string) ($host['host_id'] ?? ''),
                'label' => $host['unicode_host_url'] ?? $host['ascii_host_url'] ?? ($host['host_id'] ?? ''),
                'meta' => [
                    'verified' => $host['verified'] ?? null,
                ],
            ])
            ->filter(fn (array $item) => $item['id'] !== '')
            ->values()
            ->all();
    }
}
