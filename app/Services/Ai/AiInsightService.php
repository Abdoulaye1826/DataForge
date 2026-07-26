<?php

namespace App\Services\Ai;

use App\Enums\GeneratedBy;
use App\Enums\InsightCategory;
use App\Enums\InsightImportance;
use App\Models\Analysis;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\AiInsightRepositoryInterface;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Collection;

/**
 * Module Insights: "after each analysis, the AI automatically generates" the
 * eight structured categories from the spec. Always grounded in one table's
 * real EDA results (AiContextBuilder::tableContextForInsights) - the model
 * is instructed to return strict JSON and never invent anything outside the
 * provided data.
 *
 * Module IA proactive (§4): each insight item can additionally carry a
 * "suggested_action" - a structured pointer to a concrete next step (run a
 * forecast, a statistical test, build a chart, apply a cleaning step) that
 * the UI turns into a real button pre-filling the relevant form. The action
 * is only ever a suggestion: nothing executes without the user opening the
 * pre-filled form and confirming it themselves.
 */
class AiInsightService
{
    private const ACTION_TYPES = ['forecast', 'statistical_test', 'visualization', 'cleaning_step'];

    public function __construct(
        private readonly AiInsightRepositoryInterface $insights,
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    public function generateForTable(
        DatasetTable $table,
        Project $project,
        Analysis $analysis,
        GeneratedBy $generatedBy = GeneratedBy::Auto,
    ): Collection {
        $context = $this->contextBuilder->tableContextForInsights($table, $analysis, $project);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du JSON valide, sans texte autour, sans balises markdown.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $parsed = $this->parseJson($result['content']);

        $this->insights->deleteForTable($table->id);

        $created = new Collection();

        foreach (InsightCategory::cases() as $category) {
            foreach ((array) ($parsed[$category->value] ?? []) as $item) {
                [$text, $action] = $this->extractItem($item);

                if ($text === null || trim($text) === '') {
                    continue;
                }

                $created->push($this->insights->create([
                    'project_id' => $project->id,
                    'dataset_table_id' => $table->id,
                    'category' => $category,
                    'content' => $text,
                    'importance' => $this->defaultImportance($category),
                    'suggested_action' => $action,
                    'generated_by' => $generatedBy,
                ]));
            }
        }

        return $created;
    }

    private function prompt(string $context): string
    {
        return <<<PROMPT
            Tu es un data analyst expert. À partir des données ci-dessous (structure de la table et résultats réels de l'analyse exploratoire), génère une analyse structurée en JSON STRICT avec exactement ces clés, chacune un tableau d'éléments :
            {"summary": [...], "key_points": [...], "anomaly": [...], "trend": [...], "opportunity": [...], "risk": [...], "recommendation": [...], "visualization": [...], "conclusion": [...]}

            Chaque élément de chaque tableau est un OBJET {"text": "phrase courte en français (1-2 phrases)", "action": null ou un objet action}. Mets "action": null quand aucune action concrète n'est pertinente (c'est le cas la plupart du temps pour summary/key_points/conclusion).

            Quand une action concrète existe dans l'application (surtout pour risk/opportunity/recommendation/anomaly/trend/visualization), utilise EXACTEMENT une de ces formes, avec des noms de colonnes qui existent réellement dans les données fournies :
            - Prévision de tendance : {"type": "forecast", "params": {"date_column": "...", "value_column": "...", "periods": 4}}
            - Test statistique : {"type": "statistical_test", "params": {"test_type": "t_test", "numeric_column": "...", "group_column": "...", "group_a": "...", "group_b": "..."}} (pour "anova" mêmes clés sans group_a/group_b ; pour "chi_square" utilise "column_a"/"column_b" ; pour "correlation" utilise "column_a"/"column_b" numériques)
            - Graphique : {"type": "visualization", "params": {"chart_type": "histogram", "name": "Titre court", "column": "..."}} en adaptant les params au chart_type : bar/line/scatter -> x_column + y_column ; pie/donut/treemap -> category_column + value_column ; histogram -> column + bins ; heatmap/boxplot -> columns (tableau)
            - Étape de nettoyage : {"type": "cleaning_step", "params": {"step_type": "dedupe", "columns": ["..."]}} (step_type parmi : dedupe, trim_spaces, fix_case, fix_dates, drop_column, convert_type, categorize)

            Pour la clé "visualization", propose 2 à 4 graphiques concrets et utiles à construire à partir de CES colonnes précises, par exemple "Un histogramme de la colonne prix_unitaire pour repérer les valeurs aberrantes" ou "Un graphique en barres des ventes par région pour comparer les performances" - et fournis systématiquement l'action "visualization" correspondante.

            Base-toi UNIQUEMENT sur les données fournies ci-dessous - ne mentionne aucun chiffre ou fait qui n'y figure pas, et ne réfère jamais à une colonne qui n'existe pas. Si une catégorie n'a rien de pertinent, renvoie un tableau vide pour cette clé. Ne renvoie RIEN d'autre que ce JSON (pas de texte avant/après, pas de balises markdown).

            {$context}
            PROMPT;
    }

    private function parseJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(json)?/i', '', $content);
        $content = preg_replace('/```$/', '', trim($content));
        $decoded = json_decode(trim($content), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{0: ?string, 1: ?array} [text, action]
     */
    private function extractItem(mixed $item): array
    {
        if (is_string($item)) {
            return [$item, null];
        }

        if (is_array($item)) {
            $text = is_string($item['text'] ?? null) ? $item['text'] : null;

            return [$text, $this->validActionOrNull($item['action'] ?? null)];
        }

        return [null, null];
    }

    private function validActionOrNull(mixed $action): ?array
    {
        if (! is_array($action) || ! in_array($action['type'] ?? null, self::ACTION_TYPES, true)) {
            return null;
        }

        return [
            'type' => $action['type'],
            'params' => is_array($action['params'] ?? null) ? $action['params'] : [],
        ];
    }

    private function defaultImportance(InsightCategory $category): InsightImportance
    {
        return match ($category) {
            InsightCategory::Risk, InsightCategory::Anomaly => InsightImportance::High,
            InsightCategory::Opportunity, InsightCategory::Recommendation, InsightCategory::Visualization => InsightImportance::Medium,
            default => InsightImportance::Low,
        };
    }
}
