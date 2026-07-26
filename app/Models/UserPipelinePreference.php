<?php

namespace App\Models;

use App\Enums\PipelineStepType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module Mémoire inter-projets (§6): counts how many times a user has
 * manually applied a recognizable step pattern (currently: dropping a
 * column by name), across every project, so PipelineRecommendationService
 * can later suggest "vous supprimez habituellement cette colonne" with
 * reinforced confidence. Deliberately narrow in scope (see class docblock
 * on UserPipelinePreferenceService) rather than a generic pattern-matcher.
 */
class UserPipelinePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'step_type',
        'pattern_key',
        'pattern',
        'times_applied',
        'last_applied_at',
    ];

    protected $casts = [
        'step_type' => PipelineStepType::class,
        'pattern' => 'array',
        'last_applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
