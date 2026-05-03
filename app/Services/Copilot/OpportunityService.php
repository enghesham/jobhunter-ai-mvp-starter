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
    public function __construct(private readonly JobPathRelevanceScorer $relevanceScorer)
    {
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

        if (! (bool) ($filters['include_hidden'] ?? false)) {
            $query->whereNotIn('status', ['hidden', 'not_relevant']);
        }

        if (! empty($filters['job_path_id'])) {
            $query->where('job_path_id', (int) $filters['job_path_id']);
        }

        $items = $query->get();

        if (empty($filters['job_path_id']) && ! (bool) ($filters['show_duplicates'] ?? false)) {
            $items = $items
                ->sortByDesc(fn (JobOpportunity $opportunity): int => (int) ($opportunity->match_score ?? $opportunity->quick_relevance_score))
                ->groupBy('job_id')
                ->map(fn ($group) => $group->first())
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
                'status' => $this->statusFromMatch($match),
                'recommendation' => $match->recommendation_action ?: $match->recommendation,
                'evaluated_at' => now(),
            ])->save();
        }

        return $opportunity->fresh(['job.source', 'jobPath', 'careerProfile', 'match.profile', 'match.jobPath', 'applyPackages']);
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
        $minimum = (int) ($path?->min_relevance_score ?? config('jobhunter.opportunities.default_min_relevance_score', 45));
        $storeBelowThreshold = (bool) config('jobhunter.opportunities.store_below_threshold', false);

        if ($score['score'] < $minimum && ! $storeBelowThreshold) {
            $stats['skipped']++;

            return;
        }

        $contextKey = $path ? "path:{$path->id}" : 'primary';
        $status = $score['score'] < $minimum
            ? 'not_relevant'
            : ($score['score'] >= max(75, $minimum) ? 'recommended' : 'needs_review');

        $opportunity = JobOpportunity::query()->firstOrNew([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'context_key' => $contextKey,
        ]);
        $exists = $opportunity->exists;

        $opportunity->fill([
            'job_path_id' => $path?->id,
            'career_profile_id' => $profile?->id,
            'quick_relevance_score' => $score['score'],
            'status' => $opportunity->status === 'hidden' ? 'hidden' : $status,
            'reasons' => $score['reasons'],
            'matched_keywords' => $score['matched_keywords'],
            'missing_keywords' => $score['missing_keywords'],
        ])->save();

        $stats[$exists ? 'updated' : 'created']++;
    }

    /**
     * @return array{score: int, reasons: array<int, string>, matched_keywords: array<int, string>, missing_keywords: array<int, string>}
     */
    private function quickScore(Job $job, ?JobPath $path, ?CandidateProfile $profile): array
    {
        return $this->relevanceScorer->score($job, $path, $profile);
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

    private function statusFromMatch(JobMatch $match): string
    {
        if ($match->recommendation_action === 'skip' || (int) $match->overall_score < 45) {
            return 'not_relevant';
        }

        if ($match->recommendation_action === 'apply' || (int) $match->overall_score >= (int) config('jobhunter.match_threshold', 75)) {
            return 'recommended';
        }

        return 'evaluated';
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
