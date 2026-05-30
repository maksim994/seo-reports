<?php

namespace App\Services;

use App\Models\TechnicalAuditJob;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CursorAgentClient
{
    public function isConfigured(): bool
    {
        return filled(config('technical_audit.cursor_api_key'));
    }

    /**
     * @return array{agent: array<string, mixed>, run: array<string, mixed>}
     */
    public function createAgent(string $prompt): array
    {
        $response = $this->request('post', '/v1/agents', [
            'prompt' => [
                'text' => $prompt,
            ],
            'model' => [
                'id' => (string) config('technical_audit.cursor_model'),
            ],
            'repos' => [
                [
                    'url' => (string) config('technical_audit.cursor_repo_url'),
                    'startingRef' => (string) config('technical_audit.cursor_repo_ref'),
                ],
            ],
            'skipReviewerRequest' => true,
        ]);

        if (! isset($response['agent']['id'], $response['run']['id'])) {
            throw new RuntimeException('Cursor API returned an unexpected create-agent response.');
        }

        return [
            'agent' => $response['agent'],
            'run' => $response['run'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgent(string $agentId): array
    {
        return $this->request('get', '/v1/agents/'.$agentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRun(string $agentId, string $runId): array
    {
        return $this->request('get', '/v1/agents/'.$agentId.'/runs/'.$runId);
    }

    /**
     * @return array{run: array<string, mixed>}
     */
    public function createFollowUpRun(string $agentId, string $prompt): array
    {
        $response = $this->request('post', '/v1/agents/'.$agentId.'/runs', [
            'prompt' => [
                'text' => $prompt,
            ],
        ]);

        if (! isset($response['run']['id'])) {
            throw new RuntimeException('Cursor API returned an unexpected follow-up response.');
        }

        return ['run' => $response['run']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $apiKey = (string) config('technical_audit.cursor_api_key');
        if ($apiKey === '') {
            throw new RuntimeException('CURSOR_API_KEY is not configured.');
        }

        $baseUrl = rtrim((string) config('technical_audit.cursor_api_base_url'), '/');
        $pending = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->timeout(60);

        $response = match ($method) {
            'post' => $pending->post($baseUrl.$path, $payload),
            default => $pending->get($baseUrl.$path),
        };

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new RuntimeException('Cursor API error ('.$response->status().'): '.$message);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }
}
