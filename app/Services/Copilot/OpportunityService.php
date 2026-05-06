<?php

namespace App\Services\Copilot;

use App\Jobs\AnalyzeJobJob;
use App\Jobs\MatchJobToProfileJob;
use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobOpportunity;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Services\JobCollection\JobPathRelevanceScorer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public function __construct(
        private readonly JobPathRelevanceScorer $relevanceScorer,
        private readonly OpportunityPreferenceService $preferences,
    ) {
    }

    /**
     * @return Collection<int, JobOpportunity>
     */
    public function list(User $user, array $filters = []): Collection
    {
        $query = JobOpportunity::query()
            ->where('user_id', $user->id)
            ->with([
                'job.source',
                'jobPath',
                'careerProfile',
                'match.profile',
                'match.jobPath',
                'applyPackages' => fn ($query) => $query->where('user_id', $user->id),
            ]);

        $includeHidden = (bool) ($filters['include_hidden'] ?? false);
        $showBelowThreshold = $includeHidden || $this->preferences->shouldShowBelowThreshold($user);

        if (! $includeHidden) {
            $query->where('status', '!=', 'hidden');
        }

        if (! $showBelowThreshold) {
            $query->where(function ($query): void {
                $query
                    ->where('status', '!=', 'not_relevant')
                    ->orWhereNotNull('match_id')
                    ->orWhereNotNull('evaluated_at');
            });
        }

        if (! empty($filters['job_path_id'])) {
            $query->where('job_path_id', (int) $filters['job_path_id']);
        }

        $items = $query->get();

        if (empty($filters['job_path_id']) && ! (bool) ($filters['show_duplicates'] ?? false)) {
            $items = $items
                ->groupBy('job_id')
                ->map(fn ($group) => $group->sort(fn (JobOpportunity $a, JobOpportunity $b): int => $this->compareForDefaultRepresentation($a, $b))->first())
                ->values();
        }

        return $items
            ->sort(function (JobOpportunity $a, JobOpportunity $b): int {
                $scoreCompare = ((int) ($b->match_score ?? $b->quick_relevance_score)) <=> ((int) ($a->match_score ?? $a->quick_relevance_score));

                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                return ($b->job?->posted_at?->timestamp ?? $b->job?->created_at?->timestamp ?? 0)
                    <=> ($a->job?->posted_at?->timestamp ?? $a->job?->created_at?->timestamp ?? 0);
            })
            ->values();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, evaluated: int}
     */
    public function refresh(User $user, ?int $jobPathId = null): array
    {
        $paths = $this->pathsForRefresh($user, $jobPathId);
        $jobs = Job::query()
            ->where('user_id', $user->id)
            ->with(['analysis'])
            ->latest('posted_at')
            ->limit((int) config('jobhunter.opportunities.max_jobs_per_refresh', 200))
            ->get();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'evaluated' => 0];

        if ($paths->isEmpty()) {
            $profile = $this->primaryProfile($user);

            foreach ($jobs as $job) {
                $this->storeOpportunity($user, $job, null, $profile, $stats);
            }

            $stats['evaluated'] = $this->autoEvaluateTopCandidates($user, $jobPathId);

            return $stats;
        }

        foreach ($paths as $path) {
            $profile = $path->careerProfile ?: $this->primaryProfile($user);

            foreach ($jobs as $job) {
                $this->storeOpportunity($user, $job, $path, $profile, $stats);
            }
        }

        $stats['evaluated'] = $this->autoEvaluateTopCandidates($user, $jobPathId);

        return $stats;
    }

    public function evaluate(User $user, JobOpportunity $opportunity, bool $force = false): JobOpportunity
    {
        if ((int) $opportunity->user_id !== (int) $user->id) {
            abort(403);
        }

        $opportunity->loadMissing(['job', 'jobPath.careerProfile', 'careerProfile']);
        $profile = $opportunity->careerProfile
            ?: $opportunity->jobPath?->careerProfile
            ?: $this->primaryProfile($user);

        if (! $profile) {
            throw ValidationException::withMessages([
                'career_profile' => 'Create a Career Profile before evaluating opportunities.',
            ]);
        }

        AnalyzeJobJob::dispatchSync($opportunity->job_id, $force);
        MatchJobToProfileJob::dispatchSync($opportunity->job_id, $profile->id, $force, $opportunity->job_path_id);

        $contextKey = $opportunity->job_path_id ? "path:{$opportunity->job_path_id}" : 'primary';
        $match = JobMatch::query()
            ->where('job_id', $opportunity->job_id)
            ->where('profile_id', $profile->id)
            ->where('context_key', $contextKey)
            ->first();

        if ($match) {
            $opportunity->forceFill([
                'career_profile_id' => $profile->id,
                'match_id' => $match->id,
                'match_score' => $match->overall_score,
                'status' => $this->statusFromMatch($user, $match, $opportunity->jobPath),
                'recommendation' => $match->recommendation_action ?: $match->recommendation,
                'evaluated_at' => now(),
            ])->save();
        } else {
            throw ValidationException::withMessages([
                'opportunity' => 'The opportunity could not be evaluated. Please try again.',
            ]);
        }

        return $opportunity->fresh(['job.source', 'jobPath', 'careerProfile', 'match.profile', 'match.jobPath', 'applyPackages']);
    }

    /**
     * @param array<int, string> $skills
     *
     * @return array{opportunity: JobOpportunity, profile: CandidateProfile, added_core_skills: array<int, string>, added_nice_to_have_skills: array<int, string>}
     */
    public function addMissingSkillsToProfile(User $user, JobOpportunity $opportunity, array $skills): array
    {
        if ((int) $opportunity->user_id !== (int) $user->id) {
            abort(403);
        }

        $opportunity->loadMissing(['jobPath.careerProfile', 'careerProfile', 'match']);
        $profile = $opportunity->careerProfile
            ?: $opportunity->jobPath?->careerProfile
            ?: $this->primaryProfile($user);

        if (! $profile || (int) $profile->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'career_profile' => 'Create or select a Career Profile before updating profile skills.',
            ]);
        }

        $coreSkills = $this->uniqueStrings($profile->core_skills ?? []);
        $niceToHaveSkills = $this->uniqueStrings($profile->nice_to_have_skills ?? []);
        $niceToHaveGaps = $this->lowercaseLookup($opportunity->match?->nice_to_have_gaps ?? []);
        $addedCoreSkills = [];
        $addedNiceToHaveSkills = [];

        foreach ($this->uniqueStrings($skills) as $skill) {
            if ($this->containsSkill($coreSkills, $skill) || $this->containsSkill($niceToHaveSkills, $skill)) {
                continue;
            }

            if (isset($niceToHaveGaps[mb_strtolower($skill)])) {
                $niceToHaveSkills[] = $skill;
                $addedNiceToHaveSkills[] = $skill;
            } else {
                $coreSkills[] = $skill;
                $addedCoreSkills[] = $skill;
            }
        }

        $metadata = $profile->metadata ?? [];
        $metadata['opportunity_skill_updates'][] = [
            'opportunity_id' => $opportunity->id,
            'job_id' => $opportunity->job_id,
            'job_path_id' => $opportunity->job_path_id,
            'skills' => array_values(array_merge($addedCoreSkills, $addedNiceToHaveSkills)),
            'updated_at' => now()->toISOString(),
        ];

        $profile->forceFill([
            'core_skills' => $coreSkills,
            'nice_to_have_skills' => $niceToHaveSkills,
            'metadata' => $metadata,
        ])->save();

        return [
            'opportunity' => $opportunity->fresh(['job.source', 'jobPath', 'careerProfile', 'match.profile', 'match.jobPath', 'applyPackages']),
            'profile' => $profile->fresh(['experiences', 'projects']),
            'added_core_skills' => $addedCoreSkills,
            'added_nice_to_have_skills' => $addedNiceToHaveSkills,
        ];
    }

    public function hide(User $user, JobOpportunity $opportunity, ?string $reason = null): JobOpportunity
    {
        if ((int) $opportunity->user_id !== (int) $user->id) {
            abort(403);
        }

        $opportunity->forceFill([
            'status' => 'hidden',
            'hidden_at' => now(),
            'hidden_reason' => $reason,
        ])->save();

        return $opportunity->fresh(['job.source', 'jobPath', 'careerProfile', 'match.profile', 'match.jobPath', 'applyPackages']);
    }

    public function restore(User $user, JobOpportunity $opportunity): JobOpportunity
    {
        if ((int) $opportunity->user_id !== (int) $user->id) {
            abort(403);
        }

        $opportunity->forceFill([
            'status' => $opportunity->match_id ? 'evaluated' : 'needs_review',
            'hidden_at' => null,
            'hidden_reason' => null,
        ])->save();

        return $opportunity->fresh(['job.source', 'jobPath', 'careerProfile', 'match.profile', 'match.jobPath', 'applyPackages']);
    }

    /**
     * @return Collection<int, JobPath>
     */
    private function pathsForRefresh(User $user, ?int $jobPathId): Collection
    {
        $query = JobPath::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('careerProfile');

        if ($jobPathId !== null) {
            $query->whereKey($jobPathId);
        }

        return $query->get();
    }

    /**
     * @param array{created: int, updated: int, skipped: int, evaluated: int} $stats
     */
    private function storeOpportunity(User $user, Job $job, ?JobPath $path, ?CandidateProfile $profile, array &$stats): void
    {
        $score = $this->quickScore($job, $path, $profile);
        $minimum = $this->preferences->minRelevanceScore($user, $path);
        $recommendedThreshold = $this->preferences->quickRecommendedScore($user, $path);
        $storeBelowThreshold = $this->preferences->shouldStoreBelowThreshold($user);

        $contextKey = $path ? "path:{$path->id}" : 'primary';
        $status = $score['score'] < $minimum
            ? 'not_relevant'
            : ($score['score'] >= $recommendedThreshold ? 'recommended' : 'needs_review');

        $opportunity = JobOpportunity::query()->firstOrNew([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'context_key' => $contextKey,
        ]);
        $exists = $opportunity->exists;
        $isEvaluated = $opportunity->exists && ($opportunity->match_id !== null || $opportunity->evaluated_at !== null);

        if ($score['score'] < $minimum && ! $storeBelowThreshold && ! $isEvaluated) {
            $stats['skipped']++;

            return;
        }

        $opportunity->fill([
            'job_path_id' => $path?->id,
            'career_profile_id' => $opportunity->career_profile_id ?: $profile?->id,
            'quick_relevance_score' => $score['score'],
            'status' => $this->statusForRefresh($opportunity, $status),
            'reasons' => $score['reasons'],
            'matched_keywords' => $score['matched_keywords'],
            'missing_keywords' => $score['missing_keywords'],
        ])->save();

        $stats[$exists ? 'updated' : 'created']++;
    }

    private function statusForRefresh(JobOpportunity $opportunity, string $quickStatus): string
    {
        if ($opportunity->status === 'hidden') {
            return 'hidden';
        }

        if ($opportunity->match_id !== null || $opportunity->evaluated_at !== null) {
            return $opportunity->status ?: 'evaluated';
        }

        return $quickStatus;
    }

    /**
     * @return array{score: int, reasons: array<int, string>, matched_keywords: array<int, string>, missing_keywords: array<int, string>}
     */
    private function quickScore(Job $job, ?JobPath $path, ?CandidateProfile $profile): array
    {
        return $this->relevanceScorer->score($job, $path, $profile);
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return array<int, string>
     */
    private function uniqueStrings(array $values): array
    {
        $seen = [];
        $items = [];

        foreach ($values as $value) {
            $item = trim((string) $value);
            $key = mb_strtolower($item);

            if ($item === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<string, true>
     */
    private function lowercaseLookup(array $values): array
    {
        $lookup = [];

        foreach ($values as $value) {
            $item = trim((string) $value);

            if ($item !== '') {
                $lookup[mb_strtolower($item)] = true;
            }
        }

        return $lookup;
    }

    /**
     * @param array<int, string> $skills
     */
    private function containsSkill(array $skills, string $skill): bool
    {
        $needle = mb_strtolower($skill);

        foreach ($skills as $item) {
            if (mb_strtolower($item) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function primaryProfile(User $user): ?CandidateProfile
    {
        return CandidateProfile::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->latest()
            ->first();
    }

    private function autoEvaluateTopCandidates(User $user, ?int $jobPathId): int
    {
        if (! (bool) config('jobhunter.opportunities.auto_ai_evaluation_enabled', false)) {
            return 0;
        }

        $limit = (int) config('jobhunter.opportunities.max_ai_evaluations_per_refresh', 0);

        if ($limit < 1) {
            return 0;
        }

        $query = JobOpportunity::query()
            ->where('user_id', $user->id)
            ->whereNull('match_id')
            ->whereNotIn('status', ['hidden', 'not_relevant'])
            ->orderByDesc('quick_relevance_score')
            ->limit($limit);

        if ($jobPathId !== null) {
            $query->where('job_path_id', $jobPathId);
        }

        $count = 0;

        foreach ($query->get() as $opportunity) {
            $this->evaluate($user, $opportunity);
            $count++;
        }

        return $count;
    }

    private function statusFromMatch(User $user, JobMatch $match, ?JobPath $path): string
    {
        if ($match->recommendation_action === 'skip') {
            return 'evaluated';
        }

        if ($match->recommendation_action === 'apply' || (int) $match->overall_score >= $this->preferences->minMatchScore($user, $path)) {
            return 'recommended';
        }

        return 'evaluated';
    }

    private function compareForDefaultRepresentation(JobOpportunity $a, JobOpportunity $b): int
    {
        $evaluatedCompare = ((int) $this->isEvaluated($b)) <=> ((int) $this->isEvaluated($a));

        if ($evaluatedCompare !== 0) {
            return $evaluatedCompare;
        }

        $scoreCompare = ((int) ($b->match_score ?? $b->quick_relevance_score)) <=> ((int) ($a->match_score ?? $a->quick_relevance_score));

        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        return ($b->updated_at?->timestamp ?? 0) <=> ($a->updated_at?->timestamp ?? 0);
    }

    private function isEvaluated(JobOpportunity $opportunity): bool
    {
        return $opportunity->match_id !== null || $opportunity->evaluated_at !== null;
    }

    private function jobText(Job $job): string
    {
        return mb_strtolower(implode(' ', array_filter([
            $job->title,
            $job->company_name,
            $job->location,
            $job->remote_type,
            $job->employment_type,
            $job->description_clean,
            $job->description_raw,
            $job->salary_text,
        ])));
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function matchedTerms(string $text, array $terms): array
    {
        return collect($terms)
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '' && str_contains($text, mb_strtolower($term)))
            ->unique()
            ->values()
            ->all();
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

    private function locationMatches(Job $job, ?JobPath $path, ?CandidateProfile $profile): bool
    {
        if ($path?->remote_preference === 'remote' && ((bool) $job->is_remote || $job->remote_type === 'remote')) {
            return true;
        }

        if (in_array($path?->remote_preference, ['hybrid', 'any'], true) && in_array($job->remote_type, ['remote', 'hybrid'], true)) {
            return true;
        }

        $location = mb_strtolower($job->location ?: '');
        $preferredLocations = $this->stringList($path?->preferred_locations ?? $profile?->preferred_locations ?? []);

        return $location !== '' && collect($preferredLocations)->contains(
            fn (string $preferred): bool => $preferred !== '' && str_contains($location, mb_strtolower($preferred))
        );
    }
}
