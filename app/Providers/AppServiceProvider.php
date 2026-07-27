<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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
        Paginator::useBootstrap();

        // Throws on lazy-loaded relations outside production - catches N+1
        // regressions during development/tests instead of only in the
        // profiler, at zero behavioral cost in prod.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
