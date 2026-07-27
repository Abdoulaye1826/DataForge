<?php

namespace App\Repositories\Contracts;

use App\Models\AiInsight;
use Illuminate\Database\Eloquent\Collection;

interface AiInsightRepositoryInterface
{
    public function forTable(int $datasetTableId): Collection;

    /**
     * Most recent actionable insights (a suggested_action is set) across
     * every project owned by the user, for the Workspace intelligence feed.
     */
    public function actionableForUser(int $userId, int $limit): Collection;

    public function create(array $attributes): AiInsight;

    public function deleteForTable(int $datasetTableId): void;
}
