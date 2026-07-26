<?php

namespace App\Repositories\Eloquent;

use App\Models\UserPipelinePreference;
use App\Repositories\Contracts\UserPipelinePreferenceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentUserPipelinePreferenceRepository implements UserPipelinePreferenceRepositoryInterface
{
    public function recordApplication(int $userId, string $stepType, string $patternKey, array $pattern): UserPipelinePreference
    {
        $preference = UserPipelinePreference::where('user_id', $userId)
            ->where('step_type', $stepType)
            ->where('pattern_key', $patternKey)
            ->first();

        if ($preference) {
            $preference->update([
                'times_applied' => $preference->times_applied + 1,
                'last_applied_at' => now(),
            ]);

            return $preference;
        }

        return UserPipelinePreference::create([
            'user_id' => $userId,
            'step_type' => $stepType,
            'pattern_key' => $patternKey,
            'pattern' => $pattern,
            'times_applied' => 1,
            'last_applied_at' => now(),
        ]);
    }

    public function atOrAboveThreshold(int $userId, string $stepType, int $threshold): Collection
    {
        return UserPipelinePreference::where('user_id', $userId)
            ->where('step_type', $stepType)
            ->where('times_applied', '>=', $threshold)
            ->get();
    }
}
