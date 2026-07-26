<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Quality\DataQualityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class QualityController extends Controller
{
    public function __construct(private readonly DataQualityService $dataQuality)
    {
    }

    public function show(Project $project, Dataset $dataset, DatasetTable $table): View
    {
        $this->authorize('view', $project);

        return view('quality.show', [
            'project' => $project,
            'dataset' => $dataset,
            'table' => $table->load('latestQualityReport'),
        ]);
    }

    public function refresh(Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->dataQuality->generate($table, $project);

        return redirect()
            ->route('projects.datasets.tables.quality.show', [$project, $dataset, $table])
            ->with('status', 'Audit qualité régénéré.');
    }
}
