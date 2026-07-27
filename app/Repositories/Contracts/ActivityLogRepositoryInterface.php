<?php

namespace App\Repositories\Contracts;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog;

    public function recentForUser(int $userId, int $limit = 10): Collection;

    /**
     * Full, paginated activity history for the user, for the global
     * Historique page.
     */
    public function paginatedForUser(int $userId, int $perPage = 30): LengthAwarePaginator;

    /**
     * @return array<string, int> date (Y-m-d) => count
     */
    public function countsByDayForUser(int $userId, int $days): array;
}
