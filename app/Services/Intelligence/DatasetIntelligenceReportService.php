<?php

namespace App\Services\Intelligence;

use App\DTO\DatasetIntelligenceReport;
use App\Enums\InsightCategory;
use App\Models\Dataset;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use App\Repositories\Contracts\DatasetRelationshipRepositoryInterface;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;

/**
 * Builds the "Rapport d'intelligence" for one dataset by aggregating what
 * the import pipeline already computed (quality, semantic columns,
 * relationships, AI insights, pipeline suggestions) - nothing here triggers
 * new computation, it only reads already-persisted results.
 */
class DatasetIntelligenceReportService
{
    public function __construct(
        private readonly DatasetRelationshipRepositoryInterface $relationships,
        private readonly AiInsightRepositoryInterface $insights,
        private readonly PipelineSuggestionRepositoryInterface $pipelineSuggestions,
    ) {
    }

    public function build(Dataset $dataset): DatasetIntelligenceReport
    {
        $dataset->loadMissing(['tables.columns', 'tables.latestQualityReport']);

        $tableIds = $dataset->tables->pluck('id')->all();

        return new DatasetIntelligenceReport(
            dataset: $dataset,
            tables: $dataset->tables,
            relationships: $tableIds === [] ? collect() : $this->relationships->forTables($tableIds),
            summaryInsights: $tableIds === [] ? collect() : $this->insights->forTables($tableIds, InsightCategory::Summary),
            recommendationInsights: $tableIds === [] ? collect() : $this->insights->forTables($tableIds, InsightCategory::Recommendation),
            pendingSuggestions: $tableIds === [] ? collect() : $this->pipelineSuggestions->pendingForTables($tableIds),
        );
    }
}
