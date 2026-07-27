<?php

namespace App\Repositories\Eloquent;

use App\Enums\InsightCategory;
use App\Models\AiInsight;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAiInsightRepository implements AiInsightRepositoryInterface
{
    public function forTable(int $datasetTableId): Collection
    {
        return AiInsight::where('dataset_table_id', $datasetTableId)->get();
    }

    public function forTables(array $datasetTableIds, ?InsightCategory $category = null): Collection
    {
        return AiInsight::whereIn('dataset_table_id', $datasetTableIds)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->get();
    }

    public function actionableForUser(int $userId, int $limit): Collection
    {
        return AiInsight::whereNotNull('suggested_action')
            ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
            ->with(['project', 'table.dataset'])
            ->latest()
            ->limit($limit)
            ->get();
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
