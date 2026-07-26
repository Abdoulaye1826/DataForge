<?php

namespace App\Jobs;

use App\Models\MlAnalysis;
use App\Services\Analysis\MlAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the real ml_analysis.py computation for an MlAnalysis already
 * recorded as Pending (see MlAnalysisService::run).
 */
class RunMlAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 150;

    public function __construct(public readonly MlAnalysis $analysis)
    {
    }

    public function handle(MlAnalysisService $service): void
    {
        $service->process($this->analysis);
    }
}
