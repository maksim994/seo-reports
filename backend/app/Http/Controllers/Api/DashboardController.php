<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private PortfolioDashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $periodStart = isset($validated['period_start'])
            ? Carbon::parse($validated['period_start'])->startOfDay()
            : null;
        $periodEnd = isset($validated['period_end'])
            ? Carbon::parse($validated['period_end'])->startOfDay()
            : null;

        return response()->json([
            'data' => $this->dashboard->build($request->user(), $periodStart, $periodEnd),
        ]);
    }
}
