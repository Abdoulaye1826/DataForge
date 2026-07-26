<?php

namespace App\Services\Report;

use App\Enums\GeneratedBy;
use App\Enums\InsightCategory;
use App\Exceptions\AiProviderException;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Report;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Ai\ReportConclusionService;
use App\Services\Python\PythonRunnerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Module Rapport narratif complet (§10): assembles everything already
 * computed for a project into one printable PDF, organized as the narrative
 * arc requested - Contexte & Objectif (§1) -> Qualité agrégée (narratif §3)
 * -> Préparation (historique du pipeline justifié, §7) -> Résultats &
 * Visualisations (légendes justifiées, §8) -> Insights triés par sévérité
 * (§9) -> Recommandations agrégées -> Conclusion (synthèse IA). Nothing here
 * recomputes analysis - it only reads and formats what the pipeline already
 * produced, plus one Python round-trip to rasterize charts (dompdf has no
 * JS engine) and one guarded AI call for the closing paragraph.
 */
class ReportGenerationService
{
    private const MAX_CHARTS_PER_TABLE = 2;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly PythonRunnerService $pythonRunner,
        private readonly ReportConclusionService $conclusionService,
    ) {
    }

    public function generate(Project $project, GeneratedBy $generatedBy = GeneratedBy::OnDemand): Report
    {
        $project->load([
            'datasets.tables.latestQualityReport',
            'datasets.tables.latestAnalysis',
            'datasets.tables.aiInsights',
            'datasets.tables.visualizations',
        ]);

        $tables = $project->datasets->flatMap(fn ($dataset) => $dataset->tables->map(fn ($table) => [$dataset, $table]));

        $chartImages = $this->renderCharts($tables->map(fn ($pair) => $pair[1]), $project->id);

        $resultsSections = $this->presentSections($tables, $chartImages);

        $qualityOverview = $this->buildQualityOverview($tables);
        $preparation = $project->pipelineSteps()->orderBy('step_order')->get();
        $allInsights = $tables->flatMap(fn ($pair) => $pair[1]->aiInsights);
        $insightsBySeverity = $this->groupInsightsBySeverity($allInsights);
        $recommendations = $allInsights->where('category', InsightCategory::Recommendation)->values();

        $conclusion = $this->generateConclusionSafely($project, $qualityOverview, $allInsights, $recommendations);

        $html = view('reports.pdf', [
            'project' => $project,
            'qualityOverview' => $qualityOverview,
            'preparation' => $preparation,
            'resultsSections' => $resultsSections,
            'insightsBySeverity' => $insightsBySeverity,
            'recommendations' => $recommendations,
            'conclusion' => $conclusion,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $binary = $pdf->output();

        $directory = config('dataforge.reports.storage_path') . DIRECTORY_SEPARATOR . $project->id;
        File::ensureDirectoryExists($directory);

        $filename = Str::slug($project->name) . '-' . now()->format('Y-m-d-His') . '-' . Str::random(6) . '.pdf';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        File::put($path, $binary);

        return $this->reports->create([
            'project_id' => $project->id,
            'title' => "Rapport · {$project->name} · " . now()->format('d/m/Y'),
            'sections' => collect($resultsSections)->pluck('table_name')->push('overview')->unique()->values()->all(),
            'storage_path' => $path,
            'size_bytes' => File::size($path),
            'generated_by' => $generatedBy,
        ]);
    }

    /**
     * Renders up to MAX_CHARTS_PER_TABLE visualizations per table into PNGs
     * in a single Python call, keyed by visualization id for easy lookup.
     *
     * @return array<int, string> visualization id => base64 PNG
     */
    private function renderCharts(Collection $allTables, int $projectId): array
    {
        $selected = $allTables->flatMap(function (DatasetTable $table) {
            return $table->visualizations
                ->filter(fn ($v) => ! empty($v->data_cache))
                ->take(self::MAX_CHARTS_PER_TABLE);
        });

        if ($selected->isEmpty()) {
            return [];
        }

        $result = $this->pythonRunner->run('render_chart_image.py', [
            'charts' => $selected->values()->map(fn ($v) => [
                'chart_type' => $v->chart_type->value,
                'name' => $v->name,
                'data' => $v->data_cache,
            ])->all(),
        ], $projectId);

        $images = collect($result->data['images']);

        return $selected->values()
            ->mapWithKeys(fn ($v, $index) => [$v->id => $images->get($index)['base64'] ?? null])
            ->filter()
            ->all();
    }

    private function presentSections(Collection $tables, array $chartImages): array
    {
        return $tables->map(function ($pair) use ($chartImages) {
            [$dataset, $table] = $pair;

            return [
                'table_name' => $table->name,
                'dataset_name' => $dataset->name,
                'row_count' => $table->row_count,
                'column_count' => $table->column_count,
                'quality' => $table->latestQualityReport,
                'analysis' => $table->latestAnalysis,
                'charts' => $table->visualizations
                    ->filter(fn ($v) => isset($chartImages[$v->id]))
                    ->map(fn ($v) => ['name' => $v->name, 'rationale' => $v->rationale, 'base64' => $chartImages[$v->id]])
                    ->values(),
            ];
        })->values()->all();
    }

    /**
     * @return array{tables: Collection, average_score: ?float}
     */
    private function buildQualityOverview(Collection $tables): array
    {
        $rows = $tables->map(fn ($pair) => [
            'table_name' => $pair[1]->name,
            'report' => $pair[1]->latestQualityReport,
        ])->filter(fn ($row) => $row['report'] !== null)->values();

        $averageScore = $rows->isNotEmpty() ? round($rows->avg(fn ($row) => $row['report']->score), 1) : null;

        return ['tables' => $rows, 'average_score' => $averageScore];
    }

    private function groupInsightsBySeverity(Collection $insights): Collection
    {
        return collect(['high', 'medium', 'low'])
            ->map(fn ($level) => [
                'level' => $level,
                'items' => $insights->filter(fn ($i) => $i->importance->value === $level)
                    ->sortBy(fn ($i) => array_search($i->category, InsightCategory::ordered(), true))
                    ->values(),
            ])
            ->filter(fn ($group) => $group['items']->isNotEmpty())
            ->values();
    }

    /**
     * The AI conclusion is the report's final flourish, not a dependency -
     * a missing/broken AI provider must never block report generation,
     * same guard pattern as every other AI enrichment in the app.
     */
    private function generateConclusionSafely(Project $project, array $qualityOverview, Collection $allInsights, Collection $recommendations): ?string
    {
        try {
            return $this->conclusionService->synthesize($project, $this->conclusionContext($project, $qualityOverview, $allInsights, $recommendations));
        } catch (AiProviderException $e) {
            Log::warning("Report conclusion generation skipped for project {$project->id}: {$e->getMessage()}");

            return null;
        }
    }

    private function conclusionContext(Project $project, array $qualityOverview, Collection $allInsights, Collection $recommendations): string
    {
        $lines = [];

        if ($businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
        }

        if ($qualityOverview['average_score'] !== null) {
            $lines[] = "Score qualité moyen sur {$qualityOverview['tables']->count()} table(s) : {$qualityOverview['average_score']}/100.";
        }

        $risks = $allInsights->where('category', InsightCategory::Risk)->pluck('content');
        if ($risks->isNotEmpty()) {
            $lines[] = 'Principaux risques identifiés : ' . $risks->implode(' | ');
        }

        if ($recommendations->isNotEmpty()) {
            $lines[] = 'Recommandations déjà formulées : ' . $recommendations->pluck('content')->implode(' | ');
        }

        return implode("\n", $lines);
    }
}
