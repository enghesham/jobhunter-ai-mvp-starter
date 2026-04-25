<?php

namespace App\Services\AI\Contracts;

use App\Modules\Jobs\Domain\Models\Job;

interface AiProviderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function analyzeJob(Job $job, string $prompt): ?array;

    public function name(): string;
}
