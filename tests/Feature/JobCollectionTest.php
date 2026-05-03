<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_collect_jobs_for_job_path_from_rss_without_ai_evaluation(): void
    {
        config()->set('jobhunter.ai_enabled', false);
        config()->set('jobhunter.collection.store_below_threshold', false);

        [$user, $path] = $this->seedUserPathAndSource();

        Http::fake([
            'https://feeds.example.com/jobs.xml' => Http::response($this->rssFeed(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/job-paths/{$path->id}/collect", ['sync' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.source_count', 1)
            ->assertJsonPath('data.fetched_count', 2)
            ->assertJsonPath('data.accepted_count', 1)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.filtered_count', 1)
            ->assertJsonPath('data.opportunities_created', 1);

        $this->assertDatabaseHas('jobs', [
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'title' => 'Senior Laravel Backend Engineer',
            'remote_type' => 'remote',
        ]);

        $this->assertDatabaseHas('job_opportunities', [
            'user_id' => $user->id,
            'job_path_id' => $path->id,
            'status' => 'recommended',
        ]);

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('job_analyses', 0);
        $this->assertDatabaseCount('job_matches', 0);
    }

    public function test_user_cannot_collect_jobs_for_another_users_job_path(): void
    {
        [$user] = $this->seedUserPathAndSource();
        [$otherUser, $otherPath] = $this->seedUserPathAndSource();

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/job-paths/{$otherPath->id}/collect", ['sync' => true])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_collection_runs', [
            'user_id' => $otherUser->id,
            'job_path_id' => $otherPath->id,
        ]);
    }

    /**
     * @return array{0: User, 1: JobPath}
     */
    private function seedUserPathAndSource(): array
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'headline' => 'Senior Laravel Backend Engineer',
            'primary_role' => 'Backend Developer',
            'seniority_level' => 'senior',
            'years_experience' => 8,
            'preferred_roles' => ['Backend Developer', 'Laravel Developer'],
            'preferred_locations' => ['Remote'],
            'core_skills' => ['PHP', 'Laravel', 'PostgreSQL'],
            'nice_to_have_skills' => ['Redis', 'Docker'],
            'preferred_workplace_type' => 'remote',
        ]);

        $path = JobPath::factory()->forCareerProfile($profile)->create([
            'target_roles' => ['Laravel Backend', 'Backend Engineer'],
            'include_keywords' => ['Laravel', 'API'],
            'exclude_keywords' => ['translation', 'sales'],
            'required_skills' => ['PHP', 'Laravel', 'PostgreSQL'],
            'optional_skills' => ['Redis', 'Docker'],
            'remote_preference' => 'remote',
            'min_relevance_score' => 45,
            'auto_collect_enabled' => true,
            'next_scan_at' => now()->subMinute(),
        ]);

        JobSource::query()->create([
            'user_id' => $user->id,
            'name' => 'Example RSS Jobs',
            'type' => 'rss',
            'base_url' => 'https://feeds.example.com/jobs.xml',
            'company_name' => null,
            'is_active' => true,
            'meta' => [],
        ]);

        return [$user, $path];
    }

    private function rssFeed(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example Jobs</title>
    <item>
      <guid>acme-laravel-1</guid>
      <title>Senior Laravel Backend Engineer at Acme</title>
      <link>https://jobs.example.com/acme-laravel</link>
      <description>Remote full-time role building Laravel APIs with PHP, PostgreSQL, Redis, Docker, and clean architecture.</description>
      <pubDate>Mon, 01 May 2026 10:00:00 GMT</pubDate>
    </item>
    <item>
      <guid>translation-1</guid>
      <title>Arabic Translation Specialist at Words Co</title>
      <link>https://jobs.example.com/translation</link>
      <description>Translation, sales support, and customer follow-up role.</description>
      <pubDate>Mon, 01 May 2026 11:00:00 GMT</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
