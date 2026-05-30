<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;

class YandexWordstatProvider extends YandexOAuthProvider
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::YandexWordstat;
    }

    protected function configKey(): string
    {
        return 'yandex_wordstat';
    }

    protected function oauthScopes(): array
    {
        return config('integrations.providers.yandex_wordstat.scopes', [
            'wordstat:api',
        ]);
    }

    public function listResources(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return [];
        }

        return [[
            'id' => 'default',
            'label' => 'Яндекс Вордстат',
            'meta' => [
                'account_label' => $integration->account_label,
            ],
        ]];
    }
}
