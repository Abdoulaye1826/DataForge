<?php

namespace App\Services\Cleaning;

use App\Enums\PipelineStepType;
use App\Models\DatasetTable;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Services\Pipeline\PipelineStepService;

/**
 * Module Nettoyage: named, validated entry points for the four cleaning
 * operations. Each just shapes/validates params and delegates the actual
 * execution + history-logging to PipelineStepService.
 */
class DataCleaningService
{
    public function __construct(private readonly PipelineStepService $pipelineStepService)
    {
    }

    public function dedupe(DatasetTable $table, Project $project, ?array $columns = null): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Dedupe, [
            'columns' => $columns,
        ]);
    }

    public function trimSpaces(DatasetTable $table, Project $project, ?array $columns = null): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::TrimSpaces, [
            'columns' => $columns,
        ]);
    }

    public function fixCase(DatasetTable $table, Project $project, array $columns, string $mode = 'title'): PipelineStep
    {
        if (! in_array($mode, ['lower', 'upper', 'title'], true)) {
            throw new \InvalidArgumentException("Mode de casse invalide : {$mode}");
        }

        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::FixCase, [
            'columns' => $columns,
            'mode' => $mode,
        ]);
    }

    public function fixDates(DatasetTable $table, Project $project, string $column): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::FixDates, [
            'column' => $column,
        ]);
    }
}
