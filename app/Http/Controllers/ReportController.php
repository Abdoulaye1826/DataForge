<?php

namespace App\Http\Controllers;

use App\Enums\GeneratedBy;
use App\Exceptions\PythonExecutionException;
use App\Models\Project;
use App\Models\Report;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Report\ReportGenerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerationService $reportGeneration,
        private readonly ReportRepositoryInterface $reports,
    ) {
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('reports.index', [
            'project' => $project,
            'reports' => $this->reports->forProject($project->id),
        ]);
    }

    public function store(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $this->reportGeneration->generate($project, GeneratedBy::OnDemand);
        } catch (PythonExecutionException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return redirect()
            ->route('projects.reports.index', $project)
            ->with('status', 'Rapport généré.');
    }

    public function download(Project $project, Report $report): BinaryFileResponse
    {
        $this->authorize('view', $project);

        return response()->download($report->storage_path, basename($report->storage_path));
    }

    public function destroy(Project $project, Report $report): RedirectResponse
    {
        $this->authorize('update', $project);

        if (file_exists($report->storage_path)) {
            unlink($report->storage_path);
        }

        $this->reports->delete($report);

        return redirect()->route('projects.reports.index', $project)->with('status', 'Rapport supprimé.');
    }
}
