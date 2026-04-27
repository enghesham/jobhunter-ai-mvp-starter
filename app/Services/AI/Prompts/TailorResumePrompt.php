<?php

namespace App\Services\AI\Prompts;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;

class TailorResumePrompt
{
    /**
     * @param array<string, mixed> $resumeContext
     */
    public function build(CandidateProfile $profile, Job $job, array $resumeContext): string
    {
        $payload = [
            'rules' => [
                'Use only facts from the candidate profile, experiences, and projects.',
                'Do not invent companies, years, degrees, titles, or technologies.',
                'If something is missing, put it in warnings_or_gaps.',
                'You may rephrase summaries and bullets, but keep facts intact.',
                'Return strict JSON only.',
            ],
            'candidate_profile' => $resumeContext['candidate_profile'],
            'job' => $resumeContext['job'],
            'analysis' => $resumeContext['analysis'],
            'base_resume_payload' => $resumeContext['base_resume_payload'],
            'expected_json' => [
                'tailored_headline' => 'string',
                'tailored_summary' => 'string',
                'selected_skills' => ['string'],
                'tailored_experience_bullets' => ['string'],
                'selected_projects' => ['string'],
                'ats_keywords' => ['string'],
                'warnings_or_gaps' => ['string'],
                'confidence_score' => 'integer 0-100',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
