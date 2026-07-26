<?php

namespace App\Repositories\Contracts;

use App\Models\Visualization;
use Illuminate\Database\Eloquent\Collection;

interface VisualizationRepositoryInterface
{
    public function find(int $id): ?Visualization;

    public function forTable(int $datasetTableId): Collection;

    public function create(array $attributes): Visualization;

    public function update(Visualization $visualization, array $attributes): Visualization;

    public function delete(Visualization $visualization): void;
}
