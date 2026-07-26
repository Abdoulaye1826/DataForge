<?php

namespace App\Models;

use App\Enums\ChartType;
use App\Enums\VisualizationSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visualization extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'name',
        'chart_type',
        'config',
        'data_cache',
        'source',
        'rationale',
    ];

    protected $casts = [
        'chart_type' => ChartType::class,
        'source' => VisualizationSource::class,
        'config' => 'array',
        'data_cache' => 'array',
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
