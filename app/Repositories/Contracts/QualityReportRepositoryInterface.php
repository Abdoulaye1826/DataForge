<?php

namespace App\Repositories\Contracts;

use App\Models\QualityReport;
use Illuminate\Database\Eloquent\Collection;

interface QualityReportRepositoryInterface
{
    public function create(array $attributes): QualityReport;

    public function latestForTable(int $datasetTableId): ?QualityReport;

    public function avgScoreForUser(int $userId): ?float;

    /**
     * Latest report per table, scoring below the "good" threshold, across
     * every project owned by the user - for the Workspace intelligence feed.
     */
    public function poorForUser(int $userId, int $limit): Collection;
}
