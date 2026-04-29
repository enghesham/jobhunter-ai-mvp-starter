<?php

namespace App\Services\AI\Prompts;

use App\Modules\Jobs\Domain\Models\Job;

class AnalyzeJobPrompt
{
    public function version(): string
    {
        return (string) config('jobhunter.ai_operations.analysis.prompt_version', 'v1');
    }

    public function build(Job $job): string
    {
        $payload = [
            'meta' => [
                'prompt_version' => $this->version(),
                'operation' => 'analysis',
            ],
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'location' => $job->location,
                'remote_type' => $job->remote_type,
                'employment_type' => $job->employment_type,
                'description' => $job->description_clean ?: $job->description_raw,
            ],
            'expected_json' => [
                'required_skills' => ['string'],
                'preferred_skills' => ['string'],
                'must_have_skills' => ['string'],
                'nice_to_have_skills' => ['string'],
                'seniority' => 'string|null',
                'role_type' => 'string|null',
                'domain_tags' => ['string'],
                'tech_stack' => ['string'],
                'responsibilities' => ['string'],
                'company_context' => 'string|null',
                'ai_summary' => 'string|null',
                'confidence_score' => 'integer 0-100',
            ],
        ];

        return "Analyze this job and return strict JSON only. Do not include markdown.\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
