<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $status = 'ok';
        $db = 'ok';
        $redis = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $db = 'error';
            $status = 'degraded';
        }

        try {
            Redis::ping();
        } catch (\Throwable) {
            $redis = 'error';
            $status = 'degraded';
        }

        return response()->json([
            'status' => $status,
            'db' => $db,
            'redis' => $redis,
        ]);
    }
}
