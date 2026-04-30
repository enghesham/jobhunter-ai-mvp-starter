<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Applications\Domain\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_application_pipeline_events_and_applies_status_side_effects(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Pipeline Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'Pipeline Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'pipeline-001',
                'title' => 'Senior Backend Engineer',
                'company_name' => 'Pipeline Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/pipeline-001',
                'description' => 'Senior backend role.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        $createResponse = $this->postJson('/api/jobhunter/applications', [
            'job_id' => $jobId,
            'profile_id' => $profileId,
            'status' => 'ready_to_apply',
            'notes' => 'Pipeline created.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'ready_to_apply');

        /** @var Application $application */
        $application = Application::query()->firstOrFail();

        $this->assertCount(2, $application->events()->get());
        $this->assertSame('application_created', $application->events()->latest('id')->skip(1)->value('type'));
        $this->assertSame('status_changed', $application->events()->latest('id')->value('type'));

        $this->postJson("/api/jobhunter/applications/{$application->id}/events", [
            'type' => 'applied_manually',
            'note' => 'Applied through the company career site.',
            'occurred_at' => now()->toISOString(),
        ])->assertCreated();

        $application->refresh();

        $this->assertSame('applied', $application->status);
        $this->assertNotNull($application->applied_at);

        $showResponse = $this->getJson("/api/jobhunter/applications/{$application->id}");

        $showResponse->assertOk();
        $this->assertGreaterThanOrEqual(4, count($showResponse->json('data.events')));
    }
}
