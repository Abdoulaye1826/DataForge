<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }

    public function recentForUser(int $userId, int $limit = 10): Collection
    {
        return ActivityLog::where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function paginatedForUser(int $userId, int $perPage = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ActivityLog::where('user_id', $userId)
            ->with('project')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function countsByDayForUser(int $userId, int $days): array
    {
        return ActivityLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();
    }
}
