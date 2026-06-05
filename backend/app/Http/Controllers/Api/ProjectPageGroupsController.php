<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ProjectPageGroups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectPageGroupsController extends Controller
{
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => [
                'groups' => ProjectPageGroups::normalize($project->settings[ProjectPageGroups::SETTINGS_KEY] ?? []),
            ],
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'groups' => ['required', 'array', 'max:30'],
            'groups.*.id' => ['nullable', 'string', 'max:80'],
            'groups.*.label' => ['required', 'string', 'max:80'],
            'groups.*.pattern' => ['required', 'string', 'max:255'],
            'groups.*.enabled' => ['nullable', 'boolean'],
        ]);

        $this->validateRegexPatterns($validated['groups']);

        $groups = ProjectPageGroups::normalize($validated['groups']);
        $settings = $project->settings ?? [];
        $settings[ProjectPageGroups::SETTINGS_KEY] = $groups;
        $project->update(['settings' => $settings]);

        return response()->json([
            'data' => ['groups' => $groups],
        ]);
    }

    /** @param  list<array<string, mixed>>  $groups */
    private function validateRegexPatterns(array $groups): void
    {
        $errors = [];
        foreach ($groups as $index => $group) {
            $pattern = (string) ($group['pattern'] ?? '');
            if (! ProjectPageGroups::isValidRegex($pattern)) {
                $errors["groups.{$index}.pattern"] = ['Некорректное регулярное выражение.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
