<?php

namespace App\Http\Controllers;

use App\Enums\RelationshipStatus;
use App\Exceptions\PythonExecutionException;
use App\Models\DatasetRelationship;
use App\Models\Project;
use App\Repositories\Contracts\DatasetRelationshipRepositoryInterface;
use App\Services\Join\JoinService;
use App\Services\Quality\RelationshipDetectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelationshipController extends Controller
{
    public function __construct(
        private readonly RelationshipDetectionService $relationshipDetection,
        private readonly DatasetRelationshipRepositoryInterface $relationships,
        private readonly JoinService $joinService,
    ) {
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('relationships.index', [
            'project' => $project,
            'relationships' => $this->relationships->forProject($project->id),
        ]);
    }

    public function confirm(Project $project, DatasetRelationship $relationship): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->relationshipDetection->confirm($relationship);

        return back()->with('status', 'Relation confirmée.');
    }

    public function reject(Project $project, DatasetRelationship $relationship): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->relationshipDetection->reject($relationship);

        return back()->with('status', 'Relation rejetée.');
    }

    public function join(Request $request, Project $project, DatasetRelationship $relationship): RedirectResponse
    {
        $this->authorize('update', $project);

        if ($relationship->status !== RelationshipStatus::Confirmed) {
            return back()->withErrors(['join' => 'Seule une relation confirmée peut être jointe.']);
        }

        $request->validate(['join_type' => ['required', 'in:inner,left,right,outer']]);

        try {
            $table = $this->joinService->join($relationship, $project, $request->string('join_type')->value());
        } catch (PythonExecutionException $e) {
            return back()->withErrors(['join' => $e->getMessage()]);
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $table->dataset])
            ->with('status', "Table jointe « {$table->name} » créée.");
    }
}
