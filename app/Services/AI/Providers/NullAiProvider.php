<?php

namespace App\Services\AI\Providers;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderInterface;

class NullAiProvider implements AiProviderInterface
{
    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return null;
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return null;
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return null;
    }

    public function suggestJobPaths(CandidateProfile $profile, string $prompt): ?array
    {
        return null;
    }

    public function generateApplyPackage(CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }

    public function model(): ?string
    {
        return null;
    }
}
