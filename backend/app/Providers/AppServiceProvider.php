<?php

namespace App\Providers;

use App\Integrations\Positions\KeysSoPositionProvider;
use App\Integrations\Positions\TopvisorPositionProvider;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\Services\PositionProviderRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PositionProviderRegistry::class, function ($app) {
            return new PositionProviderRegistry([
                $app->make(TopvisorPositionProvider::class),
                $app->make(KeysSoPositionProvider::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('template', fn (string $value) => ReportTemplate::query()->findOrFail($value));
        Route::bind('reportJob', fn (string $value) => ReportJob::query()->findOrFail($value));
    }
}
