<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function index(): View
    {
        return view('projects.index', [
            'projects' => $this->projects->allForUser(auth()->user()),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projects->create($request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('status', 'Projet créé avec succès.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.show', [
            'project' => $project->load('datasets.tables.columns', 'connections'),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projects->update($project, $request->validated());

        return redirect()->route('projects.show', $project)->with('status', 'Projet mis à jour.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projects->delete($project);

        return redirect()->route('projects.index')->with('status', 'Projet supprimé.');
    }
}
