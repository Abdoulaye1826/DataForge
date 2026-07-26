<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Groq's chat completions API is OpenAI-compatible, so the message array
 * (including a system-role entry) is passed through almost as-is.
 */
class GroqProvider implements AiProviderInterface
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function chat(array $messages): array
    {
        $apiKey = config('dataforge.ai.groq.api_key');

        if (! $apiKey) {
            throw new AiProviderException('Clé API Groq manquante (GROQ_API_KEY dans .env).');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::ENDPOINT, [
                'model' => config('dataforge.ai.groq.model'),
                'messages' => $messages,
                'temperature' => 0.3,
            ]);

        if ($response->failed()) {
            throw new AiProviderException("Erreur Groq ({$response->status()}) : {$this->extractError($response)}");
        }

        return [
            'content' => $response->json('choices.0.message.content') ?? '',
            'tokens_used' => $response->json('usage.total_tokens'),
        ];
    }

    private function extractError(Response $response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
