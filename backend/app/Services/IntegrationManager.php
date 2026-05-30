<?php

namespace App\Services;

use App\Contracts\ApiKeyIntegrationProviderInterface;
use App\Contracts\IntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Integrations\GoogleAnalyticsProvider;
use App\Integrations\GoogleSearchConsoleProvider;
use App\Integrations\KeysSoProvider;
use App\Integrations\TopvisorProvider;
use App\Integrations\YandexMetrikaProvider;
use App\Integrations\YandexWebmasterProvider;
use App\Integrations\YandexWordstatProvider;
use App\Services\KeysSoDataService;
use App\Services\TopvisorDataService;
use InvalidArgumentException;

class IntegrationManager
{
    /** @var array<string, IntegrationProviderInterface> */
    private array $providers = [];

    public function __construct()
    {
        foreach ($this->defaultProviders() as $provider) {
            $this->providers[$provider->provider()->value] = $provider;
        }
    }

    /** @return list<IntegrationProviderInterface> */
    private function defaultProviders(): array
    {
        return [
            new YandexMetrikaProvider,
            new GoogleAnalyticsProvider,
            new YandexWebmasterProvider,
            new YandexWordstatProvider,
            new GoogleSearchConsoleProvider,
            new TopvisorProvider(app(TopvisorDataService::class)),
            new KeysSoProvider(app(KeysSoDataService::class)),
        ];
    }

    public function get(IntegrationProvider|string $provider): IntegrationProviderInterface
    {
        $key = $provider instanceof IntegrationProvider ? $provider->value : $provider;

        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("Unknown integration provider: {$key}");
        }

        return $this->providers[$key];
    }

    /** @return list<array{provider: string, label: string, description: string, icon: string, logo_url: string|null, configured: bool, auth_type: string, api_key_fields: list<string>}> */
    public function catalog(): array
    {
        return array_map(function (IntegrationProviderInterface $impl) {
            $provider = $impl->provider();

            return [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
                'icon' => $provider->icon(),
                'logo_url' => config('integrations.logos.'.$provider->value),
                'configured' => $impl->isConfigured(),
                'auth_type' => $impl->authType(),
                'api_key_fields' => $impl instanceof ApiKeyIntegrationProviderInterface
                    ? $impl->apiKeyFields()
                    : [],
            ];
        }, array_values($this->providers));
    }
}
