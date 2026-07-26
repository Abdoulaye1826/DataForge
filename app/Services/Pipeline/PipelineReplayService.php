<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineStepType;
use App\Exceptions\PipelineReplayException;
use App\Exceptions\PythonExecutionException;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Module Notebook: "the notebook must allow replaying the exact pipeline on
 * a new dataset". Re-applies a source table's recorded steps, in order,
 * onto a different table (typically a freshly imported one with the same
 * shape) by re-running the same PipelineStepService logic - no separate
 * replay engine, so replay can never drift from what actually happened the
 * first time.
 */
class PipelineReplayService
{
    public function __construct(
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly PipelineStepService $pipelineStepService,
    ) {
    }

    /**
     * @return Collection<int, \App\Models\PipelineStep> The steps applied to the target table.
     *
     * @throws PipelineReplayException On the first step that fails to apply.
     */
    public function replay(DatasetTable $sourceTable, DatasetTable $targetTable, Project $project): Collection
    {
        $steps = $this->pipelineSteps->orderedForTable($sourceTable->id)
            ->reject(fn ($step) => $step->step_type === PipelineStepType::Import);

        $applied = new Collection();

        foreach ($steps as $step) {
            try {
                $applied->push(
                    $this->pipelineStepService->applyStep($targetTable, $project, $step->step_type, $step->params ?? [])
                );
            } catch (PythonExecutionException $e) {
                throw new PipelineReplayException(
                    "Échec au rejeu de l'étape « {$step->label} » : {$e->getMessage()}"
                );
            }
        }

        return $applied;
    }
}
