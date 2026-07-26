<?php

namespace App\Services\Ai\Contracts;

use App\Exceptions\AiProviderException;

/**
 * Every AI provider (Groq, Claude, ...) implements this so AiAssistantService
 * and AiInsightService never care which one is actually configured
 * (config('dataforge.ai.provider')) - swapping providers is a config change,
 * not a code change.
 */
interface AiProviderInterface
{
    /**
     * @param array<int, array{role: 'system'|'user'|'assistant', content: string}> $messages
     *        Ordered conversation, system message(s) first if any.
     * @return array{content: string, tokens_used: int|null}
     *
     * @throws AiProviderException On network failure, non-2xx response, or missing API key.
     */
    public function chat(array $messages): array;
}
