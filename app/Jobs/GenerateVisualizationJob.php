<?php

namespace App\Jobs;

use App\Models\Visualization;
use App\Services\Visualization\VisualizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the real generate_chart_data.py computation for a visualization
 * whose data_cache is (still) null - used both right after creation and for
 * the manual "recompute" action (see VisualizationService::create/refresh).
 */
class GenerateVisualizationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 150;

    public function __construct(public readonly Visualization $visualization)
    {
    }

    public function handle(VisualizationService $service): void
    {
        $service->process($this->visualization);
    }
}
