<?php

namespace App\Services\Pipeline;

use App\Enums\PipelineStepType;
use App\Models\DatasetTable;
use App\Models\User;
use App\Repositories\Contracts\UserPipelinePreferenceRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Module Mémoire inter-projets (§6): learns a user's habits across
 * projects. Deliberately scoped to a single, well-defined pattern - manually
 * dropping a column by name - rather than a generic pattern-matcher across
 * all 15 step types, per the vision doc's own honest caveat that this
 * feature's real-world value is uncertain until observed in practice.
 * Recording only happens for MANUAL applications (PipelineStepController),
 * never for accepted AI suggestions - otherwise the AI would be reinforcing
 * its own suggestions instead of learning genuine user choices.
 */
class UserPipelinePreferenceService
{
    private const CONFIDENCE_THRESHOLD = 2;

    public function __construct(private readonly UserPipelinePreferenceRepositoryInterface $preferences)
    {
    }

    public function recordManualStep(User $user, PipelineStepType $type, array $params): void
    {
        if ($type !== PipelineStepType::DropColumn) {
            return;
        }

        foreach ((array) ($params['columns'] ?? []) as $columnName) {
            if (! is_string($columnName) || trim($columnName) === '') {
                continue;
            }

            $this->preferences->recordApplication(
                $user->id,
                PipelineStepType::DropColumn->value,
                "column_name:{$columnName}",
                ['column_name' => $columnName],
            );
        }
    }

    /**
     * @return Collection<int, array{step_type: string, params: array, rationale: string}>
     */
    public function reinforcedDropColumnSuggestions(User $user, DatasetTable $table, Collection $alreadySuggestedColumns): Collection
    {
        $existingColumnNames = $table->columns->pluck('name');

        return $this->preferences
            ->atOrAboveThreshold($user->id, PipelineStepType::DropColumn->value, self::CONFIDENCE_THRESHOLD)
            ->map(fn ($preference) => $preference->pattern['column_name'] ?? null)
            ->filter(fn ($columnName) => $columnName && $existingColumnNames->contains($columnName) && ! $alreadySuggestedColumns->contains($columnName))
            ->map(fn ($columnName) => [
                'step_type' => PipelineStepType::DropColumn->value,
                'params' => ['columns' => [$columnName]],
                'rationale' => "Vous supprimez habituellement la colonne « {$columnName} » dans vos projets.",
            ])
            ->values();
    }
}
