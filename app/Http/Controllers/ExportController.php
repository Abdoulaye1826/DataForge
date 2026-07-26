<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Export\TableExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(private readonly TableExportService $tableExport)
    {
    }

    public function table(Request $request, Project $project, Dataset $dataset, DatasetTable $table): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $project);

        $request->validate(['format' => ['required', 'in:csv,xlsx,json']]);

        try {
            $export = $this->tableExport->export($table, $project, $request->string('format')->value());
        } catch (PythonExecutionException $e) {
            return back()->withErrors(['export' => $e->getMessage()]);
        }

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(true);
    }
}
