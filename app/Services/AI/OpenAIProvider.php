<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements Provider
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini',
        protected string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function complete(array $messages, array $options = []): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $options['model'] ?? $this->model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.4,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('OpenAI request failed: ' . $response->status());
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }
}
