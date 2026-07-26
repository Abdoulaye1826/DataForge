<?php

namespace App\Models;

use App\Enums\GeneratedBy;
use App\Enums\InsightCategory;
use App\Enums\InsightImportance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'category',
        'content',
        'importance',
        'suggested_action',
        'generated_by',
    ];

    protected $casts = [
        'category' => InsightCategory::class,
        'importance' => InsightImportance::class,
        'suggested_action' => 'array',
        'generated_by' => GeneratedBy::class,
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
