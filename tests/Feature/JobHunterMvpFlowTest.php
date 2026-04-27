<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobHunterMvpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_from_source_to_application(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sourceResponse = $this->postJson('/api/job-sources', [
            'name' => 'Manual Import Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'Example Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ]);

        $sourceResponse->assertCreated()
            ->assertJsonPath('success', true);

        $sourceId = $sourceResponse->json('data.id');

        $ingestResponse = $this->postJson("/api/job-sources/{$sourceId}/ingest", [
            'jobs' => [
                [
                    'external_id' => 'backend-001',
                    'title' => 'Senior Backend Laravel Engineer',
                    'company_name' => 'Example Company',
                    'location' => 'Remote',
                    'is_remote' => true,
                    'url' => 'https://jobs.example.com/backend-001',
                    'description' => 'Senior backend role using PHP, Laravel, PostgreSQL, Redis, Docker, AWS, queues, clean architecture, and REST APIs.',
                    'raw_payload' => ['source' => 'manual'],
                    'status' => 'ingested',
                ],
            ],
        ]);

        $ingestResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.jobs.0.title', 'Senior Backend Laravel Engineer');

        $jobId = $ingestResponse->json('data.jobs.0.id');

        $analyzeResponse = $this->postJson("/api/jobs/{$jobId}/analyze");

        $analyzeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.analysis.seniority', 'senior')
            ->assertJsonPath('data.analysis.role_type', 'backend');

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);

        $profileResponse = $this->postJson('/api/candidate-profiles/import', $profilePayload);

        $profileResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Hesham Hasanat');

        $profileId = $profileResponse->json('data.id');

        $matchResponse = $this->postJson("/api/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ]);

        $matchResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matches.0.profile_id', $profileId);

        $matchId = $matchResponse->json('data.matches.0.id');

        $matchesListResponse = $this->getJson('/api/jobhunter/matches');

        $matchesListResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $matchId)
            ->assertJsonPath('data.data.0.job_id', $jobId)
            ->assertJsonPath('data.data.0.candidate_profile.id', $profileId);

        $resumeResponse = $this->postJson("/api/jobhunter/jobs/{$jobId}/generate-resume", [
            'profile_id' => $profileId,
        ]);

        $resumeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.job_id', $jobId)
            ->assertJsonPath('data.profile_id', $profileId);

        $resumeId = $resumeResponse->json('data.id');

        $resumesListResponse = $this->getJson('/api/jobhunter/resumes');

        $resumesListResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $resumeId)
            ->assertJsonPath('data.data.0.job.id', $jobId)
            ->assertJsonPath('data.data.0.candidate_profile.id', $profileId);

        $applicationResponse = $this->postJson('/api/applications', [
            'job_id' => $jobId,
            'profile_id' => $profileId,
            'job_match_id' => $matchId,
            'tailored_resume_id' => $resumeId,
            'status' => 'ready_to_apply',
            'notes' => 'Prepared from MVP happy path test.',
        ]);

        $applicationResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ready_to_apply');
    }
}
