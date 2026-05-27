<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;

class GoogleSearchConsoleProvider extends GoogleOAuthProvider
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::GoogleSearchConsole;
    }

    protected function configKey(): string
    {
        return 'google_search_console';
    }

    public function listResources(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return [];
        }

        $data = $this->googleGet($token, 'https://www.googleapis.com/webmasters/v3/sites');

        return collect($data['siteEntry'] ?? [])
            ->map(fn (array $site) => [
                'id' => (string) ($site['siteUrl'] ?? ''),
                'label' => $site['siteUrl'] ?? '',
                'meta' => [
                    'permission_level' => $site['permissionLevel'] ?? null,
                ],
            ])
            ->filter(fn (array $item) => $item['id'] !== '')
            ->values()
            ->all();
    }
}
