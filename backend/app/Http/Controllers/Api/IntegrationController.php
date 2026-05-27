<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ApiKeyIntegrationProviderInterface;
use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\IntegrationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class IntegrationController extends Controller
{
    public function __construct(private IntegrationManager $manager) {}

    public function providers(): JsonResponse
    {
        return response()->json(['data' => $this->manager->catalog()]);
    }

    public function index(Request $request): JsonResponse
    {
        $integrations = $request->user()
            ->integrations()
            ->withCount('projectIntegrations')
            ->latest()
            ->get()
            ->map(fn (Integration $integration) => $this->serializeIntegration($integration));

        return response()->json(['data' => $integrations]);
    }

    public function connect(Request $request, string $provider): JsonResponse
    {
        $enum = IntegrationProvider::tryFromString($provider);
        if (! $enum) {
            return response()->json(['message' => 'Unknown provider.'], 404);
        }

        $impl = $this->manager->get($enum);

        if (! $impl->isConfigured()) {
            return response()->json([
                'message' => 'OAuth для этого провайдера ещё не настроен. Добавьте client_id и client_secret в .env.',
            ], 503);
        }

        $state = Str::random(40);
        Cache::put(
            $this->stateCacheKey($state),
            ['user_id' => $request->user()->id, 'provider' => $enum->value],
            config('integrations.oauth_state_ttl', 600)
        );

        try {
            $result = $impl->getAuthorizationUrl($request->user(), $state);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['data' => $result]);
    }

    public function connectApiKey(Request $request, string $provider): JsonResponse
    {
        $enum = IntegrationProvider::tryFromString($provider);
        if (! $enum) {
            return response()->json(['message' => 'Unknown provider.'], 404);
        }

        $impl = $this->manager->get($enum);
        if (! $impl instanceof ApiKeyIntegrationProviderInterface) {
            return response()->json(['message' => 'This provider does not support API key auth.'], 422);
        }

        $fields = $impl->apiKeyFields();
        $rules = [];
        if (in_array('user_id', $fields, true)) {
            $rules['user_id'] = ['required', 'string', 'max:32'];
        }
        if (in_array('api_key', $fields, true)) {
            $rules['api_key'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        try {
            $tokenData = $impl->connectWithApiKey(
                $request->user(),
                $validated['user_id'] ?? '',
                $validated['api_key'],
            );
        } catch (RuntimeException $e) {
            Log::warning('API key connection failed', [
                'provider' => $enum->value,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось подключить: '.$e->getMessage()], 422);
        }

        Integration::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => $enum],
            [
                'credentials' => $tokenData['credentials'],
                'account_label' => $tokenData['account_label'],
                'expires_at' => $tokenData['expires_at'] ?? null,
                'status' => 'active',
            ]
        );

        return response()->json(['message' => 'Integration connected.']);
    }

    public function callback(Request $request, string $provider)
    {
        $enum = IntegrationProvider::tryFromString($provider);
        if (! $enum) {
            return $this->redirectToFrontend('error', 'unknown_provider');
        }

        if ($request->filled('error')) {
            return $this->redirectToFrontend('error', $request->query('error'));
        }

        $state = (string) $request->query('state');
        $payload = Cache::pull($this->stateCacheKey($state));

        if (! $payload || ($payload['provider'] ?? null) !== $enum->value) {
            return $this->redirectToFrontend('error', 'invalid_state');
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            return $this->redirectToFrontend('error', 'missing_code');
        }

        $impl = $this->manager->get($enum);

        try {
            $tokenData = $impl->exchangeCode($code);
        } catch (RuntimeException $e) {
            Log::warning('OAuth token exchange failed', [
                'provider' => $enum->value,
                'message' => $e->getMessage(),
            ]);

            return $this->redirectToFrontend('error', 'token_exchange_failed');
        }

        Integration::updateOrCreate(
            ['user_id' => $payload['user_id'], 'provider' => $enum],
            [
                'credentials' => $tokenData['credentials'],
                'account_label' => $tokenData['account_label'],
                'expires_at' => $tokenData['expires_at'] ?? null,
                'status' => 'active',
            ]
        );

        return $this->redirectToFrontend('connected', $enum->value);
    }

    public function destroy(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('delete', $integration);

        $integration->delete();

        return response()->json(null, 204);
    }

    public function resources(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('view', $integration);

        $impl = $this->manager->get($integration->provider);

        try {
            $resources = $impl->listResources($integration);
        } catch (RuntimeException $e) {
            Log::warning('Failed to list integration resources', [
                'integration_id' => $integration->id,
                'message' => $e->getMessage(),
            ]);

            $integration->update(['status' => 'error']);

            return response()->json([
                'message' => 'Не удалось получить список ресурсов. Переподключите интеграцию.',
            ], 422);
        }

        if ($integration->status !== 'active') {
            $integration->update(['status' => 'active']);
        }

        return response()->json([
            'data' => $resources,
        ]);
    }

    private function serializeIntegration(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider->value,
            'label' => $integration->provider->label(),
            'logo_url' => config('integrations.logos.'.$integration->provider->value),
            'status' => $integration->status->value,
            'account_label' => $integration->account_label,
            'expires_at' => $integration->expires_at,
            'project_integrations_count' => $integration->project_integrations_count ?? 0,
            'created_at' => $integration->created_at,
        ];
    }

    private function stateCacheKey(string $state): string
    {
        return 'integration_oauth:'.$state;
    }

    private function redirectToFrontend(string $status, string $detail)
    {
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $query = http_build_query(['integration' => $status, 'detail' => $detail]);

        return redirect("{$base}/integrations?{$query}");
    }
}
