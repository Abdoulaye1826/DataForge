<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Models\Dashboard;
use App\Models\Project;
use App\Models\VisualizationSuggestion;
use App\Services\Dashboard\DashboardService;
use App\Services\Visualization\VisualizationRecommendationService;
use App\Services\Visualization\VisualizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Module Suggestions IA de visualisations : génère/accepte/rejette des
 * suggestions de graphiques à l'échelle du projet (toutes les tables), pensé
 * pour être déclenché depuis la page d'un Dashboard - accepter une
 * suggestion crée la Visualization ET l'ajoute directement sur le dashboard
 * d'où la demande est partie (dashboard_id), sans que ce modèle soit lié à
 * Dashboard en base (une suggestion appartient au projet, pas à un dashboard
 * précis - plusieurs dashboards du même projet peuvent piocher dedans).
 */
class VisualizationSuggestionController extends Controller
{
    public function __construct(
        private readonly VisualizationRecommendationService $recommendations,
        private readonly VisualizationService $visualizationService,
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $dashboard = $this->resolveDashboard($request, $project);

        try {
            $this->recommendations->propose($project);
        } catch (AiProviderException $e) {
            return back()->withErrors(['visualization_suggestions' => "Impossible de générer des suggestions pour le moment : {$e->getMessage()}"]);
        }

        return $this->redirectToDashboard($project, $dashboard, 'Suggestions IA de visualisations générées.');
    }

    public function accept(Request $request, Project $project, VisualizationSuggestion $visualizationSuggestion): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($visualizationSuggestion->project_id === $project->id, 404);

        $dashboard = $this->resolveDashboard($request, $project);

        try {
            $this->recommendations->accept($visualizationSuggestion, $this->visualizationService, $dashboard, $this->dashboardService);
        } catch (Throwable $e) {
            return back()->withErrors(['visualization_suggestions' => "Échec de la création : {$e->getMessage()}"]);
        }

        return $this->redirectToDashboard($project, $dashboard, 'Visualisation créée' . ($dashboard ? ' et ajoutée au dashboard.' : '.'));
    }

    public function reject(Request $request, Project $project, VisualizationSuggestion $visualizationSuggestion): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($visualizationSuggestion->project_id === $project->id, 404);

        $dashboard = $this->resolveDashboard($request, $project);

        $this->recommendations->reject($visualizationSuggestion);

        return $this->redirectToDashboard($project, $dashboard, 'Suggestion rejetée.');
    }

    private function resolveDashboard(Request $request, Project $project): ?Dashboard
    {
        $dashboard = Dashboard::find($request->input('dashboard_id'));

        return $dashboard && $dashboard->project_id === $project->id ? $dashboard : null;
    }

    private function redirectToDashboard(Project $project, ?Dashboard $dashboard, string $status): RedirectResponse
    {
        if ($dashboard) {
            return redirect()->route('projects.dashboards.show', [$project, $dashboard])->with('status', $status);
        }

        return redirect()->route('projects.dashboards.index', $project)->with('status', $status);
    }
}
