<?php

namespace App\Services\Intelligence;

use App\Models\User;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use App\Repositories\Contracts\QualityReportRepositoryInterface;
use App\Repositories\Contracts\VisualizationSuggestionRepositoryInterface;

/**
 * Aggregates the proactive AI signals already generated across every one of
 * the user's projects (insights, pipeline/visualization suggestions, quality
 * audits) into a single ranked feed for the Workspace home page. Nothing
 * here is generated on the fly - it only surfaces rows that already exist,
 * each with a direct link to where it can be acted on.
 */
class IntelligenceFeedService
{
    public function __construct(
        private readonly AiInsightRepositoryInterface $insights,
        private readonly PipelineSuggestionRepositoryInterface $pipelineSuggestions,
        private readonly VisualizationSuggestionRepositoryInterface $visualizationSuggestions,
        private readonly QualityReportRepositoryInterface $qualityReports,
    ) {
    }

    /**
     * @return array<int, array{severity: string, icon: string, message: string, source: string, action_label: string, url: string, created_at: \Illuminate\Support\Carbon}>
     */
    public function forUser(User $user, int $limit = 6): array
    {
        $items = collect();

        foreach ($this->qualityReports->poorForUser($user->id, $limit) as $report) {
            $project = $report->table->dataset->project;

            $items->push([
                'severity' => 'warning',
                'icon' => '!',
                'message' => "Le dataset « {$report->table->dataset->name} » a un score de qualité de {$report->score}/100.",
                'source' => "Audit qualité · {$project->name}",
                'action_label' => 'Corriger',
                'url' => route('projects.datasets.show', [$project, $report->table->dataset]),
                'created_at' => $report->generated_at,
            ]);
        }

        foreach ($this->pipelineSuggestions->pendingForUser($user->id, $limit) as $suggestion) {
            $project = $suggestion->project;

            $items->push([
                'severity' => 'action',
                'icon' => '✦',
                'message' => $suggestion->rationale,
                'source' => "Suggestion de pipeline · {$project->name}",
                'action_label' => 'Voir',
                'url' => route('projects.datasets.show', [$project, $suggestion->table->dataset]),
                'created_at' => $suggestion->created_at,
            ]);
        }

        foreach ($this->visualizationSuggestions->pendingForUser($user->id, $limit) as $suggestion) {
            $project = $suggestion->project;
            $dashboard = $project->dashboards->first();

            $items->push([
                'severity' => 'action',
                'icon' => '◫',
                'message' => $suggestion->rationale,
                'source' => "Suggestion de visualisation · {$project->name}",
                'action_label' => 'Voir',
                'url' => $dashboard
                    ? route('projects.dashboards.show', [$project, $dashboard])
                    : route('projects.dashboards.index', $project),
                'created_at' => $suggestion->created_at,
            ]);
        }

        foreach ($this->insights->actionableForUser($user->id, $limit) as $insight) {
            $project = $insight->project;

            $items->push([
                'severity' => 'info',
                'icon' => '◆',
                'message' => $insight->content,
                'source' => "Insight IA · {$project->name}",
                'action_label' => 'Analyser',
                'url' => $insight->table
                    ? route('projects.datasets.show', [$project, $insight->table->dataset])
                    : route('projects.show', $project),
                'created_at' => $insight->created_at,
            ]);
        }

        return $items->sortByDesc('created_at')->take($limit)->values()->all();
    }
}
