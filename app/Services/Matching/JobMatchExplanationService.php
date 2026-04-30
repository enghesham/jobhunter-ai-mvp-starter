<?php

namespace App\Services\Matching;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\ExplainMatchPrompt;
use App\Services\Matching\Support\MatchExplanationResultValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobMatchExplanationService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly ExplainMatchPrompt $prompt,
        private readonly MatchExplanationResultValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $scoreBreakdown
     * @return array<string, mixed>
     */
    public function explain(CandidateProfile $profile, Job $job, array $scoreBreakdown, bool $force = false): array
    {
        $promptVersion = $this->prompt->version();
        $inputHash = $this->inputHash($profile, $job, $scoreBreakdown, $promptVersion);

        if (! $force && ($cached = $this->cached($profile, $job, $promptVersion, $inputHash))) {
            $this->logResult($job, $profile, true, (bool) $cached['fallback_used'], 0, $cached['ai_provider']);

            return array_merge($cached, ['cache_hit' => true]);
        }

        $startedAt = microtime(true);
        $fallback = $this->fallback($profile, $job, $scoreBreakdown, $promptVersion, $inputHash, $startedAt);

        try {
            $response = $this->provider->explainMatch($profile, $job, $scoreBreakdown, $this->prompt->build($profile, $job, $scoreBreakdown));

            if ($response === null) {
                return $fallback;
            }

            $validated = $this->validator->validate($response);

            if ($validated !== null) {
                $result = array_merge($validated, $this->metadata($response, $promptVersion, $inputHash, $startedAt));
                $this->logResult($job, $profile, false, false, (int) $result['ai_duration_ms'], $result['ai_provider']);

                return array_merge($result, ['cache_hit' => false]);
            }

            $this->logFailure('invalid_payload', $job, $profile);
        } catch (AiProviderException|\Throwable $exception) {
            $this->logFailure($exception->getMessage(), $job, $profile);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $scoreBreakdown
     * @return array<string, mixed>
     */
    private function fallback(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $promptVersion, string $inputHash, float $startedAt): array
    {
        $candidateSkills = collect($profile->core_skills ?? [])->map(fn (string $skill): string => mb_strtolower($skill))->all();
        $requiredSkills = collect($job->analysis?->required_skills ?? [])->map(fn (string $skill): string => mb_strtolower($skill))->all();
        $missingSkills = collect($job->analysis?->required_skills ?? [])
            ->filter(fn (string $skill): bool => ! in_array(mb_strtolower($skill), $candidateSkills, true))
            ->values()
            ->all();
        $strengthAreas = collect($job->analysis?->required_skills ?? [])
            ->filter(fn (string $skill): bool => in_array(mb_strtolower($skill), $candidateSkills, true))
            ->take(5)
            ->values()
            ->all();

        $riskFlags = [];

        if (($scoreBreakdown['location_score'] ?? 0) < 80) {
            $riskFlags[] = 'Location alignment is partial.';
        }

        if (($scoreBreakdown['seniority_score'] ?? 0) < 80) {
            $riskFlags[] = 'Seniority alignment is not fully confident.';
        }

        if (($scoreBreakdown['skill_score'] ?? 0) < 70) {
            $riskFlags[] = 'Several required skills are missing or unclear.';
        }

        return [
            'why_matched' => sprintf(
                'Deterministic scoring found %d aligned skills for %s, with strongest alignment in %s.',
                count($strengthAreas),
                $job->title,
                $strengthAreas === [] ? 'general backend fit' : implode(', ', array_slice($strengthAreas, 0, 3))
            ),
            'missing_skills' => $missingSkills,
            'strength_areas' => $strengthAreas,
            'risk_flags' => $riskFlags,
            'resume_focus_points' => $strengthAreas === [] ? ['Highlight backend API and system design experience.'] : array_map(
                fn (string $skill): string => "Highlight {$skill} experience prominently.",
                array_slice($strengthAreas, 0, 4)
            ),
            'ai_recommendation_summary' => sprintf(
                'Recommendation %s (%s) based on deterministic scoring with overall score %d.',
                $scoreBreakdown['recommendation'] ?? 'unknown',
                $scoreBreakdown['recommendation_action'] ?? 'consider',
                $scoreBreakdown['overall_score'] ?? 0
            ),
            'confidence_score' => 55,
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'ai_duration_ms' => $this->durationMs($startedAt),
            'fallback_used' => true,
            'cache_hit' => false,
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    private function cached(CandidateProfile $profile, Job $job, string $promptVersion, string $inputHash): ?array
    {
        if (! config('jobhunter.ai_cache_enabled', true)) {
            return null;
        }

        $match = $job->matches()
            ->where('profile_id', $profile->id)
            ->first();

        if (! $match || $match->prompt_version !== $promptVersion || $match->input_hash !== $inputHash) {
            return null;
        }

        return [
            'why_matched' => $match->why_matched,
            'missing_skills' => $match->missing_skills ?? [],
            'strength_areas' => $match->strength_areas ?? [],
            'risk_flags' => $match->risk_flags ?? [],
            'resume_focus_points' => $match->resume_focus_points ?? [],
            'ai_recommendation_summary' => $match->ai_recommendation_summary,
            'confidence_score' => $match->ai_confidence_score ?? 0,
            'ai_provider' => $match->ai_provider,
            'ai_model' => $match->ai_model,
            'ai_generated_at' => $match->ai_generated_at,
            'ai_confidence_score' => $match->ai_confidence_score,
            'ai_raw_response' => $match->ai_raw_response,
            'prompt_version' => $match->prompt_version,
            'input_hash' => $match->input_hash,
            'ai_duration_ms' => $match->ai_duration_ms,
            'fallback_used' => (bool) $match->fallback_used,
        ];
    }

    /**
     * @param array<string, mixed> $scoreBreakdown
     */
    private function inputHash(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $promptVersion): string
    {
        $profile->loadMissing(['experiences', 'projects']);
        $job->loadMissing('analysis');

        return hash('sha256', json_encode([
            'prompt_version' => $promptVersion,
            'score_breakdown' => $scoreBreakdown,
            'profile' => [
                'headline' => $profile->headline,
                'base_summary' => Str::limit((string) $profile->base_summary, 4000, ''),
                'years_experience' => $profile->years_experience,
                'preferred_roles' => $profile->preferred_roles,
                'preferred_locations' => $profile->preferred_locations,
                'core_skills' => $profile->core_skills,
                'nice_to_have_skills' => $profile->nice_to_have_skills,
                'experiences' => $profile->experiences->map(fn ($experience) => [
                    'company' => $experience->company,
                    'title' => $experience->title,
                    'description' => Str::limit((string) $experience->description, 500, ''),
                ])->values()->all(),
                'projects' => $profile->projects->map(fn ($project) => [
                    'name' => $project->name,
                    'description' => Str::limit((string) $project->description, 500, ''),
                ])->values()->all(),
            ],
            'job' => [
                'title' => $job->title,
                'company_name' => $job->company_name,
                'analysis' => [
                    'required_skills' => $job->analysis?->required_skills ?? [],
                    'must_have_skills' => $job->analysis?->must_have_skills ?? [],
                    'role_type' => $job->analysis?->role_type,
                    'seniority' => $job->analysis?->seniority,
                    'domain_tags' => $job->analysis?->domain_tags ?? [],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function logResult(Job $job, CandidateProfile $profile, bool $cacheHit, bool $fallbackUsed, int $durationMs, ?string $provider): void
    {
        Log::info('AI match explanation completed.', [
            'provider' => $provider,
            'operation' => 'match_explanation',
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
        Log::warning('AI match explanation failed.', [
            'provider' => $this->provider->name(),
            'operation' => 'match_explanation',
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'message' => $message,
        ]);
    }
}
