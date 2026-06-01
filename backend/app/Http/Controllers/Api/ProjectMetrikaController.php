<?php

namespace App\Http\Controllers\Api;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\YandexMetrikaDataService;
use App\Support\MetrikaBlockSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProjectMetrikaController extends Controller
{
    public function __construct(private YandexMetrikaDataService $metrika) {}

    public function goals(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $binding = $project->projectIntegrations()
            ->whereHas('integration', fn ($q) => $q->where('provider', IntegrationProvider::YandexMetrika))
            ->with('integration')
            ->first();

        if (! $binding) {
            return response()->json(['data' => ['goals' => [], 'traffic_sources' => []]]);
        }

        $token = $binding->integration->credentials['access_token'] ?? null;
        $counterId = $this->metrika->counterIdFromBinding($binding);

        if (! $token || ! $counterId) {
            return response()->json(['data' => ['goals' => [], 'traffic_sources' => []]]);
        }

        try {
            $goals = collect($this->metrika->listGoals($token, $counterId))
                ->map(fn (array $goal) => [
                    'value' => (string) $goal['id'],
                    'label' => $goal['name'],
                ])
                ->values()
                ->all();

            $trafficSources = [];
            foreach (MetrikaBlockSettings::TRAFFIC_SOURCE_OPTIONS as $value => $label) {
                $trafficSources[] = ['value' => $value, 'label' => $label];
            }

            return response()->json([
                'data' => [
                    'goals' => $goals,
                    'traffic_sources' => $trafficSources,
                ],
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'Не удалось загрузить цели Метрики.'], 502);
        }
    }
}
