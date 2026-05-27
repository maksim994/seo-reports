<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_default_template_on_register(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'template@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'template@example.com')->first();
        $this->assertNotNull($user);

        $template = ReportTemplate::where('user_id', $user->id)->first();
        $this->assertNotNull($template);
        $this->assertTrue($template->is_default);
        $this->assertGreaterThan(0, $template->blocks()->count());
    }

    public function test_user_can_crud_templates(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/templates', [
            'name' => 'My Template',
            'description' => 'Test',
            'blocks' => [
                ['block_type' => 'title_page'],
                ['block_type' => 'metrika_overview'],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'My Template')
            ->assertJsonCount(2, 'data.blocks');

        $id = $create->json('data.id');

        $this->actingAs($user)->getJson('/api/templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->putJson("/api/templates/{$id}", [
            'name' => 'Updated',
            'blocks' => [
                ['block_type' => 'title_page'],
                ['block_type' => 'text_block'],
                ['block_type' => 'ga_overview'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonCount(3, 'data.blocks');

        $this->actingAs($user)->deleteJson("/api/templates/{$id}")
            ->assertNoContent();
    }

    public function test_blocks_catalog_is_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/report-blocks/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'blocks' => [['block_type', 'label', 'category']],
                    'categories',
                ],
            ]);
    }

    public function test_user_can_upload_and_delete_template_logo(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/templates', [
            'name' => 'Logo Template',
            'blocks' => [['block_type' => 'title_page']],
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/templates/{$id}/logo", [
                'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 80),
            ])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => str_contains($url, "/api/templates/{$id}/logo"));

        $this->actingAs($user)
            ->get("/api/templates/{$id}/logo")
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson("/api/templates/{$id}/logo")
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);
    }
}
