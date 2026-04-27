<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
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

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->analyzeJob($job, $prompt);
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->analyzeJob($job, $prompt);
    }

    public function name(): string
    {
        return 'bedrock';
    }

    public function model(): ?string
    {
        return (string) config('jobhunter.bedrock.model', 'anthropic.claude-3-5-sonnet');
    }
}
