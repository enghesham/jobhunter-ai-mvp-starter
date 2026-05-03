<?php

namespace App\Services\Applications;

use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Applications\Domain\Models\ApplyPackage;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\ApplyPackagePrompt;
use App\Services\Resume\ResumeGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplyPackageService
{
    private const DEFAULT_SECTIONS = [
        'tailored_resume',
        'cover_letter',
        'application_answers',
        'salary_answer',
        'notice_period_answer',
        'interest_answer',
        'strengths_gaps',
        'interview_questions',
        'follow_up_email',
    ];

    public function __construct(
        private readonly ResumeGenerationService $resumeGenerationService,
        private readonly ApplicationService $applicationService,
        private readonly AiProviderInterface $aiProvider,
        private readonly ApplyPackagePrompt $prompt,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function generate(Job $job, int $userId, array $payload = []): ApplyPackage
    {
        $resolved = $this->resolveContext($job, $userId, $payload);
        $profile = $resolved['profile'];
        $jobPath = $resolved['job_path'];
        $match = $resolved['match'];
        $force = (bool) ($payload['force'] ?? false);
        $sections = $this->sectionsFromPayload($payload);
        $existing = $this->existingPackage($job, $profile, $jobPath);

        $resume = in_array('tailored_resume', $sections, true)
            ? $this->resumeGenerationService->generate($job, $profile, 'apply-package', $force)
            : $existing?->resume;

        $context = $this->context($job, $profile, $jobPath, $match, $resume, $sections);
        $promptVersion = $this->prompt->version();
        $inputHash = hash('sha256', json_encode($context, JSON_UNESCAPED_SLASHES).$promptVersion);

        if (! $force && ($cached = $this->cachedPackage($job, $profile, $jobPath, $promptVersion, $inputHash))) {
            return $cached;
        }

        $startedAt = microtime(true);
        $baseContent = $this->contentFromExisting($existing);
        $fallback = $this->filterContentForSections($this->fallbackPackage($context), $sections);
        $generatedContent = $fallback;
        $content = $fallback;
        $metadata = [
            'source' => 'fallback',
            'match_id' => $match?->id,
            'selected_sections' => $sections,
            'answer_template_keys' => collect($context['answer_templates'] ?? [])->pluck('key')->values()->all(),
        ];
        $aiProvider = null;
        $aiModel = null;
        $aiGeneratedAt = null;
        $aiConfidenceScore = null;
        $fallbackUsed = true;

        try {
            $response = $this->aiProvider->generateApplyPackage(
                $profile,
                $job,
                $context,
                $this->prompt->build($profile, $job, $context),
            );

            if ($response !== null && ($validated = $this->validateAiPayload($response, $sections)) !== null) {
                $generatedContent = array_replace($generatedContent, $validated);
                $metadata = array_merge($metadata, [
                    'source' => 'ai',
                    'raw_response' => (app()->isLocal() || config('app.debug')) ? ($response['_raw_response'] ?? null) : null,
                ]);
                $aiProvider = $this->aiProvider->name();
                $aiModel = $this->aiProvider->model();
                $aiGeneratedAt = now();
                $aiConfidenceScore = (int) ($response['confidence_score'] ?? 0);
                $fallbackUsed = false;
            }
        } catch (Throwable $exception) {
            Log::warning('AI apply package generation failed. Falling back to deterministic package.', [
                'provider' => $this->aiProvider->name(),
                'operation' => 'apply_package',
                'job_id' => $job->id,
                'profile_id' => $profile->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $content = $this->mergeSelectedContent($baseContent, $generatedContent, $sections);

        return ApplyPackage::query()->updateOrCreate(
            [
                'job_id' => $job->id,
                'career_profile_id' => $profile->id,
                'job_path_id' => $jobPath?->id,
            ],
            [
                'user_id' => $userId,
                'resume_id' => $resume?->id ?? $existing?->resume_id,
                'cover_letter' => $content['cover_letter'],
                'application_answers' => $content['application_answers'],
                'salary_answer' => $content['salary_answer'],
                'notice_period_answer' => $content['notice_period_answer'],
                'interest_answer' => $content['interest_answer'],
                'strengths' => $content['strengths'],
                'gaps' => $content['gaps'],
                'interview_questions' => $content['interview_questions'],
                'follow_up_email' => $content['follow_up_email'],
                'ai_provider' => $aiProvider,
                'ai_model' => $aiModel,
                'ai_generated_at' => $aiGeneratedAt,
                'ai_confidence_score' => $aiConfidenceScore,
                'ai_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'prompt_version' => $promptVersion,
                'input_hash' => $inputHash,
                'fallback_used' => $fallbackUsed,
                'status' => 'ready',
                'metadata' => $metadata,
            ],
        )->fresh(['job', 'careerProfile', 'jobPath', 'resume', 'application']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(ApplyPackage $package, array $payload): ApplyPackage
    {
        $package->fill($payload);
        $package->save();

        return $package->fresh(['job', 'careerProfile', 'jobPath', 'resume', 'application']);
    }

    public function createApplication(ApplyPackage $package): Application
    {
        return DB::transaction(function () use ($package): Application {
            if ($package->application_id) {
                return $package->application()->with(['job', 'profile', 'tailoredResume', 'events', 'materials'])->firstOrFail();
            }

            $application = $this->applicationService->create([
                'user_id' => $package->user_id,
                'job_id' => $package->job_id,
                'profile_id' => $package->career_profile_id,
                'tailored_resume_id' => $package->resume_id,
                'status' => ApplicationStatus::ReadyToApply->value,
                'notes' => 'Created from apply package.',
            ]);

            $package->forceFill([
                'application_id' => $application->id,
                'status' => 'used',
            ])->save();

            $this->syncApplicationMaterials($package, $application);

            return $application->fresh(['job', 'profile', 'tailoredResume', 'events', 'materials']);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{profile: CandidateProfile, job_path: ?JobPath, match: ?JobMatch}
     */
    private function resolveContext(Job $job, int $userId, array $payload): array
    {
        $profileId = $payload['career_profile_id'] ?? $payload['profile_id'] ?? null;
        $jobPath = null;
        $match = null;

        if (! empty($payload['job_path_id'])) {
            $jobPath = JobPath::query()
                ->where('user_id', $userId)
                ->whereKey((int) $payload['job_path_id'])
                ->with('careerProfile')
                ->firstOrFail();
            $profileId = $profileId ?: $jobPath->career_profile_id;
        }

        if (! $jobPath && ! $profileId) {
            $match = JobMatch::query()
                ->where('user_id', $userId)
                ->where('job_id', $job->id)
                ->whereNotNull('job_path_id')
                ->with('jobPath')
                ->orderByDesc('overall_score')
                ->latest('matched_at')
                ->first();

            if ($match) {
                $profileId = $match->profile_id;
                $jobPath = $match->jobPath;
            }
        }

        $profile = CandidateProfile::query()
            ->where('user_id', $userId)
            ->when($profileId, fn ($query) => $query->whereKey((int) $profileId))
            ->when(! $profileId, fn ($query) => $query->orderByDesc('is_primary')->latest())
            ->with(['experiences', 'projects'])
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'career_profile_id' => 'Create or select a Career Profile before generating an apply package.',
            ]);
        }

        if (! $match) {
            $match = JobMatch::query()
                ->where('user_id', $userId)
                ->where('job_id', $job->id)
                ->where('profile_id', $profile->id)
                ->when($jobPath, fn ($query) => $query->where('job_path_id', $jobPath->id))
                ->orderByDesc('overall_score')
                ->latest('matched_at')
                ->first();
        }

        return [
            'profile' => $profile,
            'job_path' => $jobPath,
            'match' => $match,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function existingPackage(Job $job, CandidateProfile $profile, ?JobPath $jobPath): ?ApplyPackage
    {
        return ApplyPackage::query()
            ->where('job_id', $job->id)
            ->where('career_profile_id', $profile->id)
            ->where('job_path_id', $jobPath?->id)
            ->with(['job', 'careerProfile', 'jobPath', 'resume', 'application'])
            ->first();
    }

    private function cachedPackage(Job $job, CandidateProfile $profile, ?JobPath $jobPath, string $promptVersion, string $inputHash): ?ApplyPackage
    {
        return ApplyPackage::query()
            ->where('job_id', $job->id)
            ->where('career_profile_id', $profile->id)
            ->where('job_path_id', $jobPath?->id)
            ->where('prompt_version', $promptVersion)
            ->where('input_hash', $inputHash)
            ->with(['job', 'careerProfile', 'jobPath', 'resume', 'application'])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Job $job, CandidateProfile $profile, ?JobPath $jobPath, ?JobMatch $match, mixed $resume, array $sections): array
    {
        $job->loadMissing('analysis');
        $profile->loadMissing(['experiences', 'projects']);

        return [
            'selected_sections' => $sections,
            'candidate_profile' => [
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
                'summary' => $profile->base_summary,
                'years_experience' => $profile->years_experience,
                'skills' => $profile->core_skills ?? [],
                'nice_to_have_skills' => $profile->nice_to_have_skills ?? [],
                'salary_expectation' => $profile->salary_expectation,
                'salary_currency' => $profile->salary_currency,
                'experiences' => $profile->experiences->map(fn ($experience): array => [
                    'company' => $experience->company,
                    'title' => $experience->title,
                    'description' => Str::limit((string) $experience->description, 600, ''),
                    'achievements' => $experience->achievements ?? [],
                    'skills' => $experience->tech_stack ?? [],
                ])->values()->all(),
                'projects' => $profile->projects->map(fn ($project): array => [
                    'name' => $project->name,
                    'description' => Str::limit((string) $project->description, 500, ''),
                    'skills' => $project->tech_stack ?? [],
                ])->values()->all(),
            ],
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'location' => $job->location,
                'url' => $job->apply_url,
                'analysis' => $job->analysis?->toArray() ?? [],
            ],
            'job_path' => $jobPath ? [
                'id' => $jobPath->id,
                'name' => $jobPath->name,
                'required_skills' => $jobPath->required_skills ?? [],
                'optional_skills' => $jobPath->optional_skills ?? [],
                'target_roles' => $jobPath->target_roles ?? [],
            ] : null,
            'match' => $match?->toArray(),
            'resume' => [
                'id' => $resume?->id,
                'headline' => $resume?->headline_text,
                'summary' => $resume?->summary_text,
                'skills' => $resume?->selected_skills ?? [],
                'warnings_or_gaps' => $resume?->warnings_or_gaps ?? [],
            ],
            'answer_templates' => $this->answerTemplates($profile->user_id),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function sectionsFromPayload(array $payload): array
    {
        $sections = $payload['sections'] ?? self::DEFAULT_SECTIONS;

        if (! is_array($sections) || $sections === []) {
            return self::DEFAULT_SECTIONS;
        }

        return collect($sections)
            ->map(fn (mixed $section): string => trim((string) $section))
            ->filter(fn (string $section): bool => in_array($section, self::DEFAULT_SECTIONS, true))
            ->unique()
            ->values()
            ->all() ?: self::DEFAULT_SECTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFromExisting(?ApplyPackage $package): array
    {
        return [
            'cover_letter' => $package?->cover_letter,
            'application_answers' => $package?->application_answers ?? [],
            'salary_answer' => $package?->salary_answer,
            'notice_period_answer' => $package?->notice_period_answer,
            'interest_answer' => $package?->interest_answer,
            'strengths' => $package?->strengths ?? [],
            'gaps' => $package?->gaps ?? [],
            'interview_questions' => $package?->interview_questions ?? [],
            'follow_up_email' => $package?->follow_up_email,
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $generated
     * @param array<int, string> $sections
     * @return array<string, mixed>
     */
    private function mergeSelectedContent(array $base, array $generated, array $sections): array
    {
        $content = $base;

        foreach ($this->fieldsForSections($sections) as $field) {
            $content[$field] = $generated[$field] ?? $this->emptyValueForField($field);
        }

        return [
            'cover_letter' => $content['cover_letter'] ?? null,
            'application_answers' => $content['application_answers'] ?? [],
            'salary_answer' => $content['salary_answer'] ?? null,
            'notice_period_answer' => $content['notice_period_answer'] ?? null,
            'interest_answer' => $content['interest_answer'] ?? null,
            'strengths' => $content['strengths'] ?? [],
            'gaps' => $content['gaps'] ?? [],
            'interview_questions' => $content['interview_questions'] ?? [],
            'follow_up_email' => $content['follow_up_email'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @param array<int, string> $sections
     * @return array<string, mixed>
     */
    private function filterContentForSections(array $content, array $sections): array
    {
        $filtered = [];

        foreach ($this->fieldsForSections($sections) as $field) {
            $filtered[$field] = $content[$field] ?? $this->emptyValueForField($field);
        }

        return $filtered;
    }

    /**
     * @param array<int, string> $sections
     * @return array<int, string>
     */
    private function fieldsForSections(array $sections): array
    {
        $fields = [];

        foreach ($sections as $section) {
            $fields = [
                ...$fields,
                ...match ($section) {
                    'cover_letter' => ['cover_letter'],
                    'application_answers' => ['application_answers'],
                    'salary_answer' => ['salary_answer'],
                    'notice_period_answer' => ['notice_period_answer'],
                    'interest_answer' => ['interest_answer'],
                    'strengths_gaps' => ['strengths', 'gaps'],
                    'interview_questions' => ['interview_questions'],
                    'follow_up_email' => ['follow_up_email'],
                    default => [],
                },
            ];
        }

        return array_values(array_unique($fields));
    }

    private function emptyValueForField(string $field): mixed
    {
        return in_array($field, ['application_answers', 'strengths', 'gaps', 'interview_questions'], true) ? [] : null;
    }

    /**
     * @return array<int, array{key: string, title: string, base_answer: string}>
     */
    private function answerTemplates(int $userId): array
    {
        return AnswerTemplate::query()
            ->where('user_id', $userId)
            ->get(['key', 'title', 'base_answer'])
            ->map(fn (AnswerTemplate $template): array => [
                'key' => $template->key,
                'title' => $template->title,
                'base_answer' => Str::limit($template->base_answer, 1200, ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function fallbackPackage(array $context): array
    {
        $candidate = $context['candidate_profile'];
        $job = $context['job'];
        $match = $context['match'] ?? [];
        $resume = $context['resume'] ?? [];
        $templateContext = $this->templateContext($context);
        $company = $job['company_name'] ?: 'your company';
        $title = $job['title'] ?: 'the role';
        $name = $candidate['full_name'] ?: 'Candidate';
        $headline = $candidate['headline'] ?: 'professional';
        $strengths = array_values(array_unique(array_filter([
            ...($match['strength_areas'] ?? []),
            ...array_slice($candidate['skills'] ?? [], 0, 4),
        ])));
        $gaps = array_values(array_unique(array_filter([
            ...($match['missing_required_skills'] ?? []),
            ...($resume['warnings_or_gaps'] ?? []),
        ])));
        $coverLetter = $this->renderTemplateByKey($context, 'cover_letter', $templateContext);
        $whyInterested = $this->renderTemplateByKey($context, 'why_interested', $templateContext);
        $aboutMe = $this->renderTemplateByKey($context, 'about_me', $templateContext);
        $salaryAnswer = $this->renderTemplateByKey($context, 'salary_expectation', $templateContext);
        $noticePeriod = $this->renderTemplateByKey($context, 'notice_period', $templateContext);
        $workAuthorization = $this->renderTemplateByKey($context, 'work_authorization', $templateContext);
        $followUp = $this->renderTemplateByKey($context, 'follow_up_email', $templateContext);

        return [
            'cover_letter' => $coverLetter ?: trim("Dear Hiring Team,\n\nI am interested in the {$title} role at {$company}. My background as a {$headline} aligns with the responsibilities of this role, especially around ".($strengths === [] ? 'relevant delivery and problem solving' : implode(', ', array_slice($strengths, 0, 4))).".\n\nI would welcome the chance to discuss how my experience can support your team.\n\nBest regards,\n{$name}"),
            'application_answers' => [
                [
                    'key' => 'about_me',
                    'question' => 'Tell us about yourself.',
                    'answer' => $aboutMe ?: "I am {$name}, {$headline}. My work has focused on ".($candidate['summary'] ?: 'building reliable products and systems').'.',
                ],
                [
                    'key' => 'work_authorization',
                    'question' => 'What is your work authorization status?',
                    'answer' => $workAuthorization ?: 'I can clarify my current work authorization status and any location-specific requirements during the application process.',
                ],
            ],
            'salary_answer' => $salaryAnswer ?: ($candidate['salary_expectation']
                ? sprintf('My expected compensation is around %s %s, depending on the full scope, benefits, and work arrangement.', $candidate['salary_expectation'], $candidate['salary_currency'] ?: '')
                : 'I am open to discussing a compensation package aligned with the responsibilities, scope, and market range for this role.'),
            'notice_period_answer' => $noticePeriod ?: 'My notice period can be confirmed based on final offer timing and current commitments.',
            'interest_answer' => $whyInterested ?: "I am interested in {$company} because this {$title} role aligns with my background and the kind of work I want to keep building.",
            'strengths' => $strengths,
            'gaps' => $gaps,
            'interview_questions' => [
                "Which outcomes should the {$title} role deliver in the first 90 days?",
                'How does the team measure success for this position?',
                'What are the main technical or business challenges this role will own?',
                'How is the team structured and how does this role collaborate with others?',
            ],
            'follow_up_email' => $followUp ?: "Subject: Follow-up on {$title} application\n\nDear Hiring Team,\n\nI wanted to follow up on my application for the {$title} role at {$company}. I remain interested in the opportunity and would be glad to share any additional information that helps with the review process.\n\nBest regards,\n{$name}",
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function templateContext(array $context): array
    {
        $candidate = $context['candidate_profile'] ?? [];
        $job = $context['job'] ?? [];
        $match = $context['match'] ?? [];

        return [
            'full_name' => $candidate['full_name'] ?? '',
            'headline' => $candidate['headline'] ?? '',
            'base_summary' => $candidate['summary'] ?? '',
            'years_experience' => $candidate['years_experience'] ?? '',
            'job_title' => $job['title'] ?? '',
            'company_name' => $job['company_name'] ?? '',
            'job_location' => $job['location'] ?? '',
            'role_type' => $job['analysis']['role_type'] ?? '',
            'required_skills' => implode(', ', $job['analysis']['required_skills'] ?? []),
            'preferred_skills' => implode(', ', $job['analysis']['preferred_skills'] ?? []),
            'strength_areas' => implode(', ', $match['strength_areas'] ?? []),
            'missing_required_skills' => implode(', ', $match['missing_required_skills'] ?? []),
            'recommendation' => $match['recommendation_action'] ?? $match['recommendation'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $templateContext
     */
    private function renderTemplateByKey(array $context, string $key, array $templateContext): ?string
    {
        $template = collect($context['answer_templates'] ?? [])->firstWhere('key', $key);

        if (! is_array($template) || empty($template['base_answer'])) {
            return null;
        }

        $rendered = (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($templateContext): string {
            $value = $templateContext[$matches[1]] ?? '';

            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return '';
        }, (string) $template['base_answer']);

        return trim($rendered) !== '' ? trim($rendered) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function validateAiPayload(array $payload, array $sections): ?array
    {
        $validated = [];

        if (in_array('cover_letter', $sections, true)) {
            $validated['cover_letter'] = $this->stringValue($payload['cover_letter'] ?? null);
        }

        if (in_array('application_answers', $sections, true)) {
            $validated['application_answers'] = $this->answers($payload['application_answers'] ?? []);
        }

        if (in_array('salary_answer', $sections, true)) {
            $validated['salary_answer'] = $this->stringValue($payload['salary_answer'] ?? null);
        }

        if (in_array('notice_period_answer', $sections, true)) {
            $validated['notice_period_answer'] = $this->stringValue($payload['notice_period_answer'] ?? null);
        }

        if (in_array('interest_answer', $sections, true)) {
            $validated['interest_answer'] = $this->stringValue($payload['interest_answer'] ?? null);
        }

        if (in_array('strengths_gaps', $sections, true)) {
            $validated['strengths'] = $this->stringList($payload['strengths'] ?? []);
            $validated['gaps'] = $this->stringList($payload['gaps'] ?? []);
        }

        if (in_array('interview_questions', $sections, true)) {
            $validated['interview_questions'] = $this->stringList($payload['interview_questions'] ?? []);
        }

        if (in_array('follow_up_email', $sections, true)) {
            $validated['follow_up_email'] = $this->stringValue($payload['follow_up_email'] ?? null);
        }

        $validated = array_filter($validated, function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });

        if ($validated === []) {
            return null;
        }

        return $validated;
    }

    private function syncApplicationMaterials(ApplyPackage $package, Application $application): void
    {
        $materials = [
            ['key' => 'cover_letter', 'material_type' => 'cover_letter', 'title' => 'Cover Letter', 'question' => null, 'content_text' => $package->cover_letter],
            ['key' => 'why_interested', 'material_type' => 'application_answer', 'title' => 'Why are you interested?', 'question' => 'Why are you interested in this role?', 'content_text' => $package->interest_answer],
            ['key' => 'salary_expectation', 'material_type' => 'application_answer', 'title' => 'Salary expectation', 'question' => 'What is your salary expectation?', 'content_text' => $package->salary_answer],
            ['key' => 'notice_period', 'material_type' => 'application_answer', 'title' => 'Notice period', 'question' => 'What is your notice period?', 'content_text' => $package->notice_period_answer],
            ['key' => 'follow_up_email', 'material_type' => 'email_template', 'title' => 'Follow-up Email', 'question' => null, 'content_text' => $package->follow_up_email],
        ];

        foreach ($package->application_answers ?? [] as $answer) {
            $materials[] = [
                'key' => 'answer_'.$answer['key'],
                'material_type' => 'application_answer',
                'title' => $answer['question'] ?? $answer['key'],
                'question' => $answer['question'] ?? null,
                'content_text' => $answer['answer'] ?? '',
            ];
        }

        foreach ($materials as $material) {
            if (trim((string) ($material['content_text'] ?? '')) === '') {
                continue;
            }

            ApplicationMaterial::query()->updateOrCreate(
                ['application_id' => $application->id, 'key' => $material['key']],
                [
                    'user_id' => $package->user_id,
                    'job_id' => $package->job_id,
                    'profile_id' => $package->career_profile_id,
                    'answer_template_id' => null,
                    'material_type' => $material['material_type'],
                    'title' => $material['title'],
                    'question' => $material['question'],
                    'content_text' => $material['content_text'],
                    'metadata' => ['source' => 'apply_package', 'apply_package_id' => $package->id],
                    'ai_provider' => $package->ai_provider,
                    'ai_model' => $package->ai_model,
                    'ai_generated_at' => $package->ai_generated_at,
                    'ai_confidence_score' => $package->ai_confidence_score,
                    'ai_raw_response' => null,
                    'prompt_version' => $package->prompt_version,
                    'input_hash' => $package->input_hash,
                    'ai_duration_ms' => $package->ai_duration_ms,
                    'fallback_used' => $package->fallback_used,
                ],
            );
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value,
        )));
    }

    /**
     * @return array<int, array{key: string, question: string, answer: string}>
     */
    private function answers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'key' => Str::slug((string) ($item['key'] ?? $item['question'] ?? 'answer'), '_'),
                'question' => trim((string) ($item['question'] ?? 'Application question')),
                'answer' => trim((string) ($item['answer'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['answer'] !== '')
            ->values()
            ->all();
    }
}
