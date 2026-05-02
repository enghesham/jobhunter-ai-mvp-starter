<?php

namespace App\Services\AI\Prompts;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;

class ApplyPackagePrompt
{
    public function version(): string
    {
        return (string) config('jobhunter.ai_operations.apply_package.prompt_version', 'v1');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function build(CandidateProfile $profile, Job $job, array $context): string
    {
        $payload = [
            'meta' => [
                'operation' => 'apply_package',
                'prompt_version' => $this->version(),
            ],
            'rules' => [
                'Use only the provided candidate facts, job facts, match data, and resume data.',
                'Do not invent companies, degrees, years, certifications, or technologies.',
                'If something is missing, put it in gaps instead of pretending the candidate has it.',
                'Keep all generated content editable and application-ready.',
                'Return strict JSON only. Do not include markdown.',
            ],
            'candidate_profile' => $context['candidate_profile'] ?? [],
            'job' => $context['job'] ?? [],
            'job_path' => $context['job_path'] ?? null,
            'match' => $context['match'] ?? null,
            'resume' => $context['resume'] ?? null,
            'expected_json' => [
                'cover_letter' => 'string',
                'application_answers' => [
                    [
                        'key' => 'string',
                        'question' => 'string',
                        'answer' => 'string',
                    ],
                ],
                'salary_answer' => 'string',
                'notice_period_answer' => 'string',
                'interest_answer' => 'string',
                'strengths' => ['string'],
                'gaps' => ['string'],
                'interview_questions' => ['string'],
                'follow_up_email' => 'string',
                'confidence_score' => 'integer 0-100',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
