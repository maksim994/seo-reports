<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsAdminController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['sometimes', 'string', 'max:255'],
            'support_email' => ['sometimes', 'email', 'max:255'],
            'registration_enabled' => ['sometimes', 'boolean'],
            'email_verification_required' => ['sometimes', 'boolean'],
            'report_retention_months' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'string', 'max:1000'],
        ]);

        return response()->json([
            'data' => $this->settings->setMany($validated),
        ]);
    }
}
