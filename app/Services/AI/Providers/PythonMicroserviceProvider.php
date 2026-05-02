<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PythonMicroserviceProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return $this->requestJson('analyze_job', $prompt, [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
            ],
        ]);
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->requestJson('explain_match', $prompt, [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
            ],
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
            ],
            'score_breakdown' => $scoreBreakdown,
        ]);
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->requestJson('tailor_resume', $prompt, [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
            ],
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
            ],
            'context' => $resumeContext,
        ]);
    }

    public function suggestJobPaths(CandidateProfile $profile, string $prompt): ?array
    {
        return $this->requestJson('suggest_job_paths', $prompt, [
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
                'primary_role' => $profile->primary_role,
                'seniority_level' => $profile->seniority_level,
                'years_experience' => $profile->years_experience,
                'core_skills' => $profile->core_skills,
                'nice_to_have_skills' => $profile->nice_to_have_skills,
            ],
        ]);
    }

    public function name(): string
    {
        return 'python_microservice';
    }

    public function model(): ?string
    {
        return 'python-microservice';
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function requestJson(string $operation, string $prompt, array $context): ?array
    {
        $baseUrl = rtrim((string) config('jobhunter.python_microservice.base_url', ''), '/');

        if ($baseUrl === '') {
            throw new AiProviderException('Python AI microservice URL is not configured.');
        }

        $response = $this->httpClient()->post($baseUrl, [
            'operation' => $operation,
            'prompt' => $prompt,
            'context' => $context,
        ]);

        if (! $response->successful()) {
            throw new AiProviderException("Python AI microservice request failed with status {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiProviderException('Python AI microservice returned invalid JSON.');
        }

        $data = data_get($payload, 'data', $payload);

        if (! is_array($data)) {
            throw new AiProviderException('Python AI microservice returned invalid data payload.');
        }

        if (app()->isLocal() || config('app.debug')) {
            $data['_raw_response'] = json_encode($payload, JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::timeout((int) config('jobhunter.ai_timeout', 30))
            ->retry(2, 500)
            ->acceptJson();

        $apiKey = (string) config('jobhunter.python_microservice.api_key', '');

        return $apiKey === '' ? $client : $client->withToken($apiKey);
    }
}
