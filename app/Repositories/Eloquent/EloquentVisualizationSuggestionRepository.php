<?php

namespace App\Repositories\Eloquent;

use App\Enums\PipelineSuggestionStatus;
use App\Models\VisualizationSuggestion;
use App\Repositories\Contracts\VisualizationSuggestionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentVisualizationSuggestionRepository implements VisualizationSuggestionRepositoryInterface
{
    public function create(array $attributes): VisualizationSuggestion
    {
        return VisualizationSuggestion::create($attributes);
    }

    public function pendingForProject(int $projectId): Collection
    {
        return VisualizationSuggestion::where('project_id', $projectId)
            ->where('status', PipelineSuggestionStatus::Pending)
            ->orderBy('id')
            ->get();
    }

    public function pendingForUser(int $userId, int $limit): Collection
    {
        return VisualizationSuggestion::where('status', PipelineSuggestionStatus::Pending)
            ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
            ->with(['project.dashboards'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function deletePendingForProject(int $projectId): void
    {
        VisualizationSuggestion::where('project_id', $projectId)
            ->where('status', PipelineSuggestionStatus::Pending)
            ->delete();
    }

    public function find(int $id): ?VisualizationSuggestion
    {
        return VisualizationSuggestion::find($id);
    }
}
