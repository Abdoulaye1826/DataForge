<?php

namespace App\Services\Ai;

use App\Enums\ProjectDomain;
use App\Models\Dataset;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Module Compréhension: infers the likely business domain of a dataset from
 * its table/column names and sample values alone (see
 * AiContextBuilder::datasetContextForDomainDetection) - independent of
 * Project::domain, which stays a manual, user-supplied field (§1) by
 * design. This is a complementary signal shown in the Dataset Intelligence
 * report, never used to silently overwrite the user's own choice.
 */
class DomainDetectionService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    /**
     * Never throws - domain detection is a nice-to-have on top of the
     * import, not something that should fail the whole cascade if the AI
     * call errors out (same guarded pattern as the other onboarding steps).
     */
    public function detectSafely(Dataset $dataset): void
    {
        try {
            $this->detect($dataset);
        } catch (Throwable $e) {
            Log::warning("Domain detection skipped for dataset {$dataset->id}: {$e->getMessage()}");
        }
    }

    private function detect(Dataset $dataset): void
    {
        $dataset->loadMissing('tables.columns');

        $context = $this->contextBuilder->datasetContextForDomainDetection($dataset);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du JSON valide, sans texte autour, sans balises markdown.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $parsed = $this->parseResult($result['content']);

        if ($parsed === null) {
            return;
        }

        $dataset->update([
            'detected_domain' => $parsed['domain'],
            'detected_domain_confidence' => $parsed['confidence'],
            // import_meta already holds tables_count/skipped_sheets/file_meta
            // from the import step - merge, don't replace, so this doesn't
            // erase them.
            'import_meta' => [...($dataset->import_meta ?? []), 'language' => $parsed['language']],
        ]);
    }

    /**
     * @return array{domain: string, confidence: float, language: ?string}|null
     */
    private function parseResult(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(json)?/i', '', $content);
        $content = preg_replace('/```$/', '', trim($content));
        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded)) {
            return null;
        }

        $domain = $decoded['domain'] ?? null;
        $confidence = $decoded['confidence'] ?? null;
        $language = $decoded['language'] ?? null;

        if (! is_string($domain) || ProjectDomain::tryFrom($domain) === null) {
            return null;
        }

        if (! is_numeric($confidence) || $confidence < 0 || $confidence > 1) {
            return null;
        }

        return [
            'domain' => $domain,
            'confidence' => (float) $confidence,
            'language' => is_string($language) && trim($language) !== '' ? trim($language) : null,
        ];
    }

    private function prompt(string $context): string
    {
        $domains = implode(', ', array_map(fn ($case) => $case->value, ProjectDomain::cases()));

        return <<<PROMPT
            Tu es un data analyst expert. À partir des tables et colonnes ci-dessous (noms, sens métier déjà déduit quand disponible, exemples de valeurs réelles), déduis le domaine métier le plus probable de ce dataset, ainsi que la langue principale des données (noms de colonnes et valeurs textuelles).

            Réponds en JSON STRICT avec exactement cette forme :
            {"domain": "une valeur EXACTE parmi : {$domains}", "confidence": 0.0 à 1.0, "language": "nom de la langue en français, ex: Français, Anglais"}

            Choisis "other" si aucun domaine ne correspond clairement. "confidence" reflète ta certitude sur le domaine (1.0 = évident, 0.5 = incertain). Base-toi UNIQUEMENT sur les données fournies - n'invente rien. Ne renvoie RIEN d'autre que ce JSON.

            {$context}
            PROMPT;
    }
}
