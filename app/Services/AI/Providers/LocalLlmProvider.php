<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class LocalLlmProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return $this->requestJson($prompt);
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->requestJson($prompt);
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->requestJson($prompt);
    }

    public function suggestJobPaths(CandidateProfile $profile, string $prompt): ?array
    {
        return $this->requestJson($prompt);
    }

    public function name(): string
    {
        return 'local_llm';
    }

    public function model(): ?string
    {
        return (string) config('jobhunter.local_llm.model', 'llama3.1');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestJson(string $prompt): ?array
    {
        $baseUrl = rtrim((string) config('jobhunter.local_llm.base_url', ''), '/');

        if ($baseUrl === '') {
            throw new AiProviderException('Local LLM base URL is not configured.');
        }

        $response = $this->httpClient()
            ->post("{$baseUrl}/chat/completions", [
                'model' => $this->model(),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Return valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
            ]);

        if (! $response->successful()) {
            throw new AiProviderException("Local LLM request failed with status {$response->status()}.");
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        if ($content === '') {
            throw new AiProviderException('Local LLM returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('Local LLM returned invalid JSON.');
        }

        if (app()->isLocal() || config('app.debug')) {
            $decoded['_raw_response'] = $content;
        }

        return $decoded;
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::timeout((int) config('jobhunter.ai_timeout', 30))
            ->retry(2, 500)
            ->acceptJson();

        $apiKey = (string) config('jobhunter.local_llm.api_key', '');

        return $apiKey === '' ? $client : $client->withToken($apiKey);
    }
}
