<?php

namespace App\Services\Applications;

use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Support\Collection;

class ApplicationMaterialGenerationService
{
    /**
     * @return Collection<int, ApplicationMaterial>
     */
    public function generate(Application $application, bool $force = false): Collection
    {
        $application->loadMissing(['job.analysis', 'profile.experiences', 'profile.projects', 'tailoredResume', 'materials']);

        $job = $application->job;
        $profile = $application->profile;

        if (! $job || ! $profile) {
            return collect();
        }

        $match = JobMatch::query()
            ->where('job_id', $application->job_id)
            ->where('profile_id', $application->profile_id)
            ->latest('matched_at')
            ->first();

        $promptVersion = (string) config('jobhunter.ai_operations.application_materials.prompt_version', 'v1');
        $context = $this->context($application, $match);
        $inputHash = sha1(json_encode($context, JSON_THROW_ON_ERROR).$promptVersion);

        if (! $force && $application->materials->isNotEmpty() && $application->materials->every(
            fn (ApplicationMaterial $material): bool => $material->prompt_version === $promptVersion && $material->input_hash === $inputHash
        )) {
            return $application->materials;
        }

        $templates = AnswerTemplate::query()
            ->where('user_id', $application->user_id)
            ->get()
            ->keyBy('key');

        $definitions = $this->definitions();

        foreach ($definitions as $definition) {
            $template = $templates->get($definition['key']);
            $content = $this->buildContent($definition['key'], $context, $template);

            ApplicationMaterial::updateOrCreate(
                [
                    'application_id' => $application->id,
                    'key' => $definition['key'],
                ],
                [
                    'user_id' => $application->user_id,
                    'job_id' => $application->job_id,
                    'profile_id' => $application->profile_id,
                    'answer_template_id' => $template?->id,
                    'material_type' => $definition['material_type'],
                    'title' => $definition['title'],
                    'question' => $definition['question'],
                    'content_text' => $content,
                    'metadata' => [
                        'template_key' => $definition['key'],
                        'used_template' => (bool) $template,
                        'template_title' => $template?->title,
                    ],
                    'ai_provider' => null,
                    'ai_model' => null,
                    'ai_generated_at' => now(),
                    'ai_confidence_score' => 60,
                    'ai_raw_response' => null,
                    'prompt_version' => $promptVersion,
                    'input_hash' => $inputHash,
                    'ai_duration_ms' => null,
                    'fallback_used' => true,
                ],
            );
        }

        return $application->fresh(['materials'])->materials;
    }

    /**
     * @return array<int, array{key: string, material_type: string, title: string, question: string|null}>
     */
    public function definitions(): array
    {
        return [
            ['key' => 'cover_letter', 'material_type' => 'cover_letter', 'title' => 'Cover Letter', 'question' => null],
            ['key' => 'why_interested', 'material_type' => 'application_answer', 'title' => 'Why are you interested?', 'question' => 'Why are you interested in this role?'],
            ['key' => 'about_me', 'material_type' => 'application_answer', 'title' => 'Tell us about yourself', 'question' => 'Tell us about yourself.'],
            ['key' => 'salary_expectation', 'material_type' => 'application_answer', 'title' => 'Salary expectation', 'question' => 'What is your salary expectation?'],
            ['key' => 'notice_period', 'material_type' => 'application_answer', 'title' => 'Notice period', 'question' => 'What is your notice period?'],
            ['key' => 'work_authorization', 'material_type' => 'application_answer', 'title' => 'Work authorization', 'question' => 'What is your current work authorization status?'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Application $application, ?JobMatch $match): array
    {
        $job = $application->job;
        $profile = $application->profile;
        $resume = $application->tailoredResume;

        return [
            'full_name' => $profile?->full_name,
            'headline' => $profile?->headline,
            'base_summary' => $profile?->base_summary,
            'years_experience' => $profile?->years_experience,
            'job_title' => $job?->title,
            'company_name' => $job?->company_name,
            'job_location' => $job?->location,
            'role_type' => $job?->analysis?->role_type,
            'domain_tags' => implode(', ', $job?->analysis?->domain_tags ?? []),
            'must_have_skills' => implode(', ', $job?->analysis?->must_have_skills ?? []),
            'required_skills' => implode(', ', $job?->analysis?->required_skills ?? []),
            'preferred_skills' => implode(', ', $job?->analysis?->preferred_skills ?? []),
            'match_overall_score' => $match?->overall_score,
            'recommendation' => $match?->recommendation_action,
            'strength_areas' => implode(', ', $match?->strength_areas ?? []),
            'missing_required_skills' => implode(', ', $match?->missing_required_skills ?? []),
            'nice_to_have_gaps' => implode(', ', $match?->nice_to_have_gaps ?? []),
            'resume_headline' => $resume?->headline_text,
            'resume_summary' => $resume?->summary_text,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildContent(string $key, array $context, ?AnswerTemplate $template): string
    {
        if ($template) {
            return $this->renderTemplate($template->base_answer, $context);
        }

        return match ($key) {
            'cover_letter' => $this->defaultCoverLetter($context),
            'why_interested' => $this->defaultWhyInterested($context),
            'about_me' => $this->defaultAboutMe($context),
            'salary_expectation' => 'I am open to discussing a compensation package aligned with the responsibilities of the role, the overall scope, and the market range for this position.',
            'notice_period' => 'My notice period can be confirmed based on the final offer and current commitments. I can share exact timing during the interview process.',
            'work_authorization' => 'I can clarify my current work authorization status and any location-specific requirements during the application process.',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? '';

            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return '';
        }, $template);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function defaultCoverLetter(array $context): string
    {
        $intro = sprintf(
            "Dear Hiring Team,\n\nI am interested in the %s position at %s.",
            $context['job_title'] ?: 'role',
            $context['company_name'] ?: 'your company',
        );

        $body = sprintf(
            " My background is centered on %s, with %s years of experience delivering backend-focused systems, APIs, and production software. The role stands out because of its focus on %s and the opportunity to contribute using strengths in %s.",
            $context['headline'] ?: 'backend engineering',
            $context['years_experience'] ?: 'multiple',
            $context['required_skills'] ?: 'modern engineering execution',
            $context['strength_areas'] ?: ($context['required_skills'] ?: 'relevant delivery work'),
        );

        $closing = " I would welcome the opportunity to discuss how my experience can support the team and the goals of this role.\n\nBest regards,\n".($context['full_name'] ?: 'Candidate');

        return trim($intro.$body.$closing);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function defaultWhyInterested(array $context): string
    {
        return trim(sprintf(
            'I am interested in this role because it aligns well with my background in %s and with the kind of backend and product work I want to keep building. The position at %s stands out because it emphasizes %s, which is closely connected to the experience I have already built in %s.',
            $context['headline'] ?: 'software engineering',
            $context['company_name'] ?: 'the company',
            $context['required_skills'] ?: 'relevant technical responsibilities',
            $context['strength_areas'] ?: ($context['required_skills'] ?: 'production delivery'),
        ));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function defaultAboutMe(array $context): string
    {
        return trim(sprintf(
            'I am %s, a %s with %s years of experience. My work has focused on %s, and I am strongest when building production systems around %s.',
            $context['full_name'] ?: 'a software engineer',
            $context['headline'] ?: 'backend engineer',
            $context['years_experience'] ?: 'multiple',
            $context['base_summary'] ?: 'backend systems, APIs, and product delivery',
            $context['strength_areas'] ?: ($context['required_skills'] ?: 'scalable backend systems'),
        ));
    }
}
