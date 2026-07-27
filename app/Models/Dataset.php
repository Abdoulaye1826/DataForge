<?php

namespace App\Models;

use App\Enums\DatasetFormat;
use App\Enums\DatasetStatus;
use App\Enums\ProjectDomain;
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
        'database_connection_id',
        'name',
        'original_filename',
        'format',
        'disk_path',
        'size_bytes',
        'status',
        'import_meta',
        'detected_domain',
        'detected_domain_confidence',
    ];

    protected $casts = [
        'format' => DatasetFormat::class,
        'status' => DatasetStatus::class,
        'import_meta' => 'array',
        'detected_domain' => ProjectDomain::class,
        'detected_domain_confidence' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
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
