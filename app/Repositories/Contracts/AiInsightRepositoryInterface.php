<?php

namespace App\Repositories\Contracts;

use App\Models\AiInsight;
use Illuminate\Database\Eloquent\Collection;

interface AiInsightRepositoryInterface
{
    public function forTable(int $datasetTableId): Collection;

    public function create(array $attributes): AiInsight;

    public function deleteForTable(int $datasetTableId): void;
}
