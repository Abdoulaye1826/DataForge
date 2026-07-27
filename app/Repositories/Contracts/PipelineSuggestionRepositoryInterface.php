<?php

namespace App\Repositories\Contracts;

use App\Models\PipelineSuggestion;
use Illuminate\Database\Eloquent\Collection;

interface PipelineSuggestionRepositoryInterface
{
    public function create(array $attributes): PipelineSuggestion;

    public function pendingForTable(int $datasetTableId): Collection;

    /**
     * Pending suggestions across several tables at once, for the Dataset
     * Intelligence report.
     *
     * @param array<int, int> $datasetTableIds
     */
    public function pendingForTables(array $datasetTableIds): Collection;

    /**
     * Pending suggestions across every project owned by the user, for the
     * Workspace intelligence feed.
     */
    public function pendingForUser(int $userId, int $limit): Collection;

    public function deletePendingForTable(int $datasetTableId): void;

    public function find(int $id): ?PipelineSuggestion;
}
