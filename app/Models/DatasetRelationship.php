<?php

namespace App\Models;

use App\Enums\RelationshipStatus;
use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_id',
        'source_table_id',
        'source_column_id',
        'target_table_id',
        'target_column_id',
        'relationship_type',
        'confidence_score',
        'status',
    ];

    protected $casts = [
        'relationship_type' => RelationshipType::class,
        'status' => RelationshipStatus::class,
        'confidence_score' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Only set when source/target happen to belong to the same dataset
     * (e.g. sibling sheets of one Excel file) - null for relationships
     * spanning two separately-imported datasets.
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function sourceTable(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'source_table_id');
    }

    public function sourceColumn(): BelongsTo
    {
        return $this->belongsTo(DatasetColumn::class, 'source_column_id');
    }

    public function targetTable(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'target_table_id');
    }

    public function targetColumn(): BelongsTo
    {
        return $this->belongsTo(DatasetColumn::class, 'target_column_id');
    }
}
