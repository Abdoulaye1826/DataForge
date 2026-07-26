<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\PipelineSuggestion;
use App\Models\Project;
use App\Services\Pipeline\PipelineRecommendationService;
use App\Services\Pipeline\PipelineStepService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Module Pipeline proposé par l'IA (§5): generates/accepts/rejects AI
 * suggestions for a table's next cleaning/preprocessing steps. Accepting a
 * suggestion never executes anything special here - it delegates straight
 * to PipelineStepService::applyStep(), the exact same engine the manual
 * transformation modal already uses.
 */
class PipelineSuggestionController extends Controller
{
    public function __construct(
        private readonly PipelineRecommendationService $recommendations,
        private readonly PipelineStepService $pipelineStepService,
    ) {
    }

    public function store(Request $request, Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $this->recommendations->propose($table, $project, $request->user());
        } catch (AiProviderException $e) {
            return back()->withErrors(['suggestions' => "Impossible de générer des suggestions pour le moment : {$e->getMessage()}"]);
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', 'Suggestions IA générées.');
    }

    public function accept(Project $project, Dataset $dataset, DatasetTable $table, PipelineSuggestion $pipelineSuggestion): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $this->recommendations->accept($pipelineSuggestion, $this->pipelineStepService);
        } catch (Throwable $e) {
            return back()->withErrors(['suggestions' => "Échec de l'application : {$e->getMessage()}"]);
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', 'Suggestion en cours d\'application...');
    }

    public function reject(Project $project, Dataset $dataset, DatasetTable $table, PipelineSuggestion $pipelineSuggestion): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->recommendations->reject($pipelineSuggestion);

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', 'Suggestion rejetée.');
    }

    public function acceptAll(Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $failed = 0;

        foreach ($table->pipelineSuggestions()->pending()->get() as $suggestion) {
            try {
                $this->recommendations->accept($suggestion, $this->pipelineStepService);
            } catch (Throwable) {
                $failed++;
            }
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', $failed > 0 ? "Suggestions en cours d'application ({$failed} échec(s) au démarrage)." : 'Toutes les suggestions sont en cours d\'application...');
    }
}
