<?php

namespace App\Repositories\Contracts;

use App\Models\PipelineSuggestion;
use Illuminate\Database\Eloquent\Collection;

interface PipelineSuggestionRepositoryInterface
{
    public function create(array $attributes): PipelineSuggestion;

    public function pendingForTable(int $datasetTableId): Collection;

    public function deletePendingForTable(int $datasetTableId): void;

    public function find(int $id): ?PipelineSuggestion;
}
