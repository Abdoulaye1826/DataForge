<?php

namespace App\Http\Controllers;

use App\Enums\StatisticalTestType;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\StatisticalTest;
use App\Services\Analysis\StatisticalTestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Throwable;

class StatisticalTestController extends Controller
{
    public function __construct(private readonly StatisticalTestService $statisticalTests)
    {
    }

    public function store(Request $request, Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'test_type' => ['required', new Enum(StatisticalTestType::class)],
        ]);

        $type = StatisticalTestType::from($request->input('test_type'));

        try {
            $this->statisticalTests->run($table, $project, $type, $this->buildConfig($request));
        } catch (Throwable $e) {
            return back()->withErrors(['statistical_test' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('projects.datasets.tables.analysis.show', [$project, $dataset, $table])
            ->with('status', 'Test statistique en cours de calcul...');
    }

    public function destroy(Project $project, Dataset $dataset, DatasetTable $table, StatisticalTest $statisticalTest): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->statisticalTests->delete($statisticalTest);

        return redirect()
            ->route('projects.datasets.tables.analysis.show', [$project, $dataset, $table])
            ->with('status', 'Test statistique supprimé.');
    }

    private function buildConfig(Request $request): array
    {
        return array_filter([
            'numeric_column' => $request->input('numeric_column'),
            'group_column' => $request->input('group_column'),
            'group_a' => $request->input('group_a'),
            'group_b' => $request->input('group_b'),
            'column_a' => $request->input('column_a'),
            'column_b' => $request->input('column_b'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
