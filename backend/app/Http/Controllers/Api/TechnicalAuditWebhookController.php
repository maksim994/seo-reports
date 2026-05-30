<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TechnicalAuditJob;
use App\Services\TechnicalAuditActivityLogger;
use App\Services\TechnicalAuditDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TechnicalAuditWebhookController extends Controller
{
    public function store(
        Request $request,
        string $token,
        TechnicalAuditDeliveryService $delivery,
        TechnicalAuditActivityLogger $logger,
    ): JsonResponse {
        $job = TechnicalAuditJob::query()->where('webhook_token', $token)->first();
        if (! $job) {
            return response()->json(['message' => 'Unknown webhook token.'], 404);
        }

        $authHeader = (string) $request->header('Authorization', '');
        if ($authHeader !== 'Bearer '.$job->webhook_token) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'JSON body is required.'], 422);
        }

        if ($job->files()->exists()) {
            return response()->json(['message' => 'Already processed.', 'id' => $job->id]);
        }

        try {
            $logger->success($job, 'Webhook получил JSON-отчёт от Cloud Agent.');
            $delivery->deliver($job, $payload);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Audit received.',
            'id' => $job->id,
        ]);
    }
}
