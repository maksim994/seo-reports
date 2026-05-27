<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class GoogleOAuthProvider extends AbstractOAuthProvider
{
    protected function buildAuthorizationUrl(string $state): string
    {
        $config = config("integrations.providers.{$this->configKey()}");
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => implode(' ', $config['scopes'] ?? []),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$params;
    }

    public function exchangeCode(string $code): array
    {
        $config = config("integrations.providers.{$this->configKey()}");

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google token exchange failed: '.$response->json('error_description', $response->body())
            );
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Google token response missing access_token');
        }

        $expiresAt = isset($data['expires_in'])
            ? now()->addSeconds((int) $data['expires_in'])
            : null;

        return [
            'account_label' => $this->fetchAccountLabel($accessToken),
            'credentials' => [
                'access_token' => $accessToken,
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $data['expires_in'] ?? null,
            ],
            'expires_at' => $expiresAt,
        ];
    }

    protected function fetchAccountLabel(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            return 'Google account';
        }

        $info = $response->json();

        return $info['email'] ?? $info['name'] ?? 'Google account';
    }

    protected function googleGet(string $accessToken, string $url): array
    {
        $response = Http::withToken($accessToken)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Google API request failed: '.$response->body());
        }

        return $response->json();
    }
}
