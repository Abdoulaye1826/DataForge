<?php

namespace App\Services\Ai;

use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\QualityReport;
use App\Services\Ai\Contracts\AiProviderInterface;

/**
 * Module Diagnostic qualité narratif: turns the score/summary/details
 * already computed by quality_audit.py into a short written diagnostic
 * ("Votre dataset obtient 81/100. Les principaux problèmes sont...") instead
 * of raw numbers alone, adapted to the project's business context (§1) when
 * available. Reuses the same guarded, single-call pattern as AiInsightService
 * - the numeric score/grade always remain the source of truth, this is a
 * bonus explanation layered on top, never a replacement.
 */
class QualityNarrativeService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    public function generate(DatasetTable $table, QualityReport $report, Project $project): void
    {
        $context = $this->contextBuilder->qualityContext($table, $report, $project);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du texte brut, sans balises markdown, sans JSON.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $narrative = trim($result['content']);

        if ($narrative !== '') {
            $report->update(['narrative' => $narrative]);
        }
    }

    private function prompt(string $context): string
    {
        return <<<PROMPT
            Tu es un data analyst expert qui explique un audit qualité à quelqu'un de non-technique. À partir des chiffres ci-dessous (déjà calculés, ne les recalcule pas), rédige un court diagnostic en français (3 à 5 phrases) qui explique CE SCORE en langage clair : ce qui va bien, les 1-3 problèmes les plus importants (en les priorisant par impact, pas juste en les listant), et pourquoi ça compte pour ce projet précis si un contexte métier est fourni.

            Base-toi UNIQUEMENT sur les chiffres fournis - n'invente aucun fait qui n'y figure pas. Pas de titre, pas de puces, juste un paragraphe fluide. Ne renvoie que ce texte, rien d'autre.

            {$context}
            PROMPT;
    }
}
