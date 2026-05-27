<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_integration_providers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/integrations/providers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['provider', 'label', 'description', 'configured'],
                ],
            ]);
    }

    public function test_connect_returns_503_when_oauth_not_configured(): void
    {
        config([
            'integrations.providers.yandex_metrika.client_id' => null,
            'integrations.providers.yandex_metrika.client_secret' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/integrations/yandex_metrika/connect')
            ->assertStatus(503);
    }

    public function test_user_can_list_their_integrations(): void
    {
        $user = User::factory()->create();
        Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'test'],
            'status' => 'active',
            'account_label' => 'Test account',
        ]);

        $this->actingAs($user)
            ->getJson('/api/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'yandex_metrika')
            ->assertJsonPath('data.0.account_label', 'Test account');
    }

    public function test_user_can_disconnect_integration(): void
    {
        $user = User::factory()->create();
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::GoogleAnalytics,
            'credentials' => ['access_token' => 'test'],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/integrations/{$integration->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id]);
    }
}
