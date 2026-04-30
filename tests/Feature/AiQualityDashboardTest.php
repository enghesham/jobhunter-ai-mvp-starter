<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiQualityDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_quality_report_is_user_scoped_and_summarized(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        [$job, $profile] = $this->seedJobAndProfile($user, 'Scoped Laravel Role');
        [$otherJob] = $this->seedJobAndProfile($otherUser, 'Other User Role');

        JobAnalysis::create([
            'job_id' => $job->id,
            'required_skills' => ['Laravel', 'Redis'],
            'preferred_skills' => ['OpenSearch'],
            'confidence_score' => 88,
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
            'ai_generated_at' => now(),
            'ai_confidence_score' => 91,
            'prompt_version' => 'v1',
            'input_hash' => 'analysis-user-hash',
            'ai_duration_ms' => 1234,
            'fallback_used' => false,
            'analyzed_at' => now(),
        ]);

        JobAnalysis::create([
            'job_id' => $otherJob->id,
            'required_skills' => ['Python'],
            'ai_provider' => 'openrouter',
            'ai_model' => 'openrouter/auto',
            'ai_generated_at' => now(),
            'ai_confidence_score' => 90,
            'prompt_version' => 'v1',
            'input_hash' => 'analysis-other-user-hash',
            'ai_duration_ms' => 800,
            'fallback_used' => false,
            'analyzed_at' => now(),
        ]);

        JobMatch::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'overall_score' => 82,
            'skill_score' => 90,
            'recommendation' => 'apply',
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => null,
            'ai_confidence_score' => 60,
            'prompt_version' => 'v1',
            'input_hash' => 'match-user-hash',
            'ai_duration_ms' => 21,
            'fallback_used' => true,
            'matched_at' => now(),
        ]);

        $resume = TailoredResume::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'version_name' => 'v1',
            'headline_text' => 'Senior Backend Engineer',
            'summary_text' => 'Laravel-focused backend engineer.',
            'ai_provider' => 'groq',
            'ai_model' => 'llama-3.3-70b-versatile',
            'ai_generated_at' => now(),
            'ai_confidence_score' => 77,
            'prompt_version' => 'v1',
            'input_hash' => 'resume-user-hash',
            'ai_duration_ms' => 2300,
            'fallback_used' => false,
        ]);

        $application = Application::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'tailored_resume_id' => $resume->id,
            'status' => 'ready_to_apply',
        ]);

        ApplicationMaterial::create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'material_type' => 'cover_letter',
            'key' => 'cover_letter',
            'title' => 'Cover Letter',
            'content_text' => 'Generated fallback cover letter.',
            'ai_provider' => null,
            'ai_model' => null,
            'ai_generated_at' => now(),
            'ai_confidence_score' => 60,
            'prompt_version' => 'v1',
            'input_hash' => 'application-material-user-hash',
            'fallback_used' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/jobhunter/ai-quality');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_runs', 4)
            ->assertJsonPath('data.summary.ai_success_runs', 2)
            ->assertJsonPath('data.summary.fallback_runs', 2)
            ->assertJsonFragment(['provider' => 'gemini', 'total_runs' => 1])
            ->assertJsonFragment(['provider' => 'groq', 'total_runs' => 1])
            ->assertJsonFragment(['provider' => 'deterministic_fallback', 'total_runs' => 2]);
    }

    public function test_ai_quality_report_requires_authentication(): void
    {
        $this->getJson('/api/jobhunter/ai-quality')->assertUnauthorized();
    }

    /**
     * @return array{0: Job, 1: CandidateProfile}
     */
    private function seedJobAndProfile(User $user, string $title): array
    {
        $source = JobSource::create([
            'user_id' => $user->id,
            'name' => $title.' Source',
            'type' => 'custom',
            'base_url' => 'https://jobs.example.com',
            'is_active' => true,
            'meta' => [],
        ]);

        $job = Job::create([
            'external_id' => strtolower(str_replace(' ', '-', $title)),
            'user_id' => $user->id,
            'source_id' => $source->id,
            'company_name' => 'Example Inc',
            'title' => $title,
            'location' => 'Remote',
            'is_remote' => true,
            'description_raw' => 'Senior Laravel backend role.',
            'description_clean' => 'Senior Laravel backend role.',
            'apply_url' => 'https://jobs.example.com/'.strtolower(str_replace(' ', '-', $title)),
            'raw_payload' => [],
            'hash' => sha1($title.$user->id),
            'status' => 'discovered',
        ]);

        $profile = CandidateProfile::create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'headline' => 'Senior Laravel Engineer',
            'base_summary' => 'Backend engineer with Laravel experience.',
            'years_experience' => 10,
            'preferred_roles' => ['Senior Backend Engineer'],
            'preferred_locations' => ['Remote'],
            'preferred_job_types' => ['remote'],
            'core_skills' => ['PHP', 'Laravel'],
            'nice_to_have_skills' => ['OpenSearch'],
        ]);

        return [$job, $profile];
    }
}
