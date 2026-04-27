<?php

namespace App\Services\Matching;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\ExplainMatchPrompt;
use App\Services\Matching\Support\MatchExplanationResultValidator;
use Illuminate\Support\Facades\Log;

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
    public function explain(CandidateProfile $profile, Job $job, array $scoreBreakdown): array
    {
        $fallback = $this->fallback($profile, $job, $scoreBreakdown);

        try {
            $response = $this->provider->explainMatch($profile, $job, $scoreBreakdown, $this->prompt->build($profile, $job, $scoreBreakdown));

            if ($response === null) {
                return $fallback;
            }

            $validated = $this->validator->validate($response);

            if ($validated !== null) {
                return array_merge($validated, $this->metadata($response));
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
    private function fallback(CandidateProfile $profile, Job $job, array $scoreBreakdown): array
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
                'Recommendation %s based on deterministic scoring with overall score %d.',
                $scoreBreakdown['recommendation'] ?? 'unknown',
                $scoreBreakdown['overall_score'] ?? 0
            ),
            'confidence_score' => 55,
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => null,
            'ai_raw_response' => null,
        ];
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
        Log::warning('AI match explanation failed.', [
            'provider' => $this->provider->name(),
            'operation' => 'match_explanation',
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'message' => $message,
        ]);
    }
}
