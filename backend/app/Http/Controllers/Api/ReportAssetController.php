<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportAssetController extends Controller
{
    public function apexcharts(): BinaryFileResponse|Response
    {
        $path = public_path('vendor/apexcharts/apexcharts.min.js');
        if (! is_file($path)) {
            return response('ApexCharts asset is missing.', 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
