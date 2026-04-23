<?php

namespace App\Services\JobIngestion;

use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\Contracts\JobSourceFetcherInterface;
use App\Services\JobIngestion\Fetchers\GreenhouseJobSourceFetcher;
use App\Services\JobIngestion\Fetchers\LeverJobSourceFetcher;
use RuntimeException;

class JobSourceFetcherRegistry
{
    /** @var array<int, JobSourceFetcherInterface> */
    private array $fetchers;

    public function __construct()
    {
        $this->fetchers = [
            app(GreenhouseJobSourceFetcher::class),
            app(LeverJobSourceFetcher::class),
        ];
    }

    public function for(JobSource $source): JobSourceFetcherInterface
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($source)) {
                return $fetcher;
            }
        }

        throw new RuntimeException("Unsupported job source type [{$source->type}].");
    }
}
