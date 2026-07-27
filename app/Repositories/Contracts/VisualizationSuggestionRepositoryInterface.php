<?php

namespace App\Repositories\Contracts;

use App\Models\VisualizationSuggestion;
use Illuminate\Database\Eloquent\Collection;

interface VisualizationSuggestionRepositoryInterface
{
    public function create(array $attributes): VisualizationSuggestion;

    public function pendingForProject(int $projectId): Collection;

    /**
     * Pending suggestions across every project owned by the user, for the
     * Workspace intelligence feed.
     */
    public function pendingForUser(int $userId, int $limit): Collection;

    public function deletePendingForProject(int $projectId): void;

    public function find(int $id): ?VisualizationSuggestion;
}
