<?php

namespace App\Services;

use App\Contracts\PositionProviderInterface;
use App\Enums\IntegrationProvider;
use InvalidArgumentException;

class PositionProviderRegistry
{
    /** @param  iterable<PositionProviderInterface>  $providers */
    public function __construct(private iterable $providers) {}

    public function get(IntegrationProvider|string $provider): PositionProviderInterface
    {
        $value = $provider instanceof IntegrationProvider ? $provider->value : $provider;

        foreach ($this->providers as $implementation) {
            if ($implementation->provider()->value === $value) {
                return $implementation;
            }
        }

        throw new InvalidArgumentException("Position provider [{$value}] is not registered.");
    }

    public function has(IntegrationProvider|string $provider): bool
    {
        try {
            $this->get($provider);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
