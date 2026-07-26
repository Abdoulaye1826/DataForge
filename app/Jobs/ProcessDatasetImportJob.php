<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\Project;
use App\Services\Import\DatasetImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the heavy part of a dataset import (real Python parsing + the full
 * onboarding cascade) off the request thread - import_dataset.py alone can
 * approach PYTHON_TIMEOUT on a large file, and onboarding chains several
 * more Python/AI calls on top of it. The Dataset row already exists with
 * status Pending by the time this is dispatched, so the UI has something
 * to show immediately regardless of how long this takes.
 */
class ProcessDatasetImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Must exceed PYTHON_TIMEOUT (120s) with margin for several chained calls. */
    public int $timeout = 300;

    public function __construct(
        public readonly Dataset $dataset,
        public readonly Project $project,
        public readonly string $action,
        public readonly string $verb,
    ) {
    }

    public function handle(DatasetImportService $service): void
    {
        $service->processDataset($this->dataset, $this->project, $this->action, $this->verb);
    }
}
