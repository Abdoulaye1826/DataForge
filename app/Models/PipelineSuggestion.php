<?php

namespace App\Models;

use App\Enums\PipelineStepType;
use App\Enums\PipelineSuggestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module Pipeline proposé par l'IA (§5): one AI-recommended step, awaiting
 * the user's Accept/Reject decision. Accepting one never executes it
 * directly here - the controller converts it into a normal
 * PipelineStepService::applyStep() call, the exact same engine a manual
 * transformation goes through, so this table only ever holds proposals.
 */
class PipelineSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'step_type',
        'params',
        'rationale',
        'plan',
        'status',
        'computed_at',
    ];

    protected $casts = [
        'step_type' => PipelineStepType::class,
        'params' => 'array',
        'status' => PipelineSuggestionStatus::class,
        'computed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'dataset_table_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PipelineSuggestionStatus::Pending);
    }

    /**
     * Module config par colonne (inspiré de Power Query) : quelle(s)
     * colonne(s) réelle(s) cette suggestion concerne, en normalisant les
     * différentes clés que "params" peut porter selon step_type (voir
     * PipelineRecommendationService::prompt() pour le schéma exact). Sert à
     * filtrer les suggestions pertinentes quand l'utilisateur clique sur
     * l'en-tête d'une colonne précise dans la page Données.
     *
     * @return array<int, string>
     */
    public function referencedColumns(): array
    {
        $params = $this->params ?? [];

        return array_values(array_unique(array_filter([
            ...(is_array($params['columns'] ?? null) ? $params['columns'] : []),
            $params['column'] ?? null,
            $params['old_name'] ?? null,
        ])));
    }
}
