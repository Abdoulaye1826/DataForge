<?php

namespace App\Models;

use App\Enums\ChartType;
use App\Enums\PipelineSuggestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module Suggestions IA de visualisations : une visualisation que l'IA
 * recommande de créer (avec sa justification), en attente de la décision
 * Accepter/Rejeter de l'utilisateur - miroir exact de PipelineSuggestion pour
 * le pipeline de nettoyage, mais côté visualisations. Accepter une suggestion
 * ne fait jamais rien de spécial ici : ça délègue à
 * VisualizationService::create(), le même moteur qu'une création manuelle.
 */
class VisualizationSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'chart_type',
        'name',
        'config',
        'rationale',
        'status',
        'computed_at',
    ];

    protected $casts = [
        'chart_type' => ChartType::class,
        'config' => 'array',
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
}
