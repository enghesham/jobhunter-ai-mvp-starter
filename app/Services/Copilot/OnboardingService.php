<?php

namespace App\Services\Copilot;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\UserOnboardingState;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\SuggestJobPathsPrompt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OnboardingService
{
    public function __construct(
        private readonly CareerProfileService $careerProfileService,
        private readonly AiProviderInterface $aiProvider,
        private readonly SuggestJobPathsPrompt $suggestJobPathsPrompt,
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
            $state = $this->state($user);
            $existingProfile = $this->profileForOnboarding($user, $state);
            $profilePayload = [
                ...$payload,
                'is_primary' => $payload['is_primary'] ?? true,
                'source' => $payload['source'] ?? 'manual',
            ];

            $profile = $existingProfile
                ? $this->careerProfileService->update($existingProfile, $profilePayload)
                : $this->careerProfileService->create($user, $profilePayload);

            $understanding = $this->summarizeProfile($profile);
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

    private function profileForOnboarding(User $user, UserOnboardingState $state): ?CandidateProfile
    {
        $profileId = $state->metadata['career_profile_id'] ?? null;

        if ($profileId) {
            $profile = CandidateProfile::query()
                ->where('user_id', $user->id)
                ->whereKey($profileId)
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        return CandidateProfile::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->latest()
            ->first();
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
        $fallbackSuggestions = $this->buildRuleBasedSuggestions($profile);
        $aiSuggestions = $this->buildAiSuggestions($profile, $fallbackSuggestions);

        return $aiSuggestions !== [] ? $aiSuggestions : $fallbackSuggestions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRuleBasedSuggestions(CandidateProfile $profile): array
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
     * @param array<int, array<string, mixed>> $fallbackSuggestions
     * @return array<int, array<string, mixed>>
     */
    private function buildAiSuggestions(CandidateProfile $profile, array $fallbackSuggestions): array
    {
        try {
            $response = $this->aiProvider->suggestJobPaths(
                $profile,
                $this->suggestJobPathsPrompt->build($profile, $fallbackSuggestions),
            );

            if (! is_array($response)) {
                return [];
            }

            $suggestions = $this->normalizeAiSuggestions($profile, $response);

            if ($suggestions === []) {
                Log::warning('AI job path suggestions returned no valid paths.', [
                    'provider' => $this->aiProvider->name(),
                    'operation' => 'job_path_suggestions',
                    'profile_id' => $profile->id,
                ]);
            }

            return $suggestions;
        } catch (Throwable $exception) {
            Log::warning('AI job path suggestions failed. Falling back to deterministic suggestions.', [
                'provider' => $this->aiProvider->name(),
                'operation' => 'job_path_suggestions',
                'profile_id' => $profile->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAiSuggestions(CandidateProfile $profile, array $response): array
    {
        $items = $response['job_paths'] ?? $response['suggestions'] ?? $response['data'] ?? [];

        if (isset($response['name']) && is_string($response['name'])) {
            $items = [$response];
        }

        if (! is_array($items) || ! array_is_list($items)) {
            return [];
        }

        $suggestions = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $suggestion = $this->normalizeAiPath($profile, $item);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }
        }

        return array_slice($this->uniqueByName($suggestions), 0, 4);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeAiPath(CandidateProfile $profile, array $item): ?array
    {
        $name = $this->cleanText($item['name'] ?? null, 80);

        if ($name === null) {
            return null;
        }

        $targetRoles = $this->stringList($item['target_roles'] ?? []);
        if ($targetRoles === []) {
            $targetRoles = array_values(array_filter(array_unique([
                $profile->primary_role,
                ...($profile->preferred_roles ?? []),
                $profile->headline,
            ])));
        }

        $requiredSkills = $this->stringList($item['required_skills'] ?? []);
        if ($requiredSkills === []) {
            $requiredSkills = array_slice($this->stringList($profile->core_skills ?? []), 0, 5);
        }

        $optionalSkills = $this->stringList($item['optional_skills'] ?? []);
        if ($optionalSkills === []) {
            $optionalSkills = array_slice($this->stringList($profile->nice_to_have_skills ?? []), 0, 5);
        }

        $locations = $this->stringList($item['preferred_locations'] ?? []);
        if ($locations === []) {
            $locations = $this->stringList($profile->preferred_locations ?: ['Remote']);
        }

        $domains = $this->stringList($item['target_domains'] ?? []);
        if ($domains === []) {
            $domains = $this->stringList($profile->industries ?: ['Technology']);
        }

        $includeKeywords = array_values(array_unique(array_filter([
            ...$this->stringList($item['include_keywords'] ?? []),
            ...$targetRoles,
            ...$requiredSkills,
        ])));

        return [
            'career_profile_id' => $profile->id,
            'name' => $name,
            'description' => $this->cleanText($item['description'] ?? null, 220),
            'target_roles' => array_slice($targetRoles, 0, 5),
            'target_domains' => array_slice($domains, 0, 8),
            'include_keywords' => array_slice($includeKeywords, 0, 15),
            'exclude_keywords' => array_values(array_unique([
                ...$this->stringList($item['exclude_keywords'] ?? []),
                'translation',
                'sales',
                'cold calling',
            ])),
            'required_skills' => array_slice($requiredSkills, 0, 10),
            'optional_skills' => array_slice($optionalSkills, 0, 10),
            'seniority_levels' => $this->stringList($item['seniority_levels'] ?? [$profile->seniority_level ?: $this->inferSeniority((int) $profile->years_experience)]),
            'preferred_locations' => array_slice($locations, 0, 10),
            'preferred_countries' => array_slice($this->stringList($item['preferred_countries'] ?? []), 0, 10),
            'preferred_job_types' => $this->stringList($item['preferred_job_types'] ?? ($profile->preferred_job_types ?: ['full-time'])),
            'remote_preference' => $this->normalizeRemotePreference(is_string($item['remote_preference'] ?? null) ? $item['remote_preference'] : $profile->preferred_workplace_type),
            'min_relevance_score' => $this->boundedScore($item['min_relevance_score'] ?? 60, 60),
            'min_match_score' => $this->boundedScore($item['min_match_score'] ?? 75, 75),
            'salary_min' => $profile->salary_expectation ? (int) $profile->salary_expectation : null,
            'salary_currency' => $profile->salary_currency,
            'is_active' => true,
            'auto_collect_enabled' => false,
            'notifications_enabled' => false,
            'metadata' => [
                'suggested_by' => 'ai_guided_onboarding',
                'ai_provider' => $this->aiProvider->name(),
                'ai_model' => $this->aiProvider->model(),
                'ai_generated_at' => now()->toISOString(),
            ],
        ];
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

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value
        ))));
    }

    private function cleanText(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function boundedScore(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max(0, min(100, (int) $value));
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
