<?php

namespace App\Services\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\QualityReportRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
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
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    public function allForUser(User $user): Collection
    {
        return $this->projects->allForUser($user->id);
    }

    public function recentProjects(User $user, int $limit = 4): Collection
    {
        return $this->projects->recentForUser($user->id, $limit);
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
            'reports' => $this->reports->countForUser($user->id),
            'avg_quality' => $this->qualityReports->avgScoreForUser($user->id),
        ];
    }

    public function recentActivity(User $user, int $limit = 10): Collection
    {
        return $this->activityLogs->recentForUser($user->id, $limit);
    }

    /**
     * Daily activity counts for the home dashboard's trend chart, zero-filled
     * so the line doesn't skip days with no activity.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function activityTrend(User $user, int $days = 14): array
    {
        $counts = $this->activityLogs->countsByDayForUser($user->id, $days);

        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');

            $labels[] = $date->translatedFormat('d M');
            $data[] = (int) ($counts[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
