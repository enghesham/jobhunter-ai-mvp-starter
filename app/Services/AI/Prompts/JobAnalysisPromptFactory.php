<?php

namespace App\Services\AI\Prompts;

use App\Modules\Jobs\Domain\Models\Job;

class JobAnalysisPromptFactory
{
    public function build(Job $job): string
    {
        $description = trim((string) ($job->description_clean ?: $job->description_raw ?: ''));

        return <<<PROMPT
Analyze the following job and return strict JSON only with this exact shape:
{
  "required_skills": ["string"],
  "preferred_skills": ["string"],
  "seniority": "junior|mid|senior|lead|staff|null",
  "role_type": "backend|frontend|full_stack|platform|data|devops|null",
  "domain_tags": ["string"],
  "ai_summary": "string|null"
}

Rules:
- required_skills and preferred_skills must be arrays of short strings.
- domain_tags must be short lowercase tags.
- Do not include markdown or extra commentary.

Job title: {$job->title}
Company: {$job->company_name}
Location: {$job->location}

Description:
{$description}
PROMPT;
    }
}
