<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deduplicates_jobs_across_sources_for_the_same_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $firstSourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Primary Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com/primary',
            'company_name' => 'Example Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->assertCreated()->json('data.id');

        $secondSourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Secondary Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com/secondary',
            'company_name' => 'Example Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->assertCreated()->json('data.id');

        $firstIngest = $this->postJson("/api/jobhunter/job-sources/{$firstSourceId}/ingest", [
            'jobs' => [
                [
                    'title' => 'Senior Backend Laravel Engineer',
                    'company_name' => 'Example Company',
                    'location' => 'Remote - CET',
                    'is_remote' => true,
                    'url' => 'https://jobs.example.com/backend-role',
                    'description' => 'Build Laravel APIs and queues.',
                    'raw_payload' => ['origin' => 'primary'],
                    'status' => 'ingested',
                ],
            ],
        ]);

        $firstIngest->assertCreated()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0);

        $jobId = $firstIngest->json('data.jobs.0.id');

        $secondIngest = $this->postJson("/api/jobhunter/job-sources/{$secondSourceId}/ingest", [
            'jobs' => [
                [
                    'title' => 'Senior Backend Laravel Engineer',
                    'company_name' => 'Example Company',
                    'location' => 'Remote - CET',
                    'is_remote' => true,
                    'url' => 'https://boards.example.com/jobs/backend-role-clone',
                    'description' => 'Build Laravel APIs, queues, and distributed services.',
                    'raw_payload' => ['origin' => 'secondary'],
                    'status' => 'ingested',
                ],
            ],
        ]);

        $secondIngest->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.jobs.0.id', $jobId);

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', [
            'id' => $jobId,
            'source_id' => $firstSourceId,
            'title' => 'Senior Backend Laravel Engineer',
            'company_name' => 'Example Company',
        ]);
    }
}
