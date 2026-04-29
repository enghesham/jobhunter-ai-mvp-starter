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
        $promptVersion = $this->prompt->version();
        $inputHash = $this->inputHash($profile, $job, $context, $fallbackPayload, $promptVersion);
        $startedAt = microtime(true);

        try {
            $response = $this->provider->tailorResume($profile, $job, $context, $this->prompt->build($profile, $job, $context));

            if ($response === null) {
                return $this->fallback($fallbackPayload, $promptVersion, $inputHash, $startedAt);
            }

            $validated = $this->validator->validate(
                payload: $response,
                allowedSkills: $context['allowed_skills'],
                allowedProjects: $context['allowed_projects'],
                allowedKeywords: $context['allowed_keywords'],
                sourceExperienceBullets: $context['source_experience_bullets'],
            );

            if ($validated !== null) {
                $result = array_merge($validated, $this->metadata($response, $promptVersion, $inputHash, $startedAt));
                $this->logResult($job, $profile, false, (bool) $result['fallback_used'], (int) $result['ai_duration_ms'], $result['ai_provider']);

                return $result;
            }

            $this->logFailure('invalid_payload', $job, $profile);
        } catch (AiProviderException|\Throwable $exception) {
            $this->logFailure($exception->getMessage(), $job, $profile);
        }

        return $this->fallback($fallbackPayload, $promptVersion, $inputHash, $startedAt);
    }

    /**
     * @param array<string, mixed> $fallbackPayload
     * @return array<string, mixed>
     */
    private function fallback(array $fallbackPayload, string $promptVersion, string $inputHash, float $startedAt): array
    {
        return array_merge($fallbackPayload, [
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'ai_duration_ms' => $this->durationMs($startedAt),
            'fallback_used' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function metadata(array $response, string $promptVersion, string $inputHash, float $startedAt): array
    {
        return [
            'ai_provider' => $this->provider->name(),
            'ai_model' => $this->provider->model(),
            'ai_generated_at' => now(),
            'ai_confidence_score' => (int) ($response['confidence_score'] ?? 0),
            'ai_raw_response' => (app()->isLocal() || config('app.debug')) ? ($response['_raw_response'] ?? null) : null,
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'ai_duration_ms' => $this->durationMs($startedAt),
            'fallback_used' => false,
        ];
    }

    public function promptVersion(): string
    {
        return $this->prompt->version();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $fallbackPayload
     */
    public function inputHash(CandidateProfile $profile, Job $job, array $context, array $fallbackPayload, ?string $promptVersion = null): string
    {
        return hash('sha256', json_encode([
            'prompt_version' => $promptVersion ?? $this->promptVersion(),
            'profile' => $context['candidate_profile'] ?? [
                'id' => $profile->id,
                'headline' => $profile->headline,
            ],
            'job' => $context['job'] ?? [
                'id' => $job->id,
                'title' => $job->title,
            ],
            'analysis' => $context['analysis'] ?? [],
            'base_resume_payload' => $fallbackPayload,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function logResult(Job $job, CandidateProfile $profile, bool $cacheHit, bool $fallbackUsed, int $durationMs, ?string $provider): void
    {
        Log::info('AI resume tailoring completed.', [
            'provider' => $provider,
            'operation' => 'resume_tailoring',
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'prompt_version' => $this->prompt->version(),
            'cache_hit' => $cacheHit,
            'fallback_used' => $fallbackUsed,
            'duration_ms' => $durationMs,
        ]);
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
