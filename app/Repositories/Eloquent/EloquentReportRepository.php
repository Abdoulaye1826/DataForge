<?php

namespace App\Repositories\Eloquent;

use App\Models\Report;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentReportRepository implements ReportRepositoryInterface
{
    public function find(int $id): ?Report
    {
        return Report::find($id);
    }

    public function forProject(int $projectId): Collection
    {
        return Report::where('project_id', $projectId)->latest()->get();
    }

    public function countForUser(int $userId): int
    {
        return Report::whereHas('project', fn ($q) => $q->where('user_id', $userId))->count();
    }

    public function allForUser(int $userId): Collection
    {
        return Report::whereHas('project', fn ($q) => $q->where('user_id', $userId))
            ->with('project')
            ->latest()
            ->get();
    }

    public function create(array $attributes): Report
    {
        return Report::create($attributes);
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }
}
