<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateExperience;
use App\Modules\Candidate\Domain\Models\CandidateProject;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResumeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_tailored_resume_draft_for_a_matched_job(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'Resume Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'Resume Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'resume-001',
                'title' => 'Senior Backend Laravel Engineer',
                'company_name' => 'Resume Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/resume-001',
                'description' => 'Senior backend role. Required: PHP, Laravel, PostgreSQL, Redis, Kubernetes. Preferred: Terraform, AWS. Build scalable APIs and queue-driven systems.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/analyze")->assertOk();

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        CandidateExperience::create([
            'profile_id' => $profileId,
            'company' => 'Platform Corp',
            'title' => 'Senior Backend Engineer',
            'description' => 'Led delivery of scalable Laravel APIs and queue-based workflows.',
            'achievements' => [
                'Improved API throughput and reduced latency on Laravel services.',
                'Built queue-driven pipelines using Redis and Docker.',
            ],
            'tech_stack' => ['PHP', 'Laravel', 'Redis', 'Docker', 'PostgreSQL'],
        ]);

        CandidateProject::create([
            'profile_id' => $profileId,
            'name' => 'Search and Matching Platform',
            'description' => 'Built a backend-heavy platform using Laravel, OpenSearch, queues, and AWS.',
            'tech_stack' => ['Laravel', 'OpenSearch', 'AWS', 'Queues'],
            'url' => 'https://example.test/projects/search-platform',
        ]);

        $this->postJson("/api/jobhunter/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ])->assertOk();

        $response = $this->postJson("/api/jobhunter/jobs/{$jobId}/generate-resume", [
            'profile_id' => $profileId,
            'version_name' => 'v1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_name', 'v1')
            ->assertJsonPath('data.headline', 'Senior Backend Engineer | Laravel | APIs | Scalable Systems')
            ->assertJsonCount(1, 'data.selected_projects')
            ->assertJsonCount(2, 'data.selected_experience_bullets');

        $warnings = $response->json('data.warnings_or_gaps');

        $this->assertIsArray($warnings);
        $this->assertTrue(
            collect($warnings)->contains(fn (string $warning): bool => str_contains($warning, 'Kubernetes'))
        );
        $this->assertTrue(
            collect($warnings)->contains(fn (string $warning): bool => str_contains($warning, 'Terraform'))
        );

        $htmlPath = $response->json('data.html_path');

        $this->assertNotNull($htmlPath);
        $this->assertFileExists(storage_path('app/public/'.$htmlPath));
    }

    public function test_it_downloads_an_existing_resume_pdf_for_the_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'PDF Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'PDF Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'pdf-001',
                'title' => 'Senior Backend Laravel Engineer',
                'company_name' => 'PDF Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/pdf-001',
                'description' => 'Senior backend role with PHP and Laravel.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        $resume = TailoredResume::create([
            'job_id' => $jobId,
            'user_id' => $user->id,
            'profile_id' => $profileId,
            'version_name' => 'v1',
            'headline_text' => 'Senior Backend Engineer',
            'summary_text' => 'PDF ready resume.',
            'skills_text' => "PHP\nLaravel",
            'experience_text' => 'Built scalable APIs.',
            'projects_text' => 'JobHunter AI Platform',
            'ats_keywords' => ['PHP', 'Laravel'],
            'warnings_or_gaps' => [],
            'html_path' => 'resumes/tailored/test-resume.html',
            'pdf_path' => 'resumes/tailored/test-resume.pdf',
        ]);

        File::ensureDirectoryExists(storage_path('app/public/resumes/tailored'));
        File::put(storage_path('app/public/resumes/tailored/test-resume.html'), '<html><body>Resume</body></html>');
        File::put(storage_path('app/public/resumes/tailored/test-resume.pdf'), 'fake pdf content');

        $response = $this->get("/api/jobhunter/resumes/{$resume->id}/download-pdf");

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_it_generates_a_real_pdf_when_mpdf_driver_is_enabled(): void
    {
        config()->set('jobhunter.pdf_driver', 'mpdf');
        config()->set('jobhunter.pdf.mpdf_temp_dir', storage_path('app/testing/mpdf-temp'));

        Sanctum::actingAs(User::factory()->create());

        $sourceId = $this->postJson('/api/jobhunter/job-sources', [
            'name' => 'mPDF Source',
            'type' => 'custom',
            'url' => 'https://jobs.example.com',
            'company_name' => 'PDF Company',
            'is_active' => true,
            'config' => ['mode' => 'manual'],
        ])->json('data.id');

        $jobId = $this->postJson("/api/jobhunter/job-sources/{$sourceId}/ingest", [
            'jobs' => [[
                'external_id' => 'mpdf-001',
                'title' => 'Senior Backend Laravel Engineer',
                'company_name' => 'PDF Company',
                'location' => 'Remote',
                'is_remote' => true,
                'url' => 'https://jobs.example.com/mpdf-001',
                'description' => 'Senior backend role. Required: PHP, Laravel, PostgreSQL, Redis.',
                'raw_payload' => ['source' => 'manual'],
            ]],
        ])->json('data.jobs.0.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/analyze")->assertOk();

        $profilePayload = json_decode((string) file_get_contents(base_path('sample_candidate_profile.json')), true, 512, JSON_THROW_ON_ERROR);
        $profileId = $this->postJson('/api/jobhunter/candidate-profiles/import', $profilePayload)->json('data.id');

        $this->postJson("/api/jobhunter/jobs/{$jobId}/match", [
            'profile_id' => $profileId,
        ])->assertOk();

        $response = $this->postJson("/api/jobhunter/jobs/{$jobId}/generate-resume", [
            'profile_id' => $profileId,
            'version_name' => 'mpdf-v1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $pdfPath = $response->json('data.pdf_path');

        $this->assertNotNull($pdfPath);
        $this->assertFileExists(storage_path('app/public/'.$pdfPath));
        $this->assertGreaterThan(0, File::size(storage_path('app/public/'.$pdfPath)));
    }
}
