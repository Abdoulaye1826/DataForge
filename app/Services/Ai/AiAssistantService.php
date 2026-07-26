<?php

namespace App\Services\Ai;

use App\Enums\MessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\AiConversationRepositoryInterface;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Module Assistant IA: a conversation always answers grounded in the
 * project's real data (see AiContextBuilder) - the system prompt is
 * rebuilt fresh on every message so it reflects the latest pipeline state,
 * not a snapshot from when the conversation started.
 */
class AiAssistantService
{
    public function __construct(
        private readonly AiConversationRepositoryInterface $conversations,
        private readonly AiProviderInterface $provider,
        private readonly AiContextBuilder $contextBuilder,
    ) {
    }

    public function conversationsForProject(Project $project): Collection
    {
        return $this->conversations->allForProject($project->id);
    }

    public function startConversation(Project $project, User $user, string $title): AiConversation
    {
        return $this->conversations->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    public function sendMessage(AiConversation $conversation, Project $project, string $content): AiMessage
    {
        AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => $content,
        ]);

        $context = $this->contextBuilder->projectContext($project);
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($context)]];

        foreach ($conversation->messages()->get() as $message) {
            $messages[] = ['role' => $message->role->value, 'content' => $message->content];
        }

        $result = $this->provider->chat($messages);

        return AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => $result['content'],
            'context_snapshot' => ['context' => $context],
            'tokens_used' => $result['tokens_used'],
        ]);
    }

    private function systemPrompt(string $context): string
    {
        return <<<PROMPT
            Tu es l'assistant IA de DataForge, un espace de travail pour data analysts. Tu dois répondre UNIQUEMENT en te basant sur les données réellement importées listées ci-dessous. Si une information n'est pas présente dans ces données, dis-le clairement plutôt que d'inventer un résultat. Réponds en français, de façon concise, actionnable, et cite les noms de colonnes/tables exacts quand c'est pertinent.

            Données du projet :
            {$context}
            PROMPT;
    }
}
