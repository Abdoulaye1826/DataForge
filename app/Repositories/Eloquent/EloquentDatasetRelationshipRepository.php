<?php

namespace App\Repositories\Eloquent;

use App\Enums\RelationshipStatus;
use App\Models\DatasetRelationship;
use App\Repositories\Contracts\DatasetRelationshipRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentDatasetRelationshipRepository implements DatasetRelationshipRepositoryInterface
{
    public function find(int $id): ?DatasetRelationship
    {
        return DatasetRelationship::find($id);
    }

    public function forProject(int $projectId): Collection
    {
        return DatasetRelationship::where('project_id', $projectId)
            ->with(['sourceTable.dataset', 'sourceColumn', 'targetTable.dataset', 'targetColumn'])
            ->get();
    }

    public function forTables(array $tableIds): Collection
    {
        return DatasetRelationship::where(fn ($query) => $query
                ->whereIn('source_table_id', $tableIds)
                ->orWhereIn('target_table_id', $tableIds))
            ->with(['sourceTable.dataset', 'sourceColumn', 'targetTable.dataset', 'targetColumn'])
            ->get();
    }

    public function create(array $attributes): DatasetRelationship
    {
        return DatasetRelationship::create($attributes);
    }

    public function deleteSuggestedForProject(int $projectId): void
    {
        DatasetRelationship::where('project_id', $projectId)
            ->where('status', RelationshipStatus::Suggested)
            ->delete();
    }

    public function updateStatus(DatasetRelationship $relationship, string $status): DatasetRelationship
    {
        $relationship->update(['status' => $status]);

        return $relationship;
    }
}
