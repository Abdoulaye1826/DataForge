<?php

namespace App\Services\Ai;

use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Ai\Contracts\AiProviderInterface;

/**
 * Module Compréhension sémantique: one grouped AI call per table (not one
 * per column, to stay cheap) that turns technical column names + detected
 * types + sample values into a business-readable label ("customer_id" ->
 * "Identifiant client"), grounded in the project's business context (§1)
 * when available.
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
            $entry = $parsed[$column->name] ?? null;

            if (! is_array($entry) || ! is_string($entry['label'] ?? null) || trim($entry['label']) === '') {
                continue;
            }

            $column->update([
                'semantic_label' => trim($entry['label']),
                'semantic_reasoning' => is_string($entry['reasoning'] ?? null) ? trim($entry['reasoning']) : null,
            ]);
        }
    }

    private function prompt(string $context): string
    {
        return <<<PROMPT
            Tu es un data analyst expert. Pour chaque colonne ci-dessous (nom technique, type détecté, exemples de valeurs réelles), déduis sa signification métier probable.

            Réponds en JSON STRICT avec exactement cette forme, une clé par nom de colonne exact fourni ci-dessous :
            {"nom_colonne": {"label": "Signification métier courte (2-4 mots, en français)", "reasoning": "Justification en une phrase courte"}, ...}

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
