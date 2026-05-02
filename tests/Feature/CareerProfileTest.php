<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_profile(): void
    {
        $user = User::factory()->create(['name' => 'Hesham User']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/jobhunter/career-profiles', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_name', 'Hesham Hasanat')
            ->assertJsonPath('data.title', 'Senior Laravel Backend Engineer')
            ->assertJsonPath('data.years_of_experience', 10)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Hesham Hasanat',
            'headline' => 'Senior Laravel Backend Engineer',
            'years_experience' => 10,
            'preferred_workplace_type' => 'remote',
            'is_primary' => true,
        ]);
    }

    public function test_user_can_list_only_own_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = CandidateProfile::factory()->create([
            'user_id' => $user->id,
            'headline' => 'Mine',
        ]);

        CandidateProfile::factory()->create([
            'user_id' => $otherUser->id,
            'headline' => 'Not Mine',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/jobhunter/career-profiles')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $mine->id)
            ->assertJsonPath('data.data.0.title', 'Mine');
    }

    public function test_user_cannot_access_another_users_profile(): void
    {
        $user = User::factory()->create();
        $otherProfile = CandidateProfile::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/jobhunter/career-profiles/{$otherProfile->id}")
            ->assertForbidden();

        $this->patchJson("/api/jobhunter/career-profiles/{$otherProfile->id}", [
            'title' => 'Should Not Update',
        ])->assertForbidden();
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/jobhunter/career-profiles/{$profile->id}", [
            'title' => 'Principal Backend Engineer',
            'skills' => ['PHP', 'Laravel', 'Redis'],
            'preferred_workplace_type' => 'hybrid',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Principal Backend Engineer')
            ->assertJsonPath('data.preferred_workplace_type', 'hybrid');

        $this->assertDatabaseHas('candidate_profiles', [
            'id' => $profile->id,
            'headline' => 'Principal Backend Engineer',
            'preferred_workplace_type' => 'hybrid',
        ]);
    }

    public function test_user_can_delete_own_profile(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/jobhunter/career-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('candidate_profiles', [
            'id' => $profile->id,
        ]);
    }

    public function test_make_primary_unsets_previous_primary_profile(): void
    {
        $user = User::factory()->create();
        $firstProfile = CandidateProfile::factory()->primary()->create(['user_id' => $user->id]);
        $secondProfile = CandidateProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/career-profiles/{$secondProfile->id}/make-primary")
            ->assertOk()
            ->assertJsonPath('data.id', $secondProfile->id)
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse($firstProfile->fresh()->is_primary);
        $this->assertTrue($secondProfile->fresh()->is_primary);
    }

    public function test_validation_works_for_skills_years_and_workplace_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/jobhunter/career-profiles', [
            'title' => 'Invalid Profile',
            'professional_summary' => 'Invalid payload.',
            'years_of_experience' => -1,
            'skills' => 'PHP',
            'preferred_workplace_type' => 'office',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'years_of_experience',
                'skills',
                'preferred_workplace_type',
            ]);
    }

    private function validPayload(): array
    {
        return [
            'display_name' => 'Hesham Hasanat',
            'title' => 'Senior Laravel Backend Engineer',
            'professional_summary' => 'Senior backend engineer with strong Laravel, API, queue, and database experience.',
            'primary_role' => 'Backend Developer',
            'seniority_level' => 'senior',
            'years_of_experience' => 10,
            'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis'],
            'secondary_skills' => ['Vue.js', 'Docker'],
            'tools' => ['Git', 'Docker', 'Postman'],
            'industries' => ['SaaS', 'Recruitment'],
            'preferred_workplace_type' => 'remote',
            'preferred_locations' => ['Remote', 'UAE'],
            'salary_expectation' => 9000,
            'salary_currency' => 'USD',
            'source' => 'manual',
            'experiences' => [
                [
                    'company' => 'Reach Digital Hub',
                    'title' => 'Senior PHP Developer',
                    'start_date' => '2023-07-01',
                    'end_date' => null,
                    'description' => 'Built scalable Laravel APIs and queue-backed workflows.',
                    'achievements' => ['Improved backend reliability.'],
                    'skills' => ['Laravel', 'Redis', 'PostgreSQL'],
                ],
            ],
            'projects' => [
                [
                    'name' => 'AI Job Platform',
                    'description' => 'Job seeker copilot with matching and resume generation.',
                    'skills' => ['Laravel', 'Vue.js', 'AI'],
                    'url' => 'https://example.com/project',
                ],
            ],
        ];
    }
}
