<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateExperience;
use App\Modules\Candidate\Domain\Models\CandidateProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResumeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_tailored_resume_draft_for_a_matched_job(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Resume Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'Resume Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'resume-001',
                'title' => 'Senior Backend Laravel Engineer',
                'company_name' => 'Resume Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/resume-001',
                'description' => 'Senior backend role. Required: PHP, Laravel, PostgreSQL, Redis, Kubernetes. Preferred: Terraform, AWS. Build scalable APIs and queue-driven systems.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/analyze")->assertOk();

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        CandidateExperience::create([
            'profile_id' => $profileId,
            'company' => 'Platform Corp',
            'title' => 'Senior Backend Engineer',
            'description' => 'Led delivery of scalable Laravel APIs and queue-based workflows.',
            'achievements' => [
                'Improved API throughput and reduced latency on Laravel services.',
                'Built queue-driven pipelines using Redis and Docker.',
            ],
            'tech_stack' => ['PHP', 'Laravel', 'Redis', 'Docker', 'PostgreSQL'],
        ]);

        CandidateProject::create([
            'profile_id' => $profileId,
            'name' => 'Search and Matching Platform',
            'description' => 'Built a backend-heavy platform using Laravel, OpenSearch, queues, and AWS.',
            'tech_stack' => ['Laravel', 'OpenSearch', 'AWS', 'Queues'],
            'url' => 'https://example.test/projects/search-platform',
        ]);

        $this->postJson("/api/jobhunter/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ])->assertOk();

        $response = $this->postJson("/api/jobhunter/jobs/{$jobId}/generate-resume", [
            'profile_id' => $profileId,
            'version_name' => 'v1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_name', 'v1')
            ->assertJsonPath('data.headline', 'Senior Backend Engineer | Laravel | APIs | Scalable Systems')
            ->assertJsonCount(1, 'data.selected_projects')
            ->assertJsonCount(2, 'data.selected_experience_bullets');

        $warnings = $response->json('data.warnings_or_gaps');

        $this->assertIsArray($warnings);
        $this->assertTrue(
            collect($warnings)->contains(fn (string $warning): bool => str_contains($warning, 'Kubernetes'))
        );
        $this->assertTrue(
            collect($warnings)->contains(fn (string $warning): bool => str_contains($warning, 'Terraform'))
        );

        $htmlPath = $response->json('data.html_path');

        $this->assertNotNull($htmlPath);
        $this->assertFileExists(storage_path('app/public/'.$htmlPath));
    }
}
