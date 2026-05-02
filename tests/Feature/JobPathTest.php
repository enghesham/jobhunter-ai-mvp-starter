<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_job_path(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/jobhunter/job-paths', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Backend Laravel Remote')
            ->assertJsonPath('data.target_roles.0', 'Senior Backend Engineer')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('job_paths', [
            'user_id' => $user->id,
            'name' => 'Backend Laravel Remote',
            'is_active' => true,
        ]);
    }

    public function test_user_can_create_job_path_with_legacy_aliases(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/jobhunter/job-paths', [
            'title' => 'Legacy Backend Path',
            'goal' => 'Legacy payload should still work.',
            'target_fields' => ['Backend Development'],
            'work_modes' => ['remote'],
            'employment_types' => ['full-time'],
            'must_have_keywords' => ['Laravel'],
            'nice_to_have_keywords' => ['Redis'],
            'avoid_keywords' => ['sales'],
            'min_fit_score' => 61,
            'min_apply_score' => 76,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Legacy Backend Path')
            ->assertJsonPath('data.description', 'Legacy payload should still work.')
            ->assertJsonPath('data.remote_preference', 'remote')
            ->assertJsonPath('data.min_relevance_score', 61)
            ->assertJsonPath('data.min_match_score', 76);

        $this->assertDatabaseHas('job_paths', [
            'user_id' => $user->id,
            'name' => 'Legacy Backend Path',
            'remote_preference' => 'remote',
            'min_relevance_score' => 61,
            'min_match_score' => 76,
        ]);
    }

    public function test_user_can_link_own_career_profile(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/job-paths', $this->validPayload([
            'career_profile_id' => $profile->id,
        ]))->assertCreated()
            ->assertJsonPath('data.career_profile_id', $profile->id)
            ->assertJsonPath('data.career_profile.id', $profile->id);
    }

    public function test_user_cannot_link_another_users_career_profile(): void
    {
        $user = User::factory()->create();
        $otherProfile = CandidateProfile::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/job-paths', $this->validPayload([
            'career_profile_id' => $otherProfile->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['career_profile_id']);
    }

    public function test_user_can_list_only_own_job_paths(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = JobPath::factory()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        JobPath::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Not Mine',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/jobhunter/job-paths')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $mine->id)
            ->assertJsonPath('data.data.0.name', 'Mine');
    }

    public function test_validation_works(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/jobhunter/job-paths', [
            'target_roles' => 'Backend',
            'remote_preference' => 'spaceship',
            'preferred_job_types' => ['permanent'],
            'min_relevance_score' => 101,
            'scan_interval_hours' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'target_roles',
                'remote_preference',
                'preferred_job_types.0',
                'min_relevance_score',
                'scan_interval_hours',
            ]);
    }

    public function test_inactive_path_should_not_be_due_for_scan(): void
    {
        $path = JobPath::factory()->inactive()->create([
            'auto_collect_enabled' => true,
            'scan_interval_hours' => 6,
            'next_scan_at' => now()->subMinute(),
        ]);

        $this->assertFalse($path->isDueForScan());
    }

    public function test_active_path_can_calculate_next_scan_at_if_auto_collect_is_enabled(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/jobhunter/job-paths', $this->validPayload([
            'auto_collect_enabled' => true,
            'scan_interval_hours' => 12,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.auto_collect_enabled', true)
            ->assertJsonPath('data.scan_interval_hours', 12);

        $path = JobPath::query()->findOrFail($response->json('data.id'));

        $this->assertNotNull($path->next_scan_at);
        $this->assertFalse($path->isDueForScan());
    }

    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Backend Laravel Remote',
            'description' => 'Find remote Laravel backend roles with strong API and database alignment.',
            'target_roles' => ['Senior Backend Engineer', 'Laravel Developer'],
            'target_domains' => ['Backend Development', 'SaaS'],
            'include_keywords' => ['Laravel', 'PHP', 'API'],
            'exclude_keywords' => ['translation', 'sales'],
            'required_skills' => ['Laravel', 'PHP', 'PostgreSQL'],
            'optional_skills' => ['Redis', 'Docker', 'AWS'],
            'seniority_levels' => ['senior'],
            'preferred_locations' => ['Remote', 'UAE'],
            'preferred_countries' => ['UAE'],
            'preferred_job_types' => ['full-time'],
            'remote_preference' => 'remote',
            'min_relevance_score' => 60,
            'min_match_score' => 75,
            'is_active' => true,
            'auto_collect_enabled' => false,
            'notifications_enabled' => false,
        ];
    }
}
