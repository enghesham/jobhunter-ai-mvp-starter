<?php

namespace App\Jobs;

use App\Modules\Copilot\Domain\Models\JobPath;
use App\Services\JobCollection\JobPathCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CollectJobsForPathJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $jobPathId)
    {
    }

    public function handle(JobPathCollectionService $collector): void
    {
        $jobPath = JobPath::query()->find($this->jobPathId);

        if (! $jobPath || ! $jobPath->is_active) {
            return;
        }

        $collector->collect($jobPath);
    }
}
