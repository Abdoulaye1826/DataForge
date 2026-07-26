<?php

namespace App\Repositories\Contracts;

use App\Models\MlAnalysis;
use Illuminate\Database\Eloquent\Collection;

interface MlAnalysisRepositoryInterface
{
    public function find(int $id): ?MlAnalysis;

    public function forTable(int $datasetTableId): Collection;

    public function create(array $attributes): MlAnalysis;

    public function delete(MlAnalysis $analysis): void;
}
