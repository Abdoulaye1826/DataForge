<?php

namespace App\Http\Controllers;

use App\Exceptions\PipelineReplayException;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\PipelineStepRepositoryInterface;
use App\Services\Pipeline\PipelineReplayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Module Notebook: read-only view of every applied pipeline step across the
 * project's tables, in order, plus the ability to replay a table's recorded
 * steps onto another table in the same project (typically a freshly
 * imported file with the same shape).
 */
class NotebookController extends Controller
{
    public function __construct(
        private readonly PipelineStepRepositoryInterface $pipelineSteps,
        private readonly PipelineReplayService $replayService,
    ) {
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        // dataset_table_id can be null for steps whose table was later hard-deleted
        // (DatasetTable has no soft-deletes) - these are orphaned history, not
        // displayable in any table's notebook, so they're excluded here.
        $stepsByTable = $this->pipelineSteps->orderedForProject($project->id)
            ->whereNotNull('dataset_table_id')
            ->groupBy('dataset_table_id');

        return view('notebook.show', [
            'project' => $project,
            'stepsByTable' => $stepsByTable,
            'tables' => $this->projectTables($project),
        ]);
    }

    public function replay(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'source_table_id' => ['required', 'integer'],
            'target_table_id' => ['required', 'integer', 'different:source_table_id'],
        ]);

        $tables = $this->projectTables($project)->keyBy('id');
        $sourceTable = $tables->get((int) $request->input('source_table_id'));
        $targetTable = $tables->get((int) $request->input('target_table_id'));

        if (! $sourceTable || ! $targetTable) {
            abort(404);
        }

        try {
            $applied = $this->replayService->replay($sourceTable, $targetTable, $project);
        } catch (PipelineReplayException $e) {
            return back()->withErrors(['replay' => $e->getMessage()]);
        }

        return redirect()
            ->route('projects.notebook.show', $project)
            ->with('status', "{$applied->count()} étape(s) rejouée(s) avec succès sur « {$targetTable->name} ».");
    }

    private function projectTables(Project $project): \Illuminate\Support\Collection
    {
        return DatasetTable::whereHas('dataset', fn ($query) => $query->where('project_id', $project->id))
            ->with('dataset')
            ->get();
    }
}
