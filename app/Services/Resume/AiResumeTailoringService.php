<?php

namespace App\Services\Resume;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\TailorResumePrompt;
use App\Services\Resume\Support\ResumeTailoringResultValidator;
use Illuminate\Support\Facades\Log;

class AiResumeTailoringService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly TailorResumePrompt $prompt,
        private readonly ResumeTailoringResultValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $fallbackPayload
     * @return array<string, mixed>
     */
    public function tailor(CandidateProfile $profile, Job $job, array $context, array $fallbackPayload): array
    {
        try {
            $response = $this->provider->tailorResume($profile, $job, $context, $this->prompt->build($profile, $job, $context));

            if ($response === null) {
                return $this->fallback($fallbackPayload);
            }

            $validated = $this->validator->validate(
                payload: $response,
                allowedSkills: $context['allowed_skills'],
                allowedProjects: $context['allowed_projects'],
                allowedKeywords: $context['allowed_keywords'],
                sourceExperienceBullets: $context['source_experience_bullets'],
            );

            if ($validated !== null) {
                return array_merge($validated, $this->metadata($response));
            }

            $this->logFailure('invalid_payload', $job, $profile);
        } catch (AiProviderException|\Throwable $exception) {
            $this->logFailure($exception->getMessage(), $job, $profile);
        }

        return $this->fallback($fallbackPayload);
    }

    /**
     * @param array<string, mixed> $fallbackPayload
     * @return array<string, mixed>
     */
    private function fallback(array $fallbackPayload): array
    {
        return array_merge($fallbackPayload, [
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function metadata(array $response): array
    {
        return [
            'ai_provider' => $this->provider->name(),
            'ai_model' => $this->provider->model(),
            'ai_generated_at' => now(),
            'ai_confidence_score' => (int) ($response['confidence_score'] ?? 0),
            'ai_raw_response' => (app()->isLocal() || config('app.debug')) ? ($response['_raw_response'] ?? null) : null,
        ];
    }

    private function logFailure(string $message, Job $job, CandidateProfile $profile): void
    {
        Log::warning('AI resume tailoring failed.', [
            'provider' => $this->provider->name(),
            'operation' => 'resume_tailoring',
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'message' => $message,
        ]);
    }
}
