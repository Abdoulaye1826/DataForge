<?php

namespace App\Models;

use App\Enums\DatabaseDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'driver' => DatabaseDriver::class,
        // Chiffré au repos avec APP_KEY - jamais renvoyé au frontend (voir
        // $hidden ci-dessus) ni journalisé en clair (voir
        // PythonRunnerService::sanitizePayloadForStorage()).
        'password' => 'encrypted',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class);
    }
}
