<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic's Messages API keeps the system prompt separate from the
 * messages array (which only takes user/assistant turns), unlike the
 * OpenAI-style shape used elsewhere in this app - this provider is the only
 * place that reshapes our internal {role, content}[] into that format.
 *
 * Built alongside GroqProvider for when an ANTHROPIC_API_KEY becomes
 * available (matches the originally planned provider) - not exercised by
 * live tests in this session since no Anthropic key was provided.
 */
class ClaudeProvider implements AiProviderInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function chat(array $messages): array
    {
        $apiKey = config('dataforge.ai.anthropic.api_key');

        if (! $apiKey) {
            throw new AiProviderException('Clé API Anthropic manquante (ANTHROPIC_API_KEY dans .env).');
        }

        $system = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");

        $conversation = collect($messages)
            ->reject(fn ($message) => $message['role'] === 'system')
            ->values()
            ->all();

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout(60)
            ->post(self::ENDPOINT, [
                'model' => config('dataforge.ai.anthropic.model'),
                'max_tokens' => 2048,
                'system' => $system,
                'messages' => $conversation,
            ]);

        if ($response->failed()) {
            throw new AiProviderException("Erreur Claude ({$response->status()}) : {$this->extractError($response)}");
        }

        return [
            'content' => $response->json('content.0.text') ?? '',
            'tokens_used' => ($response->json('usage.input_tokens') ?? 0) + ($response->json('usage.output_tokens') ?? 0),
        ];
    }

    private function extractError(Response $response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
