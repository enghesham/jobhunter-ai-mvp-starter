<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Applications\Domain\Models\ApplyPackage;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobOpportunity;
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

    public function test_default_opportunity_list_prefers_evaluated_duplicate_context(): void
    {
        config()->set('jobhunter.ai_enabled', false);

        [$user, $profile] = $this->seedProfileAndPath();
        JobPath::factory()->forCareerProfile($profile)->create([
            'name' => 'API Platform Remote',
            'include_keywords' => ['API', 'Laravel'],
            'required_skills' => ['Laravel', 'PHP'],
            'min_relevance_score' => 30,
        ]);
        $job = $this->createJob($user, 'Senior Laravel API Engineer', 'Build Laravel API products with PHP and PostgreSQL.');

        Sanctum::actingAs($user);

        $this->postJson('/api/jobhunter/opportunities/refresh')->assertOk();

        $evaluatedOpportunity = JobOpportunity::query()
            ->where('job_id', $job->id)
            ->orderBy('quick_relevance_score')
            ->firstOrFail();
        $notEvaluatedOpportunity = JobOpportunity::query()
            ->where('job_id', $job->id)
            ->whereKeyNot($evaluatedOpportunity->id)
            ->firstOrFail();

        $this->postJson("/api/jobhunter/opportunities/{$evaluatedOpportunity->id}/evaluate")
            ->assertOk()
            ->assertJsonPath('data.is_evaluated', true);

        $evaluatedOpportunity->refresh()->forceFill([
            'match_score' => 40,
            'status' => 'evaluated',
        ])->save();
        $notEvaluatedOpportunity->forceFill([
            'quick_relevance_score' => 99,
            'status' => 'recommended',
        ])->save();

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $evaluatedOpportunity->id)
            ->assertJsonPath('data.0.is_evaluated', true);
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

    public function test_refresh_preserves_evaluated_flag_and_match_data(): void
    {
        config()->set('jobhunter.ai_enabled', false);

        [$user, , $path] = $this->seedProfileAndPath();
        $job = $this->createJob($user, 'Senior Laravel Backend Engineer', 'Senior backend role using PHP, Laravel, REST APIs, Redis, Docker, and AWS.');

        Sanctum::actingAs($user);

        $opportunityId = $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->json('data.opportunities.0.id');

        $evaluated = $this->postJson("/api/jobhunter/opportunities/{$opportunityId}/evaluate")
            ->assertOk()
            ->assertJsonPath('data.is_evaluated', true)
            ->json('data');

        $path->forceFill(['min_relevance_score' => 100])->save();
        $job->forceFill([
            'description_raw' => 'A short role description that no longer passes quick relevance.',
            'description_clean' => 'A short role description that no longer passes quick relevance.',
        ])->save();

        $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->assertJsonPath('data.opportunities.0.id', $opportunityId)
            ->assertJsonPath('data.opportunities.0.is_evaluated', true)
            ->assertJsonPath('data.opportunities.0.match_id', $evaluated['match_id'])
            ->assertJsonPath('data.opportunities.0.match_score', $evaluated['match_score']);
    }

    public function test_evaluated_low_fit_opportunity_stays_visible_by_default(): void
    {
        [$user, $profile, $path] = $this->seedProfileAndPath();
        $job = $this->createJob($user, 'Junior PHP Support Developer', 'Basic support role with limited Laravel ownership.');
        $match = JobMatch::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'context_key' => "path:{$path->id}",
            'overall_score' => 38,
            'title_score' => 35,
            'skill_score' => 45,
            'recommendation' => 'Weak fit',
            'recommendation_action' => 'skip',
            'matched_at' => now(),
        ]);

        JobOpportunity::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'job_path_id' => $path->id,
            'career_profile_id' => $profile->id,
            'match_id' => $match->id,
            'context_key' => "path:{$path->id}",
            'quick_relevance_score' => 70,
            'match_score' => 38,
            'status' => 'not_relevant',
            'recommendation' => 'skip',
            'reasons' => ['Manual low-fit evaluated opportunity.'],
            'matched_keywords' => ['PHP'],
            'missing_keywords' => ['Laravel'],
            'evaluated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_evaluated', true)
            ->assertJsonPath('data.0.match_score', 38);
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

    public function test_opportunity_list_marks_existing_apply_package(): void
    {
        config()->set('jobhunter.ai_enabled', false);

        [$user, $profile, $path] = $this->seedProfileAndPath();
        $job = $this->createJob($user, 'Senior Laravel Backend Engineer', 'Senior backend role using PHP, Laravel, REST APIs, Redis, Docker, and AWS.');

        Sanctum::actingAs($user);

        $opportunityId = $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->json('data.opportunities.0.id');

        $this->postJson("/api/jobhunter/opportunities/{$opportunityId}/evaluate")->assertOk();

        $package = ApplyPackage::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'career_profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'cover_letter' => 'Saved package',
            'application_answers' => [],
            'strengths' => [],
            'gaps' => [],
            'interview_questions' => [],
            'fallback_used' => true,
            'status' => 'ready',
        ]);

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.apply_package_id', $package->id)
            ->assertJsonPath('data.0.apply_package.status', 'ready');
    }

    public function test_user_can_update_opportunity_preferences_and_apply_to_existing_paths(): void
    {
        [$user, , $path] = $this->seedProfileAndPath();

        Sanctum::actingAs($user);

        $this->patchJson('/api/jobhunter/opportunity-preferences', [
            'default_min_relevance_score' => 58,
            'default_min_match_score' => 82,
            'quick_recommended_score' => 86,
            'store_below_threshold' => true,
            'show_below_threshold' => true,
            'apply_to_existing_job_paths' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.effective.default_min_relevance_score', 58)
            ->assertJsonPath('data.effective.default_min_match_score', 82)
            ->assertJsonPath('data.effective.quick_recommended_score', 86)
            ->assertJsonPath('data.effective.store_below_threshold', true)
            ->assertJsonPath('data.effective.show_below_threshold', true);

        $this->assertDatabaseHas('user_opportunity_preferences', [
            'user_id' => $user->id,
            'default_min_relevance_score' => 58,
            'default_min_match_score' => 82,
            'quick_recommended_score' => 86,
        ]);

        $path->refresh();
        $this->assertSame(58, $path->min_relevance_score);
        $this->assertSame(82, $path->min_match_score);
    }

    public function test_opportunity_preferences_can_store_and_show_low_relevance_jobs(): void
    {
        [$user, , $path] = $this->seedProfileAndPath();
        $path->forceFill(['min_relevance_score' => 100])->save();
        $this->createJob($user, 'Junior PHP Support Developer', 'Support PHP tickets and maintain basic internal tools.');

        Sanctum::actingAs($user);

        $this->patchJson('/api/jobhunter/opportunity-preferences', [
            'store_below_threshold' => true,
            'show_below_threshold' => true,
        ])->assertOk();

        $this->postJson('/api/jobhunter/opportunities/refresh')
            ->assertOk()
            ->assertJsonPath('data.stats.created', 1)
            ->assertJsonPath('data.opportunities.0.status', 'not_relevant');

        $this->getJson('/api/jobhunter/opportunities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'not_relevant');
    }

    public function test_user_can_add_missing_opportunity_skills_to_linked_profile(): void
    {
        [$user, $profile, $path] = $this->seedProfileAndPath();
        $job = $this->createJob($user, 'Senior Laravel Kubernetes Engineer', 'Laravel, Kubernetes, AWS, and platform engineering.');
        $match = JobMatch::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'context_key' => "path:{$path->id}",
            'overall_score' => 68,
            'title_score' => 70,
            'skill_score' => 62,
            'missing_required_skills' => ['Kubernetes'],
            'nice_to_have_gaps' => ['Terraform'],
            'recommendation' => 'Consider',
            'recommendation_action' => 'consider',
            'matched_at' => now(),
        ]);
        $opportunity = JobOpportunity::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'job_path_id' => $path->id,
            'career_profile_id' => $profile->id,
            'match_id' => $match->id,
            'context_key' => "path:{$path->id}",
            'quick_relevance_score' => 82,
            'match_score' => 68,
            'status' => 'evaluated',
            'reasons' => ['Matched Laravel.'],
            'matched_keywords' => ['Laravel'],
            'missing_keywords' => ['Kubernetes'],
            'evaluated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/opportunities/{$opportunity->id}/profile-skills", [
            'skills' => ['Kubernetes', 'Terraform'],
        ])
            ->assertOk()
            ->assertJsonPath('data.added_core_skills.0', 'Kubernetes')
            ->assertJsonPath('data.added_nice_to_have_skills.0', 'Terraform')
            ->assertJsonPath('data.opportunity.career_profile.core_skills.4', 'Kubernetes')
            ->assertJsonPath('data.opportunity.career_profile.nice_to_have_skills.2', 'Terraform');

        $profile->refresh();
        $this->assertContains('Kubernetes', $profile->core_skills);
        $this->assertContains('Terraform', $profile->nice_to_have_skills);
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
