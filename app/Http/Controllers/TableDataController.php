<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Analysis\TableBrowserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableDataController extends Controller
{
    public function __construct(private readonly TableBrowserService $tableBrowser)
    {
    }

    public function show(Project $project, Dataset $dataset, DatasetTable $table): View
    {
        $this->authorize('view', $project);

        return view('tables.data', [
            'project' => $project,
            'dataset' => $dataset,
            'table' => $table,
            'page' => $this->tableBrowser->browse($table, $project),
            'suggestions' => $table->pipelineSuggestions()->pending()->get(),
            'columnsByName' => $table->columns()->get()->keyBy('name'),
        ]);
    }

    public function rows(Request $request, Project $project, Dataset $dataset, DatasetTable $table): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_column' => ['nullable', 'string', 'max:255'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);

        return response()->json($this->tableBrowser->browse(
            $table,
            $project,
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 25),
            $request->input('search'),
            $request->input('sort_column'),
            $request->input('sort_direction', 'asc'),
        ));
    }
}
