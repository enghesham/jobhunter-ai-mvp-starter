<?php

namespace App\Services\AI\Providers;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            throw new AiProviderException('OpenAI API key is not configured.');
        }

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('jobhunter.openai.model', 'gpt-4.1-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a strict job analysis assistant. Return valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
            ]);

        if (! $response->successful()) {
            throw new AiProviderException("OpenAI request failed with status {$response->status()}.");
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        if ($content === '') {
            throw new AiProviderException('OpenAI returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('OpenAI returned invalid JSON.');
        }

        return $decoded;
    }

    public function name(): string
    {
        return 'openai';
    }
}
