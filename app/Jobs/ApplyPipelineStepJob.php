<?php

namespace App\Jobs;

use App\Models\PipelineStep;
use App\Services\Pipeline\PipelineStepService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the actual clean_data.py/preprocess.py call for a PipelineStep
 * already recorded as Pending (see PipelineStepService::createPending) -
 * shared by manual transformations, accepted AI suggestions, and notebook
 * replay alike, since all three go through PipelineStepService::applyStep().
 */
class ApplyPipelineStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 150;

    public function __construct(public readonly PipelineStep $step)
    {
    }

    public function handle(PipelineStepService $service): void
    {
        $service->process($this->step);
    }
}
