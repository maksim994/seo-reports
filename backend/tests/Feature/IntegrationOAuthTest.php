<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_yandex_callback_exchanges_code_and_creates_integration(): void
    {
        config([
            'integrations.providers.yandex_metrika.client_id' => 'test-client',
            'integrations.providers.yandex_metrika.client_secret' => 'test-secret',
            'integrations.providers.yandex_metrika.redirect_uri' => 'http://localhost/api/integrations/yandex_metrika/callback',
        ]);

        Http::fake([
            'oauth.yandex.ru/token' => Http::response([
                'access_token' => 'ya-access-token',
                'refresh_token' => 'ya-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ]),
            'login.yandex.ru/info*' => Http::response([
                'default_email' => 'user@yandex.ru',
                'login' => 'testuser',
            ]),
        ]);

        $user = User::factory()->create();
        $state = 'test-state-token';
        Cache::put('integration_oauth:'.$state, [
            'user_id' => $user->id,
            'provider' => 'yandex_metrika',
        ], 600);

        $this->get('/api/integrations/yandex_metrika/callback?code=auth-code&state='.$state)
            ->assertRedirect();

        $this->assertDatabaseHas('integrations', [
            'user_id' => $user->id,
            'provider' => 'yandex_metrika',
            'account_label' => 'user@yandex.ru',
            'status' => 'active',
        ]);
    }

    public function test_google_callback_exchanges_code_and_creates_integration(): void
    {
        config([
            'integrations.providers.google_analytics.client_id' => 'google-client',
            'integrations.providers.google_analytics.client_secret' => 'google-secret',
            'integrations.providers.google_analytics.redirect_uri' => 'http://localhost/api/integrations/google_analytics/callback',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'email' => 'user@gmail.com',
                'name' => 'Test User',
            ]),
        ]);

        $user = User::factory()->create();
        $state = 'google-state-token';
        Cache::put('integration_oauth:'.$state, [
            'user_id' => $user->id,
            'provider' => 'google_analytics',
        ], 600);

        $this->get('/api/integrations/google_analytics/callback?code=google-code&state='.$state)
            ->assertRedirect();

        $this->assertDatabaseHas('integrations', [
            'user_id' => $user->id,
            'provider' => 'google_analytics',
            'account_label' => 'user@gmail.com',
        ]);
    }
}
