<?php

namespace App\Services\AI\Providers;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderInterface;

class NullAiProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }
}
