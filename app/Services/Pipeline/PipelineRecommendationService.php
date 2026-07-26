<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineStepType;
use App\Enums\PipelineSuggestionStatus;
use App\Models\DatasetTable;
use App\Models\PipelineSuggestion;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Collection;

/**
 * Module Pipeline proposé par l'IA (§5): the inversion-of-control piece -
 * instead of the user picking each cleaning/preprocessing operation from a
 * menu, the AI reads what §1-§3 already know about a table (business
 * context, column semantics, quality issues) and proposes an ordered list
 * of concrete steps with a rationale. This service only ever proposes:
 * accepting a suggestion is handled by the controller, which converts it
 * into a normal PipelineStepService::applyStep() call - the exact same
 * engine a manual transformation goes through. Zero duplication of the
 * execution logic, zero new risk on the part that already works.
 */
class PipelineRecommendationService
{
    private const MAX_SUGGESTIONS = 8;

    public function __construct(
        private readonly PipelineSuggestionRepositoryInterface $suggestions,
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
        private readonly UserPipelinePreferenceService $preferences,
    ) {
    }

    public function propose(DatasetTable $table, Project $project, ?User $user = null): Collection
    {
        $context = $this->contextBuilder->recommendationContext($table, $project);

        $result = $this->provider->chat([
            ['role' => 'system', 'content' => 'Tu réponds uniquement avec du JSON valide, sans texte autour, sans balises markdown.'],
            ['role' => 'user', 'content' => $this->prompt($context)],
        ]);

        $items = $this->parseItems($result['content']);

        // Module Mémoire inter-projets (§6): reinforce with the user's own
        // habits across projects, skipping any column the AI already
        // suggested dropping on its own to avoid a duplicate suggestion.
        if ($user) {
            $alreadySuggestedColumns = collect($items)
                ->where('step_type', PipelineStepType::DropColumn->value)
                ->flatMap(fn ($item) => $item['params']['columns'] ?? []);

            $items = [...$items, ...$this->preferences->reinforcedDropColumnSuggestions($user, $table, $alreadySuggestedColumns)];
        }

        $this->suggestions->deletePendingForTable($table->id);

        $created = new Collection();

        foreach (array_slice($items, 0, self::MAX_SUGGESTIONS) as $item) {
            $created->push($this->suggestions->create([
                'project_id' => $project->id,
                'dataset_table_id' => $table->id,
                'step_type' => $item['step_type'],
                'params' => $item['params'],
                'rationale' => $item['rationale'],
                'status' => PipelineSuggestionStatus::Pending,
                'computed_at' => now(),
            ]));
        }

        return $created;
    }

    public function accept(PipelineSuggestion $suggestion, PipelineStepService $pipelineStepService): void
    {
        $step = $pipelineStepService->applyStep(
            $suggestion->table,
            $suggestion->project,
            $suggestion->step_type,
            $suggestion->params,
        );

        $step->update(['rationale' => $suggestion->rationale]);

        $suggestion->update(['status' => PipelineSuggestionStatus::Accepted]);
    }

    public function reject(PipelineSuggestion $suggestion): void
    {
        $suggestion->update(['status' => PipelineSuggestionStatus::Rejected]);
    }

    private function prompt(string $context): string
    {
        $manualTypes = implode(', ', array_map(
            fn (PipelineStepType $type) => $type->value,
            [...PipelineStepType::cleaningTypes(), ...PipelineStepType::preprocessingTypes()],
        ));

        return <<<PROMPT
            Tu es un data analyst expert. À partir de l'état réel de cette table ci-dessous (qualité, sémantique des colonnes, contexte métier), propose une liste ORDONNÉE d'étapes de nettoyage/prétraitement concrètes et utiles à appliquer, des plus importantes aux moins importantes.

            Réponds en JSON STRICT avec exactement cette forme :
            {"suggestions": [{"step_type": "...", "params": {...}, "rationale": "justification courte et concrète en français, ex: 91% de valeurs vides, aucune valeur analytique"}, ...]}

            "step_type" doit être EXACTEMENT une de ces valeurs : {$manualTypes}.
            "params" doit utiliser EXACTEMENT ces clés selon step_type (n'utilise que des noms de colonnes qui existent réellement dans les données fournies) :
            - dedupe, trim_spaces : {"columns": [...] ou null pour toutes les colonnes texte}
            - fix_case : {"columns": [...], "mode": "title"|"upper"|"lower"}
            - fix_dates : {"column": "..."}
            - rename_column : {"old_name": "...", "new_name": "..."}
            - drop_column : {"columns": [...]}
            - merge : {"columns": [...], "new_column": "...", "separator": " "}
            - split : {"column": "...", "new_columns": [...], "separator": " "}
            - filter : {"column": "...", "operator": "eq"|"neq"|"gt"|"gte"|"lt"|"lte"|"contains"|"is_null"|"is_not_null", "value": "..."}
            - create_column : {"new_column": "...", "expression": "..."}
            - convert_type : {"column": "...", "target_type": "integer"|"float"|"string"|"date"|"datetime"|"boolean"}
            - encode : {"column": "...", "method": "label"|"onehot"}
            - normalize, standardize : {"column": "..."}
            - categorize : {"column": "...", "bins": 4, "labels": [...] ou null}

            Ne propose QUE des étapes réellement justifiées par les données fournies (colonnes constantes/quasi-vides à supprimer, doublons à nettoyer, types mal détectés à corriger...). N'invente rien. Si rien n'est pertinent, renvoie {"suggestions": []}. Maximum 8 suggestions. Ne renvoie RIEN d'autre que ce JSON.

            {$context}
            PROMPT;
    }

    /**
     * @return array<int, array{step_type: string, params: array, rationale: string}>
     */
    private function parseItems(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(json)?/i', '', $content);
        $content = preg_replace('/```$/', '', trim($content));
        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded) || ! is_array($decoded['suggestions'] ?? null)) {
            return [];
        }

        $manualTypes = array_map(
            fn (PipelineStepType $type) => $type->value,
            [...PipelineStepType::cleaningTypes(), ...PipelineStepType::preprocessingTypes()],
        );

        $items = [];

        foreach ($decoded['suggestions'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $stepType = $item['step_type'] ?? null;
            $rationale = is_string($item['rationale'] ?? null) ? trim($item['rationale']) : '';
            $params = is_array($item['params'] ?? null) ? $item['params'] : null;

            if (! in_array($stepType, $manualTypes, true) || $rationale === '' || $params === null) {
                continue;
            }

            $items[] = ['step_type' => $stepType, 'params' => $params, 'rationale' => $rationale];
        }

        return $items;
    }
}
