<?php

namespace App\Services\Visualization;

use App\Enums\ChartType;
use App\Enums\PipelineSuggestionStatus;
use App\Enums\VisualizationSource;
use App\Enums\WidgetType;
use App\Models\Dashboard;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Visualization;
use App\Models\VisualizationSuggestion;
use App\Repositories\Contracts\VisualizationSuggestionRepositoryInterface;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Dashboard\DashboardService;
use Illuminate\Support\Collection;

/**
 * Module Suggestions IA de visualisations : au lieu d'attendre que
 * l'utilisateur pense lui-même à créer tel ou tel graphique, l'IA regarde ce
 * que §1-§3 savent déjà de chaque table du projet (contexte métier, qualité,
 * sémantique des colonnes) et ce qui existe déjà comme visualisations, et
 * propose une liste ordonnée de graphiques NOUVEAUX avec leur justification.
 * Accepter une suggestion ne fait jamais rien de spécial : ça délègue à
 * VisualizationService::create(), le même moteur qu'une création manuelle
 * depuis la page Visualisations, et ajoute optionnellement le résultat comme
 * widget sur le dashboard depuis lequel la suggestion a été acceptée.
 */
class VisualizationRecommendationService
{
    private const MAX_SUGGESTIONS = 6;
    private const MAX_TABLES = 6;

    public function __construct(
        private readonly VisualizationSuggestionRepositoryInterface $suggestions,
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    public function propose(Project $project): Collection
    {
        $tables = $project->datasets->flatMap(fn ($dataset) => $dataset->tables)->take(self::MAX_TABLES)->values();

        if ($tables->isEmpty()) {
            return new Collection();
        }

        $context = $this->buildContext($project, $tables);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du JSON valide, sans texte autour, sans balises markdown.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $tablesById = $tables->keyBy('id');
        $items = $this->parseItems($result['content'], $tablesById);

        $this->suggestions->deletePendingForProject($project->id);

        $created = new Collection();

        foreach (array_slice($items, 0, self::MAX_SUGGESTIONS) as $item) {
            $created->push($this->suggestions->create([
                'project_id' => $project->id,
                'dataset_table_id' => $item['table_id'],
                'chart_type' => $item['chart_type'],
                'name' => $item['name'],
                'config' => $item['config'],
                'rationale' => $item['rationale'],
                'status' => PipelineSuggestionStatus::Pending,
                'computed_at' => now(),
            ]));
        }

        return $created;
    }

    /**
     * Accepte une suggestion : crée la Visualization réelle et, si un
     * dashboard est fourni, l'ajoute directement dessus comme widget - le
     * "proposer de les créer directement [dans mon dashboard]" demandé.
     */
    public function accept(
        VisualizationSuggestion $suggestion,
        VisualizationService $visualizationService,
        ?Dashboard $dashboard = null,
        ?DashboardService $dashboardService = null,
    ): Visualization {
        $visualization = $visualizationService->create(
            $suggestion->table,
            $suggestion->project,
            $suggestion->name,
            $suggestion->chart_type,
            $suggestion->config,
            VisualizationSource::AiRecommended,
            $suggestion->rationale,
        );

        if ($dashboard && $dashboardService) {
            $dashboardService->addWidget($dashboard, WidgetType::Chart, $visualization->name, [], $visualization->id);
        }

        $suggestion->update(['status' => PipelineSuggestionStatus::Accepted]);

        return $visualization;
    }

    public function reject(VisualizationSuggestion $suggestion): void
    {
        $suggestion->update(['status' => PipelineSuggestionStatus::Rejected]);
    }

    private function buildContext(Project $project, Collection $tables): string
    {
        $lines = [];

        if ($businessContext = $project->businessContextLine()) {
            $lines[] = $businessContext;
            $lines[] = '';
        }

        foreach ($tables as $table) {
            $lines[] = "=== Table « {$table->name} » (id={$table->id}) ===";
            $lines[] = $this->contextBuilder->recommendationContext($table);

            $existing = $table->visualizations;
            if ($existing->isNotEmpty()) {
                $lines[] = '';
                $lines[] = 'Visualisations déjà créées pour cette table (ne pas en proposer un doublon) :';
                foreach ($existing as $v) {
                    $lines[] = "- {$v->name} ({$v->chart_type->label()})";
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function prompt(string $context): string
    {
        return <<<PROMPT
            Tu es un data analyst expert. À partir de l'état réel des tables ci-dessous (contexte métier, qualité, sémantique des colonnes, visualisations déjà créées), propose une liste ORDONNÉE de visualisations NOUVELLES (qui n'existent pas déjà) qui seraient les plus utiles à ajouter à ce dashboard, des plus importantes aux moins importantes.

            Réponds en JSON STRICT avec exactement cette forme :
            {"suggestions": [{"table_id": <id numérique de la table concernée>, "chart_type": "...", "name": "titre court du graphique", "config": {...}, "rationale": "justification courte et concrète en français, ex: montre l'évolution des ventes sur la période"}, ...]}

            "chart_type" doit être EXACTEMENT une de ces valeurs : bar, line, pie, donut, scatter, histogram, heatmap, radar, treemap, boxplot.
            "config" doit utiliser EXACTEMENT ces clés selon chart_type (n'utilise que des noms de colonnes qui existent réellement dans la table indiquée par table_id) :
            - bar, line : {"x_column": "...", "y_column": "..." (optionnel), "aggregation": "count"|"sum"|"mean" (optionnel, défaut count)}
            - pie, donut : {"category_column": "...", "value_column": "..." (optionnel), "aggregation": "count"|"sum"|"mean" (optionnel)}
            - scatter : {"x_column": "...", "y_column": "..."}
            - histogram : {"column": "..."}
            - heatmap : {"columns": [...] (optionnel, toutes les colonnes numériques par défaut)}
            - radar : {"category_column": "...", "value_columns": [...]}
            - treemap : {"category_column": "...", "value_column": "..." (optionnel), "aggregation": "..." (optionnel)}
            - boxplot : {"columns": [...]}

            Ne propose QUE des visualisations réellement pertinentes vu le contexte métier et les données disponibles, et qui n'existent pas déjà. N'invente rien. Si rien de nouveau n'est pertinent, renvoie {"suggestions": []}. Maximum 6 suggestions. Ne renvoie RIEN d'autre que ce JSON.

            {$context}
            PROMPT;
    }

    /**
     * @param Collection<int, DatasetTable> $tablesById
     * @return array<int, array{table_id: int, chart_type: string, name: string, config: array, rationale: string}>
     */
    private function parseItems(string $content, Collection $tablesById): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(json)?/i', '', $content);
        $content = preg_replace('/```$/', '', trim($content));
        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded) || ! is_array($decoded['suggestions'] ?? null)) {
            return [];
        }

        $validChartTypes = array_map(fn (ChartType $type) => $type->value, ChartType::cases());

        $items = [];

        foreach ($decoded['suggestions'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $tableId = (int) ($item['table_id'] ?? 0);
            $chartType = $item['chart_type'] ?? null;
            $name = is_string($item['name'] ?? null) ? trim($item['name']) : '';
            $rationale = is_string($item['rationale'] ?? null) ? trim($item['rationale']) : '';
            $config = is_array($item['config'] ?? null) ? $item['config'] : null;

            if (
                ! $tablesById->has($tableId)
                || ! in_array($chartType, $validChartTypes, true)
                || $name === ''
                || $rationale === ''
                || $config === null
                || ! $this->configReferencesRealColumns($config, $tablesById->get($tableId))
            ) {
                continue;
            }

            $items[] = [
                'table_id' => $tableId,
                'chart_type' => $chartType,
                'name' => $name,
                'config' => $config,
                'rationale' => $rationale,
            ];
        }

        return $items;
    }

    /**
     * Defense in depth: only allow column names that really exist on the
     * table this suggestion targets, in case the model hallucinated one
     * despite the prompt's instruction not to - VisualizationService::create()
     * would otherwise happily persist a suggestion that fails at compute time.
     */
    private function configReferencesRealColumns(array $config, ?DatasetTable $table): bool
    {
        if (! $table) {
            return false;
        }

        $realNames = $table->columns->pluck('name')->all();

        foreach (['x_column', 'y_column', 'category_column', 'value_column', 'column'] as $key) {
            if (isset($config[$key]) && ! in_array($config[$key], $realNames, true)) {
                return false;
            }
        }

        foreach (['columns', 'value_columns'] as $key) {
            if (! isset($config[$key])) {
                continue;
            }

            if (! is_array($config[$key])) {
                return false;
            }

            foreach ($config[$key] as $column) {
                if (! in_array($column, $realNames, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
