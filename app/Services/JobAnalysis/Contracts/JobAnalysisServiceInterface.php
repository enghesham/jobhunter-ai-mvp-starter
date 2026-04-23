<?php

namespace App\Services\JobAnalysis\Contracts;

use App\Modules\Jobs\Domain\Models\Job;

interface JobAnalysisServiceInterface
{
    /**
     * @return array{
     *     required_skills: array<int, string>,
     *     preferred_skills: array<int, string>,
     *     seniority: string|null,
     *     role_type: string|null,
     *     domain_tags: array<int, string>,
     *     ai_summary: string|null
     * }
     */
    public function analyze(Job $job): array;
}
