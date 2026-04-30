<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobhunter_api_requires_authentication(): void
    {
        $this->getJson('/api/jobhunter/job-sources')
            ->assertUnauthorized();
    }

    public function test_users_only_see_their_own_job_sources_in_index(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        JobSource::create([
            'user_id' => $user->id,
            'name' => 'Mine',
            'type' => 'custom',
            'base_url' => 'https://mine.example.com',
            'is_active' => true,
        ]);

        JobSource::create([
            'user_id' => $otherUser->id,
            'name' => 'Not Mine',
            'type' => 'custom',
            'base_url' => 'https://other.example.com',
            'is_active' => true,
        ]);

        $this->getJson('/api/jobhunter/job-sources')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Mine');
    }

    public function test_user_cannot_view_another_users_profile_or_job(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $source = JobSource::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Source',
            'type' => 'custom',
            'base_url' => 'https://other.example.com',
            'is_active' => true,
        ]);

        $profile = CandidateProfile::create([
            'user_id' => $otherUser->id,
            'full_name' => 'Other Profile',
            'years_experience' => 5,
        ]);

        $job = Job::create([
            'user_id' => $otherUser->id,
            'source_id' => $source->id,
            'company_name' => 'Other Co',
            'title' => 'Private Job',
            'apply_url' => 'https://other.example.com/jobs/1',
            'hash' => hash('sha256', 'private-job'),
            'status' => 'new',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/jobhunter/candidate-profiles/{$profile->id}")
            ->assertForbidden();

        $this->getJson("/api/jobhunter/jobs/{$job->id}")
            ->assertForbidden();
    }
}
