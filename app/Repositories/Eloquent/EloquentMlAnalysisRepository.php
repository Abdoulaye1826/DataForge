<?php

namespace App\Repositories\Eloquent;

use App\Models\MlAnalysis;
use App\Repositories\Contracts\MlAnalysisRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentMlAnalysisRepository implements MlAnalysisRepositoryInterface
{
    public function find(int $id): ?MlAnalysis
    {
        return MlAnalysis::find($id);
    }

    public function forTable(int $datasetTableId): Collection
    {
        return MlAnalysis::where('dataset_table_id', $datasetTableId)->latest('id')->get();
    }

    public function create(array $attributes): MlAnalysis
    {
        return MlAnalysis::create($attributes);
    }

    public function update(MlAnalysis $analysis, array $attributes): MlAnalysis
    {
        $analysis->update($attributes);

        return $analysis;
    }

    public function delete(MlAnalysis $analysis): void
    {
        $analysis->delete();
    }
}
