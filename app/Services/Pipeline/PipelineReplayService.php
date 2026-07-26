<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineStepType;
use App\Jobs\ApplyPipelineStepJob;
use App\Models\DatasetTable;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use Illuminate\Bus\Bus;
use Illuminate\Support\Collection;

/**
 * Module Notebook: "the notebook must allow replaying the exact pipeline on
 * a new dataset". Re-applies a source table's recorded steps, in order,
 * onto a different table (typically a freshly imported one with the same
 * shape) by re-running the same PipelineStepService logic - no separate
 * replay engine, so replay can never drift from what actually happened the
 * first time.
 *
 * Each step now runs as a queued job (see PipelineStepService::applyStep),
 * so the steps are chained via Illuminate\Bus\PendingChain rather than
 * applied in a plain loop - a chain stops at the first job that throws,
 * preserving the original "abort on first failure" guarantee instead of
 * silently continuing to replay steps 2+ on top of a step 1 that never
 * actually succeeded.
 */
class PipelineReplayService
{
    public function __construct(
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly PipelineStepService $pipelineStepService,
    ) {
    }

    /**
     * @return Collection<int, PipelineStep> The (Pending) steps queued onto the target table.
     */
    public function replay(DatasetTable $sourceTable, DatasetTable $targetTable, Project $project): Collection
    {
        $steps = $this->pipelineSteps->orderedForTable($sourceTable->id)
            ->reject(fn ($step) => $step->step_type === PipelineStepType::Import);

        $queued = $steps->map(
            fn ($step) => $this->pipelineStepService->createPending($targetTable, $project, $step->step_type, $step->params ?? [])
        );

        if ($queued->isNotEmpty()) {
            Bus::chain($queued->map(fn (PipelineStep $step) => new ApplyPipelineStepJob($step))->all())->dispatch();
        }

        return $queued;
    }
}
