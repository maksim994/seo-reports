<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\ReportJobStatus;
use App\Models\Integration;
use App\Models\Project;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_report_with_metrika_block(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Test Site', 'domain' => 'example.com']);
        $template = ReportTemplate::create([
            'user_id' => $user->id,
            'name' => 'Test Template',
        ]);
        $template->blocks()->create(['block_type' => 'title_page', 'sort_order' => 0]);
        $template->blocks()->create(['block_type' => 'metrika_overview', 'sort_order' => 1]);

        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'metrika-token'],
            'status' => 'active',
        ]);

        $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => '12345',
            'external_resource_label' => 'example.com',
        ]);

        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'totals' => [1000, 800, 25.5, 120],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/reports", [
            'report_template_id' => $template->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $response->assertStatus(202)->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(\App\Jobs\GenerateReportJob::class);
    }

    public function test_report_generation_completes_and_creates_files(): void
    {
        Storage::fake('local');
        config(['reports.storage_disk' => 'local']);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);
        $template->blocks()->create(['block_type' => 'title_page', 'sort_order' => 0]);
        $template->blocks()->create(['block_type' => 'text_block', 'sort_order' => 1, 'settings' => ['content' => 'Hello']]);

        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        app(\App\Services\ReportGeneratorService::class)->generate($job);

        $job->refresh();
        $this->assertSame(ReportJobStatus::Done, $job->status);
        $this->assertCount(2, $job->files);

        $htmlFile = $job->files->firstWhere('format', 'html');
        $html = Storage::disk('local')->get($htmlFile->path);
        $this->assertStringContainsString('apex-chart', $html);
        $this->assertStringContainsString('section-card', $html);

        $this->actingAs($user)
            ->get("/api/reports/{$job->id}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function test_user_can_list_recent_project_reports(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);

        foreach (range(1, 12) as $day) {
            \App\Models\ReportJob::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'report_template_id' => $template->id,
                'status' => ReportJobStatus::Done,
                'period_start' => sprintf('2026-04-%02d', min($day, 28)),
                'period_end' => sprintf('2026-04-%02d', min($day + 1, 28)),
            ]);
        }

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/reports");

        $response->assertOk()->assertJsonCount(10, 'data');
    }

    public function test_user_can_delete_own_report(): void
    {
        Storage::fake('local');
        config(['reports.storage_disk' => 'local']);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);

        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Done,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $path = 'reports/'.$job->id.'/report.html';
        Storage::disk('local')->put($path, '<html></html>');
        $job->files()->create(['format' => 'html', 'path' => $path, 'size' => 13]);

        $this->actingAs($user)
            ->deleteJson("/api/reports/{$job->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('report_jobs', ['id' => $job->id]);
        Storage::disk('local')->assertMissing($path);

        $foreignJob = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Done,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $this->actingAs($other)
            ->deleteJson("/api/reports/{$foreignJob->id}")
            ->assertForbidden();
    }
}
