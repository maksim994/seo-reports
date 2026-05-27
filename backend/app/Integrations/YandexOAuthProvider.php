<?php

namespace App\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class YandexOAuthProvider extends AbstractOAuthProvider
{
    /** @return list<string> */
    abstract protected function oauthScopes(): array;

    protected function buildAuthorizationUrl(string $state): string
    {
        $config = config("integrations.providers.{$this->configKey()}");
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'state' => $state,
            'scope' => implode(' ', $this->oauthScopes()),
        ]);

        return 'https://oauth.yandex.ru/authorize?'.$params;
    }

    public function exchangeCode(string $code): array
    {
        $config = config("integrations.providers.{$this->configKey()}");

        $response = Http::asForm()->post('https://oauth.yandex.ru/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Yandex token exchange failed: '.$response->json('error_description', $response->body())
            );
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Yandex token response missing access_token');
        }

        $expiresAt = isset($data['expires_in'])
            ? now()->addSeconds((int) $data['expires_in'])
            : null;

        return [
            'account_label' => $this->fetchAccountLabel($accessToken),
            'credentials' => [
                'access_token' => $accessToken,
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_type' => $data['token_type'] ?? 'bearer',
                'expires_in' => $data['expires_in'] ?? null,
            ],
            'expires_at' => $expiresAt,
        ];
    }

    protected function fetchAccountLabel(string $accessToken): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get('https://login.yandex.ru/info', ['format' => 'json']);

        if (! $response->successful()) {
            return 'Yandex account';
        }

        $info = $response->json();

        return $info['default_email']
            ?? $info['login']
            ?? $info['display_name']
            ?? 'Yandex account';
    }

    protected function yandexGet(string $accessToken, string $url): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Yandex API request failed: '.$response->body());
        }

        return $response->json();
    }
}
