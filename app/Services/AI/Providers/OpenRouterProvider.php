<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenRouterProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return $this->requestJson(
            prompt: $prompt,
            systemInstruction: 'You are a strict job analysis assistant. Return valid JSON only.'
        );
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->requestJson(
            prompt: $prompt,
            systemInstruction: 'You explain candidate-job match results. Use only provided facts. Return valid JSON only.'
        );
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->requestJson(
            prompt: $prompt,
            systemInstruction: 'You tailor resumes using only provided candidate facts. Do not invent experience. Return valid JSON only.'
        );
    }

    public function suggestJobPaths(CandidateProfile $profile, string $prompt): ?array
    {
        return $this->requestJson(
            prompt: $prompt,
            systemInstruction: 'You are a job seeker copilot. Suggest practical job paths using only provided profile facts. Return valid JSON only.'
        );
    }

    public function generateApplyPackage(CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
    {
        return $this->requestJson(
            prompt: $prompt,
            systemInstruction: 'You generate job application packages using only provided facts. Do not invent candidate experience. Return valid JSON only.'
        );
    }

    public function name(): string
    {
        return 'openrouter';
    }

    public function model(): ?string
    {
        return (string) config('jobhunter.openrouter.model', 'openrouter/auto');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestJson(string $prompt, string $systemInstruction): ?array
    {
        $apiKey = (string) config('jobhunter.openrouter.api_key', config('services.openrouter.api_key', ''));

        if ($apiKey === '') {
            throw new AiProviderException('OpenRouter API key is not configured.');
        }

        $baseUrl = rtrim((string) config('jobhunter.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');

        $response = Http::timeout((int) config('jobhunter.ai_timeout', 30))
            ->retry(2, 500)
            ->withToken($apiKey)
            ->acceptJson()
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ]))
            ->post("{$baseUrl}/chat/completions", [
                'model' => $this->model(),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemInstruction,
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
            ]);

        if (! $response->successful()) {
            throw new AiProviderException("OpenRouter request failed with status {$response->status()}.");
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        if ($content === '') {
            throw new AiProviderException('OpenRouter returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('OpenRouter returned invalid JSON.');
        }

        if (app()->isLocal() || config('app.debug')) {
            $decoded['_raw_response'] = $content;
        }

        return $decoded;
    }
}
