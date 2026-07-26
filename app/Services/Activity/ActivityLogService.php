<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Central place every other service goes through to record a user-visible
 * event, so the Dashboard's "Historique récent" and a project's own activity
 * feed always stay consistent (see spec: "Toutes les actions sont enregistrées").
 */
class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activityLogs)
    {
    }

    public function log(Project $project, string $action, string $description, ?Model $loggable = null, array $meta = []): ActivityLog
    {
        return $this->activityLogs->create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'loggable_type' => $loggable?->getMorphClass(),
            'loggable_id' => $loggable?->getKey(),
            'action' => $action,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public function recentForUser(int $userId, int $limit = 10): Collection
    {
        return $this->activityLogs->recentForUser($userId, $limit);
    }
}
