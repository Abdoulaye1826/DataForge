<?php

namespace App\Repositories\Eloquent;

use App\Models\Analysis;
use App\Repositories\Contracts\AnalysisRepositoryInterface;

class EloquentAnalysisRepository implements AnalysisRepositoryInterface
{
    public function create(array $attributes): Analysis
    {
        return Analysis::create($attributes);
    }

    public function latestForTable(int $datasetTableId): ?Analysis
    {
        return Analysis::where('dataset_table_id', $datasetTableId)
            ->latest('computed_at')
            ->first();
    }
}
