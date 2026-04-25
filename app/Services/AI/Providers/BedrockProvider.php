<?php

namespace App\Services\AI\Providers;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;

class BedrockProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        if (! config('services.bedrock.key') || ! config('services.bedrock.secret') || ! config('services.bedrock.region')) {
            throw new AiProviderException('Bedrock credentials are not configured.');
        }

        throw new AiProviderException('BedrockProvider is a safe stub and is not implemented yet.');
    }

    public function name(): string
    {
        return 'bedrock';
    }
}
