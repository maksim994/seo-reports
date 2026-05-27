<?php

namespace App\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;

class YandexMetrikaProvider extends YandexOAuthProvider
{
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::YandexMetrika;
    }

    protected function configKey(): string
    {
        return 'yandex_metrika';
    }

    protected function oauthScopes(): array
    {
        return config('integrations.providers.yandex_metrika.scopes', ['metrika:read']);
    }

    public function listResources(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return [];
        }

        $data = $this->yandexGet($token, 'https://api-metrika.yandex.net/management/v1/counters');

        return collect($data['counters'] ?? [])
            ->map(function (array $counter) {
                $id = (string) ($counter['id'] ?? '');
                if ($id === '') {
                    return null;
                }

                $name = trim((string) ($counter['name'] ?? ''));
                $site = $this->extractCounterSite($counter);

                return [
                    'id' => $id,
                    'label' => $this->formatCounterLabel($id, $name, $site),
                    'meta' => [
                        'counter_id' => (int) $counter['id'],
                        'name' => $name !== '' ? $name : null,
                        'site' => $site,
                        'status' => $counter['status'] ?? null,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function extractCounterSite(array $counter): ?string
    {
        $site2 = $counter['site2'] ?? null;
        if (is_array($site2)) {
            foreach (['domain', 'site'] as $key) {
                $value = trim((string) ($site2[$key] ?? ''));
                if ($value !== '') {
                    return $this->normalizeSite($value);
                }
            }
        }

        $legacySite = trim((string) ($counter['site'] ?? ''));
        if ($legacySite !== '') {
            return $this->normalizeSite($legacySite);
        }

        foreach ($counter['mirrors2'] ?? [] as $mirror) {
            if (! is_array($mirror)) {
                continue;
            }
            foreach (['domain', 'site'] as $key) {
                $value = trim((string) ($mirror[$key] ?? ''));
                if ($value !== '') {
                    return $this->normalizeSite($value);
                }
            }
        }

        return null;
    }

    private function normalizeSite(string $site): string
    {
        return rtrim(preg_replace('#^https?://#i', '', $site) ?? $site, '/');
    }

    private function formatCounterLabel(string $id, string $name, ?string $site): string
    {
        $title = $name !== '' ? $name : ($site ?? 'Без названия');

        if ($site !== null && $name !== '' && $this->normalizeSite($name) !== $this->normalizeSite($site)) {
            return "#{$id} — {$title} — {$site}";
        }

        if ($site !== null && $name === '') {
            return "#{$id} — {$site}";
        }

        return "#{$id} — {$title}";
    }
}
