<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Services\ReportBlockCatalog;
use App\Services\ReportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templates,
        private ReportBlockCatalog $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = $request->user()
            ->reportTemplates()
            ->withCount('blocks')
            ->latest()
            ->get()
            ->map(fn (ReportTemplate $template) => $this->serializeListItem($template));

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.block_type' => ['required', 'string', 'max:64'],
            'blocks.*.settings' => ['nullable', 'array'],
        ]);

        $template = $request->user()->reportTemplates()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['blocks'])) {
            $this->templates->syncBlocks($template, $validated['blocks']);
        }

        return response()->json([
            'data' => $this->serializeDetail($template->fresh()->load('blocks')),
        ], 201);
    }

    public function show(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        return response()->json([
            'data' => $this->serializeDetail($template->load('blocks')),
        ]);
    }

    public function update(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'blocks' => ['sometimes', 'array'],
            'blocks.*.block_type' => ['required_with:blocks', 'string', 'max:64'],
            'blocks.*.settings' => ['nullable', 'array'],
        ]);

        $template->update(collect($validated)->only(['name', 'description'])->filter(fn ($v) => $v !== null)->all());

        if (array_key_exists('blocks', $validated)) {
            $this->templates->syncBlocks($template, $validated['blocks'] ?? []);
        }

        return response()->json([
            'data' => $this->serializeDetail($template->fresh()->load('blocks')),
        ]);
    }

    public function destroy(Request $request, ReportTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json(null, 204);
    }

    private function serializeListItem(ReportTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'is_default' => $template->is_default,
            'blocks_count' => $template->blocks_count ?? $template->blocks->count(),
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }

    private function serializeDetail(ReportTemplate $template): array
    {
        return [
            ...$this->serializeListItem($template),
            'blocks' => $template->blocks->map(fn ($block) => [
                'id' => $block->id,
                'block_type' => $block->block_type,
                'label' => $this->catalog->labelFor($block->block_type),
                'category' => $this->catalog->find($block->block_type)['category'] ?? null,
                'required_integration' => $this->catalog->find($block->block_type)['required_integration'] ?? null,
                'sort_order' => $block->sort_order,
                'settings' => $block->settings,
            ])->values(),
        ];
    }
}
