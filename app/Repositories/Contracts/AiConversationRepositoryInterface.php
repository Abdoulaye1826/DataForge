<?php

namespace App\Repositories\Contracts;

use App\Models\AiConversation;
use Illuminate\Database\Eloquent\Collection;

interface AiConversationRepositoryInterface
{
    public function find(int $id): ?AiConversation;

    public function allForProject(int $projectId): Collection;

    public function create(array $attributes): AiConversation;
}
