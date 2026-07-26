<?php

namespace App\Models;

use App\Enums\GeneratedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'sections',
        'storage_path',
        'size_bytes',
        'generated_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'generated_by' => GeneratedBy::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
