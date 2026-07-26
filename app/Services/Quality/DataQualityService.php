<?php

namespace App\Services\Quality;

use App\Exceptions\AiProviderException;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\QualityReport;
use App\Repositories\Contracts\QualityReportRepositoryInterface;
use App\Services\Ai\QualityNarrativeService;
use App\Services\Python\PythonRunnerService;
use Illuminate\Support\Facades\Log;

/**
 * Module Audit qualité: runs quality_audit.py on a table's cached data and
 * stores the resulting score/grade/findings. A new QualityReport row is
 * created on every run (never overwritten) so quality trends over time are
 * preserved; callers read the latest one via DatasetTable::latestQualityReport().
 */
class DataQualityService
{
    public function __construct(
        private readonly QualityReportRepositoryInterface $qualityReports,
        private readonly PythonRunnerService $pythonRunner,
        private readonly QualityNarrativeService $narrative,
    ) {
    }

    public function generate(DatasetTable $table, Project $project): QualityReport
    {
        $result = $this->pythonRunner->run('quality_audit.py', [
            'storage_path' => $table->storage_path,
        ], $project->id);

        $report = $this->qualityReports->create([
            'dataset_table_id' => $table->id,
            'score' => $result->data['score'],
            'grade' => $result->data['grade'],
            'summary' => $result->data['summary'],
            'details' => $result->data['details'],
            'generated_at' => now(),
        ]);

        $this->generateNarrativeSafely($table, $report, $project);

        return $report;
    }

    /**
     * The written diagnostic is a bonus explanation on top of the numeric
     * score, not a dependency - a missing/broken AI provider must never
     * block the quality audit itself, same guard pattern as Module Insights.
     */
    private function generateNarrativeSafely(DatasetTable $table, QualityReport $report, Project $project): void
    {
        try {
            $this->narrative->generate($table, $report, $project);
        } catch (AiProviderException $e) {
            Log::warning("Quality narrative generation skipped for report {$report->id}: {$e->getMessage()}");
        }
    }
}
