<?php

namespace App\Models;

use App\Enums\ColumnBusinessCategory;
use App\Enums\ColumnType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_table_id',
        'name',
        'original_name',
        'position',
        'detected_type',
        'current_type',
        'semantic_label',
        'semantic_reasoning',
        'business_category',
        'semantic_confidence',
        'is_useless',
        'null_count',
        'null_percentage',
        'distinct_count',
        'sample_values',
        'stats',
    ];

    protected $casts = [
        'detected_type' => ColumnType::class,
        'current_type' => ColumnType::class,
        'business_category' => ColumnBusinessCategory::class,
        'semantic_confidence' => 'float',
        'is_useless' => 'boolean',
        'sample_values' => 'array',
        'stats' => 'array',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'dataset_table_id');
    }
}
