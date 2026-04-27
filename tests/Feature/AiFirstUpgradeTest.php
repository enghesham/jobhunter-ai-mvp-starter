<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateExperience;
use App\Modules\Candidate\Domain\Models\CandidateProject;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiFirstUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_explanation_is_saved_and_exposed(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface {
            public function analyzeJob(Job $job, string $prompt): ?array
            {
                return null;
            }

            public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return [
                    'why_matched' => 'The profile aligns strongly with backend APIs and Laravel work.',
                    'missing_skills' => ['Kubernetes'],
                    'strength_areas' => ['Laravel', 'REST APIs'],
                    'risk_flags' => ['Cloud depth not fully evidenced.'],
                    'resume_focus_points' => ['Highlight Laravel APIs', 'Show queue architecture work'],
                    'ai_recommendation_summary' => 'Strong backend match with a few infrastructure gaps.',
                    'confidence_score' => 84,
                ];
            }

            public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return null;
            }

            public function name(): string
            {
                return 'fake-ai';
            }

            public function model(): ?string
            {
                return 'fake-model';
            }
        });

        [$jobId, $profileId] = $this->seedAnalyzedJobAndProfile();

        $response = $this->postJson("/api/jobhunter/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.matches.0.why_matched', 'The profile aligns strongly with backend APIs and Laravel work.')
            ->assertJsonPath('data.matches.0.ai_provider', 'fake-ai');

        $matchId = $response->json('data.matches.0.id');

        $this->getJson("/api/jobhunter/matches/{$matchId}/explanation")
            ->assertOk()
            ->assertJsonPath('data.resume_focus_points.0', 'Highlight Laravel APIs');
    }

    public function test_resume_tailoring_does_not_persist_invented_candidate_facts(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface {
            public function analyzeJob(Job $job, string $prompt): ?array
            {
                return null;
            }

            public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return null;
            }

            public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return [
                    'tailored_headline' => 'Senior Backend Engineer',
                    'tailored_summary' => 'Built robust APIs and distributed systems using proven backend patterns.',
                    'selected_skills' => ['PHP', 'Kubernetes'],
                    'tailored_experience_bullets' => ['Worked at Google using Rust to build satellite systems.'],
                    'selected_projects' => ['AI Job Platform', 'Stealth Space Project'],
                    'ats_keywords' => ['PHP', 'OpenSearch', 'Terraform'],
                    'warnings_or_gaps' => ['Kubernetes is not clearly shown.'],
                    'confidence_score' => 80,
                ];
            }

            public function name(): string
            {
                return 'fake-ai';
            }

            public function model(): ?string
            {
                return 'fake-model';
            }
        });

        [$jobId, $profileId] = $this->seedAnalyzedJobAndProfile();

        CandidateExperience::create([
            'profile_id' => $profileId,
            'company' => 'Reach Digital Hub',
            'title' => 'Senior PHP Developer',
            'description' => 'Built scalable Laravel APIs, queues, and OpenSearch integrations.',
            'achievements' => ['Built scalable Laravel APIs and queue workflows.'],
            'tech_stack' => ['PHP', 'Laravel', 'OpenSearch'],
        ]);

        CandidateProject::create([
            'profile_id' => $profileId,
            'name' => 'AI Job Platform',
            'description' => 'Job portal with OpenSearch indexing and recruitment automation.',
            'tech_stack' => ['Laravel', 'OpenSearch', 'Queues'],
        ]);

        $response = $this->postJson("/api/jobhunter/jobs/{$jobId}/generate-resume", [
            'profile_id' => $profileId,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.ai_provider', 'fake-ai')
            ->assertJsonPath('data.selected_skills.0', 'PHP')
            ->assertJsonPath('data.selected_projects.0', 'AI Job Platform');

        $this->assertSame(['PHP'], $response->json('data.selected_skills'));
        $this->assertSame(['AI Job Platform'], $response->json('data.selected_projects'));
        $this->assertSame([], $response->json('data.tailored_experience_bullets'));
        $this->assertContains('Kubernetes is not clearly shown.', $response->json('data.warnings_or_gaps'));
    }

    private function seedAnalyzedJobAndProfile(): array
    {
        Sanctum::actingAs(User::factory()->create());

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'AI Test Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'AI Test Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'ai-upgrade-001',
                'title' => 'Senior Backend Laravel Engineer',
                'company_name' => 'AI Test Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/ai-upgrade-001',
                'description' => 'Senior backend role using PHP, Laravel, REST APIs, Redis, OpenSearch, Docker, and AWS.',
            ]],
        ])->json('data.jobs.0.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/analyze")->assertOk();

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        return [$jobId, $profileId];
    }
}
