<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkItemRequest;
use App\Http\Requests\UpdateWorkItemRequest;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $query = $project->workItems()->latest('work_date')->latest('id');

        if ($request->filled('from')) {
            $query->whereDate('work_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('work_date', '<=', $request->query('to'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (WorkItem $item) => $this->serialize($item)),
        ]);
    }

    public function store(StoreWorkItemRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $item = $project->workItems()->create($request->validated());

        return response()->json(['data' => $this->serialize($item)], 201);
    }

    public function update(UpdateWorkItemRequest $request, Project $project, WorkItem $workItem): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($workItem->project_id === $project->id, 404);

        $workItem->update($request->validated());

        return response()->json(['data' => $this->serialize($workItem->fresh())]);
    }

    public function destroy(Request $request, Project $project, WorkItem $workItem): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($workItem->project_id === $project->id, 404);

        $workItem->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function serialize(WorkItem $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'work_date' => $item->work_date->format('Y-m-d'),
            'category' => $item->category->value,
            'category_label' => $item->category->label(),
            'description' => $item->description,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
