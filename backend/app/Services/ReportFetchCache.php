<?php

namespace App\Services;

class ReportFetchCache
{
    /** @var array<string, mixed> */
    private array $store = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function get(string $key): mixed
    {
        return $this->store[$key];
    }

    public function remember(string $key, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        return $this->store[$key] = $callback();
    }

    public function clear(): void
    {
        $this->store = [];
    }
}
