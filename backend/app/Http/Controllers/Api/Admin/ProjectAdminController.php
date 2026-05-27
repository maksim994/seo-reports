<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->with('user:id,name,email')->latest();

        if ($search = $request->query('search')) {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(domain) LIKE ?', [$term]);
            });
        }

        if ($request->filled('has_analytics')) {
            $query->where('has_analytics', filter_var($request->query('has_analytics'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }
}
