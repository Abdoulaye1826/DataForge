<?php

namespace App\Repositories\Contracts;

use App\Models\UserPipelinePreference;
use Illuminate\Database\Eloquent\Collection;

interface UserPipelinePreferenceRepositoryInterface
{
    /**
     * Increments times_applied if the (user, step_type, pattern_key) triple
     * already exists, otherwise creates it at times_applied = 1.
     */
    public function recordApplication(int $userId, string $stepType, string $patternKey, array $pattern): UserPipelinePreference;

    public function atOrAboveThreshold(int $userId, string $stepType, int $threshold): Collection;
}
