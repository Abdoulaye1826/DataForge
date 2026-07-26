<?php

namespace App\Services\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\QualityReportRepositoryInterface;
use App\Services\Activity\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly DatasetRepositoryInterface $datasets,
        private readonly ActivityLogRepositoryInterface $activityLogs,
        private readonly ActivityLogService $activityLogService,
        private readonly QualityReportRepositoryInterface $qualityReports,
    ) {
    }

    public function allForUser(User $user): Collection
    {
        return $this->projects->allForUser($user->id);
    }

    public function create(User $user, array $data): Project
    {
        $project = $this->projects->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => ProjectStatus::Draft,
            'last_activity_at' => now(),
            'domain' => $data['domain'] ?? null,
            'domain_other' => $data['domain_other'] ?? null,
            'objective' => $data['objective'] ?? null,
            'objective_other' => $data['objective_other'] ?? null,
        ]);

        $this->activityLogService->log($project, 'project.created', "Projet « {$project->name} » créé.");

        return $project;
    }

    public function update(Project $project, array $data): Project
    {
        return $this->projects->update($project, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'domain' => $data['domain'] ?? null,
            'domain_other' => $data['domain_other'] ?? null,
            'objective' => $data['objective'] ?? null,
            'objective_other' => $data['objective_other'] ?? null,
        ]);
    }

    public function delete(Project $project): void
    {
        $this->projects->delete($project);
    }

    /**
     * Aggregates used by the global Dashboard (Module 1).
     *
     * @return array{projects: int, datasets: int, reports: int, avg_quality: float|null}
     */
    public function dashboardStats(User $user): array
    {
        return [
            'projects' => $this->projects->countForUser($user->id),
            'datasets' => $this->datasets->countForUser($user->id),
            'reports' => 0, // wired once the Report module (Phase 7) exists
            'avg_quality' => $this->qualityReports->avgScoreForUser($user->id),
        ];
    }

    public function recentActivity(User $user, int $limit = 10): Collection
    {
        return $this->activityLogs->recentForUser($user->id, $limit);
    }
}
