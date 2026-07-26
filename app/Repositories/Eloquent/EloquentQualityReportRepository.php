<?php

namespace App\Repositories\Eloquent;

use App\Models\QualityReport;
use App\Repositories\Contracts\QualityReportRepositoryInterface;

class EloquentQualityReportRepository implements QualityReportRepositoryInterface
{
    public function create(array $attributes): QualityReport
    {
        return QualityReport::create($attributes);
    }

    public function latestForTable(int $datasetTableId): ?QualityReport
    {
        return QualityReport::where('dataset_table_id', $datasetTableId)
            ->latest('generated_at')
            ->first();
    }

    public function avgScoreForUser(int $userId): ?float
    {
        // Only the latest report per table should count towards the average.
        $latestIds = QualityReport::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('dataset_table_id')
            ->pluck('id');

        $avg = QualityReport::whereIn('id', $latestIds)
            ->whereHas('table.dataset.project', fn ($query) => $query->where('user_id', $userId))
            ->avg('score');

        return $avg !== null ? (float) $avg : null;
    }
}
