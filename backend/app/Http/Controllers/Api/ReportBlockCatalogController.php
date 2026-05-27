<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportBlockCatalog;
use Illuminate\Http\JsonResponse;

class ReportBlockCatalogController extends Controller
{
    public function __construct(private ReportBlockCatalog $catalog) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'blocks' => $this->catalog->all(),
                'categories' => $this->catalog->categories(),
            ],
        ]);
    }
}
