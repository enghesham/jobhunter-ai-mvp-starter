<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_answer_templates_and_generates_application_materials(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $templatesResponse = $this->postJson('/api/jobhunter/answer-templates/bootstrap-defaults');
        $templatesResponse->assertOk();
        $this->assertGreaterThanOrEqual(6, count($templatesResponse->json('data.data')));

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Materials Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'Materials Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'materials-001',
                'title' => 'Senior Backend Engineer',
                'company_name' => 'Materials Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/materials-001',
                'description' => 'Senior backend role. Required: PHP, Laravel, PostgreSQL, Redis.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/analyze")->assertOk();

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ])->assertOk();

        $applicationId = $this->postJson('/api/jobhunter/applications', [
            'job_id' => $jobId,
            'profile_id' => $profileId,
            'status' => 'ready_to_apply',
        ])->json('data.id');

        $response = $this->postJson("/api/jobhunter/applications/{$applicationId}/generate-materials");

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $materials = $response->json('data');

        $this->assertCount(6, $materials);
        $this->assertTrue(collect($materials)->contains(fn (array $item): bool => $item['key'] === 'cover_letter'));
        $this->assertTrue(collect($materials)->contains(fn (array $item): bool => $item['key'] === 'salary_expectation'));

        $showResponse = $this->getJson("/api/jobhunter/applications/{$applicationId}");
        $showResponse->assertOk();
        $this->assertCount(6, $showResponse->json('data.materials'));
    }
}
