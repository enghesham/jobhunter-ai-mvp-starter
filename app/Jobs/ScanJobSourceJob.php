<?php

namespace App\Jobs;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\JobSourceScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanJobSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobSourceId)
    {
    }

    public function handle(JobSourceScanService $scanner): void
    {
        $jobSource = JobSource::find($this->jobSourceId);

        if (! $jobSource || ! $jobSource->is_active) {
            return;
        }

        $scanner->scan($jobSource);
    }
}
