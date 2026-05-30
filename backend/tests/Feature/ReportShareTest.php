<?php

namespace Tests\Feature;

use App\Enums\ReportJobStatus;
use App\Models\Project;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_disable_public_share(): void
    {
        Storage::fake('local');
        config(['reports.storage_disk' => 'local']);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);

        $job = ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Done,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'finished_at' => now(),
        ]);

        $path = 'reports/'.$job->id.'/report.html';
        Storage::disk('local')->put($path, '<html><body>Shared report</body></html>');
        $job->files()->create(['format' => 'html', 'path' => $path, 'size' => 40]);

        $enable = $this->actingAs($user)->postJson("/api/reports/{$job->id}/share");
        $enable->assertOk()
            ->assertJsonPath('data.share_enabled', true);

        $token = $enable->json('data.share_token');
        $this->assertNotEmpty($token);

        $this->getJson("/api/public/reports/{$token}")
            ->assertOk()
            ->assertJsonPath('data.project_name', $project->name);

        $this->get("/api/public/reports/{$token}/preview")
            ->assertOk()
            ->assertSee('Shared report');

        $this->actingAs($user)
            ->deleteJson("/api/reports/{$job->id}/share")
            ->assertOk()
            ->assertJsonPath('data.share_enabled', false);

        $this->getJson("/api/public/reports/{$token}")->assertNotFound();
    }

    public function test_public_share_is_not_available_for_foreign_user(): void
    {
        Storage::fake('local');
        config(['reports.storage_disk' => 'local']);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $template = ReportTemplate::create(['user_id' => $owner->id, 'name' => 'Tpl']);

        $job = ReportJob::create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Done,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'finished_at' => now(),
            'share_enabled' => true,
            'share_token' => 'public-token-123',
        ]);

        $path = 'reports/'.$job->id.'/report.html';
        Storage::disk('local')->put($path, '<html></html>');
        $job->files()->create(['format' => 'html', 'path' => $path, 'size' => 13]);

        $this->actingAs($other)
            ->postJson("/api/reports/{$job->id}/share")
            ->assertForbidden();
    }

    public function test_cannot_share_incomplete_report(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);

        $job = ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $this->actingAs($user)
            ->postJson("/api/reports/{$job->id}/share")
            ->assertStatus(422);
    }
}
