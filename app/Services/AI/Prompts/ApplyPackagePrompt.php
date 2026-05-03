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
        $sections = $context['selected_sections'] ?? [];

        $payload = [
            'meta' => [
                'operation' => 'apply_package',
                'prompt_version' => $this->version(),
                'selected_sections' => $sections,
            ],
            'rules' => [
                'Use only the provided candidate facts, job facts, match data, and resume data.',
                'Do not invent companies, degrees, years, certifications, or technologies.',
                'If something is missing, put it in gaps instead of pretending the candidate has it.',
                'Keep all generated content editable and application-ready.',
                'Generate only the selected sections. Return empty strings or empty arrays for unselected sections if needed.',
                'Use answer_templates as style/content guidance when provided, but adapt them to the job and candidate facts.',
                'Return strict JSON only. Do not include markdown.',
            ],
            'candidate_profile' => $context['candidate_profile'] ?? [],
            'job' => $context['job'] ?? [],
            'job_path' => $context['job_path'] ?? null,
            'match' => $context['match'] ?? null,
            'resume' => $context['resume'] ?? null,
            'answer_templates' => $context['answer_templates'] ?? [],
            'expected_json' => $this->expectedJson($sections),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param array<int, string> $sections
     * @return array<string, mixed>
     */
    private function expectedJson(array $sections): array
    {
        $expected = [
            'confidence_score' => 'integer 0-100',
        ];

        if (in_array('cover_letter', $sections, true)) {
            $expected['cover_letter'] = 'string';
        }

        if (in_array('application_answers', $sections, true)) {
            $expected['application_answers'] = [
                [
                    'key' => 'string',
                    'question' => 'string',
                    'answer' => 'string',
                ],
            ];
        }

        if (in_array('salary_answer', $sections, true)) {
            $expected['salary_answer'] = 'string';
        }

        if (in_array('notice_period_answer', $sections, true)) {
            $expected['notice_period_answer'] = 'string';
        }

        if (in_array('interest_answer', $sections, true)) {
            $expected['interest_answer'] = 'string';
        }

        if (in_array('strengths_gaps', $sections, true)) {
            $expected['strengths'] = ['string'];
            $expected['gaps'] = ['string'];
        }

        if (in_array('interview_questions', $sections, true)) {
            $expected['interview_questions'] = ['string'];
        }

        if (in_array('follow_up_email', $sections, true)) {
            $expected['follow_up_email'] = 'string';
        }

        return $expected;
    }
}
