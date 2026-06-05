<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPageGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_project_page_groups(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}/page-groups", [
            'groups' => [
                ['id' => 'info', 'label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
                ['id' => 'catalog', 'label' => 'Каталог', 'pattern' => '^/catalog/', 'enabled' => true],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.groups.0.label', 'Инфо раздел')
            ->assertJsonPath('data.groups.1.pattern', '^/catalog/');

        $project->refresh();
        $this->assertSame('Каталог', $project->settings['page_groups'][1]['label']);
    }

    public function test_invalid_regex_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->putJson("/api/projects/{$project->id}/page-groups", [
            'groups' => [
                ['label' => 'Broken', 'pattern' => '[', 'enabled' => true],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('groups.0.pattern');
    }

    public function test_user_cannot_update_other_users_page_groups(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->putJson("/api/projects/{$project->id}/page-groups", [
            'groups' => [],
        ])->assertForbidden();
    }
}
