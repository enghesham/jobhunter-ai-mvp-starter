<?php

namespace App\Services\Copilot;

use App\Models\User;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Copilot\Domain\Models\UserOpportunityPreference;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OpportunityPreferenceService
{
    /**
     * @var array<int, array<string, bool|int>>
     */
    private array $effectiveCache = [];

    /**
     * @return array<string, bool|int>
     */
    public function defaults(): array
    {
        $matchThreshold = (int) config('jobhunter.match_threshold', 75);

        return [
            'default_min_relevance_score' => (int) config('jobhunter.opportunities.default_min_relevance_score', 45),
            'default_min_match_score' => $matchThreshold,
            'quick_recommended_score' => (int) config('jobhunter.opportunities.quick_recommended_score', $matchThreshold),
            'store_below_threshold' => (bool) config('jobhunter.opportunities.store_below_threshold', false)
                || (bool) config('jobhunter.collection.store_below_threshold', false),
            'show_below_threshold' => false,
        ];
    }

    public function recordFor(User $user): UserOpportunityPreference
    {
        return UserOpportunityPreference::query()->firstOrNew(['user_id' => $user->id]);
    }

    /**
     * @return array<string, bool|int>
     */
    public function effectiveForUser(User $user): array
    {
        if (isset($this->effectiveCache[$user->id])) {
            return $this->effectiveCache[$user->id];
        }

        $defaults = $this->defaults();
        $record = $this->recordFor($user);

        $effective = [
            'default_min_relevance_score' => $record->default_min_relevance_score ?? $defaults['default_min_relevance_score'],
            'default_min_match_score' => $record->default_min_match_score ?? $defaults['default_min_match_score'],
            'quick_recommended_score' => $record->quick_recommended_score ?? $defaults['quick_recommended_score'],
            'store_below_threshold' => $record->store_below_threshold ?? $defaults['store_below_threshold'],
            'show_below_threshold' => $record->show_below_threshold ?? $defaults['show_below_threshold'],
        ];
        $effective['quick_recommended_score'] = max(
            (int) $effective['quick_recommended_score'],
            (int) $effective['default_min_match_score'],
            (int) $effective['default_min_relevance_score'],
        );

        return $this->effectiveCache[$user->id] = $effective;
    }

    public function update(User $user, array $payload): UserOpportunityPreference
    {
        $applyToExistingPaths = (bool) Arr::pull($payload, 'apply_to_existing_job_paths', false);

        return DB::transaction(function () use ($user, $payload, $applyToExistingPaths): UserOpportunityPreference {
            $record = $this->recordFor($user);
            $record->fill($payload);
            $record->user_id = $user->id;
            $record->save();

            unset($this->effectiveCache[$user->id]);
            $effective = $this->effectiveForUser($user);

            if ($applyToExistingPaths) {
                JobPath::query()
                    ->where('user_id', $user->id)
                    ->update([
                        'min_relevance_score' => $effective['default_min_relevance_score'],
                        'min_match_score' => $effective['default_min_match_score'],
                        'updated_at' => now(),
                    ]);
            }

            return $record->fresh();
        });
    }

    public function minRelevanceScore(User $user, ?JobPath $path = null): int
    {
        return (int) ($path?->min_relevance_score ?? $this->effectiveForUser($user)['default_min_relevance_score']);
    }

    public function minMatchScore(User $user, ?JobPath $path = null): int
    {
        return (int) ($path?->min_match_score ?? $this->effectiveForUser($user)['default_min_match_score']);
    }

    public function quickRecommendedScore(User $user, ?JobPath $path = null): int
    {
        $effective = $this->effectiveForUser($user);

        return max(
            $this->minRelevanceScore($user, $path),
            $this->minMatchScore($user, $path),
            (int) $effective['quick_recommended_score'],
        );
    }

    public function shouldStoreBelowThreshold(User $user): bool
    {
        return (bool) $this->effectiveForUser($user)['store_below_threshold'];
    }

    public function shouldShowBelowThreshold(User $user): bool
    {
        return (bool) $this->effectiveForUser($user)['show_below_threshold'];
    }

    /**
     * @return array<string, mixed>
     */
    public function responseFor(User $user): array
    {
        $record = $this->recordFor($user);

        return [
            'id' => $record->exists ? $record->id : null,
            'values' => [
                'default_min_relevance_score' => $record->default_min_relevance_score,
                'default_min_match_score' => $record->default_min_match_score,
                'quick_recommended_score' => $record->quick_recommended_score,
                'store_below_threshold' => $record->store_below_threshold,
                'show_below_threshold' => $record->show_below_threshold,
            ],
            'effective' => $this->effectiveForUser($user),
            'defaults' => $this->defaults(),
            'descriptions' => [
                'default_min_relevance_score' => 'Minimum quick relevance score required before a collected job becomes an opportunity. Lower values show more jobs.',
                'default_min_match_score' => 'Minimum evaluated match score required before a job is treated as a best match.',
                'quick_recommended_score' => 'Minimum quick score required to label an unevaluated opportunity as recommended. Keep this near the match threshold to reduce false positives.',
                'store_below_threshold' => 'Store weak collected jobs instead of dropping them. Useful for review, but it can make the list noisy.',
                'show_below_threshold' => 'Show weak opportunities in the default Opportunities list. Hidden jobs still stay hidden.',
            ],
        ];
    }
}
