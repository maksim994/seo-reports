<?php

namespace App\Services;

use App\Contracts\IntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Integrations\GoogleAnalyticsProvider;
use App\Integrations\GoogleSearchConsoleProvider;
use App\Integrations\TopvisorProvider;
use App\Integrations\YandexMetrikaProvider;
use App\Integrations\YandexWebmasterProvider;
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
            new GoogleSearchConsoleProvider,
            new TopvisorProvider(app(TopvisorDataService::class)),
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

    /** @return list<array{provider: string, label: string, description: string, icon: string, configured: bool, auth_type: string}> */
    public function catalog(): array
    {
        return array_map(function (IntegrationProviderInterface $impl) {
            $provider = $impl->provider();

            return [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
                'icon' => $provider->icon(),
                'configured' => $impl->isConfigured(),
                'auth_type' => $impl->authType(),
            ];
        }, array_values($this->providers));
    }
}
