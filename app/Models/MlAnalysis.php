<?php

namespace App\Models;

use App\Enums\MlAnalysisStatus;
use App\Enums\MlAnalysisType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'analysis_type',
        'config',
        'status',
        'result',
        'error',
        'computed_at',
    ];

    protected $casts = [
        'analysis_type' => MlAnalysisType::class,
        'status' => MlAnalysisStatus::class,
        'config' => 'array',
        'result' => 'array',
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
}
