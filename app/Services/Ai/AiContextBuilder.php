<?php

namespace App\Services\Ai;

use App\Models\Analysis;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\QualityReport;

/**
 * Turns real project/table data into a compact text block to ground every
 * AI prompt in - the spec requires the assistant to "answer only using data
 * actually imported", so this is the one place that decides what the model
 * gets to see. Never sends raw rows, only structure/stats already computed
 * by the pipeline (column types, quality scores, EDA results) to keep
 * prompts small regardless of dataset size.
 */
class AiContextBuilder
{
    private const MAX_TABLES = 10;
    private const MAX_COLUMNS_PER_TABLE = 25;

    public function projectContext(Project $project): string
    {
        $lines = ["Projet : {$project->name}"];

        if ($project->description) {
            $lines[] = "Description : {$project->description}";
        }

        if ($businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
        }

        $tables = $project->datasets->flatMap(fn ($dataset) => $dataset->tables->map(fn ($table) => [$dataset, $table]));

        if ($tables->isEmpty()) {
            $lines[] = "Aucune donnée importée pour le moment.";

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = 'Données disponibles :';

        foreach ($tables->take(self::MAX_TABLES) as [$dataset, $table]) {
            $lines[] = $this->tableSummary($dataset->name, $table);
        }

        if ($tables->count() > self::MAX_TABLES) {
            $lines[] = '(' . ($tables->count() - self::MAX_TABLES) . ' autre(s) table(s) non détaillée(s) ici)';
        }

        $recentSteps = $project->pipelineSteps()->latest('applied_at')->limit(10)->get();

        if ($recentSteps->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Transformations récentes déjà appliquées :';
            foreach ($recentSteps as $step) {
                $lines[] = "- {$step->label}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Focused context for insight generation: one table plus its full EDA
     * results (unlike the project-wide chat context, which only summarizes).
     */
    public function tableContextForInsights(DatasetTable $table, Analysis $analysis, ?Project $project = null): string
    {
        $lines = [];

        if ($project && $businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
            $lines[] = '';
        }

        $lines[] = $this->tableSummary($table->dataset->name, $table);
        $lines[] = '';
        $lines[] = 'Résultats de l\'analyse exploratoire (JSON) :';
        $lines[] = json_encode($analysis->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $quality = $table->latestQualityReport;

        if ($quality) {
            $lines[] = '';
            $lines[] = "Audit qualité : score {$quality->score}/100 ({$quality->grade->label()}), détails : " . json_encode($quality->summary, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }

    /**
     * Module Compréhension sémantique: one compact block per column (name,
     * detected type, a few real sample values) so the model can infer
     * meaning ("customer_id" -> "Identifiant client") without ever seeing
     * full rows - grounded in the same business context as everything else.
     */
    public function columnsContextForSemantics(DatasetTable $table, ?Project $project = null): string
    {
        $lines = [];

        if ($project && $businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
            $lines[] = '';
        }

        $lines[] = "Table « {$table->name} » :";

        foreach ($table->columns as $column) {
            $samples = collect($column->sample_values ?? [])->take(5)->implode(', ');
            $lines[] = "- {$column->name} (type détecté : {$column->detected_type->label()}) : exemples de valeurs = [{$samples}]";
        }

        return implode("\n", $lines);
    }

    /**
     * Module Diagnostic qualité narratif: the score/summary/details already
     * computed by quality_audit.py, reinjected as-is (numbers only, no
     * invention) so the model can turn them into prose adapted to the
     * project's business context.
     */
    public function qualityContext(DatasetTable $table, QualityReport $report, ?Project $project = null): string
    {
        $lines = [];

        if ($project && $businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
            $lines[] = '';
        }

        $lines[] = "Table « {$table->name} » : {$table->row_count} lignes, {$table->column_count} colonnes.";
        $lines[] = "Score qualité : {$report->score}/100 ({$report->grade->label()}).";
        $lines[] = 'Résumé : ' . json_encode($report->summary, JSON_UNESCAPED_UNICODE);
        $lines[] = 'Détails : ' . json_encode($report->details, JSON_UNESCAPED_UNICODE);

        return implode("\n", $lines);
    }

    /**
     * Module Pipeline proposé par l'IA (§5): combines what §2 (semantic
     * labels), §3-equivalent quality details, and §1 (business context)
     * already know about a table into one block, so the model can recommend
     * concrete next steps instead of the user picking blindly from a menu.
     * Nothing here triggers new computation - it only reads already-stored
     * results.
     */
    public function recommendationContext(DatasetTable $table, ?Project $project = null): string
    {
        $lines = [];

        if ($project && $businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
            $lines[] = '';
        }

        $lines[] = "Table « {$table->name} » : {$table->row_count} lignes, {$table->column_count} colonnes.";

        $quality = $table->latestQualityReport;

        if ($quality) {
            $lines[] = "Score qualité : {$quality->score}/100 ({$quality->grade->label()}).";
            $lines[] = 'Doublons : ' . ($quality->details['duplicate_rows'] ?? 0) . '.';

            if (! empty($quality->details['useless_columns'])) {
                $lines[] = 'Colonnes constantes ou quasi-vides : ' . implode(', ', $quality->details['useless_columns']) . '.';
            }

            if (! empty($quality->details['highly_correlated_pairs'])) {
                $pairs = collect($quality->details['highly_correlated_pairs'])
                    ->map(fn ($pair) => "{$pair['column_a']} ↔ {$pair['column_b']} (r={$pair['correlation']})")
                    ->implode(', ');
                $lines[] = "Colonnes fortement corrélées : {$pairs}.";
            }
        }

        $lines[] = '';
        $lines[] = 'Colonnes :';

        foreach ($table->columns as $column) {
            $semantic = $column->semantic_label ? " — sens métier probable : « {$column->semantic_label} »" : '';
            $lines[] = sprintf(
                '- %s (%s)%s : %s%% de valeurs manquantes, %d valeurs distinctes%s',
                $column->name,
                $column->detected_type->label(),
                $semantic,
                round($column->null_percentage, 1),
                $column->distinct_count,
                $column->is_useless ? ', marquée peu utile' : '',
            );
        }

        return implode("\n", $lines);
    }

    private function tableSummary(string $datasetName, DatasetTable $table): string
    {
        $lines = ["- Table « {$table->name} » (fichier {$datasetName}) : {$table->row_count} lignes, {$table->column_count} colonnes"];

        $quality = $table->latestQualityReport;
        if ($quality) {
            $lines[] = "  Qualité : {$quality->score}/100 ({$quality->grade->label()})";
        }

        foreach ($table->columns->take(self::MAX_COLUMNS_PER_TABLE) as $column) {
            $lines[] = "  - {$column->name} ({$column->detected_type->label()}){$this->columnSummary($column)}";
        }

        if ($table->columns->count() > self::MAX_COLUMNS_PER_TABLE) {
            $lines[] = '  (' . ($table->columns->count() - self::MAX_COLUMNS_PER_TABLE) . ' autre(s) colonne(s) non détaillée(s))';
        }

        return implode("\n", $lines);
    }

    private function columnSummary($column): string
    {
        if ($column->detected_type->isNumeric() && isset($column->stats['mean'])) {
            return sprintf(
                ' : min=%s, max=%s, moyenne=%s',
                round($column->stats['min'], 2),
                round($column->stats['max'], 2),
                round($column->stats['mean'], 2),
            );
        }

        if (isset($column->stats['top_values'])) {
            $top = collect($column->stats['top_values'])
                ->take(3)
                ->map(fn ($count, $value) => "{$value} ({$count})")
                ->implode(', ');

            return " : valeurs fréquentes: {$top}";
        }

        return '';
    }
}
