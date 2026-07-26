<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStepType;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\PipelineStep;
use App\Models\Project;
use App\Services\Cleaning\DataCleaningService;
use App\Services\Pipeline\UserPipelinePreferenceService;
use App\Services\Preprocessing\PreprocessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Throwable;

/**
 * Single generic endpoint for every Nettoyage/Prétraitement operation
 * (rather than 15 separate routes/forms) - the transformation modal on
 * datasets/show.blade.php posts {step_type, ...operation-specific fields}
 * here and this dispatches to the matching named service method.
 */
class PipelineStepController extends Controller
{
    public function __construct(
        private readonly DataCleaningService $cleaning,
        private readonly PreprocessingService $preprocessing,
        private readonly UserPipelinePreferenceService $preferences,
    ) {
    }

    public function store(Request $request, Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'step_type' => ['required', new Enum(PipelineStepType::class)],
        ]);

        $type = PipelineStepType::from($request->input('step_type'));

        if ($type->category() === 'import') {
            abort(422, "Cette étape ne peut pas être appliquée manuellement.");
        }

        try {
            $step = $this->dispatch($type, $request, $table, $project);

            // Module Notebook explicatif: the "why" is always optional and
            // never blocks the transformation itself - a step applies the
            // same way whether or not the user bothers to document it.
            if ($rationale = trim((string) $request->input('rationale'))) {
                $step->update(['rationale' => $rationale]);
            }

            // Module Mémoire inter-projets: only a genuinely manual choice
            // teaches the user's habits - never a step converted from an
            // accepted AI suggestion (that flow bypasses this controller).
            $this->preferences->recordManualStep($request->user(), $type, ['columns' => $this->columns($request) ?? []]);
        } catch (Throwable $e) {
            return back()->withErrors(['transformation' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', 'Transformation appliquée.');
    }

    private function dispatch(PipelineStepType $type, Request $request, DatasetTable $table, Project $project): PipelineStep
    {
        return match ($type) {
            PipelineStepType::Dedupe => $this->cleaning->dedupe($table, $project, $this->columns($request)),
            PipelineStepType::TrimSpaces => $this->cleaning->trimSpaces($table, $project, $this->columns($request)),
            PipelineStepType::FixCase => $this->cleaning->fixCase($table, $project, $this->columns($request) ?? [], $request->input('mode', 'title')),
            PipelineStepType::FixDates => $this->cleaning->fixDates($table, $project, $request->input('column')),

            PipelineStepType::RenameColumn => $this->preprocessing->renameColumn($table, $project, $request->input('old_name'), $request->input('new_name')),
            PipelineStepType::DropColumn => $this->preprocessing->dropColumn($table, $project, $this->columns($request) ?? []),
            PipelineStepType::Merge => $this->preprocessing->mergeColumns($table, $project, $this->columns($request) ?? [], $request->input('new_column'), $request->input('separator', ' ')),
            PipelineStepType::Split => $this->preprocessing->splitColumn($table, $project, $request->input('column'), $this->commaList($request->input('new_columns')), $request->input('separator', ' ')),
            PipelineStepType::Filter => $this->preprocessing->filter($table, $project, $request->input('column'), $request->input('operator'), $request->input('value')),
            PipelineStepType::CreateColumn => $this->preprocessing->createColumn($table, $project, $request->input('new_column'), $request->input('expression')),
            PipelineStepType::ConvertType => $this->preprocessing->convertType($table, $project, $request->input('column'), $request->input('target_type')),
            PipelineStepType::Encode => $this->preprocessing->encode($table, $project, $request->input('column'), $request->input('method', 'label')),
            PipelineStepType::Normalize => $this->preprocessing->normalize($table, $project, $request->input('column')),
            PipelineStepType::Standardize => $this->preprocessing->standardize($table, $project, $request->input('column')),
            PipelineStepType::Categorize => $this->preprocessing->categorize(
                $table,
                $project,
                $request->input('column'),
                (int) $request->input('bins', 4),
                $this->commaList($request->input('labels')),
            ),
            default => throw new \InvalidArgumentException("Étape non applicable manuellement : {$type->value}"),
        };
    }

    private function columns(Request $request): ?array
    {
        $columns = $request->input('columns');

        return $columns === null || $columns === [] ? null : (array) $columns;
    }

    private function commaList(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
