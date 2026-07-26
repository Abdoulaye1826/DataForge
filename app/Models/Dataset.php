<?php

namespace App\Models;

use App\Enums\DatasetFormat;
use App\Enums\DatasetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'original_filename',
        'format',
        'disk_path',
        'size_bytes',
        'status',
        'import_meta',
    ];

    protected $casts = [
        'format' => DatasetFormat::class,
        'status' => DatasetStatus::class,
        'import_meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DatasetTable::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DatasetRelationship::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Dataset $dataset): void {
            // Derived (joined) datasets have no uploaded source file.
            if ($dataset->disk_path) {
                Storage::delete($dataset->disk_path);
            }
            File::deleteDirectory(storage_path("app/datasets/{$dataset->id}"));
        });
    }
}
