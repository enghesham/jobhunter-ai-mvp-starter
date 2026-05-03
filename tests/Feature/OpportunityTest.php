<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_refresh_opportunities_with_cheap_relevance_filtering(): void
    {
        config()->set('jobhunter.ai_enabled', false);

        [$user, $profile, $path] = $this->seedProfileAndPath();
        $this->createJob($user, 'Senior Laravel Backend Engineer', 'Build Laravel APIs with PHP, PostgreSQL, Redis, and queues.');
        $this->createJob($user, 'Arabic Translator', 'Translation, cold calling, and sales support.');

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->assertJsonPath('data.stats.created', 1)
            ->assertJsonPath('data.opportunities.0.job_path.id', $path->id)
            ->assertJsonPath('data.opportunities.0.career_profile.id', $profile->id);

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job.title', 'Senior Laravel Backend Engineer');
    }

    public function test_default_opportunity_list_hides_duplicates_across_paths(): void
    {
        [$user, $profile] = $this->seedProfileAndPath();
        JobPath::factory()->forCareerProfile($profile)->create([
            'name' => 'API Platform Remote',
            'include_keywords' => ['API', 'Laravel'],
            'required_skills' => ['Laravel', 'PHP'],
            'min_relevance_score' => 30,
        ]);
        $this->createJob($user, 'Senior Laravel API Engineer', 'Build Laravel API products with PHP and PostgreSQL.');

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/opportunities/refresh')->assertOk();

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/jobhunter/opportunities?show_duplicates=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_evaluate_only_selected_opportunity(): void
    {
        config()->set('jobhunter.ai_enabled', false);

        [$user] = $this->seedProfileAndPath();
        $this->createJob($user, 'Senior Laravel Backend Engineer', 'Senior backend role using PHP, Laravel, REST APIs, Redis, Docker, and AWS.');

        Sanctum::actingAs($user);

        $opportunityId = $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->json('data.opportunities.0.id');

        $response = $this->postJson("/api/jobhunter/opportunities/{$opportunityId}/evaluate")
            ->assertOk()
            ->assertJsonPath('data.match.job_path.name', 'Backend Laravel Remote')
            ->assertJsonPath('data.match.fallback_used', true);

        $this->assertGreaterThanOrEqual(70, $response->json('data.match_score'));
    }

    public function test_best_matches_endpoint_only_returns_matches_above_threshold(): void
    {
        [$user, $profile, $path] = $this->seedProfileAndPath();
        $path->forceFill(['min_match_score' => 80])->save();

        $strongJob = $this->createJob($user, 'Senior Laravel Platform Engineer', 'Laravel, PHP, Redis, PostgreSQL, AWS, and queues.');
        $weakJob = $this->createJob($user, 'Junior PHP Support Developer', 'Basic PHP support with limited Laravel ownership.');

        JobMatch::query()->create([
            'user_id' => $user->id,
            'job_id' => $strongJob->id,
            'profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'context_key' => "path:{$path->id}",
            'overall_score' => 88,
            'title_score' => 90,
            'skill_score' => 88,
            'recommendation' => 'Strong fit',
            'recommendation_action' => 'apply',
            'matched_at' => now(),
        ]);

        JobMatch::query()->create([
            'user_id' => $user->id,
            'job_id' => $weakJob->id,
            'profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'context_key' => "path:{$path->id}",
            'overall_score' => 62,
            'title_score' => 55,
            'skill_score' => 60,
            'recommendation' => 'Weak fit',
            'recommendation_action' => 'consider',
            'matched_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/jobhunter/matches?best_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.job.title', 'Senior Laravel Platform Engineer');

        $this->getJson('/api/jobhunter/matches')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    /**
     * @return array{0: User, 1: CandidateProfile, 2: JobPath}
     */
    private function seedProfileAndPath(): array
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'primary_role' => 'Backend Developer',
            'headline' => 'Senior Laravel Backend Engineer',
            'years_experience' => 10,
            'seniority_level' => 'senior',
            'preferred_roles' => ['Backend Developer', 'Laravel Developer'],
            'preferred_locations' => ['Remote'],
            'core_skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis'],
            'nice_to_have_skills' => ['Docker', 'AWS'],
            'preferred_workplace_type' => 'remote',
        ]);
        $path = JobPath::factory()->forCareerProfile($profile)->create([
            'min_relevance_score' => 45,
        ]);

        return [$user, $profile, $path];
    }

    private function createJob(User $user, string $title, string $description): Job
    {
        $source = JobSource::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Test Source',
        ], [
            'type' => 'custom',
            'base_url' => 'https://jobs.example.com',
            'company_name' => 'Example Co',
            'is_active' => true,
            'meta' => [],
        ]);

        return Job::query()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'external_id' => str($title)->slug()->toString(),
            'company_name' => 'Example Co',
            'title' => $title,
            'location' => 'Remote',
            'is_remote' => true,
            'remote_type' => 'remote',
            'employment_type' => 'full-time',
            'description_raw' => $description,
            'description_clean' => $description,
            'apply_url' => 'https://jobs.example.com/'.str($title)->slug(),
            'hash' => hash('sha256', $title.$description),
            'job_fingerprint' => hash('sha256', 'example co'.$title.'remote'),
            'source_hash' => hash('sha256', 'example co'.$title.'remote'.'https://jobs.example.com/'.str($title)->slug()),
            'status' => 'discovered',
            'posted_at' => now(),
        ]);
    }
}
