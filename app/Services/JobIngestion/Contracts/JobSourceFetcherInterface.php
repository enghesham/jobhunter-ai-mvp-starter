<?php

namespace App\Services\JobIngestion\Contracts;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\DTO\NormalizedJobData;

interface JobSourceFetcherInterface
{
    /**
     * @return array<int, NormalizedJobData>
     */
    public function fetch(JobSource $source): array;

    public function supports(JobSource $source): bool;
}
