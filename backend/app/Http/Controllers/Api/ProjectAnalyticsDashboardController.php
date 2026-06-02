<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectAnalyticsDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAnalyticsDashboardController extends Controller
{
    public function __construct(private ProjectAnalyticsDashboardService $dashboard) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $this->dashboard->showConfig($project->fresh()),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.id' => ['required', 'string', 'max:64'],
            'widgets.*.block_type' => ['required', 'string', 'max:64'],
            'widgets.*.settings' => ['nullable', 'array'],
            'widgets.*.layout' => ['required', 'array'],
            'widgets.*.layout.x' => ['required', 'integer', 'min:0', 'max:11'],
            'widgets.*.layout.y' => ['required', 'integer', 'min:0', 'max:200'],
            'widgets.*.layout.w' => ['required', 'integer', 'min:1', 'max:12'],
            'widgets.*.layout.h' => ['required', 'integer', 'min:2', 'max:24'],
        ]);

        foreach ($validated['widgets'] as $widget) {
            $x = (int) $widget['layout']['x'];
            $w = (int) $widget['layout']['w'];
            if ($x + $w > 12) {
                return response()->json([
                    'message' => 'Виджет выходит за границы сетки (x + w должно быть ≤ 12).',
                ], 422);
            }
        }

        $widgets = $this->dashboard->saveConfig($project, $validated['widgets']);

        return response()->json([
            'data' => [
                'widgets' => $widgets,
                'is_suggested' => false,
            ],
        ]);
    }

    public function data(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'compare' => ['nullable', 'boolean'],
            'widgets' => ['nullable', 'array'],
            'widgets.*.id' => ['required_with:widgets', 'string', 'max:64'],
            'widgets.*.block_type' => ['required_with:widgets', 'string', 'max:64'],
            'widgets.*.settings' => ['nullable', 'array'],
            'widgets.*.layout' => ['nullable', 'array'],
            'widgets.*.layout.x' => ['nullable', 'integer', 'min:0', 'max:11'],
            'widgets.*.layout.y' => ['nullable', 'integer', 'min:0'],
            'widgets.*.layout.w' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.layout.h' => ['nullable', 'integer', 'min:2', 'max:24'],
        ]);

        $periodStart = isset($validated['period_start'])
            ? Carbon::parse($validated['period_start'])->startOfDay()
            : null;
        $periodEnd = isset($validated['period_end'])
            ? Carbon::parse($validated['period_end'])->startOfDay()
            : null;

        $config = $this->dashboard->showConfig($project);
        $widgets = $validated['widgets'] ?? $config['widgets'];

        return response()->json([
            'data' => $this->dashboard->fetchData(
                $project,
                $widgets,
                $periodStart,
                $periodEnd,
                filter_var($validated['compare'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ),
        ]);
    }
}
