<?php

namespace App\Repositories\Eloquent;

use App\Models\Visualization;
use App\Repositories\Contracts\VisualizationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentVisualizationRepository implements VisualizationRepositoryInterface
{
    public function find(int $id): ?Visualization
    {
        return Visualization::find($id);
    }

    public function forTable(int $datasetTableId): Collection
    {
        return Visualization::where('dataset_table_id', $datasetTableId)->latest()->get();
    }

    public function create(array $attributes): Visualization
    {
        return Visualization::create($attributes);
    }

    public function update(Visualization $visualization, array $attributes): Visualization
    {
        $visualization->update($attributes);

        return $visualization;
    }

    public function delete(Visualization $visualization): void
    {
        $visualization->delete();
    }
}
