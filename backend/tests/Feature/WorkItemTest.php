<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\WorkItemCategory;
use App\Models\Integration;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_crud_work_items_for_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/work-items", [
            'work_date' => '2026-04-15',
            'category' => WorkItemCategory::Seo->value,
            'description' => 'Оптимизировали мета-теги',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Оптимизировали мета-теги');

        $itemId = $response->json('data.id');

        $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}/work-items?from=2026-04-01&to=2026-04-30")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->patchJson("/api/projects/{$project->id}/work-items/{$itemId}", [
                'description' => 'Обновили мета-теги и заголовки',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Обновили мета-теги и заголовки');

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}/work-items/{$itemId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('work_items', ['id' => $itemId]);
    }

    public function test_user_cannot_manage_foreign_project_work_items(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $item = WorkItem::create([
            'project_id' => $project->id,
            'work_date' => '2026-04-01',
            'category' => WorkItemCategory::Content,
            'description' => 'Статья',
        ]);

        $this->actingAs($other)
            ->postJson("/api/projects/{$project->id}/work-items", [
                'work_date' => '2026-04-02',
                'category' => WorkItemCategory::Seo->value,
                'description' => 'Hack',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->deleteJson("/api/projects/{$project->id}/work-items/{$item->id}")
            ->assertForbidden();
    }
}
