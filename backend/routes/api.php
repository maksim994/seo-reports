<?php

use App\Http\Controllers\Api\Admin\ProjectAdminController;
use App\Http\Controllers\Api\Admin\SettingsAdminController;
use App\Http\Controllers\Api\Admin\UserAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectIntegrationController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportBlockCatalogController;
use App\Http\Controllers\Api\ReportTemplateController;
use App\Http\Controllers\Api\WorkItemController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);
Route::get('/settings/public', [PublicSettingsController::class, 'index']);
Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('projects', ProjectController::class);

    Route::get('/projects/{project}/work-items', [WorkItemController::class, 'index']);
    Route::post('/projects/{project}/work-items', [WorkItemController::class, 'store']);
    Route::patch('/projects/{project}/work-items/{workItem}', [WorkItemController::class, 'update']);
    Route::delete('/projects/{project}/work-items/{workItem}', [WorkItemController::class, 'destroy']);

    Route::get('/integrations/providers', [IntegrationController::class, 'providers']);
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect']);
    Route::post('/integrations/{provider}/api-key', [IntegrationController::class, 'connectApiKey']);
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy']);
    Route::get('/integrations/{integration}/resources', [IntegrationController::class, 'resources']);

    Route::get('/projects/{project}/integrations', [ProjectIntegrationController::class, 'index']);
    Route::post('/projects/{project}/integrations', [ProjectIntegrationController::class, 'store']);
    Route::delete('/projects/{project}/integrations/{projectIntegration}', [ProjectIntegrationController::class, 'destroy']);

    Route::get('/report-blocks/catalog', [ReportBlockCatalogController::class, 'index']);
    Route::post('/templates/{template}/logo', [ReportTemplateController::class, 'uploadLogo']);
    Route::get('/templates/{template}/logo', [ReportTemplateController::class, 'showLogo']);
    Route::delete('/templates/{template}/logo', [ReportTemplateController::class, 'deleteLogo']);
    Route::apiResource('templates', ReportTemplateController::class);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::delete('/reports/{reportJob}', [ReportController::class, 'destroy']);
    Route::get('/reports/{reportJob}', [ReportController::class, 'show']);
    Route::get('/reports/{reportJob}/preview', [ReportController::class, 'preview']);
    Route::get('/reports/{reportJob}/download/{format}', [ReportController::class, 'download']);
    Route::get('/projects/{project}/reports', [ReportController::class, 'projectIndex']);
    Route::post('/projects/{project}/reports', [ReportController::class, 'store']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::patch('/users/{user}', [UserAdminController::class, 'update']);
        Route::delete('/users/{user}', [UserAdminController::class, 'destroy']);

        Route::get('/settings', [SettingsAdminController::class, 'index']);
        Route::put('/settings', [SettingsAdminController::class, 'update']);

        Route::get('/projects', [ProjectAdminController::class, 'index']);
    });
});
