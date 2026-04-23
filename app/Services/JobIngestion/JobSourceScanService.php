<?php

namespace App\Services\JobIngestion;

use App\Jobs\AnalyzeJobJob;
use App\Modules\Jobs\Domain\Models\JobSource;

class JobSourceScanService
{
    public function __construct(
        private readonly JobSourceFetcherRegistry $fetchers,
        private readonly JobUpsertService $upserts,
    ) {
    }

    /**
     * @return array{fetched: int, created: int, updated: int, skipped: int}
     */
    public function scan(JobSource $source): array
    {
        if (! $source->is_active) {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $jobs = $this->fetchers->for($source)->fetch($source);
        $result = ['fetched' => count($jobs), 'created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($jobs as $jobData) {
            $upsert = $this->upserts->upsert($source, $jobData);

            if ($upsert['created']) {
                $result['created']++;
            } elseif ($upsert['changed']) {
                $result['updated']++;
            } else {
                $result['skipped']++;
            }

            if ($upsert['created'] || $upsert['changed']) {
                AnalyzeJobJob::dispatch($upsert['job']->id);
            }
        }

        return $result;
    }
}
