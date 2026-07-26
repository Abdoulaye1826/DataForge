<?php

namespace App\Repositories\Eloquent;

use App\Models\AiConversation;
use App\Repositories\Contracts\AiConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAiConversationRepository implements AiConversationRepositoryInterface
{
    public function find(int $id): ?AiConversation
    {
        return AiConversation::find($id);
    }

    public function allForProject(int $projectId): Collection
    {
        return AiConversation::where('project_id', $projectId)->latest()->get();
    }

    public function create(array $attributes): AiConversation
    {
        return AiConversation::create($attributes);
    }
}
