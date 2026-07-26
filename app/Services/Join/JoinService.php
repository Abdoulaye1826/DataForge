<?php

namespace App\Services\Join;

use App\Enums\DatasetFormat;
use App\Enums\DatasetStatus;
use App\Models\DatasetRelationship;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\DatasetTableRepositoryInterface;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Services\Activity\ActivityLogService;
use App\Services\Pipeline\TableOnboardingService;
use App\Services\Python\PythonRunnerService;
use App\Services\Quality\RelationshipDetectionService;
use InvalidArgumentException;

/**
 * Module Gestion des relations: turns a confirmed DatasetRelationship into
 * an actual combined table - detecting that two columns are the same key is
 * only half the job a data analyst needs; this produces the joined rows so
 * they can be explored, charted and reported like any other table. The
 * result is stored as its own derived Dataset (no uploaded source file) so
 * every existing module (quality, EDA, insights, visualizations, export,
 * reports) works on it for free.
 */
class JoinService
{
    private const JOIN_TYPES = ['inner', 'left', 'right', 'outer'];

    public function __construct(
        private readonly DatasetRepositoryInterface $datasets,
        private readonly DatasetTableRepositoryInterface $datasetTables,
        private readonly PythonRunnerService $pythonRunner,
        private readonly TableOnboardingService $onboarding,
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly ActivityLogService $activityLogService,
        private readonly RelationshipDetectionService $relationshipDetection,
    ) {
    }

    public function join(DatasetRelationship $relationship, Project $project, string $joinType = 'inner'): DatasetTable
    {
        if (! in_array($joinType, self::JOIN_TYPES, true)) {
            throw new InvalidArgumentException("Type de jointure non supporté : {$joinType}");
        }

        $left = $relationship->sourceTable;
        $right = $relationship->targetTable;
        $leftColumn = $relationship->sourceColumn->name;
        $rightColumn = $relationship->targetColumn->name;

        $name = "{$left->name} ⋈ {$right->name}";

        $dataset = $this->datasets->create([
            'project_id' => $project->id,
            'name' => $name,
            'original_filename' => $name,
            'format' => DatasetFormat::Derived,
            'disk_path' => null,
            'size_bytes' => 0,
            'status' => DatasetStatus::Pending,
            'import_meta' => [
                'type' => 'join',
                'relationship_id' => $relationship->id,
                'left_table_id' => $left->id,
                'right_table_id' => $right->id,
                'left_column' => $leftColumn,
                'right_column' => $rightColumn,
                'join_type' => $joinType,
            ],
        ]);

        $outputDir = storage_path("app/datasets/{$dataset->id}/tables");

        $result = $this->pythonRunner->run('join_tables.py', [
            'left_storage_path' => $left->storage_path,
            'right_storage_path' => $right->storage_path,
            'left_column' => $leftColumn,
            'right_column' => $rightColumn,
            'join_type' => $joinType,
            'output_dir' => $outputDir,
            'file_stem' => $name,
        ], $project->id);

        $table = $this->datasetTables->create([
            'dataset_id' => $dataset->id,
            'name' => $name,
            'row_count' => $result->data['row_count'],
            'column_count' => $result->data['column_count'],
            'storage_path' => $result->data['storage_path'],
            'is_primary' => true,
        ]);

        $this->onboarding->onboard($table, $project);

        $this->pipelineSteps->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'step_order' => $this->pipelineSteps->nextStepOrder($project->id),
            'step_type' => 'join',
            'label' => "Jointure de « {$left->name} » et « {$right->name} » sur {$leftColumn} = {$rightColumn} ({$joinType})",
            'params' => [
                'left_table_id' => $left->id,
                'right_table_id' => $right->id,
                'left_column' => $leftColumn,
                'right_column' => $rightColumn,
                'join_type' => $joinType,
            ],
            'status' => 'applied',
            'rows_affected' => $table->row_count,
            'applied_at' => now(),
        ]);

        $this->datasets->update($dataset, [
            'status' => DatasetStatus::Imported,
            'size_bytes' => filesize($table->storage_path),
        ]);

        // La nouvelle table peut elle-même se relier à d'autres tables du
        // projet (ex: une jointure à 2 tables qui rejoint ensuite une 3e).
        $this->relationshipDetection->detectForProject($project);

        $this->activityLogService->log(
            $project,
            'dataset.joined',
            "Table jointe « {$name} » créée ({$table->row_count} lignes).",
            $dataset,
        );

        return $table->fresh()->load('columns');
    }
}
