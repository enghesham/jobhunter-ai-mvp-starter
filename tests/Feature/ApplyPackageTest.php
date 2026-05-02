<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Applications\Domain\Models\ApplyPackage;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplyPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_apply_package_for_own_job_with_ai_content(): void
    {
        [$user, $profile, $path, $job] = $this->seedScenario();
        $this->fakeAiProvider([
            'cover_letter' => 'AI cover letter for this backend role.',
            'application_answers' => [
                ['key' => 'why_interested', 'question' => 'Why are you interested?', 'answer' => 'Because the role aligns with Laravel platform work.'],
            ],
            'salary_answer' => 'Open to market-aligned compensation.',
            'notice_period_answer' => 'Available after notice period discussion.',
            'interest_answer' => 'This role fits my backend path.',
            'strengths' => ['Laravel APIs', 'PostgreSQL'],
            'gaps' => ['Kubernetes not explicit'],
            'interview_questions' => ['What does success look like in 90 days?'],
            'follow_up_email' => 'Follow-up email draft.',
            'confidence_score' => 88,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/jobs/{$job->id}/apply-package", [
            'job_path_id' => $path->id,
        ])->assertCreated()
            ->assertJsonPath('data.cover_letter', 'AI cover letter for this backend role.')
            ->assertJsonPath('data.career_profile_id', $profile->id)
            ->assertJsonPath('data.job_path_id', $path->id)
            ->assertJsonPath('data.ai_provider', 'fake-ai')
            ->assertJsonPath('data.fallback_used', false);

        $this->assertDatabaseHas('apply_packages', [
            'user_id' => $user->id,
            'job_id' => $job->id,
            'career_profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'status' => 'ready',
        ]);
    }

    public function test_user_cannot_access_another_users_package(): void
    {
        [$owner, $profile, $path, $job] = $this->seedScenario();
        $package = ApplyPackage::query()->create([
            'user_id' => $owner->id,
            'job_id' => $job->id,
            'career_profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'cover_letter' => 'Private',
            'application_answers' => [],
            'strengths' => [],
            'gaps' => [],
            'interview_questions' => [],
            'fallback_used' => true,
            'status' => 'ready',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/jobhunter/apply-packages/{$package->id}")
            ->assertNotFound();
    }

    public function test_package_uses_linked_career_profile_when_available(): void
    {
        [$user, $profile, $path, $job] = $this->seedScenario();
        $otherProfile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'headline' => 'Frontend Engineer',
        ]);
        $otherProfile->forceFill(['is_primary' => true])->save();
        $profile->forceFill(['is_primary' => false])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/jobs/{$job->id}/apply-package")
            ->assertCreated()
            ->assertJsonPath('data.career_profile_id', $profile->id)
            ->assertJsonPath('data.job_path_id', $path->id);
    }

    public function test_package_can_create_application_and_materials(): void
    {
        [$user, , $path, $job] = $this->seedScenario();

        Sanctum::actingAs($user);

        $packageId = $this->postJson("/api/jobhunter/jobs/{$job->id}/apply-package", [
            'job_path_id' => $path->id,
        ])->assertCreated()->json('data.id');

        $applicationId = $this->postJson("/api/jobhunter/apply-packages/{$packageId}/create-application")
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready_to_apply')
            ->json('data.id');

        $this->assertDatabaseHas('apply_packages', [
            'id' => $packageId,
            'application_id' => $applicationId,
            'status' => 'used',
        ]);
        $this->assertSame(1, ApplicationMaterial::query()->where('application_id', $applicationId)->where('key', 'cover_letter')->count());
    }

    public function test_fallback_package_is_saved_if_ai_fails(): void
    {
        [$user, , $path, $job] = $this->seedScenario();
        $this->fakeAiProvider(exception: new AiProviderException('provider failed'));

        Sanctum::actingAs($user);

        $this->postJson("/api/jobhunter/jobs/{$job->id}/apply-package", [
            'job_path_id' => $path->id,
        ])->assertCreated()
            ->assertJsonPath('data.fallback_used', true)
            ->assertJsonPath('data.ai_provider', null)
            ->assertJsonPath('data.status', 'ready');
    }

    /**
     * @return array{0: User, 1: CandidateProfile, 2: JobPath, 3: Job}
     */
    private function seedScenario(): array
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::factory()->primary()->create([
            'user_id' => $user->id,
            'full_name' => 'Hesham Hasanat',
            'headline' => 'Senior Laravel Backend Engineer',
            'base_summary' => 'Builds Laravel APIs, queues, PostgreSQL, Redis, and production systems.',
            'years_experience' => 10,
            'core_skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis'],
            'nice_to_have_skills' => ['Docker', 'AWS'],
            'salary_expectation' => 8000,
            'salary_currency' => 'USD',
        ]);
        $path = JobPath::factory()->forCareerProfile($profile)->create();
        $job = $this->createJob($user);

        JobMatch::query()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'job_path_id' => $path->id,
            'context_key' => "path:{$path->id}",
            'overall_score' => 86,
            'title_score' => 90,
            'skill_score' => 85,
            'experience_score' => 88,
            'seniority_score' => 90,
            'location_score' => 95,
            'backend_focus_score' => 95,
            'domain_score' => 80,
            'strength_areas' => ['Laravel APIs', 'PostgreSQL'],
            'missing_required_skills' => ['Kubernetes'],
            'recommendation' => 'strong_match',
            'recommendation_action' => 'apply',
            'fallback_used' => true,
            'matched_at' => now(),
        ]);

        return [$user, $profile, $path, $job];
    }

    private function createJob(User $user): Job
    {
        $source = JobSource::query()->create([
            'user_id' => $user->id,
            'name' => 'Apply Package Source',
            'type' => 'custom',
            'base_url' => 'https://jobs.example.com',
            'company_name' => 'Example Co',
            'is_active' => true,
            'meta' => [],
        ]);

        return Job::query()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'external_id' => 'apply-package-job',
            'company_name' => 'Example Co',
            'title' => 'Senior Laravel Backend Engineer',
            'location' => 'Remote',
            'is_remote' => true,
            'remote_type' => 'remote',
            'employment_type' => 'full-time',
            'description_raw' => 'Senior backend role using PHP, Laravel, REST APIs, PostgreSQL, Redis, Docker, and AWS.',
            'description_clean' => 'Senior backend role using PHP, Laravel, REST APIs, PostgreSQL, Redis, Docker, and AWS.',
            'apply_url' => 'https://jobs.example.com/apply-package-job',
            'hash' => hash('sha256', 'apply-package-job'),
            'job_fingerprint' => hash('sha256', 'example co senior laravel backend engineer remote'),
            'source_hash' => hash('sha256', 'example co senior laravel backend engineer remote https://jobs.example.com/apply-package-job'),
            'status' => 'matched',
            'posted_at' => now(),
        ]);
    }

    private function fakeAiProvider(?array $payload = null, ?AiProviderException $exception = null): void
    {
        app()->instance(AiProviderInterface::class, new class($payload, $exception) implements AiProviderInterface {
            public function __construct(private readonly ?array $payload, private readonly ?AiProviderException $exception)
            {
            }

            public function analyzeJob(Job $job, string $prompt): ?array
            {
                return null;
            }

            public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
            {
                return null;
            }

            public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
            {
                return null;
            }

            public function suggestJobPaths(CandidateProfile $profile, string $prompt): ?array
            {
                return null;
            }

            public function generateApplyPackage(CandidateProfile $profile, Job $job, array $context, string $prompt): ?array
            {
                if ($this->exception) {
                    throw $this->exception;
                }

                return $this->payload;
            }

            public function name(): string
            {
                return 'fake-ai';
            }

            public function model(): ?string
            {
                return 'fake-model';
            }
        });
    }
}
