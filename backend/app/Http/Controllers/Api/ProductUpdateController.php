<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductUpdateController extends Controller
{
    public function __construct(
        private readonly ProductUpdateService $productUpdates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payload = $this->productUpdates->forUser($request->user());

        return response()->json(['data' => $payload]);
    }

    public function dismiss(Request $request, string $id): JsonResponse
    {
        $this->productUpdates->dismiss($request->user(), $id);
        $payload = $this->productUpdates->forUser($request->user());

        return response()->json(['data' => $payload]);
    }

    public function dismissAll(Request $request): JsonResponse
    {
        $this->productUpdates->dismissAll($request->user());
        $payload = $this->productUpdates->forUser($request->user());

        return response()->json(['data' => $payload]);
    }
}
