<?php

namespace App\Models;

use App\Enums\PipelineStepStatus;
use App\Enums\PipelineStepType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single entry in the project's replayable notebook: one row per applied
 * transformation (import, cleaning, or preprocessing), in order. This is the
 * only history mechanism for data transformations in DataForge - the
 * "Nettoyage", "Prétraitement" and "Notebook" spec modules are all views over
 * this same table.
 */
class PipelineStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'step_order',
        'step_type',
        'label',
        'rationale',
        'params',
        'status',
        'rows_affected',
        'applied_at',
    ];

    protected $casts = [
        'step_type' => PipelineStepType::class,
        'status' => PipelineStepStatus::class,
        'params' => 'array',
        'applied_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'dataset_table_id');
    }
}
