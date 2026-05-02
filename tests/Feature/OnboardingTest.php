<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_default_onboarding_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/jobhunter/onboarding')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state.current_step', 'career_profile')
            ->assertJsonPath('data.state.is_completed', false)
            ->assertJsonPath('data.career_profile', null);
    }

    public function test_onboarding_can_create_default_career_profile_and_understanding(): void
    {
        $user = User::factory()->create(['name' => 'Hesham User']);
        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/onboarding/career-profile', $this->profilePayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state.current_step', 'review_profile')
            ->assertJsonPath('data.career_profile.display_name', 'Hesham Hasanat')
            ->assertJsonPath('data.understanding.role', 'Backend Developer')
            ->assertJsonPath('data.understanding.seniority', 'senior');

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Hesham Hasanat',
            'is_primary' => true,
        ]);
    }

    public function test_onboarding_updates_existing_profile_when_user_goes_back_to_edit(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'headline' => 'Old Headline',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/onboarding/career-profile', [
            ...$this->profilePayload(),
            'title' => 'Updated Backend Engineer',
            'skills' => ['PHP', 'Laravel', 'Redis'],
        ])->assertCreated()
            ->assertJsonPath('data.career_profile.id', $profile->id)
            ->assertJsonPath('data.career_profile.title', 'Updated Backend Engineer');

        $this->assertSame(1, CandidateProfile::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('candidate_profiles', [
            'id' => $profile->id,
            'headline' => 'Updated Backend Engineer',
        ]);
    }

    public function test_onboarding_suggests_job_paths_from_primary_profile(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'primary_role' => 'Backend Developer',
            'seniority_level' => 'senior',
            'years_experience' => 10,
            'core_skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis'],
            'nice_to_have_skills' => ['Vue.js', 'Docker'],
            'preferred_locations' => ['Remote', 'UAE'],
            'preferred_job_types' => ['full-time'],
            'preferred_workplace_type' => 'remote',
            'industries' => ['SaaS'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/jobhunter/onboarding/suggest-job-paths');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state.current_step', 'suggest_job_paths')
            ->assertJsonPath('data.career_profile.id', $profile->id)
            ->assertJsonCount(4, 'data.suggestions')
            ->assertJsonPath('data.suggestions.0.career_profile_id', $profile->id);
    }

    public function test_existing_profile_and_job_path_counts_as_completed_onboarding(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create(['user_id' => $user->id]);
        JobPath::factory()->forCareerProfile($profile)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/jobhunter/onboarding')
            ->assertOk()
            ->assertJsonPath('data.state.current_step', 'done')
            ->assertJsonPath('data.state.is_completed', true)
            ->assertJsonPath('data.state.metadata.completed_by', 'existing_data');
    }

    public function test_user_cannot_suggest_paths_from_another_users_profile(): void
    {
        $user = User::factory()->create();
        $otherProfile = CandidateProfile::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/onboarding/suggest-job-paths', [
            'career_profile_id' => $otherProfile->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['career_profile_id']);
    }

    public function test_user_can_complete_onboarding(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/jobhunter/onboarding/complete')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state.current_step', 'done')
            ->assertJsonPath('data.state.is_completed', true)
            ->assertJsonPath('data.best_matches_path', '/matches');
    }

    private function profilePayload(): array
    {
        return [
            'display_name' => 'Hesham Hasanat',
            'title' => 'Senior Laravel Backend Engineer',
            'professional_summary' => 'Backend engineer focused on Laravel APIs, queues, databases, and production systems.',
            'primary_role' => 'Backend Developer',
            'seniority_level' => 'senior',
            'years_of_experience' => 10,
            'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis'],
            'secondary_skills' => ['Vue.js', 'Docker'],
            'industries' => ['SaaS', 'Recruitment'],
            'preferred_workplace_type' => 'remote',
            'preferred_locations' => ['Remote', 'UAE'],
            'source' => 'manual',
        ];
    }
}
