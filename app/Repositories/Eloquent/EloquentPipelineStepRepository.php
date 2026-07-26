<?php

namespace App\Repositories\Eloquent;

use App\Models\PipelineStep;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentPipelineStepRepository implements PipelineStepRepositoryInterface
{
    public function create(array $attributes): PipelineStep
    {
        return PipelineStep::create($attributes);
    }

    public function orderedForProject(int $projectId): Collection
    {
        return PipelineStep::where('project_id', $projectId)
            ->with('table')
            ->orderBy('step_order')
            ->get();
    }

    public function orderedForTable(int $datasetTableId): Collection
    {
        return PipelineStep::where('dataset_table_id', $datasetTableId)
            ->orderBy('step_order')
            ->get();
    }

    public function nextStepOrder(int $projectId): int
    {
        return (int) PipelineStep::where('project_id', $projectId)->max('step_order') + 1;
    }

    public function find(int $id): ?PipelineStep
    {
        return PipelineStep::find($id);
    }
}
