<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\ProjectDomain;
use App\Enums\ProjectObjective;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'current_step',
        'last_activity_at',
        'domain',
        'domain_other',
        'objective',
        'objective_other',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'current_step' => PipelineStage::class,
        'last_activity_at' => 'datetime',
        'domain' => ProjectDomain::class,
        'objective' => ProjectObjective::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function pipelineSteps(): HasMany
    {
        return $this->hasMany(PipelineStep::class)->orderBy('step_order');
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function aiInsights(): HasMany
    {
        return $this->hasMany(AiInsight::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DatasetRelationship::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Module Contexte métier: one line summarizing what this project is
     * for, falling back to the free-text "other" fields when the enum case
     * is Other. Null when neither domain nor objective is set, so callers
     * can skip the line entirely instead of printing "Domaine : —".
     */
    public function businessContextLine(): ?string
    {
        $domain = $this->domain === ProjectDomain::Other ? $this->domain_other : $this->domain?->label();
        $objective = $this->objective === ProjectObjective::Other ? $this->objective_other : $this->objective?->label();

        if (! $domain && ! $objective) {
            return null;
        }

        $parts = [];
        if ($domain) {
            $parts[] = "Domaine : {$domain}";
        }
        if ($objective) {
            $parts[] = "Objectif : {$objective}";
        }

        return implode('. ', $parts) . '.';
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Real per-stage completion for the pipeline stepper, derived from
     * actual records rather than the largely-unmaintained current_step
     * column - a stage reads "done" the moment its own data exists,
     * regardless of what step the user thinks they're on.
     *
     * @return array<string, bool> keyed by PipelineStage value
     */
    public function pipelineProgress(): array
    {
        $datasetIds = $this->datasets()->pluck('id');
        $tables = DatasetTable::whereIn('dataset_id', $datasetIds);

        return [
            PipelineStage::Import->value => $datasetIds->isNotEmpty(),
            PipelineStage::Understanding->value => (clone $tables)->whereHas('columns')->exists(),
            PipelineStage::Audit->value => (clone $tables)->whereHas('latestQualityReport')->exists(),
            PipelineStage::Cleaning->value => $this->pipelineSteps()
                ->whereIn('step_type', ['dedupe', 'fix_dates', 'trim_spaces', 'fix_case'])->exists(),
            PipelineStage::Preprocessing->value => $this->pipelineSteps()
                ->whereIn('step_type', [
                    'rename_column', 'drop_column', 'merge', 'split', 'filter',
                    'create_column', 'convert_type', 'encode', 'normalize', 'standardize', 'categorize',
                ])->exists(),
            PipelineStage::Transformation->value => $this->pipelineSteps()->where('step_type', 'join')->exists(),
            PipelineStage::Eda->value => (clone $tables)->whereHas('latestAnalysis')->exists(),
            PipelineStage::Visualization->value => (clone $tables)->whereHas('visualizations')->exists(),
            PipelineStage::Dashboard->value => $this->dashboards()->exists(),
            PipelineStage::Report->value => $this->reports()->exists(),
            PipelineStage::Export->value => false,
        ];
    }
}
