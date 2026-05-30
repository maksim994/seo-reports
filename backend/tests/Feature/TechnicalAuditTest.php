<?php

namespace Tests\Feature;

use App\Enums\TechnicalAuditJobStatus;
use App\Jobs\RunTechnicalAuditJob;
use App\Models\Project;
use App\Models\TechnicalAuditJob;
use App\Models\User;
use App\Services\TechnicalAuditDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_technical_audit_when_cursor_configured(): void
    {
        Queue::fake();
        config(['technical_audit.cursor_api_key' => 'cursor_test_key']);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['domain' => 'example.com']);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/technical-audits", [
            'site_url' => 'https://example.com',
            'crawl_depth' => 'light',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.site_url', 'https://example.com/');

        $this->assertDatabaseHas('technical_audit_jobs', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => TechnicalAuditJobStatus::Queued->value,
        ]);

        Queue::assertPushed(RunTechnicalAuditJob::class);
    }

    public function test_start_returns_service_unavailable_without_cursor_key(): void
    {
        config(['technical_audit.cursor_api_key' => null]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/technical-audits", [
            'site_url' => 'https://example.com',
        ]);

        $response->assertStatus(503);
    }

    public function test_webhook_stores_audit_files(): void
    {
        Storage::fake('local');
        config(['technical_audit.storage_disk' => 'local']);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $job = TechnicalAuditJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => TechnicalAuditJobStatus::Running,
            'site_url' => 'https://example.com/',
            'site_name' => 'Example',
            'crawl_depth' => 'light',
            'lang' => 'ru',
        ]);

        $payload = [
            'site_url' => 'https://example.com/',
            'site_name' => 'Example',
            'audit_date' => '2026-05-28',
            'totals' => ['critical' => 1, 'warning' => 2, 'ok' => 3],
            'checks' => [
                [
                    'id' => 'main_mirror',
                    'title' => 'Главное зеркало',
                    'status' => 'critical',
                    'finding' => 'Неверный редирект',
                    'evidence' => ['curl -I https://example.com'],
                ],
            ],
            'top_priorities' => ['Исправить зеркало'],
        ];

        $response = $this->postJson(
            '/api/webhooks/technical-audits/'.$job->webhook_token,
            $payload,
            ['Authorization' => 'Bearer '.$job->webhook_token],
        );

        $response->assertOk()->assertJsonPath('id', $job->id);

        $job->refresh();
        $this->assertSame(TechnicalAuditJobStatus::Done, $job->status);
        $this->assertGreaterThanOrEqual(2, $job->files->count());
        $this->assertTrue($job->files()->where('format', 'json')->exists());
        $this->assertTrue($job->files()->where('format', 'md')->exists());
        $this->assertNotEmpty($job->activity_log);
    }

    public function test_delivery_service_builds_markdown(): void
    {
        $service = app(TechnicalAuditDeliveryService::class);

        $markdown = $service->buildMarkdown([
            'site_url' => 'https://example.com/',
            'site_name' => 'Example',
            'audit_date' => '2026-05-28',
            'totals' => ['critical' => 1, 'warning' => 0, 'ok' => 0],
            'checks' => [
                [
                    'title' => 'Robots.txt',
                    'status' => 'warning',
                    'finding' => 'Есть замечания',
                ],
            ],
        ]);

        $this->assertStringContainsString('# Технический аудит сайта', $markdown);
        $this->assertStringContainsString('Robots.txt', $markdown);
    }

    public function test_launch_job_calls_cursor_api(): void
    {
        Queue::fake();
        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'bc-test-agent', 'latestRunId' => 'run-test-1'],
                'run' => ['id' => 'run-test-1', 'status' => 'RUNNING'],
            ], 201),
        ]);

        config(['technical_audit.cursor_api_key' => 'cursor_test_key']);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $job = TechnicalAuditJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => TechnicalAuditJobStatus::Queued,
            'site_url' => 'https://example.com/',
            'crawl_depth' => 'light',
            'lang' => 'ru',
        ]);

        (new RunTechnicalAuditJob($job))->handle(
            app(\App\Services\CursorAgentClient::class),
            app(\App\Services\TechnicalAuditPromptBuilder::class),
            app(\App\Services\TechnicalAuditActivityLogger::class),
        );

        $job->refresh();
        $this->assertSame(TechnicalAuditJobStatus::Running, $job->status, $job->error_message ?? '');
        $this->assertSame('bc-test-agent', $job->cursor_agent_id);

        Queue::assertPushed(\App\Jobs\PollTechnicalAuditJob::class);
    }
}
