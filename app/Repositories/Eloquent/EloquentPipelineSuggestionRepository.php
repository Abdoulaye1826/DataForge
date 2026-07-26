<?php

namespace App\Repositories\Eloquent;

use App\Enums\PipelineSuggestionStatus;
use App\Models\PipelineSuggestion;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentPipelineSuggestionRepository implements PipelineSuggestionRepositoryInterface
{
    public function create(array $attributes): PipelineSuggestion
    {
        return PipelineSuggestion::create($attributes);
    }

    public function pendingForTable(int $datasetTableId): Collection
    {
        return PipelineSuggestion::where('dataset_table_id', $datasetTableId)
            ->where('status', PipelineSuggestionStatus::Pending)
            ->orderBy('id')
            ->get();
    }

    public function deletePendingForTable(int $datasetTableId): void
    {
        PipelineSuggestion::where('dataset_table_id', $datasetTableId)
            ->where('status', PipelineSuggestionStatus::Pending)
            ->delete();
    }

    public function find(int $id): ?PipelineSuggestion
    {
        return PipelineSuggestion::find($id);
    }
}
