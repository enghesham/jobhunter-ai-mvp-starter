<?php

namespace Tests\Unit;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Prompts\AnalyzeJobPrompt;
use App\Services\JobAnalysis\AiAwareJobAnalysisService;
use App\Services\JobAnalysis\BasicKeywordJobAnalysisService;
use App\Services\JobAnalysis\Support\JobAnalysisResultValidator;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiAwareJobAnalysisServiceTest extends TestCase
{
    public function test_it_falls_back_to_keyword_analyzer_when_provider_returns_null(): void
    {
        $service = $this->makeService(new class implements AiProviderInterface {
            public function analyzeJob(Job $job, string $prompt): ?array
            {
                return null;
            }

            public function explainMatch(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return null;
            }

            public function tailorResume(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return null;
            }

            public function suggestJobPaths(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, string $prompt): ?array
            {
                return null;
            }

            public function generateApplyPackage(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
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
        });

        $analysis = $service->analyze($this->job());

        $this->assertSame('senior', $analysis['seniority']);
        $this->assertSame('backend', $analysis['role_type']);
        $this->assertContains('Laravel', $analysis['required_skills']);
    }

    public function test_it_falls_back_and_logs_when_ai_provider_throws(): void
    {
        Log::spy();

        $service = $this->makeService(new class implements AiProviderInterface {
            public function analyzeJob(Job $job, string $prompt): ?array
            {
                throw new AiProviderException('provider timeout');
            }

            public function explainMatch(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return null;
            }

            public function tailorResume(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return null;
            }

            public function suggestJobPaths(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, string $prompt): ?array
            {
                return null;
            }

            public function generateApplyPackage(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
            {
                return null;
            }

            public function name(): string
            {
                return 'failing-provider';
            }

            public function model(): ?string
            {
                return 'fake-model';
            }
        });

        $analysis = $service->analyze($this->job());

        $this->assertSame('senior', $analysis['seniority']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_falls_back_when_ai_returns_invalid_payload(): void
    {
        Log::spy();

        $service = $this->makeService(new class implements AiProviderInterface {
            public function analyzeJob(Job $job, string $prompt): ?array
            {
                return [
                    'required_skills' => 'Laravel',
                    'preferred_skills' => ['Docker'],
                    'seniority' => 'senior',
                    'role_type' => 'backend',
                    'domain_tags' => ['saas'],
                    'ai_summary' => 'Invalid because required_skills is not an array.',
                ];
            }

            public function explainMatch(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return null;
            }

            public function tailorResume(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return null;
            }

            public function suggestJobPaths(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, string $prompt): ?array
            {
                return null;
            }

            public function generateApplyPackage(\App\Modules\Candidate\Domain\Models\CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
            {
                return null;
            }

            public function name(): string
            {
                return 'invalid-provider';
            }

            public function model(): ?string
            {
                return 'fake-model';
            }
        });

        $analysis = $service->analyze($this->job());

        $this->assertContains('Laravel', $analysis['required_skills']);
        Log::shouldHaveReceived('warning')->once();
    }

    private function makeService(AiProviderInterface $provider): AiAwareJobAnalysisService
    {
        return new AiAwareJobAnalysisService(
            $provider,
            new BasicKeywordJobAnalysisService(),
            new AnalyzeJobPrompt(),
            new JobAnalysisResultValidator(),
        );
    }

    private function job(): Job
    {
        return new Job([
            'id' => 1,
            'company_name' => 'Acme',
            'title' => 'Senior Backend Laravel Engineer',
            'description_clean' => 'Build PHP Laravel APIs with PostgreSQL, Redis, Docker, AWS and queues.',
        ]);
    }
}
