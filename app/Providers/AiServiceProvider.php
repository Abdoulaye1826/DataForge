<?php

namespace App\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\GroqProvider;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            return match (config('dataforge.ai.provider')) {
                'groq' => new GroqProvider(),
                default => new ClaudeProvider(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
