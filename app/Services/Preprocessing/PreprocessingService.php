<?php

namespace App\Services\Preprocessing;

use App\Enums\PipelineStepType;
use App\Models\DatasetTable;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Services\Pipeline\PipelineStepService;

/**
 * Module Prétraitement: named, validated entry points for the eleven
 * preprocessing operations. Each just shapes/validates params and delegates
 * the actual execution + history-logging to PipelineStepService.
 */
class PreprocessingService
{
    private const FILTER_OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'is_null', 'is_not_null'];
    private const CONVERT_TYPES = ['integer', 'float', 'string', 'date', 'datetime', 'boolean'];
    private const ENCODE_METHODS = ['label', 'onehot'];

    public function __construct(private readonly PipelineStepService $pipelineStepService)
    {
    }

    public function renameColumn(DatasetTable $table, Project $project, string $oldName, string $newName): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::RenameColumn, [
            'old_name' => $oldName,
            'new_name' => $newName,
        ]);
    }

    public function dropColumn(DatasetTable $table, Project $project, array $columns): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::DropColumn, [
            'columns' => $columns,
        ]);
    }

    public function mergeColumns(DatasetTable $table, Project $project, array $columns, string $newColumn, string $separator = ' '): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Merge, [
            'columns' => $columns,
            'new_column' => $newColumn,
            'separator' => $separator,
        ]);
    }

    public function splitColumn(DatasetTable $table, Project $project, string $column, array $newColumns, string $separator = ' '): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Split, [
            'column' => $column,
            'new_columns' => $newColumns,
            'separator' => $separator,
        ]);
    }

    public function filter(DatasetTable $table, Project $project, string $column, string $operator, mixed $value = null): PipelineStep
    {
        if (! in_array($operator, self::FILTER_OPERATORS, true)) {
            throw new \InvalidArgumentException("Opérateur de filtre invalide : {$operator}");
        }

        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Filter, [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ]);
    }

    public function createColumn(DatasetTable $table, Project $project, string $newColumn, string $expression): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::CreateColumn, [
            'new_column' => $newColumn,
            'expression' => $expression,
        ]);
    }

    public function convertType(DatasetTable $table, Project $project, string $column, string $targetType): PipelineStep
    {
        if (! in_array($targetType, self::CONVERT_TYPES, true)) {
            throw new \InvalidArgumentException("Type cible invalide : {$targetType}");
        }

        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::ConvertType, [
            'column' => $column,
            'target_type' => $targetType,
        ]);
    }

    public function encode(DatasetTable $table, Project $project, string $column, string $method = 'label'): PipelineStep
    {
        if (! in_array($method, self::ENCODE_METHODS, true)) {
            throw new \InvalidArgumentException("Méthode d'encodage invalide : {$method}");
        }

        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Encode, [
            'column' => $column,
            'method' => $method,
        ]);
    }

    public function normalize(DatasetTable $table, Project $project, string $column): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Normalize, [
            'column' => $column,
        ]);
    }

    public function standardize(DatasetTable $table, Project $project, string $column): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Standardize, [
            'column' => $column,
        ]);
    }

    public function categorize(DatasetTable $table, Project $project, string $column, int|array $bins, ?array $labels = null): PipelineStep
    {
        return $this->pipelineStepService->applyStep($table, $project, PipelineStepType::Categorize, [
            'column' => $column,
            'bins' => $bins,
            'labels' => $labels,
        ]);
    }
}
