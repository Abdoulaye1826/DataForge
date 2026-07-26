<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatasetTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'name',
        'row_count',
        'column_count',
        'storage_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DatasetColumn::class)->orderBy('position');
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(QualityReport::class);
    }

    public function latestQualityReport(): HasOne
    {
        return $this->hasOne(QualityReport::class)->latestOfMany('generated_at');
    }

    public function pipelineSteps(): HasMany
    {
        return $this->hasMany(PipelineStep::class)->orderBy('step_order');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(Analysis::class)->latestOfMany('computed_at');
    }

    public function visualizations(): HasMany
    {
        return $this->hasMany(Visualization::class);
    }

    public function aiInsights(): HasMany
    {
        return $this->hasMany(AiInsight::class);
    }

    public function statisticalTests(): HasMany
    {
        return $this->hasMany(StatisticalTest::class)->latest('computed_at');
    }

    public function mlAnalyses(): HasMany
    {
        return $this->hasMany(MlAnalysis::class)->latest('computed_at');
    }

    public function pipelineSuggestions(): HasMany
    {
        return $this->hasMany(PipelineSuggestion::class);
    }
}
