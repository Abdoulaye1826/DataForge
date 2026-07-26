<?php

namespace App\Services\Ai;

use App\Models\Project;
use App\Services\Ai\Contracts\AiProviderInterface;

/**
 * Module Rapport narratif complet (§10): the closing paragraph of the PDF
 * report - a single AI call that reads the already-aggregated numbers (not
 * raw data) and synthesizes them into 3-5 sentences a non-technical reader
 * can act on. Grounded in the same business context as everything else;
 * guarded by the caller like every other AI enrichment in the app.
 */
class ReportConclusionService
{
    public function __construct(private readonly AiProviderInterface $provider)
    {
    }

    public function synthesize(Project $project, string $context): ?string
    {
        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du texte brut, sans balises markdown, sans JSON.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $conclusion = trim($result['content']);

        return $conclusion !== '' ? $conclusion : null;
    }

    private function prompt(string $context): string
    {
        return <<<PROMPT
            Tu es un data analyst expert qui conclut un rapport d'analyse pour un lecteur non-technique (ex : un directeur). À partir de la synthèse ci-dessous (déjà calculée, ne recalcule rien), rédige une conclusion en français (3 à 5 phrases) qui résume l'essentiel : l'état général des données, le point le plus important à retenir, et la ou les prochaines actions recommandées compte tenu de l'objectif du projet.

            Base-toi UNIQUEMENT sur les éléments fournis - n'invente aucun fait qui n'y figure pas. Pas de titre, pas de puces, juste un paragraphe fluide et actionnable. Ne renvoie que ce texte, rien d'autre.

            {$context}
            PROMPT;
    }
}
