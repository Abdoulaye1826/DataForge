<?php

namespace App\Repositories\Eloquent;

use App\Models\AiInsight;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAiInsightRepository implements AiInsightRepositoryInterface
{
    public function forTable(int $datasetTableId): Collection
    {
        return AiInsight::where('dataset_table_id', $datasetTableId)->get();
    }

    public function create(array $attributes): AiInsight
    {
        return AiInsight::create($attributes);
    }

    public function deleteForTable(int $datasetTableId): void
    {
        AiInsight::where('dataset_table_id', $datasetTableId)->delete();
    }
}
