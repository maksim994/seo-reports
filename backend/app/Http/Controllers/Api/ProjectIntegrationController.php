<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Project;
use App\Models\ProjectIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectIntegrationController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $bindings = $project->projectIntegrations()
            ->with('integration')
            ->get()
            ->map(fn (ProjectIntegration $binding) => [
                'id' => $binding->id,
                'integration_id' => $binding->integration_id,
                'provider' => $binding->integration->provider->value,
                'external_resource_id' => $binding->external_resource_id,
                'external_resource_label' => $binding->external_resource_label,
                'config' => $binding->config,
            ]);

        return response()->json(['data' => $bindings]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'external_resource_id' => ['required', 'string', 'max:255'],
            'external_resource_label' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
        ]);

        /** @var Integration $integration */
        $integration = Integration::query()->findOrFail($validated['integration_id']);

        if ($integration->user_id !== $project->user_id) {
            abort(403);
        }

        $this->authorize('view', $integration);

        $binding = $project->projectIntegrations()->updateOrCreate(
            ['integration_id' => $integration->id],
            [
                'external_resource_id' => $validated['external_resource_id'],
                'external_resource_label' => $validated['external_resource_label'] ?? null,
                'config' => $validated['config'] ?? null,
            ]
        );

        if ($integration->provider->isAnalytics()) {
            $project->update(['has_analytics' => true]);
        }

        return response()->json([
            'data' => [
                'id' => $binding->id,
                'integration_id' => $binding->integration_id,
                'provider' => $integration->provider->value,
                'external_resource_id' => $binding->external_resource_id,
                'external_resource_label' => $binding->external_resource_label,
                'config' => $binding->config,
            ],
        ], 201);
    }

    public function destroy(Request $request, Project $project, ProjectIntegration $projectIntegration): JsonResponse
    {
        $this->authorize('update', $project);

        if ($projectIntegration->project_id !== $project->id) {
            abort(404);
        }

        $projectIntegration->delete();

        $hasAnalytics = $project->projectIntegrations()
            ->whereHas('integration', fn ($q) => $q->whereIn('provider', ['yandex_metrika', 'google_analytics']))
            ->exists();

        $project->update(['has_analytics' => $hasAnalytics]);

        return response()->json(null, 204);
    }
}
