<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineStepStatus;
use App\Enums\PipelineStepType;
use App\Exceptions\PythonExecutionException;
use App\Jobs\ApplyPipelineStepJob;
use App\Models\DatasetTable;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Repositories\Contracts\DatasetTableRepositoryInterface;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Services\Activity\ActivityLogService;
use App\Services\Python\PythonRunnerService;

/**
 * Shared engine behind both the Nettoyage and Prétraitement modules: applies
 * a single transformation to a table, refreshes its row/column counts and
 * DatasetColumn rows from the result, and records it as a PipelineStep -
 * simultaneously the cleaning/preprocessing history AND the replayable
 * notebook (see PipelineReplayService).
 *
 * Split in two so the actual Python work can run off the request thread:
 * applyStep() (called by every DataCleaningService/PreprocessingService
 * method, PipelineRecommendationService::accept(), and replay) creates the
 * PipelineStep row immediately as Pending and dispatches the job; process()
 * is what the job calls to do the real work and settle the row to
 * Applied/Failed.
 */
class PipelineStepService
{
    public function __construct(
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly DatasetTableRepositoryInterface $datasetTables,
        private readonly PythonRunnerService $pythonRunner,
        private readonly DatasetColumnSyncService $columnSync,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function applyStep(DatasetTable $table, Project $project, PipelineStepType $type, array $params): PipelineStep
    {
        $step = $this->createPending($table, $project, $type, $params);

        ApplyPipelineStepJob::dispatch($step);

        return $step;
    }

    public function createPending(DatasetTable $table, Project $project, PipelineStepType $type, array $params): PipelineStep
    {
        return $this->pipelineSteps->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'step_order' => $this->pipelineSteps->nextStepOrder($project->id),
            'step_type' => $type->value,
            'label' => "{$type->label()} — en cours...",
            'params' => $params,
            'status' => PipelineStepStatus::Pending->value,
            'rows_affected' => null,
            'applied_at' => null,
        ]);
    }

    /**
     * Runs the real Python transformation for a step already recorded as
     * Pending and settles it to Applied or Failed. Called by
     * ApplyPipelineStepJob - never call this synchronously from a request,
     * it defeats the point of queuing.
     */
    public function process(PipelineStep $step): void
    {
        $table = $step->table;
        $project = $step->project;
        $type = $step->step_type;
        $params = $step->params ?? [];

        try {
            $script = $type->category() === 'cleaning' ? 'clean_data.py' : 'preprocess.py';

            $result = $this->pythonRunner->run($script, [
                'storage_path' => $table->storage_path,
                'operation' => $type->value,
                'params' => $params,
            ], $project->id);

            $this->datasetTables->update($table, [
                'row_count' => $result->data['row_count'],
                'column_count' => $result->data['column_count'],
            ]);

            $this->columnSync->sync($table, $result->data['columns']);

            $label = $this->buildLabel($type, $params, $result->data);

            $step->update([
                'label' => $label,
                'status' => PipelineStepStatus::Applied->value,
                'rows_affected' => $result->data['rows_affected'] ?? null,
                'applied_at' => now(),
            ]);

            $this->activityLogService->log($project, "pipeline.{$type->value}", $label, $table);
        } catch (PythonExecutionException $e) {
            $step->update([
                'label' => "{$type->label()} — échec",
                'status' => PipelineStepStatus::Failed->value,
                'applied_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Builds the human-readable notebook line for a step, e.g. "Suppression
     * de 134 doublons" - mirrors the spec's own notebook example, which is
     * why it needs the operation's result (rows_affected) rather than just
     * the params known before running it.
     */
    private function buildLabel(PipelineStepType $type, array $params, array $result): string
    {
        $rows = $result['rows_affected'] ?? null;

        return match ($type) {
            PipelineStepType::Dedupe => "Suppression de {$rows} doublons",
            PipelineStepType::TrimSpaces => "Espaces superflus supprimés sur {$rows} valeurs",
            PipelineStepType::FixCase => "Casse corrigée sur {$rows} valeurs",
            PipelineStepType::FixDates => "Dates normalisées sur la colonne « {$params['column']} » ({$rows} valeurs)",
            PipelineStepType::RenameColumn => "Colonne « {$params['old_name']} » renommée en « {$params['new_name']} »",
            PipelineStepType::DropColumn => 'Colonne(s) supprimée(s) : ' . implode(', ', $params['columns']),
            PipelineStepType::Merge => "Colonnes fusionnées en « {$params['new_column']} »",
            PipelineStepType::Split => "Colonne « {$params['column']} » séparée en " . implode(', ', $params['new_columns']),
            PipelineStepType::Filter => "Filtre appliqué sur « {$params['column']} » ({$rows} lignes supprimées)",
            PipelineStepType::CreateColumn => "Colonne calculée « {$params['new_column']} » créée",
            PipelineStepType::ConvertType => "Colonne « {$params['column']} » convertie en {$params['target_type']}",
            PipelineStepType::Encode => "Colonne « {$params['column']} » encodée ({$params['method']})",
            PipelineStepType::Normalize => "Colonne « {$params['column']} » normalisée",
            PipelineStepType::Standardize => "Colonne « {$params['column']} » standardisée",
            PipelineStepType::Categorize => "Colonne « {$params['column']} » catégorisée",
            default => $type->label(),
        };
    }
}
