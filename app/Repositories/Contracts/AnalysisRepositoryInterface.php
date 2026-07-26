<?php

namespace App\Repositories\Contracts;

use App\Models\Analysis;

interface AnalysisRepositoryInterface
{
    public function create(array $attributes): Analysis;

    public function latestForTable(int $datasetTableId): ?Analysis;
}
