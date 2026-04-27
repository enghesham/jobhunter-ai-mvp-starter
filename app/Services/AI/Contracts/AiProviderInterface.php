<?php

namespace App\Services\AI\Contracts;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;

interface AiProviderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function analyzeJob(Job $job, string $prompt): ?array;

    /**
     * @param array<string, mixed> $scoreBreakdown
     * @return array<string, mixed>|null
     */
    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array;

    /**
     * @param array<string, mixed> $resumeContext
     * @return array<string, mixed>|null
     */
    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array;

    public function name(): string;

    public function model(): ?string;
}
