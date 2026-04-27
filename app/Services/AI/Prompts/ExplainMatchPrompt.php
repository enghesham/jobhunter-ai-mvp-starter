<?php

namespace App\Services\AI\Prompts;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;

class ExplainMatchPrompt
{
    /**
     * @param array<string, mixed> $scoreBreakdown
     */
    public function build(CandidateProfile $profile, Job $job, array $scoreBreakdown): string
    {
        $profile->loadMissing(['experiences', 'projects']);
        $job->loadMissing('analysis');

        $payload = [
            'rules' => [
                'Use only the provided candidate facts.',
                'Do not invent candidate experience, skills, or projects.',
                'If a skill is missing, include it in missing_skills or risk_flags.',
                'Return strict JSON only.',
            ],
            'candidate' => [
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
                'years_experience' => $profile->years_experience,
                'preferred_roles' => $profile->preferred_roles,
                'preferred_locations' => $profile->preferred_locations,
                'core_skills' => $profile->core_skills,
                'nice_to_have_skills' => $profile->nice_to_have_skills,
                'experience_titles' => $profile->experiences->map(fn ($experience) => $experience->title.' at '.$experience->company)->values()->all(),
                'project_names' => $profile->projects->pluck('name')->values()->all(),
            ],
            'job' => [
                'title' => $job->title,
                'company_name' => $job->company_name,
                'analysis' => $job->analysis?->toArray(),
            ],
            'score_breakdown' => $scoreBreakdown,
            'expected_json' => [
                'why_matched' => 'string',
                'missing_skills' => ['string'],
                'strength_areas' => ['string'],
                'risk_flags' => ['string'],
                'resume_focus_points' => ['string'],
                'ai_recommendation_summary' => 'string',
                'confidence_score' => 'integer 0-100',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
