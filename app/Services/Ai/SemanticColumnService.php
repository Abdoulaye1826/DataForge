<?php

namespace App\Services\Ai;

use App\Enums\ColumnBusinessCategory;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Ai\Contracts\AiProviderInterface;

/**
 * Module Compréhension sémantique: one grouped AI call per table (not one
 * per column, to stay cheap) that turns technical column names + detected
 * types + sample values into a business-readable label ("customer_id" ->
 * "Identifiant client"), a business category ("amount" -> Monetary, distinct
 * from its purely technical ColumnType of Float), and a confidence score -
 * grounded in the project's business context (§1) when available.
 */
class SemanticColumnService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    public function generateForTable(DatasetTable $table, Project $project): void
    {
        $context = $this->contextBuilder->columnsContextForSemantics($table, $project);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du JSON valide, sans texte autour, sans balises markdown.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $parsed = $this->parseJson($result['content']);

        foreach ($table->columns as $column) {
            $entry = $this->parseColumnEntry($parsed[$column->name] ?? null);

            if ($entry === null) {
                continue;
            }

            $column->update($entry);
        }
    }

    /**
     * @return array{semantic_label: string, semantic_reasoning: ?string, business_category: ?string, semantic_confidence: ?float}|null
     */
    private function parseColumnEntry(mixed $entry): ?array
    {
        if (! is_array($entry) || ! is_string($entry['label'] ?? null) || trim($entry['label']) === '') {
            return null;
        }

        $category = $entry['business_category'] ?? null;
        $confidence = $entry['confidence'] ?? null;

        return [
            'semantic_label' => trim($entry['label']),
            'semantic_reasoning' => is_string($entry['reasoning'] ?? null) ? trim($entry['reasoning']) : null,
            'business_category' => is_string($category) && ColumnBusinessCategory::tryFrom($category) !== null ? $category : null,
            'semantic_confidence' => is_numeric($confidence) && $confidence >= 0 && $confidence <= 1 ? (float) $confidence : null,
        ];
    }

    private function prompt(string $context): string
    {
        $categories = implode(', ', array_map(fn ($case) => $case->value, ColumnBusinessCategory::cases()));

        return <<<PROMPT
            Tu es un data analyst expert. Pour chaque colonne ci-dessous (nom technique, type détecté, exemples de valeurs réelles), déduis sa signification métier probable.

            Réponds en JSON STRICT avec exactement cette forme, une clé par nom de colonne exact fourni ci-dessous :
            {"nom_colonne": {"label": "Signification métier courte (2-4 mots, en français)", "reasoning": "Justification en une phrase courte", "business_category": "une valeur EXACTE parmi : {$categories}", "confidence": 0.0 à 1.0}, ...}

            "business_category" classe le rôle métier de la colonne (distinct de son type technique) : {$categories}. "confidence" reflète ta certitude sur cette interprétation (1.0 = évident, 0.5 = incertain).

            Base-toi UNIQUEMENT sur le nom, le type et les exemples fournis - n'invente rien d'autre. Si une colonne est déjà un nom métier clair, garde-le tel quel comme label. Ne renvoie RIEN d'autre que ce JSON (pas de texte avant/après, pas de balises markdown).

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
}
