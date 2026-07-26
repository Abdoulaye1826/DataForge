<?php

namespace App\Repositories\Contracts;

use App\Models\QualityReport;

interface QualityReportRepositoryInterface
{
    public function create(array $attributes): QualityReport;

    public function latestForTable(int $datasetTableId): ?QualityReport;

    public function avgScoreForUser(int $userId): ?float;
}
