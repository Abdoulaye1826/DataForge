<?php

namespace App\Models;

use App\Enums\StatisticalTestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticalTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'dataset_table_id',
        'test_type',
        'config',
        'result',
        'computed_at',
    ];

    protected $casts = [
        'test_type' => StatisticalTestType::class,
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
