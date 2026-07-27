<?php

namespace App\Repositories\Contracts;

use App\Models\PipelineStep;
use Illuminate\Database\Eloquent\Collection;

interface PipelineStepRepositoryInterface
{
    public function create(array $attributes): PipelineStep;

    public function orderedForProject(int $projectId): Collection;

    public function orderedForTable(int $datasetTableId): Collection;

    public function nextStepOrder(int $projectId): int;

    public function find(int $id): ?PipelineStep;

    /**
     * Most recent pipeline steps across every project owned by the user,
     * for the global Pipelines page.
     */
    public function recentForUser(int $userId, int $limit): Collection;
}
