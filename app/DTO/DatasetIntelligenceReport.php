<?php

namespace App\DTO;

use App\Models\Dataset;
use Illuminate\Support\Collection;

/**
 * Everything the "Rapport d'intelligence" view needs for one dataset,
 * bundled into one typed object instead of a sprawling untyped array - built
 * purely by aggregating data the pipeline already computed (quality,
 * relationships, insights, suggestions), nothing recomputed here.
 */
final class DatasetIntelligenceReport
{
    public function __construct(
        public readonly Dataset $dataset,
        /** @var Collection<int, \App\Models\DatasetTable> tables with columns + latestQualityReport eager-loaded */
        public readonly Collection $tables,
        /** @var Collection<int, \App\Models\DatasetRelationship> relationships touching any table of this dataset */
        public readonly Collection $relationships,
        /** @var Collection<int, \App\Models\AiInsight> category=Summary, across this dataset's tables */
        public readonly Collection $summaryInsights,
        /** @var Collection<int, \App\Models\AiInsight> category=Recommendation, across this dataset's tables */
        public readonly Collection $recommendationInsights,
        /** @var Collection<int, \App\Models\PipelineSuggestion> status=pending, across this dataset's tables */
        public readonly Collection $pendingSuggestions,
    ) {
    }
}
