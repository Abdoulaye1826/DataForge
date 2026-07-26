<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Routes that call an AI provider (chat, quality narrative, insights,
        // pipeline recommendations, report conclusion) - each call costs real
        // tokens/money and can take several seconds, so these get the
        // tightest limit.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Routes that run a Python subprocess synchronously within the
        // request (import, cleaning/preprocessing, stats, ML, exports,
        // chart rendering) - no AI cost, but still blocks a PHP worker for
        // up to PYTHON_TIMEOUT seconds, so still worth capping.
        RateLimiter::for('heavy', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Routes hit repeatedly during normal interactive use (data grid
        // pagination/search, live dashboard filters) - generous limit, only
        // meant to stop runaway/scripted abuse, not normal browsing.
        RateLimiter::for('read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
