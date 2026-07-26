<?php

namespace App\Models;

use App\Enums\QualityGrade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_table_id',
        'score',
        'grade',
        'summary',
        'details',
        'narrative',
        'generated_at',
    ];

    protected $casts = [
        'grade' => QualityGrade::class,
        'summary' => 'array',
        'details' => 'array',
        'generated_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DatasetTable::class, 'dataset_table_id');
    }
}
