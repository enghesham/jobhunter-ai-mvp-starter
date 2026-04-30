<?php

namespace Tests\Unit;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\JobAnalysis\BasicKeywordJobAnalysisService;
use PHPUnit\Framework\TestCase;

class BasicKeywordJobAnalysisServiceTest extends TestCase
{
    public function test_it_extracts_backend_skills_and_seniority(): void
    {
        $job = new Job([
            'company_name' => 'Acme',
            'title' => 'Senior Backend Laravel Engineer',
            'description_clean' => 'Build REST APIs with PHP, Laravel, PostgreSQL, Redis, Docker, AWS, queues, and clean architecture.',
        ]);

        $analysis = (new BasicKeywordJobAnalysisService())->analyze($job);

        $this->assertSame('senior', $analysis['seniority']);
        $this->assertSame('backend', $analysis['role_type']);
        $this->assertContains('Laravel', $analysis['required_skills']);
        $this->assertContains('PostgreSQL', $analysis['required_skills']);
        $this->assertContains('AWS', $analysis['required_skills']);
    }

    public function test_it_extracts_structured_fields_from_deterministic_fallback(): void
    {
        $job = new Job([
            'company_name' => 'Acme',
            'title' => 'Senior Backend Engineer',
            'description_clean' => <<<TEXT
Location: Remote within Europe. Preferred timezone: CET.
Requirements:
- 5+ years of experience with PHP, Laravel, PostgreSQL, Redis, Docker, and AWS.
- Strong communication and mentoring.
Nice to have:
- Vue.js and Kubernetes.
Compensation: USD 120k - 150k.
TEXT,
        ]);

        $analysis = (new BasicKeywordJobAnalysisService())->analyze($job);

        $this->assertSame(5, $analysis['years_experience_min']);
        $this->assertSame('remote', $analysis['workplace_type']);
        $this->assertSame('USD 120K - 150K', strtoupper($analysis['salary_text']));
        $this->assertSame(120000, $analysis['salary_min']);
        $this->assertSame(150000, $analysis['salary_max']);
        $this->assertSame('CET', $analysis['timezone_hint']);
        $this->assertContains('Vue.js', $analysis['preferred_skills']);
        $this->assertContains('Kubernetes', $analysis['nice_to_have_skills']);
        $this->assertContains('Communication', $analysis['skill_categories']['soft_skills']);
        $this->assertContains('Laravel', $analysis['skill_categories']['backend']);
    }
}
