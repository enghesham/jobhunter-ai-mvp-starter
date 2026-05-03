<?php

namespace App\Services\JobCollection;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobCollectionRun;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\Copilot\OpportunityService;
use App\Services\JobIngestion\JobSourceFetcherRegistry;
use App\Services\JobIngestion\JobUpsertService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobPathCollectionService
{
    public function __construct(
        private readonly JobSourceFetcherRegistry $fetchers,
        private readonly JobUpsertService $upserts,
        private readonly JobPathRelevanceScorer $relevanceScorer,
        private readonly OpportunityService $opportunities,
    ) {
    }

    public function collect(JobPath $jobPath): JobCollectionRun
    {
        $jobPath->loadMissing(['user', 'careerProfile']);

        $run = JobCollectionRun::query()->create([
            'user_id' => $jobPath->user_id,
            'job_path_id' => $jobPath->id,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'job_path_name' => $jobPath->name,
                'min_relevance_score' => $jobPath->min_relevance_score,
                'ai_used' => false,
                'pipeline' => ['job_path', 'safe_sources', 'normalize', 'quick_filter', 'deduplicate', 'opportunities'],
            ],
        ]);

        try {
            $stats = $this->collectIntoJobs($jobPath, $run);
            $opportunityStats = $this->opportunities->refresh($jobPath->user, $jobPath->id);

            $jobPath->forceFill([
                'last_scanned_at' => now(),
                'next_scan_at' => $jobPath->calculateNextScanAt(),
            ])->save();

            $run->forceFill([
                ...$stats,
                'opportunities_created' => $opportunityStats['created'],
                'opportunities_updated' => $opportunityStats['updated'],
                'status' => $stats['failed_count'] > 0 ? 'partial' : 'completed',
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Job collection failed.', [
                'job_path_id' => $jobPath->id,
                'user_id' => $jobPath->user_id,
                'message' => $exception->getMessage(),
            ]);

            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $run->fresh('jobPath');
    }

    /**
     * @return array<int, JobCollectionRun>
     */
    public function collectDueForUser(User $user): array
    {
        return $this->duePaths($user)
            ->map(fn (JobPath $path): JobCollectionRun => $this->collect($path))
            ->all();
    }

    /**
     * @return Collection<int, JobPath>
     */
    public function duePaths(User $user): Collection
    {
        return JobPath::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('auto_collect_enabled', true)
            ->where(function ($query): void {
                $query->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', now());
            })
            ->with(['user', 'careerProfile'])
            ->get();
    }

    /**
     * @return Collection<int, JobPath>
     */
    public function activePaths(User $user): Collection
    {
        return JobPath::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['user', 'careerProfile'])
            ->get();
    }

    /**
     * @return array{
     *     source_count: int,
     *     fetched_count: int,
     *     accepted_count: int,
     *     created_count: int,
     *     updated_count: int,
     *     duplicate_count: int,
     *     filtered_count: int,
     *     failed_count: int,
     *     metadata: array<string, mixed>
     * }
     */
    private function collectIntoJobs(JobPath $jobPath, JobCollectionRun $run): array
    {
        $profile = $jobPath->careerProfile ?: $this->primaryProfile($jobPath->user);
        $sources = $this->sources($jobPath->user);
        $stats = [
            'source_count' => $sources->count(),
            'fetched_count' => 0,
            'accepted_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'duplicate_count' => 0,
            'filtered_count' => 0,
            'failed_count' => 0,
            'metadata' => [
                ...($run->metadata ?? []),
                'sources' => [],
            ],
        ];

        foreach ($sources as $source) {
            $sourceStats = [
                'source_id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'fetched' => 0,
                'accepted' => 0,
                'created' => 0,
                'updated' => 0,
                'duplicates' => 0,
                'filtered' => 0,
                'failed' => false,
            ];

            try {
                $jobs = $this->fetchers->for($source)->fetch($source);
                $sourceStats['fetched'] = count($jobs);
                $stats['fetched_count'] += count($jobs);

                foreach ($jobs as $jobData) {
                    $score = $this->relevanceScorer->score($jobData, $jobPath, $profile);

                    if (! $this->shouldStore($score['score'], $jobPath)) {
                        $stats['filtered_count']++;
                        $sourceStats['filtered']++;

                        continue;
                    }

                    $upsert = $this->upserts->upsert($source, $jobData);
                    $stats['accepted_count']++;
                    $sourceStats['accepted']++;

                    if ($upsert['created']) {
                        $stats['created_count']++;
                        $sourceStats['created']++;
                    } elseif ($upsert['changed']) {
                        $stats['updated_count']++;
                        $sourceStats['updated']++;
                    } else {
                        $stats['duplicate_count']++;
                        $sourceStats['duplicates']++;
                    }
                }
            } catch (Throwable $exception) {
                $stats['failed_count']++;
                $sourceStats['failed'] = true;
                $sourceStats['error'] = $exception->getMessage();

                Log::warning('Job source collection failed.', [
                    'source_id' => $source->id,
                    'source_type' => $source->type,
                    'job_path_id' => $jobPath->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            $stats['metadata']['sources'][] = $sourceStats;
        }

        return $stats;
    }

    /**
     * @return Collection<int, JobSource>
     */
    private function sources(User $user): Collection
    {
        $safeTypes = config('jobhunter.collection.safe_source_types', ['rss', 'greenhouse', 'lever']);

        return JobSource::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('type', $safeTypes)
            ->get();
    }

    private function shouldStore(int $score, JobPath $jobPath): bool
    {
        if ((bool) config('jobhunter.collection.store_below_threshold', false)) {
            return true;
        }

        return $score >= (int) $jobPath->min_relevance_score;
    }

    private function primaryProfile(User $user): ?CandidateProfile
    {
        return CandidateProfile::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_primary')
            ->latest()
            ->first();
    }
}
