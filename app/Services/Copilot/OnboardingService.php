<?php

namespace App\Services\Copilot;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\UserOnboardingState;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public function __construct(
        private readonly CareerProfileService $careerProfileService,
    ) {
    }

    public function state(User $user): UserOnboardingState
    {
        $existing = UserOnboardingState::query()->where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        $primaryProfile = CandidateProfile::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->latest()
            ->first();
        $hasJobPath = $user->jobPaths()->exists();

        if ($primaryProfile && $hasJobPath) {
            return UserOnboardingState::query()->create([
                'user_id' => $user->id,
                'current_step' => 'done',
                'completed_at' => now(),
                'metadata' => [
                    'career_profile_id' => $primaryProfile->id,
                    'completed_by' => 'existing_data',
                ],
            ]);
        }

        return UserOnboardingState::query()->create([
            'user_id' => $user->id,
            'current_step' => $primaryProfile ? 'suggest_job_paths' : 'career_profile',
            'metadata' => $primaryProfile ? ['career_profile_id' => $primaryProfile->id] : [],
        ]);
    }

    public function saveCareerProfile(User $user, array $payload): array
    {
        return DB::transaction(function () use ($user, $payload): array {
            $profile = $this->careerProfileService->create($user, [
                ...$payload,
                'is_primary' => $payload['is_primary'] ?? true,
                'source' => $payload['source'] ?? 'manual',
            ]);

            $understanding = $this->summarizeProfile($profile);
            $state = $this->state($user);
            $state->forceFill([
                'current_step' => 'review_profile',
                'metadata' => array_merge($state->metadata ?? [], [
                    'career_profile_id' => $profile->id,
                    'understanding' => $understanding,
                ]),
            ])->save();

            return [
                'state' => $state->fresh(),
                'career_profile' => $profile,
                'understanding' => $understanding,
            ];
        });
    }

    public function suggestJobPaths(User $user, ?int $careerProfileId = null): array
    {
        $profile = $this->resolveProfile($user, $careerProfileId);
        $suggestions = $this->buildSuggestions($profile);
        $state = $this->state($user);

        $state->forceFill([
            'current_step' => 'suggest_job_paths',
            'metadata' => array_merge($state->metadata ?? [], [
                'career_profile_id' => $profile->id,
                'suggested_job_paths' => $suggestions,
            ]),
        ])->save();

        return [
            'state' => $state->fresh(),
            'career_profile' => $profile->loadMissing(['experiences', 'projects']),
            'suggestions' => $suggestions,
        ];
    }

    public function complete(User $user): UserOnboardingState
    {
        $state = $this->state($user);
        $state->forceFill([
            'current_step' => 'done',
            'completed_at' => $state->completed_at ?? now(),
            'metadata' => array_merge($state->metadata ?? [], [
                'completed_by' => 'guided_onboarding',
            ]),
        ])->save();

        return $state->fresh();
    }

    public function summarizeProfile(CandidateProfile $profile): array
    {
        $skills = array_values(array_filter($profile->core_skills ?? []));
        $role = $profile->primary_role
            ?: Arr::first($profile->preferred_roles ?? [])
            ?: $profile->headline
            ?: 'Professional';

        return [
            'role' => $role,
            'seniority' => $profile->seniority_level ?: $this->inferSeniority((int) $profile->years_experience),
            'skills' => array_slice($skills, 0, 10),
            'experience' => [
                'years' => (int) $profile->years_experience,
                'headline' => $profile->headline,
                'summary' => $profile->base_summary,
            ],
            'suggested_job_directions' => array_map(
                fn (array $path): string => $path['name'],
                array_slice($this->buildSuggestions($profile), 0, 4),
            ),
        ];
    }

    private function resolveProfile(User $user, ?int $careerProfileId): CandidateProfile
    {
        $query = CandidateProfile::query()
            ->where('user_id', $user->id)
            ->with(['experiences', 'projects']);

        if ($careerProfileId !== null) {
            $profile = (clone $query)->whereKey($careerProfileId)->first();

            if (! $profile) {
                throw ValidationException::withMessages([
                    'career_profile_id' => 'The selected career profile is invalid.',
                ]);
            }

            return $profile;
        }

        $profile = (clone $query)
            ->orderByDesc('is_primary')
            ->latest()
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'career_profile_id' => 'Create a career profile before suggesting job paths.',
            ]);
        }

        return $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSuggestions(CandidateProfile $profile): array
    {
        $role = $profile->primary_role
            ?: Arr::first($profile->preferred_roles ?? [])
            ?: $this->roleFromHeadline($profile->headline)
            ?: 'Professional';
        $seniority = $profile->seniority_level ?: $this->inferSeniority((int) $profile->years_experience);
        $skills = array_values(array_unique(array_filter($profile->core_skills ?? [])));
        $optionalSkills = array_values(array_unique(array_filter($profile->nice_to_have_skills ?? [])));
        $locations = $profile->preferred_locations ?: ['Remote'];
        $remotePreference = $this->normalizeRemotePreference($profile->preferred_workplace_type);
        $domains = $profile->industries ?: ['Technology'];
        $primarySkill = $skills[0] ?? $role;
        $secondarySkill = $skills[1] ?? $primarySkill;
        $hasLaravel = $this->containsSkill($skills, 'Laravel');
        $hasVue = $this->containsSkill([...$skills, ...$optionalSkills], 'Vue');
        $gulfLocations = array_values(array_filter($locations, fn (string $location): bool => preg_match('/uae|dubai|saudi|gulf|qatar|riyadh/i', $location) === 1));

        $suggestions = [
            $this->pathPayload(
                name: trim(ucwords($seniority).' '.$role.' '.ucwords($remotePreference === 'any' ? 'Flexible' : $remotePreference)),
                description: "Target {$seniority} {$role} roles aligned with your strongest skills.",
                profile: $profile,
                requiredSkills: array_slice($skills, 0, 5),
                optionalSkills: array_slice($optionalSkills, 0, 5),
                domains: $domains,
                remotePreference: $remotePreference,
                locations: $locations,
            ),
        ];

        if ($hasLaravel || $this->containsSkill($skills, 'PHP')) {
            $suggestions[] = $this->pathPayload(
                name: 'Laravel Developer '.($gulfLocations !== [] ? 'Gulf' : 'Remote'),
                description: 'Focus on Laravel/PHP roles that match your backend delivery experience.',
                profile: $profile,
                requiredSkills: array_values(array_filter(['PHP', 'Laravel', $secondarySkill])),
                optionalSkills: array_slice($optionalSkills, 0, 5),
                domains: $domains,
                remotePreference: $gulfLocations !== [] ? 'hybrid' : $remotePreference,
                locations: $gulfLocations ?: $locations,
            );
        }

        if ($hasLaravel && $hasVue) {
            $suggestions[] = $this->pathPayload(
                name: 'Full Stack Laravel/Vue',
                description: 'Target roles that combine Laravel backend work with Vue product UI delivery.',
                profile: $profile,
                requiredSkills: ['Laravel', 'Vue.js', 'REST APIs'],
                optionalSkills: array_slice($optionalSkills, 0, 5),
                domains: $domains,
                remotePreference: $remotePreference,
                locations: $locations,
            );
        }

        $suggestions[] = $this->pathPayload(
            name: "{$primarySkill} API Developer",
            description: "Find API-heavy roles where {$primarySkill} and system delivery matter.",
            profile: $profile,
            requiredSkills: array_slice($skills, 0, 4),
            optionalSkills: array_slice($optionalSkills, 0, 5),
            domains: $domains,
            remotePreference: $remotePreference,
            locations: $locations,
        );

        return array_slice($this->uniqueByName($suggestions), 0, 4);
    }

    /**
     * @param array<int, array<string, mixed>> $suggestions
     * @return array<int, array<string, mixed>>
     */
    private function uniqueByName(array $suggestions): array
    {
        $seen = [];

        return array_values(array_filter($suggestions, function (array $suggestion) use (&$seen): bool {
            $key = mb_strtolower($suggestion['name']);

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        }));
    }

    /**
     * @param array<int, string> $requiredSkills
     * @param array<int, string> $optionalSkills
     * @param array<int, string> $domains
     * @param array<int, string> $locations
     * @return array<string, mixed>
     */
    private function pathPayload(
        string $name,
        string $description,
        CandidateProfile $profile,
        array $requiredSkills,
        array $optionalSkills,
        array $domains,
        string $remotePreference,
        array $locations,
    ): array {
        $targetRoles = array_values(array_filter(array_unique([
            $profile->primary_role,
            ...($profile->preferred_roles ?? []),
            $profile->headline,
        ])));

        return [
            'career_profile_id' => $profile->id,
            'name' => $name,
            'description' => $description,
            'target_roles' => array_slice($targetRoles, 0, 5),
            'target_domains' => array_values(array_filter($domains)),
            'include_keywords' => array_values(array_unique(array_filter([...$targetRoles, ...$requiredSkills]))),
            'exclude_keywords' => ['translation', 'sales', 'cold calling'],
            'required_skills' => array_values(array_filter($requiredSkills)),
            'optional_skills' => array_values(array_filter($optionalSkills)),
            'seniority_levels' => array_values(array_filter([$profile->seniority_level ?: $this->inferSeniority((int) $profile->years_experience)])),
            'preferred_locations' => array_values(array_filter($locations)),
            'preferred_countries' => [],
            'preferred_job_types' => $profile->preferred_job_types ?: ['full-time'],
            'remote_preference' => $remotePreference,
            'min_relevance_score' => 60,
            'min_match_score' => 75,
            'salary_min' => $profile->salary_expectation ? (int) $profile->salary_expectation : null,
            'salary_currency' => $profile->salary_currency,
            'is_active' => true,
            'auto_collect_enabled' => false,
            'notifications_enabled' => false,
            'metadata' => [
                'suggested_by' => 'guided_onboarding',
            ],
        ];
    }

    private function inferSeniority(int $years): string
    {
        return match (true) {
            $years >= 8 => 'senior',
            $years >= 4 => 'mid',
            $years >= 1 => 'junior',
            default => 'entry',
        };
    }

    private function roleFromHeadline(?string $headline): ?string
    {
        if (! $headline) {
            return null;
        }

        return trim((string) preg_replace('/\s*[|,-].*$/', '', $headline));
    }

    private function normalizeRemotePreference(?string $value): string
    {
        return in_array($value, ['remote', 'hybrid', 'onsite', 'any'], true) ? $value : 'any';
    }

    /**
     * @param array<int, string> $skills
     */
    private function containsSkill(array $skills, string $needle): bool
    {
        return collect($skills)->contains(fn (string $skill): bool => str_contains(mb_strtolower($skill), mb_strtolower($needle)));
    }
}
