<?php

namespace App\Services\Quality;

use App\Enums\RelationshipStatus;
use App\Models\DatasetColumn;
use App\Models\DatasetRelationship;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\DatasetRelationshipRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Module "Gestion des Excel multi-feuilles": detects candidate joins between
 * tables across an entire project - whether those tables are sibling sheets
 * of one Excel file or columns split across several separately-imported
 * files (e.g. patients.csv + staff.csv + staff_schedule.csv) - and stores
 * them as suggestions the user must explicitly confirm. Nothing here ever
 * gets applied automatically.
 */
class RelationshipDetectionService
{
    public function __construct(
        private readonly DatasetRelationshipRepositoryInterface $relationships,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function detectForProject(Project $project): Collection
    {
        $tables = DatasetTable::whereIn('dataset_id', $project->datasets()->pluck('id'))->get();

        if ($tables->count() < 2) {
            return new Collection();
        }

        $this->relationships->deleteSuggestedForProject($project->id);

        $result = $this->pythonRunner->run('detect_relationships.py', [
            'tables' => $tables->map(fn (DatasetTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'storage_path' => $table->storage_path,
            ])->values()->all(),
        ], $project->id);

        $tablesById = $tables->keyBy('id');
        $created = new Collection();

        foreach ($result->data['relationships'] as $relationship) {
            $sourceColumn = DatasetColumn::where('dataset_table_id', $relationship['source_table_id'])
                ->where('name', $relationship['source_column'])
                ->first();
            $targetColumn = DatasetColumn::where('dataset_table_id', $relationship['target_table_id'])
                ->where('name', $relationship['target_column'])
                ->first();

            if (! $sourceColumn || ! $targetColumn) {
                continue;
            }

            $sourceTable = $tablesById->get($relationship['source_table_id']);
            $targetTable = $tablesById->get($relationship['target_table_id']);
            $sameDataset = $sourceTable && $targetTable && $sourceTable->dataset_id === $targetTable->dataset_id;

            $created->push($this->relationships->create([
                'project_id' => $project->id,
                'dataset_id' => $sameDataset ? $sourceTable->dataset_id : null,
                'source_table_id' => $relationship['source_table_id'],
                'source_column_id' => $sourceColumn->id,
                'target_table_id' => $relationship['target_table_id'],
                'target_column_id' => $targetColumn->id,
                'relationship_type' => $relationship['relationship_type'],
                'confidence_score' => $relationship['confidence_score'],
                'status' => RelationshipStatus::Suggested,
            ]));
        }

        return $created;
    }

    public function confirm(DatasetRelationship $relationship): DatasetRelationship
    {
        return $this->relationships->updateStatus($relationship, RelationshipStatus::Confirmed->value);
    }

    public function reject(DatasetRelationship $relationship): DatasetRelationship
    {
        return $this->relationships->updateStatus($relationship, RelationshipStatus::Rejected->value);
    }
}
