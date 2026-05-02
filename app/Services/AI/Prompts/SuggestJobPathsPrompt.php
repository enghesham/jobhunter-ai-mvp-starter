<?php

namespace App\Services\AI\Prompts;

use App\Modules\Candidate\Domain\Models\CandidateProfile;

class SuggestJobPathsPrompt
{
    /**
     * @param array<int, array<string, mixed>> $fallbackSuggestions
     */
    public function build(CandidateProfile $profile, array $fallbackSuggestions): string
    {
        $profile->loadMissing(['experiences', 'projects']);

        $payload = [
            'meta' => [
                'operation' => 'job_path_suggestions',
                'prompt_version' => 'v1',
            ],
            'rules' => [
                'Suggest 2 to 4 practical Job Paths for this job seeker.',
                'Use only the provided profile facts.',
                'Do not invent skills, industries, degrees, companies, or seniority.',
                'Prefer paths that can be searched and matched later.',
                'Avoid unrelated fields such as translation, generic sales, or cold calling unless the profile explicitly supports them.',
                'Return strict JSON only. Do not include markdown.',
            ],
            'career_profile' => [
                'headline' => $profile->headline,
                'professional_summary' => $profile->base_summary,
                'primary_role' => $profile->primary_role,
                'seniority_level' => $profile->seniority_level,
                'years_experience' => $profile->years_experience,
                'preferred_roles' => $profile->preferred_roles,
                'preferred_locations' => $profile->preferred_locations,
                'preferred_job_types' => $profile->preferred_job_types,
                'preferred_workplace_type' => $profile->preferred_workplace_type,
                'core_skills' => $profile->core_skills,
                'nice_to_have_skills' => $profile->nice_to_have_skills,
                'tools' => $profile->tools,
                'industries' => $profile->industries,
                'salary_expectation' => $profile->salary_expectation,
                'salary_currency' => $profile->salary_currency,
                'experiences' => $profile->experiences
                    ->map(fn ($experience): array => [
                        'company' => $experience->company,
                        'title' => $experience->title,
                        'description' => $experience->description,
                        'achievements' => $experience->achievements,
                        'skills' => $experience->tech_stack,
                    ])
                    ->values()
                    ->all(),
                'projects' => $profile->projects
                    ->map(fn ($project): array => [
                        'name' => $project->name,
                        'description' => $project->description,
                        'skills' => $project->tech_stack,
                    ])
                    ->values()
                    ->all(),
            ],
            'fallback_baseline' => $fallbackSuggestions,
            'expected_json' => [
                'job_paths' => [
                    [
                        'name' => 'short user-facing path name',
                        'description' => 'one sentence explaining this path',
                        'target_roles' => ['string'],
                        'target_domains' => ['string'],
                        'include_keywords' => ['string'],
                        'exclude_keywords' => ['string'],
                        'required_skills' => ['string'],
                        'optional_skills' => ['string'],
                        'seniority_levels' => ['entry|junior|mid|senior|lead|principal'],
                        'preferred_locations' => ['string'],
                        'preferred_countries' => ['string'],
                        'preferred_job_types' => ['remote|hybrid|onsite|full-time|contract'],
                        'remote_preference' => 'remote|hybrid|onsite|any',
                        'min_relevance_score' => 'integer 0-100',
                        'min_match_score' => 'integer 0-100',
                    ],
                ],
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
