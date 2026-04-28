<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
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

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): ?string
    {
        return (string) config('jobhunter.gemini.model', 'gemini-2.5-flash');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestJson(string $prompt): ?array
    {
        $apiKey = (string) config('jobhunter.gemini.api_key', config('services.gemini.api_key', ''));

        if ($apiKey === '') {
            throw new AiProviderException('Gemini API key is not configured.');
        }

        $response = Http::timeout((int) config('jobhunter.ai_timeout', 30))
            ->retry(2, 500)
            ->acceptJson()
            ->post(sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                $this->model(),
                urlencode($apiKey)
            ), [
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                ],
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new AiProviderException("Gemini request failed with status {$response->status()}.");
        }

        $content = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($content === '') {
            throw new AiProviderException('Gemini returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('Gemini returned invalid JSON.');
        }

        if (app()->isLocal() || config('app.debug')) {
            $decoded['_raw_response'] = $content;
        }

        return $decoded;
    }
}
