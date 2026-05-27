<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->withCount('projects')->latest();

        if ($search = $request->query('search')) {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }

        if ($request->filled('is_admin')) {
            $query->where('is_admin', filter_var($request->query('is_admin'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_blocked')) {
            $query->where('is_blocked', filter_var($request->query('is_blocked'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id && $request->has('is_admin') && ! $request->boolean('is_admin')) {
            return response()->json(['message' => 'Нельзя снять admin с самого себя.'], 422);
        }

        $validated = $request->validate([
            'is_admin' => ['sometimes', 'boolean'],
            'is_blocked' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'data' => $user->fresh()->loadCount('projects'),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Нельзя удалить свой аккаунт.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
