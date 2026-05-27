<?php

namespace App\Providers;

use App\Models\ReportJob;
use App\Models\ReportTemplate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
